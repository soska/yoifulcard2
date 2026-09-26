<?php

namespace App\Http\Controllers\Preferences;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Locales;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Changes the interface language.
 *
 * - Signed-in users save it on `users.locale`. An empty value (or `browser`)
 *   clears it, which means "match my browser".
 * - Guests (public card, login and register pages) get the `locale` cookie.
 *
 * Unsupported values are IGNORED rather than refused: nothing is saved and the
 * page simply reloads in the language it was already in.
 *
 * The answer is a full document reload (Inertia::location), not an Inertia
 * visit, because server copy (validation messages, errors) only re-renders on
 * a fresh request, and the browser's catalog is loaded once, before first paint.
 */
class LocaleController extends Controller
{
    /** The switcher's value for "match my browser". */
    public const BROWSER = 'browser';

    public function __invoke(Request $request): Response
    {
        $value = $request->input('locale');
        $value = $value === null || $value === '' || $value === self::BROWSER ? null : $value;

        $user = $request->user();
        $back = url()->previous();

        if ($value !== null && ! Locales::isSupported($value)) {
            return Inertia::location($back);
        }

        if ($user instanceof User) {
            $user->forceFill(['locale' => $value])->save();
        } elseif ($value === null) {
            return Inertia::location($back);
        }

        // Signed-in users get the cookie too (or lose it for "match my
        // browser"), so the cached offline page and the signed-out pages on
        // this device follow the same choice. It never outranks the column.
        Cookie::queue($value === null
            ? Cookie::forget(Locales::COOKIE)
            : cookie(Locales::COOKIE, $value, Locales::COOKIE_MINUTES, httpOnly: false, sameSite: 'lax'));

        return Inertia::location($back);
    }
}
