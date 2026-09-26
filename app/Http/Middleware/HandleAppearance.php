<?php

namespace App\Http\Middleware;

use App\Support\Theme;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Shares the `theme` cookie (light, dark, or system) with the root Blade
 * layout, so the first paint already has the right colors.
 */
class HandleAppearance
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        View::share('appearance', Theme::fromRequest($request));

        return $next($request);
    }
}
