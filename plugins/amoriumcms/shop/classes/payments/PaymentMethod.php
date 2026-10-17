<?php namespace AmoriumCms\Shop\Classes\Payments;

use AmoriumCms\Shop\Models\Order;
use AmoriumCms\Shop\Models\Payment;

/**
 * PaymentMethod is implemented by every payment type. Online payments added later
 * return a redirect URL from instructions().
 */
interface PaymentMethod
{
    /**
     * code stored on the order and payment
     */
    public function code(): string;

    /**
     * defaultName is the English name used when a site has not set its own
     */
    public function defaultName(): string;

    /**
     * name shown to the customer on the active site
     */
    public function name(): string;

    /**
     * isAvailable on the active site
     */
    public function isAvailable(): bool;

    /**
     * fee is the VAT inclusive extra charge of the method
     */
    public function fee(): string;

    /**
     * orderStatus is the status of a newly placed order
     */
    public function orderStatus(): string;

    /**
     * instructions tell the customer how to complete the payment
     */
    public function instructions(Order $order, Payment $payment): array;
}
