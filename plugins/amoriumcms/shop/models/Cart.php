<?php namespace AmoriumCms\Shop\Models;

use Str;
use Model;

/**
 * Cart is found by its token, for guests and customers alike. Prices are never stored
 * on the cart; totals are calculated from the current prices on every read.
 */
class Cart extends Model
{
    public $table = 'amoriumcms_shop_carts';

    protected $fillable = [
        'user_id', 'site_id', 'email', 'shipping_address_id', 'billing_address_id',
        'guest_shipping_address', 'guest_billing_address', 'shipping_method_id',
        'payment_method', 'customer_note', 'expires_at',
    ];

    protected $jsonable = ['guest_shipping_address', 'guest_billing_address'];

    protected $dates = ['expires_at'];

    public $hasMany = [
        'items' => [CartItem::class, 'key' => 'cart_id', 'delete' => true],
    ];

    public $belongsTo = [
        'user' => \RainLab\User\Models\User::class,
        'shipping_address' => Address::class,
        'billing_address' => Address::class,
        'shipping_method' => ShippingMethod::class,
        'order' => Order::class,
    ];

    /**
     * beforeCreate
     */
    public function beforeCreate()
    {
        $this->token = $this->token ?: (string) Str::uuid();
    }

    /**
     * scopeIsOpen lists the carts that are not ordered yet
     */
    public function scopeIsOpen($query)
    {
        return $query->whereNull('order_id');
    }
}
