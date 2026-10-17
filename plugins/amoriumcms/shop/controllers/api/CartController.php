<?php namespace AmoriumCms\Shop\Controllers\Api;

use Request;
use Validator;
use AmoriumCms\Shop\Classes\CartService;
use AmoriumCms\Shop\Classes\Api\ApiResponse;

/**
 * CartController. Guests send the cart token in the X-Cart-Token header.
 */
class CartController
{
    /**
     * create returns the open cart of the customer, or a new guest cart
     */
    public function create()
    {
        $cart = CartService::resolve(true);

        return ApiResponse::created(CartService::present($cart));
    }

    /**
     * show
     */
    public function show()
    {
        return ApiResponse::ok(CartService::present(CartService::resolve()));
    }

    /**
     * clear
     */
    public function clear()
    {
        $cart = CartService::resolve();
        $cart->items()->delete();
        $cart->unsetRelation('items');

        return ApiResponse::ok(CartService::present($cart));
    }

    /**
     * addItem adds to the quantity already in the cart
     */
    public function addItem()
    {
        $data = Validator::make(Request::all(), [
            'product_id' => 'required|integer',
            'quantity' => 'nullable|integer|min:1|max:' . CartService::MAX_QUANTITY,
        ])->validate();

        $cart = CartService::resolve(true);
        $quantity = CartService::currentQuantity($cart, $data['product_id']) + (int) ($data['quantity'] ?? 1);

        CartService::setQuantity($cart, $data['product_id'], min($quantity, CartService::MAX_QUANTITY));

        return ApiResponse::ok(CartService::present($cart));
    }

    /**
     * updateItem sets the quantity; zero removes the line
     */
    public function updateItem(int $productId)
    {
        $data = Validator::make(Request::all(), [
            'quantity' => 'required|integer|min:0|max:' . CartService::MAX_QUANTITY,
        ])->validate();

        $cart = CartService::resolve();
        CartService::setQuantity($cart, $productId, $data['quantity']);

        return ApiResponse::ok(CartService::present($cart));
    }

    /**
     * removeItem
     */
    public function removeItem(int $productId)
    {
        $cart = CartService::resolve();
        CartService::setQuantity($cart, $productId, 0);

        return ApiResponse::ok(CartService::present($cart));
    }
}
