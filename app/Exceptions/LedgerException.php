<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A ledger rule refused a balance change. The message is written in the
 * request's language when the exception is made: each factory below passes a
 * literal `__('…')`, so `php artisan i18n:manifest` declares it to the shared
 * catalog. Controllers turn it into a validation error on the given field.
 */
class LedgerException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $field = 'amount',
    ) {
        parent::__construct($message);
    }

    public function translated(): string
    {
        return $this->getMessage();
    }

    public static function invalidAmount(): self
    {
        return new self(__('Enter an amount with up to two decimals.'));
    }

    public static function amountNotPositive(): self
    {
        return new self(__('The amount must be greater than 0.'));
    }

    public static function amountIsZero(): self
    {
        return new self(__('The adjustment cannot be 0.'));
    }

    public static function noteRequired(): self
    {
        return new self(__('A note is required for adjustments.'), 'note');
    }

    public static function insufficientBalance(): self
    {
        return new self(__('Insufficient balance.'));
    }

    public static function belowZero(): self
    {
        return new self(__('The adjustment would leave a negative balance.'));
    }

    public static function aboveMaximum(string $maximum): self
    {
        return new self(__('The balance cannot be more than :max.', ['max' => $maximum]));
    }

    public static function cardFrozen(): self
    {
        return new self(__('This card is frozen.'), 'card');
    }

    public static function cardCancelled(): self
    {
        return new self(__('This card is cancelled.'), 'card');
    }

    public static function cardInactive(): self
    {
        return new self(__('This card is not activated yet.'), 'card');
    }

    public static function cardNotInactive(): self
    {
        return new self(__('This card is already activated.'), 'card');
    }

    public static function cardLimitReached(int $limit): self
    {
        return new self(__('You have reached your plan limit of :limit cards. Contact support to raise the limit.', ['limit' => $limit]), 'card_limit');
    }

    public static function organizationNotWritable(): self
    {
        return new self(__('This business is suspended. Contact support.'), 'organization');
    }
}
