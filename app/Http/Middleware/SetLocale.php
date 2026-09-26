<?php

namespace App\Http\Middleware;

use App\Support\Locale;
use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sets the interface language from the `locale` cookie (see Locale).
 */
class SetLocale
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = Locale::fromRequest($request);

        app()->setLocale($locale);
        Carbon::setLocale($locale);

        return $next($request);
    }
}
