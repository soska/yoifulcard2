<?php

use App\Models\User;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

/** A route that fails with the given status, registered for one test. */
function failingRoute(int $status): string
{
    Route::middleware('web')->match(['get', 'post'], '/__test/fail/'.$status, fn () => abort($status));

    return '/__test/fail/'.$status;
}

test('missing page renders the translated 404 in spanish', function () {
    // English by default.
    $this->get('/this-page-does-not-exist')
        ->assertNotFound()
        ->assertInertia(fn (Assert $page) => $page->component('errors/error')->where('locale', 'en'));

    // No route matches, so the web middleware never runs: the handler sets
    // the language from the cookie itself.
    $this->withUnencryptedCookie('locale', 'es')
        ->get('/this-page-does-not-exist')
        ->assertNotFound()
        ->assertSee('<html lang="es"', false)
        ->assertInertia(fn (Assert $page) => $page
            ->component('errors/error')
            ->where('status', 404)
            ->where('locale', 'es')
            ->where('intlLocale', 'es-MX')
            ->where('auth.user', null)
            ->where('translations', fn ($lines) => $lines['Page not found'] === 'Página no encontrada'
                && $lines['Go home'] === 'Ir al inicio'));

    // A missing model inside a signed-in route gets the same page.
    [$user] = cardOwner();

    $this->actingAs($user)
        ->withUnencryptedCookie('locale', 'es')
        ->get(route('cards.show', '00000000-0000-0000-0000-000000000000'))
        ->assertNotFound()
        ->assertInertia(fn (Assert $page) => $page
            ->component('errors/error')
            ->where('status', 404)
            ->where('locale', 'es')
            ->where('auth.user.id', $user->id));
});

test('419, 500 and 503 render translated error pages', function (int $status, string $title, string $spanish) {
    config(['app.debug' => false]);
    $url = failingRoute($status);

    $this->withUnencryptedCookie('locale', 'es')
        ->get($url)
        ->assertStatus($status)
        ->assertInertia(fn (Assert $page) => $page
            ->component('errors/error')
            ->where('status', $status)
            ->where('locale', 'es')
            ->where('translations.'.$title, $spanish));
})->with([
    'page expired' => [419, 'Page expired', 'La página expiró'],
    'server error' => [500, 'Something went wrong', 'Algo salió mal'],
    'maintenance' => [503, 'Down for maintenance', 'En mantenimiento'],
]);

test('server errors keep the debug page in debug mode', function () {
    config(['app.debug' => true]);
    $url = failingRoute(500);

    $response = $this->get($url)->assertStatus(500);

    expect($response->getContent())->not->toContain('errors/error');
});

test('a thrown exception renders the 500 page when debug is off', function () {
    config(['app.debug' => false]);
    Route::middleware('web')->get('/__test/throw', fn () => throw new RuntimeException('secret failure detail'));

    $this->withUnencryptedCookie('locale', 'es')
        ->get('/__test/throw')
        ->assertStatus(500)
        ->assertDontSee('secret failure detail')
        ->assertInertia(fn (Assert $page) => $page->component('errors/error')->where('status', 500));
});

test('json requests still get json errors', function () {
    config(['app.debug' => false]);

    $this->withUnencryptedCookie('locale', 'es')
        ->getJson('/this-page-does-not-exist')
        ->assertNotFound()
        ->assertHeader('Content-Type', 'application/json')
        ->assertJsonStructure(['message']);

    foreach ([403, 419, 500, 503] as $status) {
        $this->getJson(failingRoute($status))
            ->assertStatus($status)
            ->assertHeader('Content-Type', 'application/json')
            ->assertJsonStructure(['message']);
    }
});

test('inertia visits get the error page as an inertia response', function () {
    $this->withHeaders(['X-Inertia' => 'true'])
        ->withUnencryptedCookie('locale', 'es')
        ->get('/this-page-does-not-exist')
        ->assertNotFound()
        ->assertHeader('X-Inertia', 'true')
        ->assertJsonPath('component', 'errors/error')
        ->assertJsonPath('props.locale', 'es');
});

test('403 still renders the translated forbidden page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withUnencryptedCookie('locale', 'es')
        ->get(route('admin.index'))
        ->assertForbidden()
        ->assertInertia(fn (Assert $page) => $page
            ->component('errors/forbidden')
            ->where('locale', 'es')
            ->where('canClaimSuperadmin', true));
});

test('the public card keeps its own not-found page', function () {
    $this->withUnencryptedCookie('locale', 'es')
        ->get(route('public-card.show', str_repeat('a', 64)))
        ->assertNotFound()
        ->assertInertia(fn (Assert $page) => $page->component('public-card/not-found'));
});
