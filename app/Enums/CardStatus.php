<?php

namespace App\Enums;

enum CardStatus: string
{
    /**
     * Preissued for printing: the card exists but can't be used until the
     * business activates it with an amount (CardLedger::activate).
     */
    case Inactive = 'inactive';
    case Active = 'active';
    case Frozen = 'frozen';
    case Depleted = 'depleted';
    case Cancelled = 'cancelled';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
