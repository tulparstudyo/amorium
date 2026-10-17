<?php namespace AmoriumCms\Shop\Controllers\Api;

use Request;
use AmoriumCms\Shop\Classes\CartService;
use AmoriumCms\Shop\Classes\OrderService;
use AmoriumCms\Shop\Classes\CheckoutService;
use AmoriumCms\Shop\Classes\Api\ApiCode;
use AmoriumCms\Shop\Classes\Api\ApiResponse;

/**
 * CheckoutController is the one page checkout.
 */
class CheckoutController
{
    /**
     * show
     */
    public function show()
    {
        $cart = CartService::resolve();
        CheckoutService::trackIncomplete($cart);

        return ApiResponse::ok(CheckoutService::show($cart));
    }

    /**
     * update
     */
    public function update()
    {
        $cart = CheckoutService::update(CartService::resolve(), Request::all());
        CheckoutService::trackIncomplete($cart);

        return ApiResponse::ok(CheckoutService::show($cart));
    }

    /**
     * place requires the Idempotency-Key header
     */
    public function place()
    {
        $result = CheckoutService::place(Request::header('Idempotency-Key'), Request::input('expected_total'));

        $order = $result['order']->fresh();
        $data = OrderService::present($order);

        if ($order->guest_access_token) {
            $data['guest_access_token'] = $order->guest_access_token;
        }

        return $result['duplicate']
            ? ApiResponse::ok($data, null, 200, ApiCode::DUPLICATE_REQUEST)
            : ApiResponse::created($data);
    }
}
