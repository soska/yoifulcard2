<?php

namespace App\Http\Middleware;

use App\Enums\FlashMessage;
use App\Support\CurrentOrganization;
use App\Support\Flash;
use Closure;
use Illuminate\Http\Request;
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

        Flash::error(FlashMessage::OrganizationNotWritable);

        return back()->withErrors(['organization' => $message]);
    }
}
