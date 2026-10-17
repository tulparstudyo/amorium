<?php

use AmoriumCms\Shop\Classes\Api\ApiCode;
use AmoriumCms\Shop\Classes\Api\ApiResponse;
use AmoriumCms\Shop\Classes\Middleware\RequestId;
use AmoriumCms\Shop\Classes\Middleware\ResolveSite;
use AmoriumCms\Shop\Classes\Middleware\ValidateJson;
use AmoriumCms\Shop\Classes\Middleware\CustomerAuth;
use AmoriumCms\Shop\Classes\Middleware\AdminAuth;
use AmoriumCms\Shop\Classes\Middleware\PublicCache;
use AmoriumCms\Shop\Classes\Middleware\PrivateResponse;
use AmoriumCms\Shop\Classes\Middleware\NoStoreResponse;
use AmoriumCms\Shop\Controllers\Api;
use AmoriumCms\Shop\Controllers\Api\Admin;

$notFound = fn () => ApiResponse::error(ApiCode::ENDPOINT_NOT_FOUND);

/*
 * Storefront and customer API, prefixed by the site code: /api/v1/{site}/...
 */
Route::prefix('api/v1/{site}')
    ->where(['site' => '[a-z0-9_-]+'])
    ->middleware(['api', RequestId::class, ResolveSite::class, ValidateJson::class])
    ->group(function () use ($notFound) {

        Route::middleware(PublicCache::class)->group(function () {
            Route::get('categories', [Api\CatalogController::class, 'categories']);
            Route::get('categories/{slug}', [Api\CatalogController::class, 'category']);
            Route::get('brands', [Api\CatalogController::class, 'brands']);
            Route::get('brands/{slug}', [Api\CatalogController::class, 'brand']);
            Route::get('products', [Api\ProductController::class, 'index']);
            Route::get('products/filters', [Api\ProductController::class, 'filters']);
            Route::get('products/{slug}', [Api\ProductController::class, 'show']);
            Route::get('products/{slug}/reviews', [Api\ReviewController::class, 'index']);
            Route::get('shipping-methods', [Api\CatalogController::class, 'shippingMethods']);
        });

        Route::middleware(NoStoreResponse::class)->group(function () {
            Route::get('stock', [Api\StockController::class, 'index']);
        });

        Route::middleware(PrivateResponse::class)->group(function () {
            Route::post('auth/register', [Api\AuthController::class, 'register']);
            Route::post('auth/login', [Api\AuthController::class, 'login']);
            Route::post('auth/password/forgot', [Api\AuthController::class, 'forgotPassword']);
            Route::post('auth/password/reset', [Api\AuthController::class, 'resetPassword']);
            Route::get('orders/{number}', [Api\OrderController::class, 'guestShow']);
        });

        Route::middleware([CustomerAuth::class, PrivateResponse::class])->group(function () {
            Route::post('auth/refresh', [Api\AuthController::class, 'refresh']);
            Route::post('auth/logout', [Api\AuthController::class, 'logout']);

            Route::get('account/profile', [Api\AccountController::class, 'show']);
            Route::put('account/profile', [Api\AccountController::class, 'update']);
            Route::put('account/password', [Api\AccountController::class, 'password']);

            Route::get('account/addresses', [Api\AddressController::class, 'index']);
            Route::post('account/addresses', [Api\AddressController::class, 'store']);
            Route::put('account/addresses/{id}', [Api\AddressController::class, 'update']);
            Route::delete('account/addresses/{id}', [Api\AddressController::class, 'destroy']);

            Route::get('account/orders', [Api\OrderController::class, 'index']);
            Route::get('account/orders/{number}', [Api\OrderController::class, 'show']);
            Route::post('account/orders/{number}/cancel', [Api\OrderController::class, 'cancel']);

            Route::get('account/favorites', [Api\FavoriteController::class, 'index']);
            Route::post('account/favorites', [Api\FavoriteController::class, 'store']);
            Route::delete('account/favorites/{productId}', [Api\FavoriteController::class, 'destroy']);

            Route::get('account/reviews', [Api\ReviewController::class, 'mine']);
            Route::post('products/{slug}/reviews', [Api\ReviewController::class, 'store']);
            Route::put('account/reviews/{id}', [Api\ReviewController::class, 'update']);
            Route::delete('account/reviews/{id}', [Api\ReviewController::class, 'destroy']);
        });

        Route::middleware([CustomerAuth::class . ':optional', PrivateResponse::class])->group(function () {
            Route::post('cart', [Api\CartController::class, 'create']);
            Route::get('cart', [Api\CartController::class, 'show']);
            Route::delete('cart', [Api\CartController::class, 'clear']);
            Route::post('cart/items', [Api\CartController::class, 'addItem']);
            Route::patch('cart/items/{productId}', [Api\CartController::class, 'updateItem']);
            Route::delete('cart/items/{productId}', [Api\CartController::class, 'removeItem']);

            Route::get('checkout', [Api\CheckoutController::class, 'show']);
            Route::put('checkout', [Api\CheckoutController::class, 'update']);
            Route::post('checkout/place', [Api\CheckoutController::class, 'place']);
        });

        Route::middleware(NoStoreResponse::class)->group(function () {
            Route::get('payments/stripe/return', [Api\StripeController::class, 'returned']);
            Route::post('payments/stripe/webhook', [Api\StripeController::class, 'webhook']);
        });

        Route::any('{any?}', $notFound)->where('any', '.*');
    });

/*
 * Admin API, used by the backend pages with the backend session.
 */
Route::prefix('api/admin/v1')
    ->middleware(['web', RequestId::class, ValidateJson::class, AdminAuth::class, PrivateResponse::class])
    ->group(function () use ($notFound) {

        Route::get('meta', [Admin\MetaController::class, 'index']);

        Route::middleware(AdminAuth::class . ':shop.catalog.manage')->group(function () {
            Route::get('categories', [Admin\CategoryController::class, 'index']);
            Route::post('categories', [Admin\CategoryController::class, 'store']);
            Route::post('categories/reorder', [Admin\CategoryController::class, 'reorder']);
            Route::get('categories/{id}', [Admin\CategoryController::class, 'show']);
            Route::put('categories/{id}', [Admin\CategoryController::class, 'update']);
            Route::delete('categories/{id}', [Admin\CategoryController::class, 'destroy']);

            Route::get('colors', [Admin\ColorController::class, 'index']);
            Route::post('colors', [Admin\ColorController::class, 'store']);

            Route::get('brands', [Admin\BrandController::class, 'index']);
            Route::post('brands', [Admin\BrandController::class, 'store']);
            Route::get('brands/{id}', [Admin\BrandController::class, 'show']);
            Route::post('brands/{id}', [Admin\BrandController::class, 'update']);
            Route::put('brands/{id}', [Admin\BrandController::class, 'update']);
            Route::delete('brands/{id}', [Admin\BrandController::class, 'destroy']);

            Route::get('specs', [Admin\SpecController::class, 'index']);
            Route::post('specs', [Admin\SpecController::class, 'store']);
            Route::get('specs/{id}', [Admin\SpecController::class, 'show']);
            Route::put('specs/{id}', [Admin\SpecController::class, 'update']);
            Route::delete('specs/{id}', [Admin\SpecController::class, 'destroy']);
            Route::get('specs/{id}/variant-impact', [Admin\SpecController::class, 'variantImpact']);
            Route::post('specs/{id}/options', [Admin\SpecController::class, 'storeOption']);
            Route::put('specs/{id}/options/{optionId}', [Admin\SpecController::class, 'updateOption']);
            Route::delete('specs/{id}/options/{optionId}', [Admin\SpecController::class, 'destroyOption']);

            Route::get('product-groups', [Admin\ProductGroupController::class, 'index']);
            Route::post('product-groups', [Admin\ProductGroupController::class, 'store']);
            Route::get('product-groups/{id}', [Admin\ProductGroupController::class, 'show']);
            Route::put('product-groups/{id}', [Admin\ProductGroupController::class, 'update']);
            Route::delete('product-groups/{id}', [Admin\ProductGroupController::class, 'destroy']);
            Route::post('product-groups/{id}/images', [Admin\ProductGroupController::class, 'storeImage']);
            Route::put('product-groups/{id}/images/{imageId}', [Admin\ProductGroupController::class, 'updateImage']);
            Route::delete('product-groups/{id}/images/{imageId}', [Admin\ProductGroupController::class, 'destroyImage']);

            Route::get('products', [Admin\ProductController::class, 'index']);
            Route::post('products', [Admin\ProductController::class, 'store']);
            Route::put('products/prices', [Admin\ProductController::class, 'bulkPrices']);
            Route::get('products/{id}', [Admin\ProductController::class, 'show']);
            Route::put('products/{id}', [Admin\ProductController::class, 'update']);
            Route::delete('products/{id}', [Admin\ProductController::class, 'destroy']);
            Route::post('products/{id}/stock', [Admin\ProductController::class, 'adjustStock']);
            Route::get('products/{id}/stock', [Admin\ProductController::class, 'stockMovements']);
        });

        Route::middleware(AdminAuth::class . ':shop.reviews.moderate')->group(function () {
            Route::get('reviews', [Admin\ReviewController::class, 'index']);
            Route::get('reviews/{id}', [Admin\ReviewController::class, 'show']);
            Route::patch('reviews/{id}', [Admin\ReviewController::class, 'update']);
            Route::delete('reviews/{id}', [Admin\ReviewController::class, 'destroy']);
        });

        Route::middleware(AdminAuth::class . ':shop.customers.view')->group(function () {
            Route::get('customers', [Admin\CustomerController::class, 'index']);
            Route::get('customers/{id}', [Admin\CustomerController::class, 'show']);
        });

        Route::middleware(AdminAuth::class . ':shop.orders.view,shop.orders.manage')->group(function () {
            Route::get('orders', [Admin\OrderController::class, 'index']);
            Route::get('orders/{id}', [Admin\OrderController::class, 'show']);
            Route::get('dashboard', [Admin\DashboardController::class, 'index']);
        });

        Route::middleware(AdminAuth::class . ':shop.orders.manage')->group(function () {
            Route::patch('orders/{id}/status', [Admin\OrderController::class, 'updateStatus']);
            Route::put('orders/{id}/note', [Admin\OrderController::class, 'updateNote']);
            Route::post('orders/{id}/payments/{paymentId}/confirm', [Admin\OrderController::class, 'confirmPayment']);
        });

        Route::middleware(AdminAuth::class . ':shop.settings.manage')->group(function () {
            Route::get('shipping-methods', [Admin\ShippingMethodController::class, 'index']);
            Route::post('shipping-methods', [Admin\ShippingMethodController::class, 'store']);
            Route::get('shipping-methods/{id}', [Admin\ShippingMethodController::class, 'show']);
            Route::put('shipping-methods/{id}', [Admin\ShippingMethodController::class, 'update']);
            Route::delete('shipping-methods/{id}', [Admin\ShippingMethodController::class, 'destroy']);
            Route::get('settings', [Admin\SettingsController::class, 'show']);
            Route::put('settings', [Admin\SettingsController::class, 'update']);
            Route::get('payment-methods', [Admin\PaymentMethodController::class, 'index']);
            Route::put('payment-methods/{code}', [Admin\PaymentMethodController::class, 'update'])->where('code', '[a-z0-9_]+');
        });

        Route::any('{any?}', $notFound)->where('any', '.*');
    });
