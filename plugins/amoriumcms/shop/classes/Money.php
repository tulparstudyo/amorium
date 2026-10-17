<?php namespace AmoriumCms\Shop\Classes;

/**
 * Money calculates amounts as strings with bcmath, rounding half up to two decimals.
 */
class Money
{
    /**
     * round
     */
    public static function round($value, int $scale = 2): string
    {
        $value = (string) $value;
        $half = '0.' . str_repeat('0', $scale) . '5';

        return str_starts_with($value, '-')
            ? bcsub($value, $half, $scale)
            : bcadd($value, $half, $scale);
    }

    /**
     * add
     */
    public static function add(...$values): string
    {
        $total = '0';
        foreach ($values as $value) {
            $total = bcadd($total, (string) ($value ?? '0'), 6);
        }

        return static::round($total);
    }

    /**
     * multiply
     */
    public static function multiply($value, $times): string
    {
        return static::round(bcmul((string) $value, (string) $times, 6));
    }

    /**
     * includedTax returns the VAT part of a VAT inclusive amount
     */
    public static function includedTax($amount, $rate): string
    {
        $amount = (string) $amount;
        $divisor = bcadd('1', bcdiv((string) $rate, '100', 6), 6);
        $net = bcdiv($amount, $divisor, 6);

        return static::round(bcsub($amount, $net, 6));
    }

    /**
     * compare
     */
    public static function compare($a, $b): int
    {
        return bccomp((string) $a, (string) $b, 2);
    }

    /**
     * toFloat is used for JSON output only
     */
    public static function toFloat($value): ?float
    {
        return $value === null ? null : (float) static::round($value);
    }
}
