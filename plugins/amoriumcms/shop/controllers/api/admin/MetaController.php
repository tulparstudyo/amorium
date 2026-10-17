<?php namespace AmoriumCms\Shop\Controllers\Api\Admin;

use Site;
use BackendAuth;
use AmoriumCms\Shop\Classes\StockStatus;
use AmoriumCms\Shop\Classes\Api\ApiResponse;
use AmoriumCms\Shop\Classes\Payments\PaymentMethods;
use AmoriumCms\Shop\Models\Spec;
use AmoriumCms\Shop\Models\Order;
use AmoriumCms\Shop\Models\Review;
use AmoriumCms\Shop\Models\ShopSetting;
use AmoriumCms\Shop\Models\StockMovement;

/**
 * MetaController returns the lists the admin pages need to build their forms.
 */
class MetaController extends AdminController
{
    const PERMISSIONS = [
        'shop.catalog.manage',
        'shop.orders.view',
        'shop.orders.manage',
        'shop.customers.view',
        'shop.reviews.moderate',
        'shop.settings.manage',
    ];

    /**
     * index
     */
    public function index()
    {
        $user = BackendAuth::getUser();
        $primaryId = Site::getPrimarySite()?->id;

        $sites = Site::listEnabled()->map(function ($site) use ($primaryId) {
            return Site::withContext($site->id, fn () => [
                'id' => $site->id,
                'code' => $site->code,
                'name' => $site->name,
                'locale' => $site->hard_locale,
                'is_primary' => $site->id === $primaryId,
                'currency' => ShopSetting::value('currency'),
            ]);
        })->values()->all();

        return ApiResponse::ok([
            'sites' => $sites,
            'locales' => $this->locales(),
            'spec_types' => Spec::TYPES,
            'order_statuses' => Order::STATUSES,
            'order_transitions' => Order::TRANSITIONS,
            'payment_statuses' => [Order::PAYMENT_PENDING, Order::PAYMENT_PAID, Order::PAYMENT_REFUNDED],
            'payment_methods' => array_values(array_map(fn ($method) => [
                'code' => $method->code(),
                'name' => __($method->name()),
            ], PaymentMethods::all())),
            'review_statuses' => Review::STATUSES,
            'stock_statuses' => [StockStatus::IN_STOCK, StockStatus::LOW_STOCK, StockStatus::OUT_OF_STOCK],
            'stock_reasons' => [StockMovement::REASON_ORDER, StockMovement::REASON_CANCEL, StockMovement::REASON_MANUAL],
            'permissions' => collect(static::PERMISSIONS)->mapWithKeys(fn ($code) => [$code => $user->hasAccess($code)])->all(),
        ]);
    }
}
