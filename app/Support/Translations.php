<?php

namespace App\Support;

use Illuminate\Support\Facades\File;

/**
 * The JSON translations shared with React. Keys are the English text, so
 * only lines whose text differs from the key are sent: nothing for English,
 * and for Spanish every Spanish line plus any English line that is not the
 * key itself (missing Spanish keys fall back to English).
 */
class Translations
{
    /**
     * @return array<string, string>
     */
    public static function for(string $locale): array
    {
        $fallback = (string) config('app.fallback_locale', 'en');

        $lines = self::lines($fallback);

        if ($locale !== $fallback) {
            $lines = array_merge($lines, self::lines($locale));
        }

        return array_filter(
            $lines,
            fn (string $value, string $key): bool => $value !== $key,
            ARRAY_FILTER_USE_BOTH,
        );
    }

    /**
     * A short fingerprint of the files, so browsers drop cached translations
     * after a deploy that changes them.
     */
    public static function version(string $locale): string
    {
        $fallback = (string) config('app.fallback_locale', 'en');
        $hash = '';

        foreach (array_unique([$fallback, $locale]) as $name) {
            $path = lang_path($name.'.json');
            $hash .= File::exists($path) ? (string) File::lastModified($path).File::size($path) : '0';
        }

        return substr(md5($hash), 0, 8);
    }

    /**
     * @return array<string, string>
     */
    private static function lines(string $locale): array
    {
        $lines = app('translator')->getLoader()->load($locale, '*', '*');

        return array_filter($lines, fn ($value, $key): bool => is_string($key) && is_string($value), ARRAY_FILTER_USE_BOTH);
    }
}
