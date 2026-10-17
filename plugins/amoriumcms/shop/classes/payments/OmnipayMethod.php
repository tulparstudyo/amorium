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
 * OmnipayMethod is every card provider declared in the payment form.
 * checkout is Stripe Checkout. redirect sends the customer away.
 * direct confirms a local test payment when the customer returns.
 */
class OmnipayMethod implements PaymentMethod, StartsPayment
{
    public function __construct(protected array $definition)
    {
    }

    public function code(): string
    {
        return $this->definition['code'];
    }

    public function defaultName(): string
    {
        return $this->definition['customer'];
    }

    public function name(): string
    {
        return PaymentSettings::name(PaymentSettings::key($this->code(), 'name'), $this->defaultName());
    }

    public function description(): string
    {
        return 'You will be redirected to the secure payment page after you place the order.';
    }

    public function isAvailable(): bool
    {
        return PaymentSettings::enabled(PaymentSettings::key($this->code(), 'enabled')) && $this->credentialsReady();
    }

    public function fee(): string
    {
        return '0.00';
    }

    public function orderStatus(): string
    {
        return PaymentSettings::orderStatus(
            PaymentSettings::key($this->code(), 'order_status'),
            Order::STATUS_PENDING_PAYMENT
        );
    }

    public function instructions(Order $order, Payment $payment): array
    {
        return [
            'type' => 'redirect',
            'url' => $payment->meta['checkout_url'] ?? null,
        ];
    }

    /**
     * start opens the provider payment and keeps its address on the payment
     */
    public function start(Order $order, Payment $payment): void
    {
        if ($payment->reference) {
            return;
        }

        $token = Str::random(40);
        $return = $this->returnUrl() . '?token=' . $token;

        if ($this->definition['flow'] === 'direct') {
            $reference = 'dummy-' . $order->number . '-' . Str::lower(Str::random(8));
            $payment->reference = $reference;
            $payment->meta = [
                'checkout_url' => $return . '&paymentId=' . rawurlencode($reference),
                'return_token' => $token,
            ];
            $payment->save();

            return;
        }

        try {
            $response = $this->gateway()->purchase($this->purchase($order, $payment, $return))->send();
        }
        catch (\Throwable $exception) {
            $this->failed($order, $exception);
        }

        $data = is_array($response->getData()) ? $response->getData() : [];
        $reference = $this->definition['flow'] === 'checkout'
            ? ($data['id'] ?? null)
            : $response->getTransactionReference();
        $url = $this->definition['flow'] === 'checkout'
            ? ($data['url'] ?? null)
            : ($response->isRedirect() ? $response->getRedirectUrl() : null);
        $started = $this->definition['flow'] === 'checkout' ? $response->isSuccessful() : $response->isRedirect();

        if (!$started || !$reference || !$url) {
            $this->failed($order, null, $response->getMessage());
        }

        $payment->reference = $reference;
        $payment->meta = [
            'checkout_url' => $url,
            'return_token' => $token,
        ];
        $payment->save();
    }

    /**
     * complete marks the payment paid only after the provider confirms the amount
     */
    public function complete(Payment $payment, array $query, string $providerToken = '', ?array $posted = null): bool
    {
        $order = $payment->order;

        if ($payment->method !== $this->code() || !$payment->reference || !$order) {
            return false;
        }

        if ($payment->status === Payment::STATUS_COMPLETED) {
            return true;
        }

        $data = match ($this->definition['flow']) {
            'checkout' => $this->fetch($order, $payment->reference),
            'direct' => $this->completeDirect($payment),
            default => $this->fetchRedirect($order, $payment, (string) ($query['PayerID'] ?? $query['payer_id'] ?? ''), $providerToken),
        };

        if (!$this->paid($order, $payment, $data)) {
            Log::warning('The gateway payment does not match the order.', [
                'order_id' => $order->id,
                'method' => $this->code(),
                'reference' => $payment->reference,
            ]);

            if ($posted !== null) {
                $this->rememberError($payment, $this->customerMessage($data, $posted));
            }

            return false;
        }

        $meta = (array) $payment->meta;
        unset($meta['return_error']);
        if ($this->definition['flow'] === 'checkout') {
            $meta['payment_intent'] = $data['payment_intent'] ?? null;
        }
        else {
            $meta['sale_id'] = $data['transactions'][0]['related_resources'][0]['sale']['id'] ?? null;
        }
        $payment->meta = $meta;
        $payment->save();

        try {
            OrderService::confirmPayment($order, $payment);
        }
        catch (ApiException $exception) {
            if ($exception->apiCode !== ApiCode::PAYMENT_ALREADY_CONFIRMED) {
                Log::warning('The gateway payment could not be confirmed.', [
                    'order_id' => $order->id,
                    'method' => $this->code(),
                    'code' => $exception->apiCode,
                ]);

                return false;
            }
        }

        return true;
    }

    /**
     * acceptWebhook reads a notification and confirms the stored payment from the provider
     */
    public function acceptWebhook(string $payload, ?string $signature): string
    {
        if ($this->definition['flow'] === 'checkout') {
            $secret = $this->setting($this->definition['webhook']);
            if (!$this->definition['webhook'] || !$this->stripeSignature($payload, $signature, $secret)) {
                return 'invalid';
            }

            $event = json_decode($payload, true);
            $type = $event['type'] ?? '';
            $reference = (string) ($event['data']['object']['id'] ?? '');

            if (!in_array($type, ['checkout.session.completed', 'checkout.session.async_payment_succeeded'], true)) {
                return 'ignored';
            }
        }
        else {
            $event = json_decode($payload, true);
            $type = $event['event_type'] ?? '';
            $reference = (string) ($event['resource']['parent_payment'] ?? '');

            if ($type !== 'PAYMENT.SALE.COMPLETED' || $reference === '') {
                return 'ignored';
            }
        }

        $payment = Payment::with('order')->where('method', $this->code())->where('reference', $reference)->first();
        if (!$payment || !$payment->order || (int) $payment->order->site_id !== (int) Site::getActiveSite()->id) {
            Log::warning('The gateway webhook did not match a payment.', [
                'method' => $this->code(),
                'type' => $type,
            ]);

            return 'ignored';
        }

        $pending = $payment->status === Payment::STATUS_PENDING;
        $paid = $this->complete($payment, []);

        if (!$paid && $pending) {
            return 'retry';
        }

        return 'ok';
    }

    /**
     * referenceQuery is the return parameter that carries the provider payment id
     */
    public function referenceQuery(): string
    {
        return $this->definition['flow'] === 'checkout' ? 'session_id' : 'paymentId';
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
     * purchase is the Omnipay purchase payload for this flow
     */
    protected function purchase(Order $order, Payment $payment, string $return): array
    {
        if ($this->definition['flow'] === 'checkout') {
            return [
                'success_url' => $return . '&session_id={CHECKOUT_SESSION_ID}',
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
            ];
        }

        return [
            'amount' => Money::round($payment->amount),
            'currency' => strtoupper($order->currency),
            'description' => __('Order :number', ['number' => $order->number]),
            'transactionId' => $order->number,
            'returnUrl' => $return,
            'cancelUrl' => $return . '&paymentId=' . rawurlencode((string) $order->number),
        ];
    }

    /**
     * gateway with the credentials of the active site
     */
    protected function gateway()
    {
        $gateway = Omnipay::create($this->definition['gateway']);

        foreach ($this->definition['credentials'] as $setter => $suffix) {
            $gateway->{'set' . ucfirst($setter)}($this->setting($suffix));
        }

        if ($this->definition['testMode']) {
            $test = PaymentSettings::enabled(PaymentSettings::key($this->code(), $this->definition['testMode']));
            $gateway->setTestMode($test);
            if (method_exists($gateway, 'setDeveloperMode')) {
                $gateway->setDeveloperMode($test);
            }
        }

        return $gateway;
    }

    /**
     * credentialsReady when every credential has been saved
     */
    protected function credentialsReady(): bool
    {
        foreach ($this->definition['credentials'] as $suffix) {
            if ($this->setting($suffix) === '') {
                return false;
            }
        }

        return true;
    }

    /**
     * setting reads one suffix of this provider
     */
    protected function setting(?string $suffix): string
    {
        if ($suffix === null || $suffix === '') {
            return '';
        }

        return trim((string) ShopSetting::value(PaymentSettings::key($this->code(), $suffix)));
    }

    /**
     * returnUrl
     */
    protected function returnUrl(): string
    {
        return Url::to('api/v1/' . Site::getActiveSite()->code . '/payments/' . $this->code() . '/return');
    }

    /**
     * fetch reads the payment from the provider
     */
    protected function fetch(Order $order, string $reference): array
    {
        try {
            $request = $this->definition['flow'] === 'checkout'
                ? $this->gateway()->fetchTransaction(['transactionReference' => $reference])
                : $this->gateway()->fetchPurchase(['transactionReference' => $reference]);
            $response = $request->send();
        }
        catch (\Throwable $exception) {
            Log::warning('The gateway payment could not be read.', [
                'order_id' => $order->id,
                'method' => $this->code(),
                'error' => $exception->getMessage(),
            ]);

            return [];
        }

        return $response->isSuccessful() && is_array($response->getData()) ? $response->getData() : [];
    }

    /**
     * fetchRedirect reads the payment and executes it when the customer has approved it
     */
    protected function fetchRedirect(Order $order, Payment $payment, string $payerId, string $providerToken = ''): array
    {
        $data = $this->fetch($order, $payment->reference);

        if ($this->paid($order, $payment, $data)) {
            return $data;
        }

        try {
            $parameters = ['transactionReference' => $payment->reference];
            if ($payerId !== '') {
                $parameters['payerId'] = $payerId;
            }
            if ($providerToken !== '') {
                $parameters['token'] = $providerToken;
            }
            $response = $this->gateway()->completePurchase($parameters)->send();
        }
        catch (\Throwable $exception) {
            Log::warning('The gateway payment could not be executed.', [
                'order_id' => $order->id,
                'method' => $this->code(),
                'error' => $exception->getMessage(),
            ]);

            return ['errorMessage' => ''];
        }

        $data = is_array($response->getData()) ? $response->getData() : [];

        if (!$response->isSuccessful()) {
            Log::warning('The gateway payment could not be executed.', [
                'order_id' => $order->id,
                'method' => $this->code(),
                'error' => $response->getMessage(),
            ]);

            $data['errorMessage'] = $data['errorMessage'] ?? $response->getMessage();

            return $data;
        }

        return $data;
    }

    /**
     * customerMessage is the text the provider gave the customer, or a short fallback
     */
    protected function customerMessage(array $data, array $posted): string
    {
        foreach (['failed_reason_msg', 'fail_message', 'mdErrorMsg', 'errorMessage', 'err_msg'] as $key) {
            $message = $this->plainMessage($posted[$key] ?? '');
            if ($message !== '') {
                return $message;
            }
        }

        $message = $this->plainMessage($data['errorMessage'] ?? '');

        return $message !== '' ? $message : (string) __('The card payment could not be completed.');
    }

    /**
     * rememberError keeps the provider message until the customer tries again
     */
    protected function rememberError(Payment $payment, string $message): void
    {
        $meta = (array) $payment->meta;
        $meta['return_error'] = $message;
        $payment->meta = $meta;
        $payment->save();
    }

    /**
     * plainMessage is a single line safe to show on the checkout page
     */
    protected function plainMessage($value): string
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', strip_tags((string) $value)));

        return mb_strlen($text) > 300 ? mb_substr($text, 0, 300) : $text;
    }

    /**
     * paid checks the amount and currency the provider captured
     */
    protected function paid(Order $order, Payment $payment, array $data): bool
    {
        if ($this->definition['flow'] === 'checkout') {
            return ($data['payment_status'] ?? '') === 'paid'
                && strcasecmp((string) ($data['currency'] ?? ''), $order->currency) === 0
                && (int) ($data['amount_total'] ?? -1) === $this->minorAmount($payment->amount, $order->currency);
        }

        if ($this->definition['flow'] === 'direct') {
            return ($data['success'] ?? false) === true
                && (string) ($data['reference'] ?? '') === (string) $payment->reference;
        }

        $amount = $data['transactions'][0]['amount'] ?? [];
        $sale = $data['transactions'][0]['related_resources'][0]['sale']['state'] ?? '';

        if (($data['state'] ?? '') === 'approved'
            && $sale === 'completed'
            && strcasecmp((string) ($amount['currency'] ?? ''), $order->currency) === 0
            && bccomp(Money::round($amount['total'] ?? '0'), Money::round($payment->amount), 2) === 0) {
            return true;
        }

        $status = strtolower((string) ($data['status'] ?? ''));
        $captured = is_array($data['amount'] ?? null) ? $data['amount'] : [];
        $total = $captured['total'] ?? $captured['value'] ?? null;
        $currency = $captured['currency'] ?? ($data['currency'] ?? '');

        return in_array($status, ['paid', 'completed', 'authorized'], true)
            && $total !== null
            && strcasecmp((string) $currency, $order->currency) === 0
            && bccomp(Money::round($total), Money::round($payment->amount), 2) === 0;
    }

    /**
     * completeDirect asks the local test gateway to accept the stored reference
     */
    protected function completeDirect(Payment $payment): array
    {
        try {
            $response = $this->gateway()->completePurchase([
                'transactionReference' => $payment->reference,
            ])->send();
        }
        catch (\Throwable $exception) {
            Log::warning('The gateway payment could not be executed.', [
                'method' => $this->code(),
                'error' => $exception->getMessage(),
            ]);

            return [];
        }

        return $response->isSuccessful() && is_array($response->getData()) ? $response->getData() : [];
    }

    /**
     * minorAmount converts a major-unit amount to the smallest currency unit
     */
    protected function minorAmount($amount, string $currency): int
    {
        $currency = strtoupper($currency);
        $none = ['BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA', 'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF'];
        $three = ['BHD', 'JOD', 'KWD', 'OMR', 'TND'];
        $exponent = in_array($currency, $none, true) ? 0 : (in_array($currency, $three, true) ? 3 : 2);

        return (int) bcmul(Money::round($amount), bcpow('10', (string) $exponent, 0), 0);
    }

    /**
     * stripeSignature checks the Stripe-Signature header against the raw body
     */
    protected function stripeSignature(string $payload, ?string $header, string $secret): bool
    {
        if ($header === null || $header === '' || $secret === '') {
            return false;
        }

        $parts = [];
        foreach (explode(',', $header) as $item) {
            $pair = explode('=', trim($item), 2);
            if (count($pair) === 2) {
                $parts[$pair[0]][] = $pair[1];
            }
        }

        $timestamp = $parts['t'][0] ?? '';
        if ($timestamp === '' || abs(time() - (int) $timestamp) > 300) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp . '.' . $payload, $secret);

        foreach ($parts['v1'] ?? [] as $signature) {
            if (hash_equals($expected, $signature)) {
                return true;
            }
        }

        return false;
    }

    /**
     * failed logs the gateway error and stops the order
     */
    protected function failed(Order $order, ?\Throwable $exception = null, ?string $message = null): never
    {
        Log::error('The gateway payment could not be started.', [
            'order_id' => $order->id,
            'method' => $this->code(),
            'error' => $exception ? $exception->getMessage() : $message,
        ]);

        throw ApiException::make(ApiCode::PAYMENT_FAILED);
    }
}
