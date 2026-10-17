<?php namespace AmoriumCms\Shop\Models;

use Model;

/**
 * Payment of an order. An order can have more than one payment.
 */
class Payment extends Model
{
    const STATUS_PENDING = 'pending';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';
    const STATUS_REFUNDED = 'refunded';

    public $table = 'amoriumcms_shop_payments';

    protected $fillable = ['order_id', 'method', 'amount', 'status', 'reference', 'meta', 'paid_at'];

    protected $jsonable = ['meta'];

    protected $dates = ['paid_at'];

    public $belongsTo = [
        'order' => Order::class,
    ];
}
