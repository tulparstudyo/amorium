<?php namespace AmoriumCms\Shop\Controllers\Api;

use AmoriumCms\Shop\Classes\Storefront;
use AmoriumCms\Shop\Classes\Api\ApiCode;
use AmoriumCms\Shop\Classes\Api\ApiResponse;
use AmoriumCms\Shop\Classes\Api\ApiException;

/**
 * CatalogController serves categories, brands and shipping methods to the storefront.
 */
class CatalogController
{
    /**
     * categories returns the active category tree
     */
    public function categories()
    {
        return ApiResponse::ok(Storefront::categories());
    }

    /**
     * category
     */
    public function category(string $slug)
    {
        if (!$data = Storefront::category($slug)) {
            throw ApiException::make(ApiCode::NOT_FOUND);
        }

        return ApiResponse::ok($data);
    }

    /**
     * brands
     */
    public function brands()
    {
        return ApiResponse::ok(Storefront::brands());
    }

    /**
     * brand
     */
    public function brand(string $slug)
    {
        if (!$data = Storefront::brand($slug)) {
            throw ApiException::make(ApiCode::NOT_FOUND);
        }

        return ApiResponse::ok($data);
    }

    /**
     * shippingMethods lists the methods with their base price on the active site
     */
    public function shippingMethods()
    {
        return ApiResponse::ok(Storefront::shippingMethods());
    }
}
