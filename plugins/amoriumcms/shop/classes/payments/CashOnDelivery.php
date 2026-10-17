<?php namespace AmoriumCms\Shop\Classes\Payments;

use AmoriumCms\Shop\Classes\Money;
use AmoriumCms\Shop\Models\Order;
use AmoriumCms\Shop\Models\Payment;
use AmoriumCms\Shop\Models\ShopSetting;

/**
 * CashOnDelivery orders are prepared right away; the payment is completed on delivery.
 */
class CashOnDelivery implements PaymentMethod
{
    public function code(): string
    {
        return 'cash_on_delivery';
    }

    public function defaultName(): string
    {
        return 'Cash on Delivery';
    }

    public function name(): string
    {
        return PaymentSettings::name('cod_name', $this->defaultName());
    }

    public function isAvailable(): bool
    {
        return PaymentSettings::enabled('cod_enabled');
    }

    public function fee(): string
    {
        return Money::round(ShopSetting::value('cod_fee') ?: 0);
    }

    public function orderStatus(): string
    {
        return PaymentSettings::orderStatus('cod_order_status', Order::STATUS_PROCESSING);
    }

    public function instructions(Order $order, Payment $payment): array
    {
        return [
            'type' => 'cash_on_delivery',
            'amount' => Money::toFloat($payment->amount),
        ];
    }
}
