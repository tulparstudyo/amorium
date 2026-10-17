<?php namespace AmoriumCms\Shop\Controllers;

use Lang;
use Flash;
use Backend;
use BackendMenu;
use ForbiddenException;
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

    public function __construct()
    {
        parent::__construct();

        if ($this->action !== 'payments') {
            return;
        }

        Lang::addJsonPath(plugins_path('amoriumcms/shop/lang'));

        $model = ShopSetting::instance();

        $payment = $this->makeConfig('$/amoriumcms/shop/models/shopsetting/payments.yaml');
        $payment->model = $model;
        $payment->alias = 'paymentForm';
        $payment->arrayName = 'Payment';
        $this->paymentForm = $this->makeWidget(FormWidget::class, $payment);
        $this->paymentForm->bindToController();
    }

    public function index()
    {
        $this->authorize(['shop.orders.view', 'shop.orders.manage']);
        $this->bootPage('dashboard', 'Dashboard');
    }

    public function settings()
    {
        $this->authorize(['shop.settings.manage']);
        $this->bootPage('settings', 'Settings');
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

        PaymentSettings::saveFromForm($this->paymentForm->getSaveData());

        Flash::success(__('The payment methods have been saved.'));
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
