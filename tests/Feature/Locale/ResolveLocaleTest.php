<?php

use App\Console\Commands\ExportEnumTypes;
use App\Enums\FlashMessage;
use App\Models\Card;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Phase 9.1: one locale decision per request (App\Support\Locales::resolve)
 * and toasts as codes.
 */
test("signed-in user's saved locale wins over cookie and browser", function () {
    $user = User::factory()->create(['locale' => 'es']);

    // Signed in over HTTP, and the next request starts like a real browser's:
    // a fresh guard and session store, with only the session cookie to go on.
    // actingAs() (or the test's in-memory guard) would hand ResolveLocale the
    // user even if it ran before StartSession, which is the bug Multiplano
    // shipped when the middleware was prepended to `web`.
    config(['session.driver' => 'file']);

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect();
    $this->assertAuthenticatedAs($user);

    $sessionId = session()->getId();
    app('auth')->forgetGuards();
    app('session')->forgetDrivers();
    app()->forgetInstance('session.store');

    $this->withCookie(config('session.cookie'), $sessionId)
        ->withUnencryptedCookie('locale', 'en')
        ->withHeader('Accept-Language', 'en-US,en;q=0.9')
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertSee('<html lang="es"', escape: false)
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.user.id', $user->id)
            ->where('locale.current', 'es')
            ->where('locale.intl', 'es-MX')
            // Server strings (the shared Phase 8 lines) follow the same decision.
            ->where('translations.Log out', 'Cerrar sesión'));

    // And the preference is what mail would use (HasLocalePreference).
    expect($user->preferredLocale())->toBe('es');
});

test('guest cookie wins over Accept-Language', function () {
    $this->withUnencryptedCookie('locale', 'en')
        ->withHeader('Accept-Language', 'es-MX,es;q=0.9')
        ->get(route('login'))
        ->assertSee('<html lang="en"', escape: false)
        ->assertInertia(fn (Assert $page) => $page->where('locale.current', 'en'));

    $this->withUnencryptedCookie('locale', 'es')
        ->withHeader('Accept-Language', 'en-US,en;q=0.9')
        ->get(route('login'))
        ->assertInertia(fn (Assert $page) => $page->where('locale.current', 'es'));

    // Without a cookie the browser decides (q-weights, es-MX -> es), then English.
    $this->withUnencryptedCookie('locale', '')
        ->withHeader('Accept-Language', 'fr-FR, es;q=0.8, en;q=0.5')
        ->get(route('login'))
        ->assertInertia(fn (Assert $page) => $page->where('locale.current', 'es'));

    $this->withHeader('Accept-Language', 'fr-FR,de;q=0.9')
        ->get(route('login'))
        ->assertInertia(fn (Assert $page) => $page->where('locale.current', 'en'));
});

test('null users.locale follows the browser', function () {
    $user = User::factory()->create(['locale' => null]);

    $this->actingAs($user)
        ->withHeader('Accept-Language', 'es-MX,es;q=0.9,en;q=0.8')
        ->get(route('profile.edit'))
        ->assertInertia(fn (Assert $page) => $page->where('locale.current', 'es'));

    // The guest cookie is a guest's answer; a signed-in user answers with the
    // column, and null means "match my browser".
    $this->actingAs($user)
        ->withUnencryptedCookie('locale', 'es')
        ->withHeader('Accept-Language', 'en-US,en;q=0.9')
        ->get(route('profile.edit'))
        ->assertInertia(fn (Assert $page) => $page->where('locale.current', 'en'));

    // Choosing "match my browser" clears a saved language.
    $user->forceFill(['locale' => 'en'])->save();

    $this->actingAs($user)
        ->from(route('profile.edit'))
        ->post(route('locale.update'), ['locale' => 'browser'])
        ->assertRedirect(route('profile.edit'));

    expect($user->fresh()->locale)->toBeNull();

    $this->actingAs($user->fresh())
        ->withHeader('Accept-Language', 'es')
        ->get(route('profile.edit'))
        ->assertInertia(fn (Assert $page) => $page->where('locale.current', 'es'));
});

test('signed-in users save their language on the account', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('dashboard'))
        ->withHeaders(['X-Inertia' => 'true'])
        ->post(route('locale.update'), ['locale' => 'es'])
        ->assertStatus(409)
        ->assertHeader('X-Inertia-Location', route('dashboard'));

    expect($user->fresh()->locale)->toBe('es');
});

test('a language picked before signing in becomes the saved one', function () {
    $user = User::factory()->create(['locale' => null]);

    $this->withUnencryptedCookie('locale', 'es')
        ->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);

    expect($user->fresh()->locale)->toBe('es');

    // A saved language is never overwritten by the cookie.
    $other = User::factory()->create(['locale' => 'en']);
    auth()->logout();

    $this->withUnencryptedCookie('locale', 'es')
        ->post(route('login.store'), ['email' => $other->email, 'password' => 'password']);

    expect($other->fresh()->locale)->toBe('en');
});

test('unsupported locale values are ignored', function () {
    // A guest posting an unknown language gets no cookie and no error.
    $this->from(route('login'))
        ->post(route('locale.update'), ['locale' => 'fr'])
        ->assertRedirect(route('login'))
        ->assertCookieMissing('locale')
        ->assertSessionHasNoErrors();

    // A signed-in user keeps the saved language.
    $user = User::factory()->create(['locale' => 'es']);

    $this->actingAs($user)
        ->from(route('dashboard'))
        ->post(route('locale.update'), ['locale' => 'fr'])
        ->assertRedirect(route('dashboard'))
        ->assertSessionHasNoErrors();

    expect($user->fresh()->locale)->toBe('es');

    // An unknown cookie or stored value falls through to the browser.
    auth()->logout();

    $this->withUnencryptedCookie('locale', 'xx')
        ->withHeader('Accept-Language', 'es')
        ->get(route('login'))
        ->assertInertia(fn (Assert $page) => $page->where('locale.current', 'es'));

    $stale = User::factory()->create();
    $stale->forceFill(['locale' => 'fr'])->save();

    expect($stale->fresh()->preferredLocale())->toBeNull();

    $this->actingAs($stale->fresh())
        ->withHeader('Accept-Language', 'es')
        ->get(route('profile.edit'))
        ->assertInertia(fn (Assert $page) => $page->where('locale.current', 'es'));
});

test('flash messages are sent as codes', function () {
    [$user, $organization, $program] = cardOwner();
    $card = Card::factory()->for($program)->create(['balance' => '5.00']);

    $this->actingAs($user)
        ->from(route('cards.show', $card))
        ->post(route('cards.freeze', $card))
        ->assertInertiaFlash('toast', ['type' => 'success', 'code' => 'card.frozen', 'params' => []]);

    $this->actingAs($user)
        ->from(route('cards.show', $card))
        ->post(route('cards.unfreeze', $card))
        ->assertInertiaFlash('toast.code', FlashMessage::CardUnfrozen->value);

    $this->actingAs($user)
        ->from(route('cards.show', $card))
        ->post(route('cards.spend', $card), ['amount' => '1.00'])
        ->assertInertiaFlash('toast', ['type' => 'success', 'code' => 'ledger.charged', 'params' => ['code' => $card->code]]);

    // Refusals flash an error code (the field error stays a server sentence).
    $organization->update(['status' => 'suspended']);

    $this->actingAs($user)
        ->from(route('cards.show', $card))
        ->post(route('cards.spend', $card), ['amount' => '1.00'])
        ->assertInertiaFlash('toast', ['type' => 'error', 'code' => 'organization.not_writable', 'params' => []]);

    // No controller flashes a sentence any more.
    foreach (File::allFiles(app_path()) as $file) {
        expect($file->getContents())->not->toMatch("/['\"]message['\"]\s*=>/", $file->getRelativePathname().' flashes a sentence');
    }

    // The TypeScript union is up to date, and lib/flash.ts has words for
    // every code (tsc checks the Record is exhaustive; this checks the file
    // the compiler reads is the current one).
    $this->artisan('types:enums', ['--check' => true])->assertSuccessful();

    $flash = File::get(resource_path('js/lib/flash.ts'));
    foreach (FlashMessage::cases() as $case) {
        expect($flash)->toContain("'{$case->value}':");
    }

    expect(ExportEnumTypes::render())->toContain("| '".FlashMessage::CardFrozen->value."'");
});
