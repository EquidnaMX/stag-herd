<?php

namespace Equidna\StagHerd\Support;

final class MoneyFormatter
{
    /**
     * Convert minor units to decimal amount.
     */
    public static function toDecimal(
        int $amount,
        int $decimals = 2,
    ): float {
        return $amount / (10 ** $decimals);
    }

    /**
     * Convert minor units to decimal string.
     */
    public static function toDecimalString(
        int $amount,
        int $decimals = 2,
    ): string {
        return number_format(
            self::toDecimal($amount, $decimals),
            $decimals,
            '.',
            '',
        );
    }

    /**
     * Return amount as minor units.
     */
    public static function toMinorUnits(int $amount): int
    {
        return $amount;
    }

    /**
     * Convert decimal amount to minor units.
     */
    public static function fromDecimal(
        int|float|string $amount,
        int $decimals = 2,
    ): int {
        return (int) round(((float) $amount) * (10 ** $decimals));
    }
}
