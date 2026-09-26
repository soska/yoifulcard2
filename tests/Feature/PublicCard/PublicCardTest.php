<?php

use App\Enums\OrganizationStatus;
use App\Models\Card;
use App\Providers\AppServiceProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * A card in a branded organization.
 *
 * @param  array<string, mixed>  $organization
 * @param  array<string, mixed>  $card
 */
function publicCard(array $organization = [], array $card = []): Card
{
    [, $org] = cardOwner([
        'name' => 'Café Luna',
        'logo_url' => 'https://example.com/storage/logos/luna.png',
        'primary_color' => '#1E40AF',
        'currency' => 'MXN',
        ...$organization,
    ]);

    return Card::factory()->forOrganization($org)->create([
        'code' => 'YGFT-LUNA',
        'balance' => '250.50',
        'email' => 'holder@example.com',
        ...$card,
    ]);
}

beforeEach(function () {
    RateLimiter::clear('public-card-email');
});

test('public card shows name, logo, color, and balance while logged out', function () {
    $card = publicCard();

    $this->assertGuest();

    $this->get(route('public-card.show', ['token' => $card->qr_token]))
        ->assertOk()
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertHeader('Referrer-Policy', 'no-referrer')
        ->assertInertia(fn (Assert $page) => $page
            ->component('public-card/show')
            ->where('organization.name', 'Café Luna')
            ->where('organization.logo_url', 'https://example.com/storage/logos/luna.png')
            ->where('organization.primary_color', '#1e40af')
            ->where('organization.currency', 'MXN')
            ->where('card.balance', '250.50')
            ->where('card.status', 'active')
            ->where('auth.user', null)
            ->where('currentOrganization', null));
});

test('public card works for a logged-in user of another organization', function () {
    $card = publicCard();
    [$stranger] = cardOwner();

    $this->actingAs($stranger)
        ->get(route('public-card.show', ['token' => $card->qr_token]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('public-card/show')
            ->where('card.balance', '250.50'));
});

test('unknown and malformed tokens return the same page and status', function () {
    publicCard();

    $unknown = Str::random(64);
    $responses = collect([
        $unknown,                     // well formed, no such card
        'short',                      // too short
        str_repeat('a', 65),          // too long
        str_repeat('!', 64),          // wrong characters
        'abc/def',                    // extra path segment
        "' or 1=1 --",                // not a token at all
    ])->map(fn (string $token) => $this->get('/c/'.implode('/', array_map(rawurlencode(...), explode('/', $token)))));

    foreach ($responses as $response) {
        $response->assertNotFound()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertInertia(fn (Assert $page) => $page
                ->component('public-card/not-found')
                ->missing('card')
                ->missing('organization'));
    }

    // Apart from the URL, the page data is identical.
    $pages = $responses->map(fn ($response) => collect($response->viewData('page'))->except('url')->all());

    expect($pages->unique(fn ($page) => json_encode($page))->count())->toBe(1);
});

test('public card props contain only the allowed fields', function () {
    $card = publicCard();
    $shared = ['errors', 'name', 'auth', 'currentOrganization', 'organizations', 'sidebarOpen', 'locale', 'intlLocale', 'theme', 'translations'];

    $response = $this->get(route('public-card.show', ['token' => $card->qr_token]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('public-card/show')
            ->has('card', fn (Assert $props) => $props
                ->hasAll(['balance', 'status'])
                ->etc(false))
            ->has('organization', fn (Assert $props) => $props
                ->hasAll(['name', 'logo_url', 'primary_color', 'currency'])));

    $props = $response->viewData('page')['props'];

    expect(array_keys($props))->toEqualCanonicalizing([...$shared, 'card', 'organization'])
        ->and(array_keys($props['card']))->toEqualCanonicalizing(['balance', 'status'])
        ->and(array_keys($props['organization']))->toEqualCanonicalizing(['name', 'logo_url', 'primary_color', 'currency']);

    // Nothing else about the card, program, or organization leaks.
    $organization = $card->organization();
    $json = json_encode($props);

    foreach ([$card->id, $card->code, $card->email, $card->program_id, $card->program->name, $organization->id, $organization->slug] as $secret) {
        expect($json)->not->toContain($secret);
    }

    expect($json)->not->toContain($card->qr_token);
});

test('an invalid stored brand color falls back to black', function () {
    $card = publicCard(['primary_color' => 'red;x']);

    $this->get(route('public-card.show', ['token' => $card->qr_token]))
        ->assertInertia(fn (Assert $page) => $page->where('organization.primary_color', '#000000'));
});

test('public card shows a frozen card\'s status and balance', function () {
    $card = publicCard(card: ['status' => 'frozen']);

    $this->get(route('public-card.show', ['token' => $card->qr_token]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('card.status', 'frozen')
            ->where('card.balance', '250.50'));
});

test('email capture saves the email on the card', function () {
    $card = publicCard(card: ['email' => null]);
    $url = route('public-card.show', ['token' => $card->qr_token]);

    $this->from($url)
        ->post(route('public-card.email', ['token' => $card->qr_token]), ['email' => '  Holder@Example.COM '])
        ->assertRedirect($url)
        ->assertSessionHasNoErrors();

    expect($card->fresh()->email)->toBe('holder@example.com');
    $this->assertGuest();
});

test('email capture validates the address', function (mixed $email) {
    $card = publicCard(card: ['email' => null]);

    $this->post(route('public-card.email', ['token' => $card->qr_token]), ['email' => $email])
        ->assertSessionHasErrors('email');

    expect($card->fresh()->email)->toBeNull();
})->with([
    'missing' => [''],
    'not an email' => ['not-an-email'],
    'too long' => [str_repeat('a', 250).'@example.com'],
    'array' => [['a@example.com']],
]);

test('email capture does not replace an email already on the card', function () {
    $card = publicCard(card: ['email' => 'first@example.com']);

    $this->post(route('public-card.email', ['token' => $card->qr_token]), ['email' => 'second@example.com'])
        ->assertSessionHasErrors(['email' => 'An email is already saved for this card.']);

    expect($card->fresh()->email)->toBe('first@example.com');
});

test('email capture for an unknown or malformed token is not found', function (string $token) {
    $this->post('/c/'.$token.'/email', ['email' => 'a@example.com'])
        ->assertNotFound()
        ->assertInertia(fn (Assert $page) => $page->component('public-card/not-found'));

    expect(Card::query()->whereNotNull('email')->count())->toBe(0);
})->with([
    'unknown' => [str_repeat('x', 64)],
    'malformed' => ['nope'],
]);

test('email capture is rate limited', function () {
    $card = publicCard(card: ['email' => null]);
    $url = route('public-card.show', ['token' => $card->qr_token]);
    $post = fn (string $email) => $this->from($url)
        ->post(route('public-card.email', ['token' => $card->qr_token]), ['email' => $email]);

    // Failed attempts count too.
    for ($i = 0; $i < AppServiceProvider::PUBLIC_CARD_EMAIL_PER_MINUTE; $i++) {
        $post('not-an-email')->assertSessionHasErrors('email');
    }

    $post('holder@example.com')
        ->assertRedirect($url)
        ->assertSessionHasErrors(['email' => 'Too many attempts. Please try again in a minute.'])
        ->assertHeader('Retry-After');

    expect($card->fresh()->email)->toBeNull();

    // Another address is not blocked.
    $this->from($url)
        ->withServerVariables(['REMOTE_ADDR' => '10.0.0.2'])
        ->post(route('public-card.email', ['token' => $card->qr_token]), ['email' => 'holder@example.com'])
        ->assertSessionHasNoErrors();

    expect($card->fresh()->email)->toBe('holder@example.com');

    // After a minute the first address may post again.
    $this->travel(61)->seconds();

    $post('other@example.com')->assertSessionHasErrors(['email' => 'An email is already saved for this card.']);
});

test('suspended organization\'s card still shows balance', function (OrganizationStatus $status) {
    $card = publicCard(['status' => $status]);

    $this->get(route('public-card.show', ['token' => $card->qr_token]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('public-card/show')
            ->where('card.balance', '250.50')
            ->where('card.status', 'active')
            // No suspension notice: the organization's status is not sent.
            ->missing('organization.status')
            ->missing('suspended'))
        ->assertDontSee('suspended', false);
})->with([
    'suspended' => [OrganizationStatus::Suspended],
    'cancelled' => [OrganizationStatus::Cancelled],
]);

test('the public card page selects only the allowed columns', function () {
    $card = publicCard();

    DB::enableQueryLog();
    DB::flushQueryLog();

    $this->get(route('public-card.show', ['token' => $card->qr_token]))->assertOk();

    $queries = collect(DB::getQueryLog())->pluck('query')->filter(fn ($sql) => str_contains($sql, 'cards'));

    expect($queries)->toHaveCount(1)
        ->and($queries->first())->not->toContain('cards.*')
        ->and($queries->first())->toContain('"cards"."balance"');
});
