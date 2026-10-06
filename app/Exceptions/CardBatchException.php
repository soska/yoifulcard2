<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * CardBatchIssuer refused to create or void preissued cards, or
 * CardBatchPrinter refused to print them. Like LedgerException, the message
 * is written in the request's language when the exception is made, from a
 * literal `__('…')` that `php artisan i18n:manifest` declares to the shared
 * catalog. Controllers turn it into a validation error on the given field.
 */
class CardBatchException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $field = 'count',
    ) {
        parent::__construct($message);
    }

    public function translated(): string
    {
        return $this->getMessage();
    }

    public static function countNotPositive(): self
    {
        return new self(__('A batch needs at least one card.'));
    }

    public static function tooMany(int $maximum): self
    {
        return new self(__('A batch can have at most :max cards.', ['max' => $maximum]));
    }

    public static function stockLimitReached(int $limit, int $room): self
    {
        return new self(__('This business can hold at most :limit cards in stock. There is room for :room more.', ['limit' => $limit, 'room' => $room]));
    }

    public static function noProgram(): self
    {
        return new self(__('This business has no active program to issue cards from.'), 'program');
    }

    public static function batchVoided(): self
    {
        return new self(__('This batch is already voided.'), 'batch');
    }

    public static function nothingToPrint(): self
    {
        return new self(__('This batch has no cards in stock to print.'), 'batch');
    }

    public static function templateNotAvailable(): self
    {
        return new self(__('That template is not available here.'), 'template');
    }

    public static function cardNotInactive(): self
    {
        return new self(__('Only a card that is not activated yet can be voided.'), 'status');
    }

    public static function organizationNotWritable(): self
    {
        return new self(__('This business is suspended. Contact support.'), 'organization');
    }
}
