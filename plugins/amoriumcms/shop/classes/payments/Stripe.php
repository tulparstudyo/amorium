<?php namespace AmoriumCms\Shop\Classes\Payments;

use Log;
use Str;
use Url;
use Site;
use Cms\Facades\Cms;
use Omnipay\Omnipay;
use AmoriumCms\Shop\Classes\Money;
use AmoriumCms\Shop\Classes\Api\ApiCode;
use AmoriumCms\Shop\Classes\Api\ApiException;
use AmoriumCms\Shop\Classes\OrderService;
use AmoriumCms\Shop\Models\Order;
use AmoriumCms\Shop\Models\Payment;
use AmoriumCms\Shop\Models\ShopSetting;

/**
 * Stripe sends the customer to Stripe Checkout. Card details are entered there,
 * and the payment is confirmed only after Stripe reports the session as paid.
 */
class Stripe implements PaymentMethod, StartsPayment
{
    public function code(): string
    {
        return 'stripe';
    }

    public function defaultName(): string
    {
        return 'Card';
    }

    public function name(): string
    {
        return PaymentSettings::name('stripe_name', $this->defaultName());
    }

    public function description(): string
    {
        return 'You will be redirected to the secure payment page after you place the order.';
    }

    public function isAvailable(): bool
    {
        return PaymentSettings::enabled('stripe_enabled') && $this->secret() !== '';
    }

    public function fee(): string
    {
        return '0.00';
    }

    public function orderStatus(): string
    {
        return PaymentSettings::orderStatus('stripe_order_status', Order::STATUS_PENDING_PAYMENT);
    }

    public function instructions(Order $order, Payment $payment): array
    {
        return [
            'type' => 'redirect',
            'url' => $payment->meta['checkout_url'] ?? null,
        ];
    }

    /**
     * start creates a Checkout Session and keeps its address on the payment
     */
    public function start(Order $order, Payment $payment): void
    {
        if ($payment->reference) {
            return;
        }

        $token = Str::random(40);
        $return = Url::to('api/v1/' . Site::getActiveSite()->code . '/payments/stripe/return');

        try {
            $response = $this->gateway()->purchase([
                'success_url' => $return . '?token=' . $token . '&session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => $this->orderUrl($order),
                'payment_method_types' => ['card'],
                'mode' => 'payment',
                'line_items' => [[
                    'quantity' => 1,
                    'price_data' => [
                        'currency' => strtolower($order->currency),
                        'unit_amount' => $this->minorAmount($payment->amount, $order->currency),
                        'product_data' => [
                            'name' => __('Order :number', ['number' => $order->number]),
                        ],
                    ],
                ]],
            ])->send();
        }
        catch (\Throwable $exception) {
            $this->failed($order, $exception);
        }

        $data = is_array($response->getData()) ? $response->getData() : [];
        $sessionId = $data['id'] ?? null;
        $url = $data['url'] ?? null;

        if (!$response->isSuccessful() || !$sessionId || !$url) {
            $this->failed($order, null, $response->getMessage());
        }

        $payment->reference = $sessionId;
        $payment->meta = [
            'checkout_url' => $url,
            'return_token' => $token,
        ];
        $payment->save();
    }

    /**
     * complete marks the payment paid when the Checkout Session Stripe returns is paid
     * for this order. An already confirmed payment is left as it is.
     */
    public function complete(Payment $payment, string $sessionId): bool
    {
        $order = $payment->order;

        if ($payment->method !== $this->code() || $payment->reference !== $sessionId || !$order) {
            return false;
        }

        if ($payment->status === Payment::STATUS_COMPLETED) {
            return true;
        }

        try {
            $response = $this->gateway()->fetchTransaction([
                'transactionReference' => $sessionId,
            ])->send();
        }
        catch (\Throwable $exception) {
            Log::warning('Stripe session could not be read.', [
                'order_id' => $order->id,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }

        $session = $response->getData();

        if (!$response->isSuccessful() || !$this->sessionMatches($order, $payment, $session)) {
            Log::warning('Stripe session does not match the order.', [
                'order_id' => $order->id,
                'session_id' => $sessionId,
            ]);

            return false;
        }

        $meta = (array) $payment->meta;
        $meta['payment_intent'] = $session['payment_intent'] ?? null;
        $payment->meta = $meta;
        $payment->save();

        try {
            OrderService::confirmPayment($order, $payment);
        }
        catch (ApiException $exception) {
            if ($exception->apiCode !== ApiCode::PAYMENT_ALREADY_CONFIRMED) {
                Log::warning('Stripe payment could not be confirmed.', [
                    'order_id' => $order->id,
                    'code' => $exception->apiCode,
                ]);

                return false;
            }
        }

        return true;
    }

    /**
     * minorAmount converts a major-unit amount to Stripe's smallest currency unit
     */
    public function minorAmount($amount, string $currency): int
    {
        $factor = bcpow('10', (string) $this->exponent($currency), 0);

        return (int) bcmul(Money::round($amount), $factor, 0);
    }

    /**
     * orderUrl is the shop order page the customer returns to
     */
    public function orderUrl(Order $order, array $query = []): string
    {
        $path = Cms::pageUrl('shop/order', ['number' => $order->number]) ?: '/shop/order/' . rawurlencode($order->number);
        $url = Url::to($path);

        if ($order->guest_access_token) {
            $query['token'] = $order->guest_access_token;
        }

        return $query ? $url . (str_contains($url, '?') ? '&' : '?') . http_build_query($query) : $url;
    }

    /**
     * gateway
     */
    protected function gateway()
    {
        $gateway = Omnipay::create('Stripe\Checkout');
        $gateway->setApiKey($this->secret());

        return $gateway;
    }

    /**
     * secret of the active site
     */
    protected function secret(): string
    {
        return trim((string) ShopSetting::value('stripe_secret_key'));
    }

    /**
     * sessionMatches checks the amount and currency Stripe charged
     */
    protected function sessionMatches(Order $order, Payment $payment, array $session): bool
    {
        return ($session['payment_status'] ?? '') === 'paid'
            && strcasecmp((string) ($session['currency'] ?? ''), $order->currency) === 0
            && (int) ($session['amount_total'] ?? -1) === $this->minorAmount($payment->amount, $order->currency);
    }

    /**
     * exponent is the number of decimal places Stripe uses for the currency
     */
    protected function exponent(string $currency): int
    {
        $currency = strtoupper($currency);
        $none = ['BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA', 'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF'];
        $three = ['BHD', 'JOD', 'KWD', 'OMR', 'TND'];

        if (in_array($currency, $none, true)) {
            return 0;
        }

        return in_array($currency, $three, true) ? 3 : 2;
    }

    /**
     * failed logs the gateway error and stops the order
     */
    protected function failed(Order $order, ?\Throwable $exception = null, ?string $message = null): never
    {
        Log::error('Stripe Checkout could not be started.', [
            'order_id' => $order->id,
            'error' => $exception ? $exception->getMessage() : $message,
        ]);

        throw ApiException::make(ApiCode::PAYMENT_FAILED);
    }
}
