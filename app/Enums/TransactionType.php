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
     * The name shown to people, in the current language. The keys are
     * `type.*` because "Charge" is also a verb elsewhere in the app.
     */
    public function label(): string
    {
        return __('type.'.$this->value);
    }
}
