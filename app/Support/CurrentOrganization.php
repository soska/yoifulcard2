<?php

namespace App\Support;

use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Contracts\Session\Session;
use Illuminate\Http\Request;

/**
 * Finds the organization the signed-in user is working in. The result is
 * kept on the request, so each request resolves it at most once.
 */
class CurrentOrganization
{
    public const SESSION_KEY = 'current_organization_id';

    private const ATTRIBUTE = 'currentMembership';

    /**
     * Find the membership for the organization stored in the session, falling
     * back to the user's first membership, and remember it in the session.
     */
    public static function resolve(Request $request): ?Membership
    {
        if ($request->attributes->has(self::ATTRIBUTE)) {
            return $request->attributes->get(self::ATTRIBUTE);
        }

        $user = $request->user();
        $membership = null;

        if ($user instanceof User && $request->hasSession()) {
            $session = $request->session();
            $stored = $session->get(self::SESSION_KEY);

            $membership = is_string($stored) ? $user->membershipFor($stored) : null;
            $membership ??= $user->firstMembership();
            $membership?->loadMissing('organization');

            self::store($session, $membership);
        }

        $request->attributes->set(self::ATTRIBUTE, $membership);

        return $membership;
    }

    /**
     * Store the user's first organization in the session, for example right
     * after login.
     */
    public static function remember(User $user, Session $session): void
    {
        self::store($session, $user->firstMembership());
    }

    public static function membership(Request $request): ?Membership
    {
        return self::resolve($request);
    }

    public static function organization(Request $request): ?Organization
    {
        return self::resolve($request)?->organization;
    }

    /**
     * The shape shared with every page as the `currentOrganization` prop.
     *
     * @return array{id: string, name: string, status: string, timezone: string, role: string}|null
     */
    public static function toProp(Request $request): ?array
    {
        $membership = self::resolve($request);
        $organization = $membership?->organization;

        if ($membership === null || $organization === null) {
            return null;
        }

        return [
            'id' => $organization->id,
            'name' => $organization->name,
            'status' => $organization->status->value,
            'timezone' => $organization->timezone,
            'role' => $membership->role->value,
        ];
    }

    private static function store(Session $session, ?Membership $membership): void
    {
        if ($membership === null) {
            $session->forget(self::SESSION_KEY);

            return;
        }

        $session->put(self::SESSION_KEY, $membership->organization_id);
    }
}
