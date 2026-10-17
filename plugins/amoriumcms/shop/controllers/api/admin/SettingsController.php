<?php namespace AmoriumCms\Shop\Controllers\Api\Admin;

use Site;
use Request;
use AmoriumCms\Shop\Classes\Api\ApiResponse;
use AmoriumCms\Shop\Classes\Api\ApiException;
use AmoriumCms\Shop\Models\ShopSetting;

/**
 * SettingsController reads and writes the shop settings of one site: ?site_id=, primary site by default.
 * Stock and limit settings are copied to every site when saved.
 */
class SettingsController extends AdminController
{
    const FIELDS = [
        'currency',
        'order_number_prefix',
        'low_stock_threshold',
        'service_tax_rate',
        'cart_ttl_days',
        'out_of_stock_limit_customer',
        'out_of_stock_limit_ip',
        'out_of_stock_decay_minutes',
    ];

    /**
     * show
     */
    public function show()
    {
        $siteId = $this->siteId(true);

        return ApiResponse::ok(Site::withContext($siteId, fn () => $this->present($siteId)));
    }

    /**
     * update accepts any subset of the fields
     */
    public function update()
    {
        $siteId = $this->siteId(true);

        $input = $this->validate([
            'currency' => 'sometimes|required|string|size:3|alpha',
            'order_number_prefix' => 'sometimes|required|string|max:8|alpha_num',
            'low_stock_threshold' => 'sometimes|required|integer|min:1|max:1000',
            'service_tax_rate' => 'sometimes|required|numeric|min:0|max:100',
            'cart_ttl_days' => 'sometimes|required|integer|min:1|max:365',
            'out_of_stock_limit_customer' => 'sometimes|required|integer|min:1|max:1000',
            'out_of_stock_limit_ip' => 'sometimes|required|integer|min:1|max:10000',
            'out_of_stock_decay_minutes' => 'sometimes|required|integer|min:1|max:1440',
        ]);

        if (isset($input['currency'])) {
            $input['currency'] = strtoupper($input['currency']);
        }

        return ApiResponse::ok(Site::withContext($siteId, function () use ($siteId, $input) {
            $settings = ShopSetting::instance();

            $customer = $input['out_of_stock_limit_customer'] ?? $settings->out_of_stock_limit_customer;
            $ip = $input['out_of_stock_limit_ip'] ?? $settings->out_of_stock_limit_ip;
            if ((int) $ip < (int) $customer) {
                throw ApiException::validation(['out_of_stock_limit_ip' => __('The IP address limit must not be lower than the customer limit.')]);
            }

            foreach ($input as $key => $value) {
                $settings->{$key} = $value;
            }
            $settings->save();

            ShopSetting::clearInternalCache();

            return $this->present($siteId);
        }));
    }

    /**
     * present
     */
    protected function present(int $siteId): array
    {
        $data = ['site_id' => $siteId];

        foreach (static::FIELDS as $field) {
            $data[$field] = ShopSetting::value($field);
        }

        return $data;
    }
}
