<?php namespace AmoriumCms\Shop;

use App;
use Lang;
use Backend;
use System\Classes\PluginBase;
use System\Classes\SettingsManager;
use RainLab\User\Models\User;
use AmoriumCms\Shop\Classes\Api\ErrorRenderer;
use AmoriumCms\Shop\Models\Address;
use AmoriumCms\Shop\Models\Customer;
use AmoriumCms\Shop\Models\Product;

class Plugin extends PluginBase
{
    /**
     * @var array require
     */
    public $require = ['RainLab.User'];

    public function pluginDetails()
    {
        return [
            'name' => 'Shop',
            'description' => 'REST API based e-commerce: customers, products and orders',
            'author' => 'AmoriumCms',
            'icon' => 'icon-shopping-cart'
        ];
    }

    public function register()
    {
        $this->registerConsoleCommand('shop.cancel-unpaid', \AmoriumCms\Shop\Console\CancelUnpaidOrders::class);
        $this->registerConsoleCommand('shop.purge-carts', \AmoriumCms\Shop\Console\PurgeCarts::class);
        $this->registerConsoleCommand('shop.import-product', \AmoriumCms\Shop\Console\ImportProduct::class);
        $this->registerConsoleCommand('shop.import-queue', \AmoriumCms\Shop\Console\ImportQueue::class);
    }

    public function boot()
    {
        ErrorRenderer::register();

        if (!App::runningInBackend()) {
            Lang::addJsonPath(plugins_path('amoriumcms/shop/lang'));
        }

        User::extend(function ($model) {
            $model->hasOne['shop_customer'] = [Customer::class, 'key' => 'user_id', 'delete' => true];
            $model->hasMany['shop_addresses'] = [Address::class, 'key' => 'user_id', 'delete' => true];
            $model->belongsToMany['shop_favorites'] = [
                Product::class,
                'table' => 'amoriumcms_shop_favorites',
                'key' => 'user_id',
                'otherKey' => 'product_id',
                'pivot' => ['created_at'],
            ];
        });
    }

    public function registerComponents()
    {
        return [
            \AmoriumCms\Shop\Components\ShopConfig::class => 'shopConfig',
            \AmoriumCms\Shop\Components\ShopCatalog::class => 'shopCatalog',
            \AmoriumCms\Shop\Components\ShopProduct::class => 'shopProduct',
        ];
    }

    public function registerMailTemplates()
    {
        return [
            'amoriumcms.shop::mail.order_placed',
            'amoriumcms.shop::mail.order_status',
        ];
    }

    public function registerSchedule($schedule)
    {
        $schedule->command('shop:cancel-unpaid')->hourly();
        $schedule->command('shop:purge-carts')->daily();
        $schedule->command('shop:import-queue')->everyMinute()->withoutOverlapping();
    }

    public function registerNavigation()
    {
        $url = fn ($path) => Backend::url('amoriumcms/shop/' . $path);

        return [
            'shop' => [
                'label' => 'Shop',
                'url' => $url('shop'),
                'icon' => 'icon-shopping-cart',
                'permissions' => ['shop.*'],
                'order' => 300,
                'sideMenu' => [
                    'dashboard' => ['label' => 'Dashboard', 'icon' => 'icon-dashboard', 'url' => $url('shop'), 'permissions' => ['shop.orders.view', 'shop.orders.manage']],
                    'orders' => ['label' => 'Orders', 'icon' => 'icon-shopping-basket', 'url' => $url('orders'), 'permissions' => ['shop.orders.view', 'shop.orders.manage']],
                    'products' => ['label' => 'Products', 'icon' => 'icon-cubes', 'url' => $url('products'), 'permissions' => ['shop.catalog.manage']],
                    'categories' => ['label' => 'Categories', 'icon' => 'icon-sitemap', 'url' => $url('categories'), 'permissions' => ['shop.catalog.manage']],
                    'brands' => ['label' => 'Brands', 'icon' => 'icon-certificate', 'url' => $url('brands'), 'permissions' => ['shop.catalog.manage']],
                    'specs' => ['label' => 'Specs', 'icon' => 'icon-list-ul', 'url' => $url('specs'), 'permissions' => ['shop.catalog.manage']],
                    'reviews' => ['label' => 'Reviews', 'icon' => 'icon-star', 'url' => $url('reviews'), 'permissions' => ['shop.reviews.moderate']],
                    'customers' => ['label' => 'Customers', 'icon' => 'icon-users', 'url' => $url('customers'), 'permissions' => ['shop.customers.view']],
                    'shipping' => ['label' => 'Shipping Methods', 'icon' => 'icon-truck', 'url' => $url('shipping'), 'permissions' => ['shop.settings.manage']],
                    'payments' => ['label' => 'Payment Methods', 'icon' => 'icon-credit-card', 'url' => $url('shop/payments'), 'permissions' => ['shop.settings.manage']],
                    'settings' => ['label' => 'Settings', 'icon' => 'icon-cog', 'url' => $url('shop/settings'), 'permissions' => ['shop.settings.manage']],
                ],
            ],
        ];
    }

    public function registerPermissions()
    {
        return [
            'shop.catalog.manage' => [
                'tab' => 'Shop',
                'label' => 'Manage categories, brands, specs and products'
            ],
            'shop.orders.view' => [
                'tab' => 'Shop',
                'label' => 'View orders'
            ],
            'shop.orders.manage' => [
                'tab' => 'Shop',
                'label' => 'Change order status and confirm payments'
            ],
            'shop.customers.view' => [
                'tab' => 'Shop',
                'label' => 'View customers'
            ],
            'shop.reviews.moderate' => [
                'tab' => 'Shop',
                'label' => 'Approve and reject reviews'
            ],
            'shop.settings.manage' => [
                'tab' => 'Shop',
                'label' => 'Manage shop settings and shipping methods'
            ],
        ];
    }

    public function registerSettings()
    {
        return [
            'settings' => [
                'label' => 'Shop Settings',
                'description' => 'Currency, stock and attempt limits for each site.',
                'category' => 'Shop',
                'icon' => 'icon-shopping-cart',
                'class' => \AmoriumCms\Shop\Models\ShopSetting::class,
                'permissions' => ['shop.settings.manage'],
                'order' => 500,
                'keywords' => 'shop currency iban bank cash delivery stock'
            ]
        ];
    }
}
