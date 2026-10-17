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

            $gateway = Gateways::find($code);
            if ($gateway && $input['enabled']) {
                foreach ($gateway['credentials'] as $suffix) {
                    if (trim((string) ($input['fields'][$suffix] ?? '')) === '') {
                        $errors['Payment.' . static::key($code, $suffix)] = [__('The :name is required when the method is enabled.', [
                            'name' => $gateway['labels'][$suffix] ?? $suffix,
                        ])];
                    }
                }
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

        $gateway = Gateways::find($code);
        if ($gateway) {
            foreach (Gateways::suffixes($gateway) as $suffix) {
                $value = $fields[$suffix] ?? '';
                $settings->{static::key($code, $suffix)} = $suffix === $gateway['testMode']
                    ? (bool) $value
                    : trim((string) $value);
            }

            if ($settings->{static::key($code, 'enabled')}) {
                $missing = [];
                foreach ($gateway['credentials'] as $suffix) {
                    if (trim((string) $settings->{static::key($code, $suffix)}) === '') {
                        $missing['fields.' . $suffix] = __('The :name is required when the method is enabled.', [
                            'name' => $gateway['labels'][$suffix] ?? $suffix,
                        ]);
                    }
                }
                if ($missing) {
                    throw ApiException::validation($missing);
                }
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

        $gateway = Gateways::find($code);
        if ($gateway) {
            foreach (Gateways::suffixes($gateway) as $suffix) {
                $rules['fields.' . $suffix] = $suffix === $gateway['testMode'] ? 'nullable|boolean' : 'nullable|string|max:255';
            }
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

        $gateway = Gateways::find($code);
        if ($gateway) {
            foreach (Gateways::suffixes($gateway) as $suffix) {
                $value = $flat[static::key($code, $suffix)] ?? '';
                $input['fields'][$suffix] = $suffix === $gateway['testMode']
                    ? !in_array($value, [null, false, 0, '0', '', 'false'], true)
                    : $value;
            }
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

        if (str_starts_with($field, 'fields.') && Gateways::find($code)) {
            return static::key($code, substr($field, strlen('fields.')));
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

        $gateway = Gateways::find($code);
        if (!$gateway) {
            return [];
        }

        $site = Site::getActiveSite()?->code ?: '{site}';
        $rows = [];

        foreach (Gateways::suffixes($gateway) as $suffix) {
            $boolean = $suffix === $gateway['testMode'];
            $row = [
                'key' => $suffix,
                'type' => $boolean ? 'boolean' : 'password',
                'label' => $gateway['labels'][$suffix] ?? $suffix,
                'value' => $boolean
                    ? static::enabled(static::key($code, $suffix))
                    : (string) ShopSetting::value(static::key($code, $suffix)),
            ];

            if ($suffix === $gateway['webhook']) {
                $row['comment'] = 'The gateway notifies this address: :url';
                $row['comment_url'] = '/api/v1/' . $site . '/payments/' . $code . '/webhook';
            }

            $rows[] = $row;
        }

        return $rows;
    }
}
