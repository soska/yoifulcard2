<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;

/**
 * The languages this app can render, in one place.
 *
 * The switcher, the controller that saves a choice, the middleware, the user
 * model, the offline page and the frontend all read from HERE. A second
 * hard-coded ['en', 'es'] anywhere is a bug waiting for the day a third
 * language is added and one of the copies is missed. Adding a language means
 * adding it here plus its catalog.
 *
 * Supported is not the same as translated: a partial catalog is safe, because
 * duckalization renders the inline English for a missing id and Laravel falls
 * back to `fallback_locale`.
 */
final class Locales
{
    /**
     * English (the source language) FIRST: Symfony's negotiation returns
     * element zero when the browser asks for nothing we have, so the order of
     * this list is also the definition of "default".
     *
     * @var list<string>
     */
    public const SUPPORTED = ['en', 'es'];

    /**
     * The `Intl` locale (BCP 47) each language formats dates, money and
     * numbers with. First customers are in Mexico, so Spanish is `es-MX`.
     *
     * @var array<string, string>
     */
    public const INTL = [
        'en' => 'en-US',
        'es' => 'es-MX',
    ];

    /** The guest switcher's cookie (public card and login pages). */
    public const COOKIE = 'locale';

    /** Cookie lifetime in minutes (one year). */
    public const COOKIE_MINUTES = 60 * 24 * 365;

    /**
     * @return list<string>
     */
    public static function supported(): array
    {
        return self::SUPPORTED;
    }

    /**
     * @phpstan-assert-if-true string $locale
     */
    public static function isSupported(mixed $locale): bool
    {
        return is_string($locale) && in_array($locale, self::SUPPORTED, true);
    }

    /** The language every other rule falls back to. */
    public static function default(): string
    {
        return self::SUPPORTED[0];
    }

    /** The Intl locale for a language, e.g. `es` -> `es-MX`. */
    public static function intl(string $locale): string
    {
        return self::INTL[$locale] ?? self::INTL[self::default()];
    }

    /**
     * The best of the browser's Accept-Language that we can render, or the
     * default. Delegated to Symfony, which handles q-weights and `es-MX -> es`.
     */
    public static function negotiate(Request $request): string
    {
        $preferred = $request->getPreferredLanguage(self::SUPPORTED);

        return self::isSupported($preferred) ? $preferred : self::default();
    }

    /**
     * The guest switcher's choice, if it names a supported language.
     */
    public static function fromCookie(Request $request): ?string
    {
        $cookie = $request->cookie(self::COOKIE);

        return self::isSupported($cookie) ? $cookie : null;
    }

    /**
     * The one decision per request:
     *
     *   1. a signed-in user's saved choice (`users.locale`; null = never chose),
     *   2. for guests, the `locale` cookie from the public card and login
     *      switchers,
     *   3. the browser's Accept-Language,
     *   4. English.
     *
     * The cookie is a guest's answer. A signed-in user answers with the column,
     * so an old cookie on a shared device never overrides a person's own
     * choice, and "match my browser" (null) really does follow the browser.
     */
    public static function resolve(Request $request, ?User $user = null): string
    {
        if ($user !== null) {
            return $user->preferredLocale() ?? self::negotiate($request);
        }

        return self::fromCookie($request) ?? self::negotiate($request);
    }

    /**
     * The shared `locale` Inertia prop. `current` is the RESOLVED language:
     * the browser renders what the server decided and never decides again.
     * `intl` is its Intl locale, for dates and money.
     *
     * @return array{current: string, available: list<string>, intl: string}
     */
    public static function prop(string $current): array
    {
        return [
            'current' => $current,
            'available' => self::SUPPORTED,
            'intl' => self::intl($current),
        ];
    }
}
