<?php

namespace App\Http\Middleware;

use App\Support\CurrentOrganization;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class EnsureOrganizationWritable
{
    /**
     * Refuse writes while the current organization is suspended or cancelled.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $organization = CurrentOrganization::organization($request);

        if ($organization !== null && $organization->isWritable()) {
            return $next($request);
        }

        $message = __('This business is suspended. Contact support.');

        Inertia::flash('toast', ['type' => 'error', 'message' => $message]);

        return back()->withErrors(['organization' => $message]);
    }
}
