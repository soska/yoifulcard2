<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Money values as two-place decimal strings for bcmath, from database
 * columns, sums and validated input, without going through float.
 */
final class Decimal
{
    /**
     * A null (the sum of no rows) is zero; anything else must be numeric.
     *
     * @return numeric-string
     */
    public static function of(mixed $value): string
    {
        $value = trim((string) ($value ?? '0'));

        if (! is_numeric($value)) {
            throw new InvalidArgumentException("Not a decimal: \"{$value}\".");
        }

        return bcadd($value, '0', 2);
    }
}
