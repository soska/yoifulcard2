<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['bg-background', 'dark' => ($appearance ?? 'system') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="theme-color" content="#0a0a0a">

        {{-- Served from the service worker cache when the network is down, so it loads nothing but the app stylesheet. --}}
        <script>
            (function() {
                const appearance = '{{ $appearance ?? "system" }}';

                if (appearance === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches) {
                    document.documentElement.classList.add('dark');
                }
            })();
        </script>

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="apple-touch-icon" href="/icons/icon-192x192.png">
        <link rel="manifest" href="/manifest.webmanifest">

        @vite(['resources/css/app.css'])

        <title>{{ __('You are offline') }} - {{ config('app.name', 'Yoiful') }}</title>
    </head>
    <body class="font-sans antialiased">
        <main class="flex min-h-svh items-center justify-center bg-background p-6 text-foreground">
            {{--
                The service worker caches this page once, at install. It holds every
                language, and the script below shows the one in the `locale` cookie
                (the switcher's cookie, readable here because it is not encrypted), so
                the cached copy follows later language changes.
            --}}
            @foreach (array_keys(\App\Support\Locale::SUPPORTED) as $offlineLocale)
                <div data-locale="{{ $offlineLocale }}" lang="{{ $offlineLocale }}" data-title="{{ __('You are offline', [], $offlineLocale) }} - {{ config('app.name', 'Yoiful') }}" @if ($offlineLocale !== app()->getLocale()) hidden @endif class="flex w-full max-w-sm flex-col items-center gap-4 text-center">
                    <div class="flex size-14 items-center justify-center rounded-full bg-muted text-muted-foreground">
                        {{-- lucide "wifi-off" --}}
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-7" aria-hidden="true">
                            <path d="M12 20h.01" />
                            <path d="M8.5 16.429a5 5 0 0 1 7 0" />
                            <path d="M5 12.859a10 10 0 0 1 5.17-2.69" />
                            <path d="M19 12.859a10 10 0 0 0-2.007-1.523" />
                            <path d="M2 8.82a15 15 0 0 1 4.177-2.643" />
                            <path d="M22 8.82a15 15 0 0 0-11.288-3.764" />
                            <path d="m2 2 20 20" />
                        </svg>
                    </div>
                    <h1 class="text-2xl font-semibold">{{ __('You are offline', [], $offlineLocale) }}</h1>
                    <p class="text-muted-foreground">
                        {{ __('The reader needs an internet connection to look up cards and post charges. Check your connection and try again.', [], $offlineLocale) }}
                    </p>
                    <a href="/scan" class="inline-flex h-12 w-full items-center justify-center rounded-lg bg-primary px-6 text-base font-medium text-primary-foreground">
                        {{ __('Try again', [], $offlineLocale) }}
                    </a>
                </div>
            @endforeach
        </main>
        <script>
            (function () {
                const match = document.cookie.match(/(?:^|;\s*)locale=([^;]*)/);
                const wanted = match ? decodeURIComponent(match[1]) : null;
                const sections = document.querySelectorAll('[data-locale]');
                const chosen = Array.from(sections).find((section) => section.dataset.locale === wanted);

                // No cookie (or an unknown value): keep the language the server picked.
                if (!chosen) {
                    return;
                }

                sections.forEach((section) => {
                    section.hidden = section !== chosen;
                });
                document.documentElement.lang = wanted;
                document.title = chosen.dataset.title;
            })();
        </script>
    </body>
</html>
