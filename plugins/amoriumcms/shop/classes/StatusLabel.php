<?php namespace AmoriumCms\Shop\Classes;

/**
 * StatusLabel turns a status code into its English label, the key of the translations:
 * pending_payment => "Pending payment". The Vue pages use the same rule.
 */
class StatusLabel
{
    /**
     * of
     */
    public static function of(?string $status): string
    {
        return ucfirst(str_replace('_', ' ', (string) $status));
    }

    /**
     * options returns code => label for a list of codes
     */
    public static function options(array $codes): array
    {
        return array_combine($codes, array_map([static::class, 'of'], $codes));
    }
}
