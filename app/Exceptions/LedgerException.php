<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A ledger rule refused a balance change. The message is a translation key;
 * controllers turn it into a validation error on the given field.
 */
class LedgerException extends RuntimeException
{
    /**
     * @param  array<string, string>  $replace
     */
    public function __construct(
        public readonly string $key,
        public readonly string $field = 'amount',
        public readonly array $replace = [],
    ) {
        parent::__construct($key);
    }

    public function translated(): string
    {
        return __($this->key, $this->replace);
    }

    public static function invalidAmount(): self
    {
        return new self('Enter an amount with up to two decimals.');
    }

    public static function amountNotPositive(): self
    {
        return new self('The amount must be greater than 0.');
    }

    public static function amountIsZero(): self
    {
        return new self('The adjustment cannot be 0.');
    }

    public static function noteRequired(): self
    {
        return new self('A note is required for adjustments.', 'note');
    }

    public static function insufficientBalance(): self
    {
        return new self('Insufficient balance.');
    }

    public static function belowZero(): self
    {
        return new self('The adjustment would leave a negative balance.');
    }

    public static function aboveMaximum(string $maximum): self
    {
        return new self('The balance cannot be more than :max.', 'amount', ['max' => $maximum]);
    }

    public static function cardFrozen(): self
    {
        return new self('This card is frozen.', 'card');
    }

    public static function cardCancelled(): self
    {
        return new self('This card is cancelled.', 'card');
    }

    public static function organizationNotWritable(): self
    {
        return new self('This business is suspended. Contact support.', 'organization');
    }
}
