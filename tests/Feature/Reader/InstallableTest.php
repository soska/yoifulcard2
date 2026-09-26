<?php

test('the offline page is public and links the app stylesheet', function () {
    $this->get(route('offline'))
        ->assertOk()
        ->assertSee('You are offline')
        ->assertSee('href="/scan"', false)
        ->assertSee('/manifest.webmanifest', false);
});

test('the web app manifest starts the reader', function () {
    $manifest = json_decode((string) file_get_contents(public_path('manifest.webmanifest')), true, flags: JSON_THROW_ON_ERROR);

    expect($manifest['start_url'])->toBe('/scan')
        ->and($manifest['display'])->toBe('standalone')
        ->and(collect($manifest['icons'])->pluck('sizes')->all())->toContain('192x192', '512x512');

    foreach ($manifest['icons'] as $icon) {
        expect(public_path(ltrim($icon['src'], '/')))->toBeFile();
    }
});

test('pages link the manifest', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee('<link rel="manifest" href="/manifest.webmanifest">', false);
});

test('the service worker falls back to the offline page and caches only built assets', function () {
    $worker = (string) file_get_contents(public_path('sw.js'));

    expect($worker)->toContain("const OFFLINE_URL = '/offline';")
        ->and($worker)->toContain("request.mode === 'navigate'")
        ->and($worker)->toContain("url.pathname.startsWith('/build/assets/')");
});
