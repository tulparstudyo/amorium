<?php namespace AmoriumCms\Shop\Classes\Payments;

use AmoriumCms\Shop\Classes\Money;
use AmoriumCms\Shop\Classes\Presenter;

/**
 * PaymentMethods is the registry of payment types.
 */
class PaymentMethods
{
    /**
     * all
     *
     * @return PaymentMethod[]
     */
    public static function all(): array
    {
        $methods = [new BankTransfer, new CashOnDelivery];

        foreach (Gateways::definitions() as $definition) {
            $methods[] = new OmnipayMethod($definition);
        }

        $result = [];
        foreach ($methods as $method) {
            $result[$method->code()] = $method;
        }

        return $result;
    }

    /**
     * find
     */
    public static function find(?string $code): ?PaymentMethod
    {
        return $code ? (static::all()[$code] ?? null) : null;
    }

    /**
     * available returns the methods accepted on the active site
     */
    public static function available(): array
    {
        return array_filter(static::all(), fn ($method) => $method->isAvailable());
    }

    /**
     * present
     */
    public static function present(PaymentMethod $method): array
    {
        $data = [
            'code' => $method->code(),
            'name' => __($method->name()),
            'fee' => Money::toFloat($method->fee()),
            'currency' => Presenter::currency(),
        ];

        if ($method instanceof BankTransfer) {
            $data['accounts'] = $method->accounts();
        }

        if (method_exists($method, 'description')) {
            $data['description'] = __($method->description());
        }

        return $data;
    }
}
