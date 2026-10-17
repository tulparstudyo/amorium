<?php namespace AmoriumCms\Shop\Models;

use Model;

/**
 * CartItem
 */
class CartItem extends Model
{
    public $table = 'amoriumcms_shop_cart_items';

    protected $fillable = ['cart_id', 'product_id', 'quantity'];

    protected $casts = ['quantity' => 'integer'];

    public $belongsTo = [
        'cart' => Cart::class,
        'product' => Product::class,
    ];
}
