<?php namespace AmoriumCms\Shop\Models;

use Site;
use Url;
use System\Models\SettingModel;

/**
 * ShopSetting holds the shop settings of each site. Currency and payment details are kept
 * per site; the stock and limit values are copied to every site.
 */
class ShopSetting extends SettingModel
{
    use \October\Rain\Database\Traits\Multisite;
    use \October\Rain\Database\Traits\Validation;

    public $settingsCode = 'amoriumcms_shop_settings';

    public $settingsFields = 'fields.yaml';

    public $rules = [
        'currency' => 'required|size:3',
        'low_stock_threshold' => 'required|integer|min:1',
        'cod_fee' => 'nullable|numeric|min:0',
        'service_tax_rate' => 'required|numeric|min:0|max:100',
        'bank_transfer_days' => 'required|integer|min:1',
        'cart_ttl_days' => 'required|integer|min:1',
        'out_of_stock_limit_customer' => 'required|integer|min:1',
        'out_of_stock_limit_ip' => 'required|integer|min:1',
        'out_of_stock_decay_minutes' => 'required|integer|min:1',
    ];

    protected $propagatable = [
        'low_stock_threshold',
        'service_tax_rate',
        'cart_ttl_days',
        'order_number_prefix',
        'out_of_stock_limit_customer',
        'out_of_stock_limit_ip',
        'out_of_stock_decay_minutes',
    ];

    protected $propagatableSync = true;

    public function isMultisiteEnabled()
    {
        return Site::hasFeature();
    }

    public function initSettingsData()
    {
        $this->currency = 'TRY';
        $this->low_stock_threshold = 3;
        $this->service_tax_rate = 20;
        $this->bank_transfer_enabled = true;
        $this->bank_transfer_name = '';
        $this->bank_transfer_order_status = 'pending_payment';
        $this->bank_accounts = [];
        $this->bank_transfer_days = 3;
        $this->cod_enabled = true;
        $this->cod_name = '';
        $this->cod_order_status = 'processing';
        $this->cod_fee = 0;
        $this->stripe_enabled = false;
        $this->stripe_name = '';
        $this->stripe_order_status = 'pending_payment';
        $this->stripe_secret_key = '';
        $this->stripe_webhook_secret = '';
        $this->cart_ttl_days = 30;
        $this->order_number_prefix = 'AM';
        $this->out_of_stock_limit_customer = 10;
        $this->out_of_stock_limit_ip = 30;
        $this->out_of_stock_decay_minutes = 10;
    }

    /**
     * filterFields fills the webhook address for the site being edited
     */
    public function filterFields($fields, $context = null)
    {
        if (!isset($fields->stripe_webhook_secret)) {
            return;
        }

        $code = Site::getSiteFromContext()?->code ?: 'site';
        $fields->stripe_webhook_secret->comment = __('Stripe notifies this address: :url', [
            'url' => Url::to('api/v1/' . $code . '/payments/stripe/webhook'),
        ]);
    }

    /**
     * value reads a setting of the active site with its default
     */
    public static function value(string $key)
    {
        $value = static::get($key);

        if ($value === null || $value === '') {
            $defaults = new static;
            $defaults->initSettingsData();
            $value = $defaults->{$key};
        }

        return $value;
    }
}
