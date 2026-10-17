<?php namespace AmoriumCms\Shop\Controllers\Api;

use Auth;
use Request;
use AmoriumCms\Shop\Classes\Money;
use AmoriumCms\Shop\Classes\OrderService;
use AmoriumCms\Shop\Classes\Api\ApiCode;
use AmoriumCms\Shop\Classes\Api\ApiResponse;
use AmoriumCms\Shop\Classes\Api\ApiException;
use AmoriumCms\Shop\Models\Order;

/**
 * OrderController shows the order history of the customer. Guests open their order
 * with the token sent in the order e-mail.
 */
class OrderController
{
    /**
     * index
     */
    public function index()
    {
        $paginator = Order::with('items')
            ->where('user_id', Auth::id())
            ->isPlaced()
            ->orderBy('id', 'desc')
            ->paginate(min(50, max(1, (int) Request::query('per_page', 10))));

        return ApiResponse::paginated($paginator, fn ($order) => [
            'number' => $order->number,
            'status' => $order->status,
            'payment_status' => $order->payment_status,
            'grand_total' => Money::toFloat($order->grand_total),
            'currency' => $order->currency,
            'item_count' => $order->items->sum('quantity'),
            'placed_at' => $order->placed_at?->toIso8601String(),
        ]);
    }

    /**
     * show
     */
    public function show(string $number)
    {
        return ApiResponse::ok(OrderService::present($this->findOwn($number)));
    }

    /**
     * cancel
     */
    public function cancel(string $number)
    {
        $order = OrderService::cancelByCustomer($this->findOwn($number));

        return ApiResponse::ok(OrderService::present($order->fresh()));
    }

    /**
     * guestShow
     */
    public function guestShow(string $number)
    {
        $token = (string) Request::query('token');

        $order = $token !== ''
            ? Order::where('number', $number)->whereNull('user_id')->where('guest_access_token', $token)->first()
            : null;

        if (!$order) {
            throw ApiException::make(ApiCode::NOT_FOUND);
        }

        return ApiResponse::ok(OrderService::present($order));
    }

    /**
     * findOwn
     */
    protected function findOwn(string $number): Order
    {
        $order = Order::where('user_id', Auth::id())->where('number', $number)->first();
        if (!$order) {
            throw ApiException::make(ApiCode::NOT_FOUND);
        }

        return $order;
    }
}
