<?php

namespace App\Services;

use App\Enums\CardStatus;
use App\Enums\TransactionType;
use App\Enums\TransactionUnit;
use App\Exceptions\LedgerException;
use App\Models\Card;
use App\Models\Organization;
use App\Models\Program;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The only code that writes card balances and points.
 *
 * Every change locks the card row, checks the rules against the locked
 * balance, records a transaction with the balance after the change, and
 * updates the card, all in one database transaction. Amounts are decimal
 * strings with up to two decimals and all arithmetic uses bcmath. Points are
 * whole numbers kept in their own column, with their own ledger entries
 * (unit: points); they never change the money balance or the card's status.
 */
class CardLedger
{
    /**
     * The largest value a decimal(10,2) column holds.
     */
    public const MAX_BALANCE = '99999999.99';

    /**
     * The most points a card holds.
     */
    public const MAX_POINTS = 99999999;

    private const SCALE = 2;

    /**
     * Add funds to a card.
     */
    public function load(Card $card, string $amount, User $user, ?string $note = null): Transaction
    {
        $amount = $this->positive($amount);

        return $this->post($card, TransactionType::Load, $user, $note, function (string $balance) use ($amount): string {
            return bcadd($balance, $amount, self::SCALE);
        }, $amount);
    }

    /**
     * Load a new card's initial balance. It is recorded as a load, but it is
     * not a use: `last_used_at` stays as it was (null on a new card).
     */
    public function issue(Card $card, string $amount, User $user): Transaction
    {
        $amount = $this->positive($amount);

        return $this->post($card, TransactionType::Load, $user, null, function (string $balance) use ($amount): string {
            return bcadd($balance, $amount, self::SCALE);
        }, $amount, marksUse: false);
    }

    /**
     * Activate a preissued (inactive) card with its first load. Like issue(),
     * the load is not a use. The card becomes active and gets `activated_at`.
     * An activated card counts toward the card limit, so this is refused at
     * the limit, under the same organization lock as creating a card.
     */
    public function activate(Card $card, string $amount, User $user): Transaction
    {
        $amount = $this->positive($amount);

        return DB::transaction(function () use ($card, $amount, $user): Transaction {
            // The organization row first, then the card (CardLimit's lock
            // order), so two activations cannot both take the last slot.
            $organization = CardLimit::lock(
                (string) Program::query()->whereKey($card->program_id)->value('organization_id'),
            );

            return $this->post($card, TransactionType::Load, $user, null, function (string $balance) use ($amount): string {
                return bcadd($balance, $amount, self::SCALE);
            }, $amount, marksUse: false, activating: $organization);
        });
    }

    /**
     * Charge a card. The amount may not be above the locked balance.
     */
    public function spend(Card $card, string $amount, User $user, ?string $note = null): Transaction
    {
        $amount = $this->positive($amount);

        return $this->post($card, TransactionType::Spend, $user, $note, function (string $balance) use ($amount): string {
            if (bccomp($amount, $balance, self::SCALE) > 0) {
                throw LedgerException::insufficientBalance();
            }

            return bcsub($balance, $amount, self::SCALE);
        }, $amount);
    }

    /**
     * Correct a card's balance up (positive amount) or down (negative
     * amount). A note is required and the result may not go below zero.
     */
    public function adjust(Card $card, string $amount, User $user, ?string $note): Transaction
    {
        $amount = $this->normalize($amount);

        if (bccomp($amount, '0', self::SCALE) === 0) {
            throw LedgerException::amountIsZero();
        }

        if ($note === null || trim($note) === '') {
            throw LedgerException::noteRequired();
        }

        return $this->post($card, TransactionType::Adjustment, $user, $note, function (string $balance) use ($amount): string {
            $after = bcadd($balance, $amount, self::SCALE);

            if (bccomp($after, '0', self::SCALE) < 0) {
                throw LedgerException::belowZero();
            }

            return $after;
        }, $amount, marksUse: false);
    }

    /**
     * Activate a preissued (inactive) card with no money on it, for a card
     * that will only collect reward points. No ledger entry is written (there
     * is no amount to record); the card becomes active and gets
     * `activated_at`, and `last_used_at` stays as it was. Like activate(), it
     * counts toward the card limit and is refused at the limit.
     */
    public function activateWithoutFunds(Card $card): Card
    {
        $locked = DB::transaction(function () use ($card): Card {
            $organization = CardLimit::lock(
                (string) Program::query()->whereKey($card->program_id)->value('organization_id'),
            );

            [$locked] = $this->lockUsable($card, activating: true);

            CardLimit::ensureRoom($organization);

            $locked->status = CardStatus::Active;
            $locked->activated_at = now();
            $locked->save();

            return $locked;
        });

        $card->setRawAttributes($locked->getAttributes(), true);

        return $card;
    }

    /**
     * Award points to a card.
     */
    public function earn(Card $card, int $points, User $user, ?string $note = null): Transaction
    {
        $points = $this->positivePoints($points);

        return $this->postPoints($card, TransactionType::Earn, $user, $note, fn (int $balance): int => $balance + $points, $points);
    }

    /**
     * Spend points on a reward. The amount may not be above the locked points.
     */
    public function redeem(Card $card, int $points, User $user, ?string $note = null): Transaction
    {
        $points = $this->positivePoints($points);

        return $this->postPoints($card, TransactionType::Redeem, $user, $note, function (int $balance) use ($points): int {
            if ($points > $balance) {
                throw LedgerException::insufficientPoints();
            }

            return $balance - $points;
        }, $points);
    }

    /**
     * Correct a card's points up (positive) or down (negative). A note is
     * required and the result may not go below zero. Not a use of the card.
     */
    public function adjustPoints(Card $card, int $points, User $user, ?string $note): Transaction
    {
        if ($points === 0) {
            throw LedgerException::pointsAreZero();
        }

        if ($note === null || trim($note) === '') {
            throw LedgerException::noteRequired();
        }

        return $this->postPoints($card, TransactionType::PointsAdjustment, $user, $note, function (int $balance) use ($points): int {
            $after = $balance + $points;

            if ($after < 0) {
                throw LedgerException::pointsBelowZero();
            }

            return $after;
        }, $points, marksUse: false);
    }

    /**
     * @param  callable(numeric-string): numeric-string  $apply  Receives the locked balance and returns the new one, or throws.
     * @param  numeric-string  $amount
     * @param  bool  $marksUse  Whether the change sets `last_used_at`. Adjustments and the initial load do not.
     * @param  Organization|null  $activating  When activating, the card's organization, already locked. Only an inactive card is accepted, and it must fit under the card limit.
     */
    private function post(Card $card, TransactionType $type, User $user, ?string $note, callable $apply, string $amount, bool $marksUse = true, ?Organization $activating = null): Transaction
    {
        $note = $note === null || trim($note) === '' ? null : trim($note);

        [$transaction, $locked] = DB::transaction(function () use ($card, $type, $user, $note, $apply, $amount, $marksUse, $activating): array {
            [$locked] = $this->lockUsable($card, $activating !== null);

            if ($activating !== null) {
                CardLimit::ensureRoom($activating);
            }

            $before = $this->normalize((string) $locked->balance);
            $after = $apply($before);

            if (bccomp($after, self::MAX_BALANCE, self::SCALE) > 0) {
                throw LedgerException::aboveMaximum(self::MAX_BALANCE);
            }

            $transaction = $locked->transactions()->create([
                'type' => $type,
                'amount' => $amount,
                'balance_after' => $after,
                'note' => $note,
                'performed_by' => $user->getKey(),
            ]);

            $locked->balance = $after;
            $locked->status = $activating !== null ? CardStatus::Active : $this->statusAfter($locked->status, $after);

            if ($activating !== null) {
                $locked->activated_at = $transaction->created_at ?? now();
            }

            if ($marksUse) {
                $locked->last_used_at = $transaction->created_at ?? now();
            }

            $locked->save();

            return [$transaction, $locked];
        });

        // Hand the caller's model the committed state.
        $card->setRawAttributes($locked->getAttributes(), true);

        return $transaction;
    }

    /**
     * Lock the card row and refuse it unless it can take a change: its
     * organization is writable, it is not frozen or cancelled, and it is
     * active (or, when activating, inactive).
     *
     * @return array{0: Card, 1: Organization}
     */
    private function lockUsable(Card $card, bool $activating = false): array
    {
        /** @var Card $locked */
        $locked = Card::query()->whereKey($card->getKey())->lockForUpdate()->firstOrFail();

        /** @var Organization $organization */
        $organization = Organization::query()
            ->whereIn('id', Program::query()->select('organization_id')->whereKey($locked->program_id))
            ->firstOrFail();

        if (! $organization->isWritable()) {
            throw LedgerException::organizationNotWritable();
        }

        if ($locked->status === CardStatus::Frozen) {
            throw LedgerException::cardFrozen();
        }

        if ($locked->status === CardStatus::Cancelled) {
            throw LedgerException::cardCancelled();
        }

        if (! $activating && $locked->status === CardStatus::Inactive) {
            throw LedgerException::cardInactive();
        }

        if ($activating && $locked->status !== CardStatus::Inactive) {
            throw LedgerException::cardNotInactive();
        }

        return [$locked, $organization];
    }

    /**
     * Post a points entry. The card must be usable and its program must have
     * rewards on. The money balance and the card's status do not change.
     *
     * @param  callable(int): int  $apply  Receives the locked points and returns the new total, or throws.
     * @param  bool  $marksUse  Whether the change sets `last_used_at`. Point adjustments do not.
     */
    private function postPoints(Card $card, TransactionType $type, User $user, ?string $note, callable $apply, int $points, bool $marksUse = true): Transaction
    {
        $note = $note === null || trim($note) === '' ? null : trim($note);

        [$transaction, $locked] = DB::transaction(function () use ($card, $type, $user, $note, $apply, $points, $marksUse): array {
            [$locked] = $this->lockUsable($card);

            $rewardsEnabled = Program::query()->whereKey($locked->program_id)->value('rewards_enabled');

            if (! $rewardsEnabled) {
                throw LedgerException::rewardsNotEnabled();
            }

            $after = $apply((int) $locked->points);

            if ($after > self::MAX_POINTS) {
                throw LedgerException::pointsAboveMaximum(self::MAX_POINTS);
            }

            $transaction = $locked->transactions()->create([
                'type' => $type,
                'unit' => TransactionUnit::Points,
                'amount' => (string) $points,
                'balance_after' => (string) $after,
                'note' => $note,
                'performed_by' => $user->getKey(),
            ]);

            $locked->points = $after;

            if ($marksUse) {
                $locked->last_used_at = $transaction->created_at ?? now();
            }

            $locked->save();

            return [$transaction, $locked];
        });

        $card->setRawAttributes($locked->getAttributes(), true);

        return $transaction;
    }

    private function positivePoints(int $points): int
    {
        if ($points <= 0) {
            throw LedgerException::pointsNotPositive();
        }

        return $points;
    }

    /**
     * An active card that reaches zero is depleted; a depleted card that
     * gets funds is active again. Other statuses never reach here (an
     * inactive card only through activate(), which sets it active).
     *
     * @param  numeric-string  $balance
     */
    private function statusAfter(CardStatus $status, string $balance): CardStatus
    {
        $empty = bccomp($balance, '0', self::SCALE) === 0;

        return match (true) {
            $status === CardStatus::Active && $empty => CardStatus::Depleted,
            $status === CardStatus::Depleted && ! $empty => CardStatus::Active,
            default => $status,
        };
    }

    /**
     * @return numeric-string
     */
    private function positive(string $amount): string
    {
        $amount = $this->normalize($amount);

        if (bccomp($amount, '0', self::SCALE) <= 0) {
            throw LedgerException::amountNotPositive();
        }

        return $amount;
    }

    /**
     * Accept a plain decimal string with at most two decimals and return it
     * with exactly two. Anything else, such as "0.30000000000000004" from a
     * float, "1e3", or "", is refused.
     *
     * @return numeric-string
     */
    private function normalize(string $amount): string
    {
        $amount = trim($amount);

        if (preg_match('/^[+-]?(\d+(\.\d{0,2})?|\.\d{1,2})$/', $amount) !== 1) {
            throw LedgerException::invalidAmount();
        }

        $amount = ltrim($amount, '+');

        // Always numeric after the pattern; this tells bcmath's types so.
        if (! is_numeric($amount)) {
            throw LedgerException::invalidAmount();
        }

        return bcadd($amount, '0', self::SCALE);
    }
}
