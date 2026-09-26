<?php

namespace App\Policies;

use App\Models\Card;
use App\Models\Organization;
use App\Models\User;

class CardPolicy
{
    /**
     * Members of the card's organization can see it, including its QR image.
     */
    public function view(User $user, Card $card): bool
    {
        return $user->membershipFor($card->organization()) !== null;
    }

    /**
     * Members can create cards while their organization is active.
     */
    public function create(User $user, Organization $organization): bool
    {
        return $organization->isWritable()
            && $user->membershipFor($organization) !== null;
    }

    /**
     * Members can freeze, unfreeze, and set the email while the card's
     * organization is active.
     */
    public function update(User $user, Card $card): bool
    {
        $organization = $card->organization();

        return $organization->isWritable()
            && $user->membershipFor($organization) !== null;
    }

    /**
     * Members can load, charge, and adjust the card while its organization
     * is active. CardLedger checks the organization again under its lock.
     */
    public function transact(User $user, Card $card): bool
    {
        $organization = $card->organization();

        return $organization->isWritable()
            && $user->membershipFor($organization) !== null;
    }
}
