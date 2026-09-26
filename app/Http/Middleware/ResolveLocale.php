<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\Locales;
use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Decides, once per request, which language the app speaks (see
 * App\Support\Locales::resolve for the precedence).
 *
 * One decision, two consumers: Laravel's translator (validation, ledger
 * errors, error pages) and duckalization in the browser, which reads the
 * shared `locale.current` prop. They must never disagree.
 *
 * ORDER. It is the first of the middleware APPENDED to `web`, so it runs:
 *
 *   - after StartSession, because `$request->user()` needs the session to know
 *     who is signed in. Prepending it (before StartSession) looks safer but
 *     silently ignores `users.locale` in every real browser, while tests that
 *     use actingAs() still pass. Multiplano shipped exactly that bug; the
 *     regression test here signs in over HTTP;
 *   - before HandleInertiaRequests, whose shared props call __(), so shared
 *     and page props are always in the same language.
 */
class ResolveLocale
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        $locale = Locales::resolve($request, $user instanceof User ? $user : null);

        app()->setLocale($locale);
        Carbon::setLocale($locale);

        return $next($request);
    }
}
