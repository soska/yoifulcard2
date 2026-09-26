<?php

namespace App\Http\Controllers\Preferences;

use App\Http\Controllers\Controller;
use App\Support\Locales;
use App\Support\Theme;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Stores light, dark, or system in the `theme` cookie. The theme switcher
 * also writes the cookie in the browser for an instant change; this route
 * is the server-side way to set it.
 */
class ThemeController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'theme' => ['required', 'string', Rule::in(Theme::VALUES)],
        ]);

        return back()->withCookie(cookie(
            Theme::COOKIE,
            $validated['theme'],
            Locales::COOKIE_MINUTES,
            httpOnly: false,
            sameSite: 'lax',
        ));
    }
}
