<?php namespace AmoriumCms\Shop\Controllers;

use Lang;
use Flash;
use Backend;
use BackendMenu;
use Validator;
use ForbiddenException;
use October\Rain\Exception\ValidationException;
use Backend\Widgets\Form as FormWidget;
use AmoriumCms\Shop\Classes\BackendPage;
use AmoriumCms\Shop\Classes\Payments\PaymentSettings;
use AmoriumCms\Shop\Models\ShopSetting;

/**
 * Shop hosts the dashboard and the settings. The list pages have their own controllers.
 */
class Shop extends BackendPage
{
    /**
     * @var FormWidget payment methods form
     */
    public $paymentForm;

    /**
     * @var FormWidget shop settings form
     */
    public $settingsForm;

    public function __construct()
    {
        parent::__construct();

        if (!in_array($this->action, ['payments', 'settings'], true)) {
            return;
        }

        Lang::addJsonPath(plugins_path('amoriumcms/shop/lang'));

        $model = ShopSetting::instance();

        if ($this->action === 'payments') {
            $payment = $this->makeConfig('$/amoriumcms/shop/models/shopsetting/payments.yaml');
            $payment->model = $model;
            $payment->alias = 'paymentForm';
            $payment->arrayName = 'Payment';
            $this->paymentForm = $this->makeWidget(FormWidget::class, $payment);
            $this->paymentForm->bindToController();
        }

        if ($this->action === 'settings') {
            $settings = $this->makeConfig('$/amoriumcms/shop/models/shopsetting/fields.yaml');
            $settings->model = $model;
            $settings->alias = 'settingsForm';
            $settings->arrayName = 'Setting';
            $this->settingsForm = $this->makeWidget(FormWidget::class, $settings);
            $this->settingsForm->bindToController();
        }
    }

    public function index()
    {
        $this->authorize(['shop.orders.view', 'shop.orders.manage']);
        $this->bootPage('dashboard', 'Dashboard');
    }

    public function settings()
    {
        $this->authorize(['shop.settings.manage']);
        BackendMenu::setContext('AmoriumCms.Shop', 'shop', 'settings');
        $this->pageTitle = 'Settings';
    }

    public function payments()
    {
        $this->authorize(['shop.settings.manage']);
        BackendMenu::setContext('AmoriumCms.Shop', 'shop', 'payments');
        $this->pageTitle = 'Payment Methods';
    }

    /**
     * onSave writes every payment method of the site selected in the backend
     */
    public function onSave()
    {
        $this->authorize(['shop.settings.manage']);

        if ($this->action === 'settings') {
            $this->saveSettings();
            Flash::success(__('The settings have been saved.'));
            return;
        }

        PaymentSettings::saveFromForm($this->paymentForm->getSaveData());

        Flash::success(__('The payment methods have been saved.'));
    }

    /**
     * saveSettings writes the general settings of the site selected in the backend.
     * Stock and limit values are copied to every site by the model.
     */
    protected function saveSettings(): void
    {
        $data = $this->settingsForm->getSaveData();

        if (isset($data['currency'])) {
            $data['currency'] = strtoupper(trim((string) $data['currency']));
        }
        if (isset($data['order_number_prefix'])) {
            $data['order_number_prefix'] = trim((string) $data['order_number_prefix']);
        }

        $validator = Validator::make($data, [
            'currency' => 'required|string|size:3|alpha',
            'order_number_prefix' => 'required|string|max:8|alpha_num',
            'low_stock_threshold' => 'required|integer|min:1|max:1000',
            'service_tax_rate' => 'required|numeric|min:0|max:100',
            'cart_ttl_days' => 'required|integer|min:1|max:365',
            'out_of_stock_limit_customer' => 'required|integer|min:1|max:1000',
            'out_of_stock_limit_ip' => 'required|integer|min:1|max:10000',
            'out_of_stock_decay_minutes' => 'required|integer|min:1|max:1440',
        ]);

        $errors = [];
        if ($validator->fails()) {
            foreach ($validator->errors()->getMessages() as $field => $messages) {
                $errors['Setting.' . $field] = $messages;
            }
        }

        $customer = $data['out_of_stock_limit_customer'] ?? null;
        $ip = $data['out_of_stock_limit_ip'] ?? null;
        if ($customer !== null && $ip !== null && (int) $ip < (int) $customer && !isset($errors['Setting.out_of_stock_limit_ip'])) {
            $errors['Setting.out_of_stock_limit_ip'] = [__('The IP address limit must not be lower than the customer limit.')];
        }

        if ($errors) {
            throw new ValidationException($errors);
        }

        $integers = [
            'low_stock_threshold',
            'cart_ttl_days',
            'out_of_stock_limit_customer',
            'out_of_stock_limit_ip',
            'out_of_stock_decay_minutes',
        ];

        $settings = ShopSetting::instance();
        foreach ($data as $key => $value) {
            $settings->{$key} = in_array($key, $integers, true) ? (int) $value : $value;
        }
        $settings->save();
        ShopSetting::clearInternalCache();
    }

    public function orders() { return $this->movedTo('orders'); }
    public function products() { return $this->movedTo('products'); }
    public function categories() { return $this->movedTo('categories'); }
    public function brands() { return $this->movedTo('brands'); }
    public function specs() { return $this->movedTo('specs'); }
    public function reviews() { return $this->movedTo('reviews'); }
    public function customers() { return $this->movedTo('customers'); }
    public function shipping() { return $this->movedTo('shipping'); }

    /**
     * movedTo keeps the old shop/shop/{section} links working.
     */
    protected function movedTo(string $section)
    {
        return Backend::redirect('amoriumcms/shop/' . $section);
    }

    /**
     * authorize
     */
    protected function authorize(array $permissions)
    {
        if (!$this->user->hasAnyAccess($permissions)) {
            throw new ForbiddenException;
        }
    }
}
