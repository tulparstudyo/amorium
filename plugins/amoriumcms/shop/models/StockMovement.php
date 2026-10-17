<?php namespace AmoriumCms\Shop\Models;

use Model;

/**
 * StockMovement records every stock change with its reason.
 */
class StockMovement extends Model
{
    const REASON_ORDER = 'order';
    const REASON_CANCEL = 'cancel';
    const REASON_MANUAL = 'manual';

    const UPDATED_AT = null;

    public $table = 'amoriumcms_shop_stock_movements';

    protected $fillable = ['product_id', 'quantity_change', 'stock_after', 'reason', 'order_id', 'backend_user_id', 'note'];

    public $belongsTo = [
        'product' => Product::class,
        'order' => Order::class,
        'backend_user' => \Backend\Models\User::class,
    ];
}
