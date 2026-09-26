<?php

namespace App\Enums;

enum TransactionType: string
{
    case Load = 'load';
    case Spend = 'spend';
    case Adjustment = 'adjustment';
    // Reserved on the enum. Not exposed in v1.
    case Refund = 'refund';

    /**
     * The types a person can filter by. Refund is not exposed in v1.
     *
     * @return list<string>
     */
    public static function visibleValues(): array
    {
        return [self::Load->value, self::Spend->value, self::Adjustment->value];
    }

    /**
     * The name shown to people, in the current language (the CSV's type
     * column). Literal `__()` calls so the i18n manifest declares them; the
     * browser has its own labels in resources/js/lib/labels.ts.
     */
    public function label(): string
    {
        return match ($this) {
            self::Load => __('Load'),
            self::Spend => __('Charge'),
            self::Adjustment => __('Adjustment'),
            self::Refund => __('Refund'),
        };
    }
}
