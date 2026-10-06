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

    /**
     * Owners and managers can see the card batches of a business that is
     * allowed to preissue cards (can_preissue). Superadmins see every batch
     * from the admin area instead.
     */
    public function viewBatches(User $user, Organization $organization): bool
    {
        return $organization->can_preissue
            && $user->membershipFor($organization)?->role->canManageSettings() === true;
    }

    /**
     * Owners and managers can create and void card batches while the
     * organization is active and allowed to preissue cards. CardBatchIssuer
     * checks the organization again under its lock.
     */
    public function preissue(User $user, Organization $organization): bool
    {
        return $organization->isWritable() && $this->viewBatches($user, $organization);
    }
}
