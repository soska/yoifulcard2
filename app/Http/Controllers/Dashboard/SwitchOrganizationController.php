<?php

namespace App\Http\Controllers\Dashboard;

use App\Enums\FlashMessage;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Support\CurrentOrganization;
use App\Support\Flash;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SwitchOrganizationController extends Controller
{
    /**
     * Make another of the user's businesses the current one. Only the user's
     * own memberships count, even for a superadmin: admin pages are how
     * superadmins see other businesses. The reader posts `reader=1` and goes
     * back to the scanner; everyone else lands on the dashboard.
     */
    public function __invoke(Request $request, Organization $organization): RedirectResponse
    {
        $membership = $request->user()->membershipFor($organization);

        abort_if($membership === null, 403);

        CurrentOrganization::switchTo($request, $membership);

        Flash::success(FlashMessage::OrganizationSwitched, ['name' => $organization->name]);

        return $request->boolean('reader') ? to_route('scan') : to_route('dashboard');
    }
}
