<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Membership;
use App\Models\User;
use App\Support\OneTimeCredentials;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public const PER_PAGE = 20;

    /**
     * Every user, newest first, searchable by name or email, with their
     * organizations and whether they are a superadmin.
     */
    public function index(Request $request): Response
    {
        OneTimeCredentials::release($request);

        $search = trim((string) $request->query('q', ''));

        $users = User::query()
            ->with(['superadmin', 'memberships' => fn ($query) => $query->with('organization:id,name,status')->oldest()->oldest('id')])
            ->when($search !== '', function (Builder $query) use ($search): void {
                $pattern = '%'.addcslashes(mb_strtolower($search), '\\%_').'%';

                $query->where(fn (Builder $query) => $query
                    ->whereRaw('lower(name) like ?', [$pattern])
                    ->orWhereRaw('lower(email) like ?', [$pattern]));
            })
            ->latest()
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'created_at' => $user->created_at?->toIso8601String(),
                'is_superadmin' => $user->superadmin !== null,
                'superadmin_granted_at' => $user->superadmin?->granted_at?->toIso8601String(),
                'memberships' => $user->memberships->map(fn (Membership $membership) => [
                    'organization_id' => $membership->organization_id,
                    'organization' => $membership->organization?->name,
                    'role' => $membership->role->value,
                ])->values(),
            ]);

        return Inertia::render('admin/users/index', [
            'users' => $users,
            'filters' => ['q' => $search],
        ]);
    }
}
