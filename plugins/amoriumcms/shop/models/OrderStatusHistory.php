<?php namespace AmoriumCms\Shop\Models;

use Model;

/**
 * OrderStatusHistory records every status change of an order.
 */
class OrderStatusHistory extends Model
{
    const UPDATED_AT = null;

    public $table = 'amoriumcms_shop_order_status_history';

    protected $fillable = ['order_id', 'from_status', 'to_status', 'note', 'backend_user_id', 'customer_notified'];

    protected $casts = ['customer_notified' => 'boolean'];

    public $belongsTo = [
        'order' => Order::class,
        'backend_user' => \Backend\Models\User::class,
    ];
}
