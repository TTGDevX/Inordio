<?php

namespace App\Support;

/**
 * Money math done in integer cents to avoid floating-point penny drift.
 * Storage stays as decimal(…,2) columns; all *calculations* (line totals,
 * subtotals, tax, balances) round to cents at each step and sum in cents.
 */
class Money
{
    /** Convert a dollar amount to whole cents. */
    public static function cents(int|float|string $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }

    /** Round a dollar amount to 2 decimals via cents. */
    public static function round(int|float|string $amount): float
    {
        return self::cents($amount) / 100;
    }

    /**
     * Sum dollar amounts exactly by adding their cents first.
     *
     * @param  iterable<int|float|string>  $amounts
     */
    public static function sum(iterable $amounts): float
    {
        $cents = 0;
        foreach ($amounts as $amount) {
            $cents += self::cents($amount);
        }

        return $cents / 100;
    }
}
