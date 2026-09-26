<?php

namespace App\Policies;

use App\Models\Organization;
use App\Models\User;

class OrganizationPolicy
{
    /**
     * Any member can see their organization.
     */
    public function view(User $user, Organization $organization): bool
    {
        return $user->membershipFor($organization) !== null;
    }

    /**
     * Owners and managers can change settings while the organization is active.
     */
    public function update(User $user, Organization $organization): bool
    {
        if (! $organization->isWritable()) {
            return false;
        }

        return $user->membershipFor($organization)?->role->canManageSettings() === true;
    }
}
