<?php namespace AmoriumCms\Shop\Controllers\Api\Admin;

use Site;
use AmoriumCms\Shop\Classes\Api\ApiResponse;
use AmoriumCms\Shop\Classes\Api\ApiException;
use AmoriumCms\Shop\Classes\Api\ApiCode;
use AmoriumCms\Shop\Classes\Payments\PaymentMethods;
use AmoriumCms\Shop\Classes\Payments\PaymentSettings;

/**
 * PaymentMethodController edits the payment methods of one site: ?site_id=, primary site by default.
 */
class PaymentMethodController extends AdminController
{
    /**
     * index
     */
    public function index()
    {
        $siteId = $this->siteId(true);

        return ApiResponse::ok(Site::withContext($siteId, fn () => PaymentSettings::all()));
    }

    /**
     * update
     */
    public function update(string $code)
    {
        $method = PaymentMethods::find($code);
        if (!$method) {
            throw ApiException::make(ApiCode::NOT_FOUND);
        }

        $siteId = $this->siteId(true);
        $input = $this->validate(PaymentSettings::rules($code));

        return ApiResponse::ok(Site::withContext($siteId, fn () => PaymentSettings::save($method, $input)));
    }
}
