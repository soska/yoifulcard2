<?php

use Illuminate\Cookie\Middleware\EncryptCookies;

/**
 * The visible (not `hidden`) language sections of the offline page.
 *
 * @return array<int, string>
 */
function visibleOfflineLocales(string $html): array
{
    preg_match_all('/<div data-locale="(\w+)"[^>]*>/', $html, $matches, PREG_SET_ORDER);

    return array_values(array_map(
        fn (array $match) => $match[1],
        array_filter($matches, fn (array $match) => ! preg_match('/\shidden(\s|>)/', $match[0])),
    ));
}

test('the cached offline page carries every language and follows the locale cookie', function () {
    // The service worker caches one copy at install, in whatever language
    // was active then. It must hold both languages...
    $english = $this->get(route('offline'))->assertOk();

    $english->assertSee('You are offline')
        ->assertSee('Estás sin conexión')
        ->assertSee('The reader needs an internet connection', false)
        ->assertSee('El lector necesita conexión a internet', false)
        ->assertSee('Reintentar');

    // ...shows the server's language until the script runs...
    expect(visibleOfflineLocales($english->getContent()))->toBe(['en']);

    $spanish = $this->withUnencryptedCookie('locale', 'es')->get(route('offline'))->assertOk();
    expect(visibleOfflineLocales($spanish->getContent()))->toBe(['es']);

    // ...and then picks the section named by the `locale` cookie, which the
    // browser can read because it is not encrypted.
    $spanish->assertSee('document.cookie.match(/(?:^|;\s*)locale=([^;]*)/)', false)
        ->assertSee("document.querySelectorAll('[data-locale]')", false)
        ->assertSee('data-title="Estás sin conexión', false);

    expect(app(EncryptCookies::class)->isDisabled('locale'))->toBeTrue();

    // The worker still caches the single /offline URL.
    expect((string) file_get_contents(public_path('sw.js')))->toContain("const OFFLINE_URL = '/offline';");
});
