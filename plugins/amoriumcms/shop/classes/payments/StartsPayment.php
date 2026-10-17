<?php namespace AmoriumCms\Shop\Classes\Payments;

use AmoriumCms\Shop\Models\Order;
use AmoriumCms\Shop\Models\Payment;

/**
 * StartsPayment is implemented by methods that open a payment at the gateway
 * when the order is placed.
 */
interface StartsPayment
{
    /**
     * start opens the payment and stores what the customer needs to finish it
     */
    public function start(Order $order, Payment $payment): void;
}
