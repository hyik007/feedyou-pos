<?php

namespace App\Support;

final class Money
{
    public static function format(int $sen, string $currency = 'RM'): string
    {
        $sign = $sen < 0 ? '-' : '';

        return $sign.$currency.' '.number_format(abs($sen) / 100, 2);
    }

    /** "12.50" | 12.5 -> 1250 */
    public static function toSen(string|int|float $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }

    /** Malaysian rounding mechanism: total rounded to the nearest 5 sen. */
    public static function roundTo5Sen(int $sen): int
    {
        return (int) (round($sen / 5) * 5);
    }
}
