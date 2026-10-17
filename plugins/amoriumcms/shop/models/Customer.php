<?php namespace AmoriumCms\Shop\Models;

use Model;

/**
 * Customer holds the shop details of a RainLab.User user.
 */
class Customer extends Model
{
    const TYPE_INDIVIDUAL = 'individual';
    const TYPE_CORPORATE = 'corporate';

    public $table = 'amoriumcms_shop_customers';

    protected $fillable = ['user_id', 'phone', 'type', 'company_name', 'tax_number', 'tax_office'];

    public $belongsTo = [
        'user' => \RainLab\User\Models\User::class,
    ];

    /**
     * forUser returns the customer row of a user, creating it when missing
     */
    public static function forUser($user): static
    {
        return static::firstOrCreate(['user_id' => $user->getKey()], ['type' => static::TYPE_INDIVIDUAL]);
    }
}
