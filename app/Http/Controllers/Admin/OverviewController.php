<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrganizationStatus;
use App\Http\Controllers\Controller;
use App\Models\Card;
use App\Models\Organization;
use App\Models\Superadmin;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

class OverviewController extends Controller
{
    /** How many new organizations the overview lists. */
    public const RECENT_LIMIT = 5;

    /**
     * Platform totals and the newest organizations.
     */
    public function __invoke(): Response
    {
        $organizations = Organization::query()
            ->toBase()
            ->selectRaw('count(*) as total')
            ->selectRaw('count(*) filter (where status = ?) as active', [OrganizationStatus::Active->value])
            ->selectRaw('count(*) filter (where status = ?) as suspended', [OrganizationStatus::Suspended->value])
            ->first();

        return Inertia::render('admin/index', [
            'stats' => [
                'organizations' => (int) $organizations->total,
                'activeOrganizations' => (int) $organizations->active,
                'suspendedOrganizations' => (int) $organizations->suspended,
                'cards' => Card::query()->count(),
                'users' => User::query()->count(),
                'superadmins' => Superadmin::query()->count(),
            ],
            'recentOrganizations' => Organization::query()
                ->latest()
                ->orderByDesc('id')
                ->limit(self::RECENT_LIMIT)
                ->get()
                ->map(fn (Organization $organization) => [
                    'id' => $organization->id,
                    'name' => $organization->name,
                    'slug' => $organization->slug,
                    'status' => $organization->status->value,
                    'created_at' => $organization->created_at?->toIso8601String(),
                ]),
        ]);
    }
}
