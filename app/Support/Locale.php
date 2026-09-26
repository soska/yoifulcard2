<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * The interface languages. The `locale` cookie picks one; without it the
 * browser's Accept-Language header decides, then the app default.
 */
class Locale
{
    public const COOKIE = 'locale';

    /** Cookie lifetime in minutes (one year). */
    public const COOKIE_MINUTES = 60 * 24 * 365;

    /**
     * Supported app locales and the Intl locale used for dates and money.
     *
     * @var array<string, string>
     */
    public const SUPPORTED = [
        'en' => 'en-US',
        'es' => 'es-MX',
    ];

    public static function isSupported(mixed $locale): bool
    {
        return is_string($locale) && array_key_exists($locale, self::SUPPORTED);
    }

    public static function fromRequest(Request $request): string
    {
        $cookie = $request->cookie(self::COOKIE);

        if (self::isSupported($cookie)) {
            return $cookie;
        }

        if ($request->headers->has('Accept-Language')) {
            $preferred = $request->getPreferredLanguage(array_keys(self::SUPPORTED));

            if (self::isSupported($preferred)) {
                return $preferred;
            }
        }

        $default = config('app.locale');

        return self::isSupported($default) ? $default : 'en';
    }

    /** The Intl locale (BCP 47) for an app locale, e.g. `es` -> `es-MX`. */
    public static function intl(string $locale): string
    {
        return self::SUPPORTED[$locale] ?? self::SUPPORTED['en'];
    }
}
