<?php namespace AmoriumCms\Shop\Classes\Payments;

/**
 * Gateways reads Omnipay providers from the payment form. A provider is any
 * enabled field that names a gateway. Adding one is that field plus the
 * Omnipay package in composer.json.
 */
class Gateways
{
    /**
     * @var array<string, array>|null
     */
    protected static ?array $definitions = null;

    /**
     * definitions keyed by the method code
     */
    public static function definitions(): array
    {
        if (static::$definitions !== null) {
            return static::$definitions;
        }

        $yaml = app(\October\Rain\Parse\Yaml::class);
        $file = plugins_path('amoriumcms/shop/models/shopsetting/payments.yaml');
        $fields = $yaml->parseFile($file)['tabs']['fields'] ?? [];
        $definitions = [];

        foreach ($fields as $name => $config) {
            if (!is_array($config) || empty($config['gateway']) || !str_ends_with($name, '_enabled')) {
                continue;
            }

            $code = substr($name, 0, -strlen('_enabled'));
            $credentials = is_array($config['credentials'] ?? null) ? $config['credentials'] : [];
            $definitions[$code] = [
                'code' => $code,
                'gateway' => (string) $config['gateway'],
                'flow' => in_array($config['flow'] ?? 'redirect', ['checkout', 'direct'], true) ? $config['flow'] : 'redirect',
                'customer' => (string) ($config['customer'] ?? $code),
                'credentials' => $credentials,
                'testMode' => isset($config['testMode']) ? (string) $config['testMode'] : null,
                'webhook' => isset($config['webhook']) ? (string) $config['webhook'] : null,
                'labels' => static::labels($fields, $code),
            ];
        }

        return static::$definitions = $definitions;
    }

    /**
     * find
     */
    public static function find(string $code): ?array
    {
        return static::definitions()[$code] ?? null;
    }

    /**
     * suffixes are the extra settings stored beside enabled, name and order status
     */
    public static function suffixes(array $definition): array
    {
        $suffixes = array_values($definition['credentials']);

        if ($definition['testMode']) {
            $suffixes[] = $definition['testMode'];
        }

        if ($definition['webhook']) {
            $suffixes[] = $definition['webhook'];
        }

        return array_values(array_unique($suffixes));
    }

    /**
     * labels of the form fields that belong to the provider
     */
    protected static function labels(array $fields, string $code): array
    {
        $labels = [];

        foreach ($fields as $name => $config) {
            $prefix = $code . '_';
            if (!is_array($config) || !str_starts_with($name, $prefix) || empty($config['label'])) {
                continue;
            }

            $labels[substr($name, strlen($prefix))] = (string) $config['label'];
        }

        return $labels;
    }
}
