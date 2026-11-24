<?php

namespace App\Enums;

enum TransactionUnit: string
{
    case Money = 'money';
    case Points = 'points';

    /**
     * The name shown to people, in the current language (the CSV's unit
     * column). Literal `__()` calls so the i18n manifest declares them.
     */
    public function label(): string
    {
        return match ($this) {
            self::Money => __('Money'),
            self::Points => __('Points'),
        };
    }
}
