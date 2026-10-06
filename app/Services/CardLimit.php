<?php

namespace App\Services;

use App\Exceptions\LedgerException;
use App\Models\Organization;

/**
 * The plan's card limit, checked under a lock on the organization row.
 *
 * Creating a card and activating a preissued one both take a slot. Each one
 * locks the organization row first and counts under that lock, so two
 * requests cannot both take the last slot. Call these inside a database
 * transaction; the lock is held until it ends.
 *
 * Lock order: the organization row, then card rows. Anything that locks
 * both must take them in that order.
 */
class CardLimit
{
    /**
     * Lock the organization row, then refuse unless it can take one more
     * card: it must be writable and below its card limit.
     *
     * @throws LedgerException
     */
    public static function reserve(string $organizationId): Organization
    {
        $organization = self::lock($organizationId);

        self::ensureRoom($organization);

        return $organization;
    }

    /**
     * Lock the organization row for the rest of the transaction.
     */
    public static function lock(string $organizationId): Organization
    {
        return Organization::query()->lockForUpdate()->findOrFail($organizationId);
    }

    /**
     * Refuse unless the organization, already locked, can take one more card.
     *
     * @throws LedgerException
     */
    public static function ensureRoom(Organization $organization): void
    {
        if (! $organization->isWritable()) {
            throw LedgerException::organizationNotWritable();
        }

        if ($organization->card_limit !== null && $organization->cardUsage()['atLimit']) {
            throw LedgerException::cardLimitReached($organization->card_limit);
        }
    }
}
