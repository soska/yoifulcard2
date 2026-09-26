<?php

namespace App\Enums;

enum CardStatus: string
{
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
