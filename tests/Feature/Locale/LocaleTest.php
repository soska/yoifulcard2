<?php

use App\Models\Card;
use App\Models\Organization;
use App\Models\Program;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia as Assert;

test('locale cookie switches the language and persists', function () {
    // A guest's switch stores a plain, long-lived cookie and reloads the page.
    $this->from(route('login'))
        ->post(route('locale.update'), ['locale' => 'es'])
        ->assertRedirect(route('login'))
        ->assertCookie('locale', 'es', encrypted: false)
        ->assertCookieNotExpired('locale');

    // An Inertia visit gets a full document reload, not an Inertia page, so
    // server copy and the browser catalog both come back in the new language.
    $this->from(route('login'))
        ->withHeaders(['X-Inertia' => 'true'])
        ->post(route('locale.update'), ['locale' => 'es'])
        ->assertStatus(409)
        ->assertHeader('X-Inertia-Location', route('login'));
    $this->flushHeaders();

    // The next requests carry the cookie: the page renders in Spanish (the
    // browser loads the es catalog for `locale.current`), and so do server
    // messages.
    $this->withUnencryptedCookie('locale', 'es')
        ->get(route('login'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('auth/login')
            ->where('locale.current', 'es')
            ->where('locale.available', ['en', 'es'])
            ->where('locale.intl', 'es-MX'));

    $this->withUnencryptedCookie('locale', 'es')
        ->from(route('login'))
        ->post(route('login.store'), ['email' => '', 'password' => ''])
        ->assertSessionHasErrors(['email' => 'El campo correo electrónico es obligatorio.']);

    // An unknown cookie value is ignored.
    $this->withUnencryptedCookie('locale', 'fr')
        ->get(route('login'))
        ->assertInertia(fn (Assert $page) => $page->where('locale.current', 'en')->where('locale.intl', 'en-US'));
});

test('the browser gets its words from the catalog, not a translations prop', function () {
    // Phase 9: React translates with __() from resources/js/locales, loaded
    // for `locale.current` before the first paint. No lines are shared.
    foreach (['en', 'es'] as $locale) {
        $this->withUnencryptedCookie('locale', $locale)
            ->get(route('login'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('locale.current', $locale)
                ->missing('translations'));
    }

    // The Spanish catalog has what the Phase 8 lines said.
    expect(catalogLine('es', 'Log in'))->toBe('Iniciar sesión')
        ->and(catalogLine('es', 'This business is suspended. Contact support.'))->toBe('Este negocio está suspendido. Contacta a soporte.')
        ->and(catalogLine('es', 'Charge', 'transaction type'))->toBe('Cobro')
        ->and(catalogLine('es', 'Charge', 'verb: charge a card'))->toBe('Cobrar');
});

test('missing spanish key falls back to english', function () {
    // lang/es.json is built from the shared catalog and leaves out anything
    // not yet translated (never ""), so Laravel shows the English it is keyed
    // by. The browser does the same with a missing catalog entry
    // (resources/js/locales/catalog.test.ts).
    $path = storage_path('framework/testing/lang-fallback');
    File::ensureDirectoryExists($path);
    File::put($path.'/es.json', json_encode(['Translated line.' => 'Línea traducida.']));

    try {
        app('translator')->getLoader()->addJsonPath($path);
        app('translator')->setLoaded([]);

        app()->setLocale('es');
        expect(__('Translated line.'))->toBe('Línea traducida.')
            ->and(__('Only in English, with :name.', ['name' => 'Ana']))->toBe('Only in English, with Ana.');
    } finally {
        File::deleteDirectory($path);
    }

    // No real Spanish line is blank.
    $es = json_decode(File::get(lang_path('es.json')), true, flags: JSON_THROW_ON_ERROR);
    expect(array_filter($es, fn ($line) => ! is_string($line) || trim($line) === ''))->toBe([]);
});

test('html lang matches the cookie', function () {
    $this->get(route('login'))->assertSee('<html lang="en"', escape: false);

    // Without a cookie, the browser's language picks.
    $this->withHeader('Accept-Language', 'es-MX,es;q=0.9,en;q=0.8')
        ->get(route('home'))
        ->assertSee('<html lang="es"', escape: false);
    $this->withHeader('Accept-Language', 'en-US,en;q=0.9');

    $this->withUnencryptedCookie('locale', 'es')
        ->get(route('login'))
        ->assertSee('<html lang="es"', escape: false);

    $this->withUnencryptedCookie('locale', 'en')
        ->get(route('home'))
        ->assertSee('<html lang="en"', escape: false);
});

test('theme cookie persists', function () {
    $this->from(route('home'))
        ->post(route('theme.update'), ['theme' => 'dark'])
        ->assertRedirect(route('home'))
        ->assertCookie('theme', 'dark', encrypted: false)
        ->assertCookieNotExpired('theme');

    // The root layout reads the cookie on the next request.
    $this->withUnencryptedCookie('theme', 'dark')
        ->get(route('home'))
        ->assertSee('data-theme="dark"', escape: false)
        ->assertSee('class="bg-background dark"', escape: false)
        ->assertInertia(fn (Assert $page) => $page->where('theme', 'dark'));

    $this->withUnencryptedCookie('theme', 'light')
        ->get(route('home'))
        ->assertSee('data-theme="light"', escape: false)
        ->assertDontSee('class="bg-background dark"', escape: false);

    // No cookie or a bad value means "system".
    $this->withUnencryptedCookie('theme', 'neon')
        ->get(route('home'))
        ->assertSee('data-theme="system"', escape: false);

    $this->post(route('theme.update'), ['theme' => 'neon'])->assertSessionHasErrors('theme');
});

test('ledger errors, flash messages, and the csv are translated', function () {
    [$user, $organization, $program] = cardOwner();
    $user->forceFill(['locale' => 'es'])->save();
    $card = Card::factory()->for($program)->create(['balance' => '5.00']);

    $this->actingAs($user)
        ->from(route('cards.show', $card))
        ->post(route('cards.spend', $card), ['amount' => '10.00'])
        ->assertSessionHasErrors(['amount' => 'Saldo insuficiente.']);

    // Toasts are codes; the browser says them in the reader's language
    // (resources/js/lib/flash.ts).
    $this->actingAs($user)
        ->from(route('cards.show', $card))
        ->post(route('cards.load', $card), ['amount' => '1.00'])
        ->assertInertiaFlash('toast.code', 'ledger.loaded')
        ->assertInertiaFlash('toast.params.code', $card->code);

    $csv = $this->actingAs($user)
        ->get(route('transactions.export'))
        ->streamedContent();

    $rows = array_map(fn (string $line) => str_getcsv($line, escape: ''), array_values(array_filter(explode("\n", $csv))));

    expect($rows[0])->toBe(['Fecha', 'Tarjeta', 'Tipo', 'Monto', 'Saldo después', 'Nota', 'Realizada por'])
        ->and($rows[1][2])->toBe('Carga');
});

test('suspended organizations get the translated error', function () {
    [$user, $organization, $program] = cardOwner();
    $user->forceFill(['locale' => 'es'])->save();
    $organization->update(['status' => 'suspended']);
    $card = Card::factory()->for($program)->create(['balance' => '5.00']);

    $this->actingAs($user)
        ->from(route('cards.show', $card))
        ->post(route('cards.spend', $card), ['amount' => '1.00'])
        ->assertSessionHasErrors(['organization' => 'Este negocio está suspendido. Contacta a soporte.']);
});

test('spanish sign-ups get a spanish business and program name', function () {
    $this->withUnencryptedCookie('locale', 'es')
        ->post(route('register.store'), [
            'name' => 'Ana Pérez',
            'email' => 'ana@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])
        ->assertRedirect(route('dashboard', absolute: false));

    expect(Organization::sole()->name)->toBe('Negocio de Ana Pérez')
        ->and(Program::sole()->name)->toBe('Tarjeta de regalo');
});
