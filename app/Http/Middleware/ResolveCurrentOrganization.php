<?php

namespace App\Http\Middleware;

use App\Support\CurrentOrganization;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class ResolveCurrentOrganization
{
    /**
     * Load the current organization from the session and check the
     * membership. HandleInertiaRequests shares it as `currentOrganization`.
     * Without a membership, show the empty state instead of the page.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() === null) {
            return $next($request);
        }

        if (CurrentOrganization::resolve($request) === null) {
            abort_unless($request->isMethodSafe(), 403);

            return Inertia::render('organizations/empty')->toResponse($request);
        }

        return $next($request);
    }
}
