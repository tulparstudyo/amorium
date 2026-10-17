<?php namespace AmoriumCms\Shop\Models;

use Model;

/**
 * OrderItem is a copy of the barcode, name, variant and price at the time of the order.
 */
class OrderItem extends Model
{
    public $table = 'amoriumcms_shop_order_items';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected $jsonable = ['variant_label'];

    protected $casts = ['quantity' => 'integer'];

    public $belongsTo = [
        'order' => Order::class,
        'product' => Product::class,
    ];
}
