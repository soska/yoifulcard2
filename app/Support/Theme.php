<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * The light or dark theme, stored in the `theme` cookie. `system` follows
 * the device setting.
 */
class Theme
{
    public const COOKIE = 'theme';

    /** @var list<string> */
    public const VALUES = ['light', 'dark', 'system'];

    public static function fromRequest(Request $request): string
    {
        $value = $request->cookie(self::COOKIE);

        return is_string($value) && in_array($value, self::VALUES, true) ? $value : 'system';
    }
}
