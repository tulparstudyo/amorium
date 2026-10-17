<?php namespace AmoriumCms\Shop\Classes\Payments;

use AmoriumCms\Shop\Models\Order;
use AmoriumCms\Shop\Models\Payment;
use AmoriumCms\Shop\Models\ShopSetting;

/**
 * BankTransfer orders wait for payment until an administrator confirms it,
 * and are cancelled when not paid in the configured number of days.
 */
class BankTransfer implements PaymentMethod
{
    public function code(): string
    {
        return 'bank_transfer';
    }

    public function defaultName(): string
    {
        return 'Bank Transfer';
    }

    public function name(): string
    {
        return PaymentSettings::name('bank_transfer_name', $this->defaultName());
    }

    public function isAvailable(): bool
    {
        return PaymentSettings::enabled('bank_transfer_enabled') && $this->accounts();
    }

    public function fee(): string
    {
        return '0.00';
    }

    public function orderStatus(): string
    {
        return PaymentSettings::orderStatus('bank_transfer_order_status', Order::STATUS_PENDING_PAYMENT);
    }

    public function instructions(Order $order, Payment $payment): array
    {
        return [
            'type' => 'bank_transfer',
            'reference' => $order->number,
            'due_days' => (int) ShopSetting::value('bank_transfer_days'),
            'accounts' => $this->accounts(),
        ];
    }

    /**
     * accounts
     */
    public function accounts(): array
    {
        $accounts = [];

        foreach ((array) ShopSetting::value('bank_accounts') as $row) {
            if (!empty($row['iban'])) {
                $accounts[] = [
                    'bank_name' => $row['bank_name'] ?? null,
                    'account_holder' => $row['account_holder'] ?? null,
                    'iban' => $row['iban'],
                ];
            }
        }

        return $accounts;
    }
}
