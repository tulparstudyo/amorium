<?php namespace AmoriumCms\Shop\Controllers\Api;

use Request;
use AmoriumCms\Shop\Classes\Storefront;
use AmoriumCms\Shop\Classes\Api\ApiCode;
use AmoriumCms\Shop\Classes\Api\ApiResponse;
use AmoriumCms\Shop\Classes\Api\ApiException;

/**
 * ProductController lists product groups and shows a product page. An empty listing is a
 * normal 200 answer; only a missing product returns 404.
 */
class ProductController
{
    /**
     * index
     */
    public function index()
    {
        $result = Storefront::products(Request::query());

        return ApiResponse::ok($result['data'], $result['meta']);
    }

    /**
     * filters returns the filter options of a listing
     */
    public function filters()
    {
        return ApiResponse::ok(Storefront::filters(Request::query()));
    }

    /**
     * show
     */
    public function show(string $slug)
    {
        if (!$data = Storefront::product($slug)) {
            throw ApiException::make(ApiCode::NOT_FOUND);
        }

        return ApiResponse::ok($data);
    }
}
