<?php namespace AmoriumCms\Shop\Classes\Payments;

use Site;
use Validator;
use October\Rain\Exception\ValidationException;
use AmoriumCms\Shop\Classes\Api\ApiException;
use AmoriumCms\Shop\Classes\Money;
use AmoriumCms\Shop\Classes\StatusLabel;
use AmoriumCms\Shop\Models\Order;
use AmoriumCms\Shop\Models\ShopSetting;

/**
 * PaymentSettings reads and writes the per-site configuration of a payment method:
 * the name shown to customers, whether it is enabled, the first order status and
 * the fields that method needs.
 */
class PaymentSettings
{
    const INITIAL_STATUSES = [
        Order::STATUS_PENDING_PAYMENT,
        Order::STATUS_PROCESSING,
    ];

    /**
     * name is the customer-facing name of the active site, or the English default
     */
    public static function name(string $key, string $fallback): string
    {
        $name = trim((string) ShopSetting::value($key));

        return $name !== '' ? $name : $fallback;
    }

    /**
     * orderStatus is the status of a newly placed order on the active site
     */
    public static function orderStatus(string $key, string $fallback): string
    {
        $status = (string) ShopSetting::value($key);

        return in_array($status, static::INITIAL_STATUSES, true) ? $status : $fallback;
    }

    /**
     * enabled
     */
    public static function enabled(string $key): bool
    {
        return !in_array(ShopSetting::value($key), [null, false, 0, '0', '', 'false'], true);
    }

    /**
     * all returns every method as the active site has configured it
     */
    public static function all(): array
    {
        return array_values(array_map(fn ($method) => static::present($method), PaymentMethods::all()));
    }

    /**
     * present
     */
    public static function present(PaymentMethod $method): array
    {
        $code = $method->code();

        return [
            'code' => $code,
            'default_name' => __($method->defaultName()),
            'name' => trim((string) ShopSetting::value(static::key($code, 'name'))),
            'enabled' => static::enabled(static::key($code, 'enabled')),
            'available' => $method->isAvailable(),
            'order_status' => $method->orderStatus(),
            'order_statuses' => array_map(fn ($status) => [
                'code' => $status,
                'name' => __(StatusLabel::of($status)),
            ], static::INITIAL_STATUSES),
            'fields' => static::fields($code),
        ];
    }

    /**
     * saveFromForm stores the YAML form. Card providers are sections of the Credit Card tab.
     */
    public static function saveFromForm(array $payment): void
    {
        $errors = [];

        foreach (PaymentMethods::all() as $code => $method) {
            $input = static::inputFromFlat($code, $payment);
            $validator = Validator::make($input, static::rules($code));

            if ($validator->fails()) {
                foreach ($validator->errors()->getMessages() as $field => $messages) {
                    $errors['Payment.' . static::flatKey($code, $field)] = $messages;
                }
            }

            if ($code === 'stripe' && $input['enabled'] && trim((string) ($input['fields']['secret_key'] ?? '')) === '') {
                $errors['Payment.stripe_secret_key'] = [__('The Stripe secret key is required when card payment is enabled.')];
            }
        }

        if ($errors) {
            throw new ValidationException($errors);
        }

        foreach (PaymentMethods::all() as $method) {
            static::save($method, static::inputFromFlat($method->code(), $payment));
        }
    }

    /**
     * save writes one method for the active site
     */
    public static function save(PaymentMethod $method, array $input): array
    {
        $code = $method->code();
        $settings = ShopSetting::instance();
        $fields = $input['fields'] ?? [];

        $settings->{static::key($code, 'enabled')} = (bool) ($input['enabled'] ?? false);
        $settings->{static::key($code, 'name')} = trim((string) ($input['name'] ?? ''));
        $settings->{static::key($code, 'order_status')} = $input['order_status'];

        if ($code === 'bank_transfer') {
            $settings->bank_transfer_days = (int) $fields['days'];
            $settings->bank_accounts = array_map(fn ($account) => [
                'bank_name' => $account['bank_name'],
                'account_holder' => $account['account_holder'],
                'iban' => strtoupper(preg_replace('/\s+/', '', $account['iban'])),
            ], array_values($fields['accounts'] ?? []));
        }

        if ($code === 'cash_on_delivery') {
            $settings->cod_fee = Money::round($fields['fee'] ?? 0);
        }

        if ($code === 'stripe') {
            $secret = trim((string) ($fields['secret_key'] ?? ''));
            $settings->stripe_secret_key = $secret;
            $settings->stripe_webhook_secret = trim((string) ($fields['webhook_secret'] ?? ''));

            if ($settings->stripe_enabled && $secret === '') {
                throw ApiException::validation(['fields.secret_key' => __('The Stripe secret key is required when card payment is enabled.')]);
            }
        }

        $settings->save();
        ShopSetting::clearInternalCache();

        return static::present($method);
    }

    /**
     * rules validates the shared fields and the fields of one method
     */
    public static function rules(string $code): array
    {
        $rules = [
            'enabled' => 'required|boolean',
            'name' => 'nullable|string|max:255',
            'order_status' => 'required|in:' . implode(',', static::INITIAL_STATUSES),
            'fields' => 'array',
        ];

        if ($code === 'bank_transfer') {
            $rules['fields.days'] = 'required|integer|min:1|max:60';
            $rules['fields.accounts'] = 'array|max:20';
            $rules['fields.accounts.*.bank_name'] = 'required|string|max:255';
            $rules['fields.accounts.*.account_holder'] = 'required|string|max:255';
            $rules['fields.accounts.*.iban'] = 'required|string|max:34';
        }

        if ($code === 'cash_on_delivery') {
            $rules['fields.fee'] = 'nullable|numeric|min:0';
        }

        if ($code === 'stripe') {
            $rules['fields.secret_key'] = 'nullable|string|max:255';
            $rules['fields.webhook_secret'] = 'nullable|string|max:255';
        }

        return $rules;
    }

    /**
     * inputFromFlat turns the posted form fields into the shape save() expects
     */
    protected static function inputFromFlat(string $code, array $flat): array
    {
        $input = [
            'enabled' => !in_array($flat[static::key($code, 'enabled')] ?? false, [null, false, 0, '0', '', 'false'], true),
            'name' => (string) ($flat[static::key($code, 'name')] ?? ''),
            'order_status' => (string) ($flat[static::key($code, 'order_status')] ?? ''),
            'fields' => [],
        ];

        if ($code === 'bank_transfer') {
            $input['fields'] = [
                'days' => $flat['bank_transfer_days'] ?? null,
                'accounts' => array_values($flat['bank_accounts'] ?? []),
            ];
        }

        if ($code === 'cash_on_delivery') {
            $input['fields'] = [
                'fee' => $flat['cod_fee'] ?? null,
            ];
        }

        if ($code === 'stripe') {
            $input['fields'] = [
                'secret_key' => $flat['stripe_secret_key'] ?? '',
                'webhook_secret' => $flat['stripe_webhook_secret'] ?? '',
            ];
        }

        return $input;
    }

    /**
     * flatKey maps a validation field back to the posted form name
     */
    protected static function flatKey(string $code, string $field): string
    {
        if (in_array($field, ['enabled', 'name', 'order_status'], true)) {
            return static::key($code, $field);
        }

        if ($field === 'fields.days') {
            return 'bank_transfer_days';
        }

        if ($field === 'fields.fee') {
            return 'cod_fee';
        }

        if ($field === 'fields.secret_key') {
            return 'stripe_secret_key';
        }

        if ($field === 'fields.webhook_secret') {
            return 'stripe_webhook_secret';
        }

        if (str_starts_with($field, 'fields.accounts')) {
            return 'bank_accounts' . substr($field, strlen('fields.accounts'));
        }

        return $field;
    }

    /**
     * key maps a method and a shared setting onto the stored setting name
     */
    public static function key(string $code, string $part): string
    {
        $prefix = $code === 'cash_on_delivery' ? 'cod' : $code;

        return $prefix . '_' . $part;
    }

    /**
     * fields are the values this method stores besides the shared ones
     */
    protected static function fields(string $code): array
    {
        if ($code === 'bank_transfer') {
            return [
                [
                    'key' => 'days',
                    'type' => 'number',
                    'label' => 'Cancel Unpaid Orders After (days)',
                    'comment' => 'Applies while the order is waiting for payment.',
                    'value' => (int) ShopSetting::value('bank_transfer_days'),
                ],
                [
                    'key' => 'accounts',
                    'type' => 'accounts',
                    'label' => 'Bank Accounts',
                    'comment' => 'The method stays hidden until an account is added.',
                    'value' => array_values((array) ShopSetting::value('bank_accounts')),
                ],
            ];
        }

        if ($code === 'cash_on_delivery') {
            return [[
                'key' => 'fee',
                'type' => 'money',
                'label' => 'Cash on Delivery Fee',
                'comment' => 'VAT included.',
                'value' => Money::toFloat(ShopSetting::value('cod_fee') ?: 0),
            ]];
        }

        $site = Site::getActiveSite();

        return [
            [
                'key' => 'secret_key',
                'type' => 'password',
                'label' => 'Stripe Secret Key',
                'comment' => 'Starts with sk_. The key stays on the server.',
                'value' => (string) ShopSetting::value('stripe_secret_key'),
            ],
            [
                'key' => 'webhook_secret',
                'type' => 'password',
                'label' => 'Stripe Webhook Secret',
                'comment' => 'Stripe notifies this address: :url',
                'comment_url' => '/api/v1/' . ($site?->code ?: '{site}') . '/payments/stripe/webhook',
                'value' => (string) ShopSetting::value('stripe_webhook_secret'),
            ],
        ];
    }
}
