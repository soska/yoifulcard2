<?php

namespace App\Http\Controllers\Preferences;

use App\Http\Controllers\Controller;
use App\Support\Locale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Stores the interface language in the `locale` cookie. Open to guests, so
 * the public card and login pages can switch too.
 */
class LocaleController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'locale' => ['required', 'string', Rule::in(array_keys(Locale::SUPPORTED))],
        ]);

        return back()->withCookie(cookie(
            Locale::COOKIE,
            $validated['locale'],
            Locale::COOKIE_MINUTES,
            httpOnly: false,
            sameSite: 'lax',
        ));
    }
}
