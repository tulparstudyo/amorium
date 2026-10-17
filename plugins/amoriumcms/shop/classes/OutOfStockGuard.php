<?php namespace AmoriumCms\Shop\Classes;

use Log;
use Auth;
use Request;
use Illuminate\Support\Facades\RateLimiter;
use AmoriumCms\Shop\Classes\Api\ApiCode;
use AmoriumCms\Shop\Classes\Api\ApiException;
use AmoriumCms\Shop\Models\Cart;
use AmoriumCms\Shop\Models\ShopSetting;

/**
 * OutOfStockGuard counts OUT_OF_STOCK answers per customer (or cart) and per IP address on
 * the server, and logs each one so abuse can be found and handled by an administrator.
 */
class OutOfStockGuard
{
    /**
     * ensureAllowed stops the stock checking endpoints once a limit is reached
     */
    public static function ensureAllowed(Cart $cart)
    {
        foreach (static::limits($cart) as $key => $limit) {
            if (RateLimiter::tooManyAttempts($key, $limit)) {
                $exception = ApiException::make(ApiCode::TOO_MANY_REQUESTS);
                $exception->headers = ['Retry-After' => RateLimiter::availableIn($key)];
                throw $exception;
            }
        }
    }

    /**
     * hit counts and logs an OUT_OF_STOCK answer
     */
    public static function hit(Cart $cart, array $items)
    {
        $decay = (int) ShopSetting::value('out_of_stock_decay_minutes') * 60;

        foreach (array_keys(static::limits($cart)) as $key) {
            RateLimiter::hit($key, $decay);
        }

        Log::warning('Shop: out of stock answer', [
            'barcodes' => array_column($items, 'barcode'),
            'user_id' => Auth::id(),
            'cart_token' => $cart->token,
            'ip' => Request::ip(),
        ]);
    }

    /**
     * run calls the stock check and counts its OUT_OF_STOCK answer
     */
    public static function run(Cart $cart, callable $callback)
    {
        static::ensureAllowed($cart);

        try {
            return $callback();
        }
        catch (ApiException $ex) {
            if ($ex->apiCode === ApiCode::OUT_OF_STOCK) {
                static::hit($cart, $ex->data['items'] ?? []);
            }

            throw $ex;
        }
    }

    /**
     * limits returns rate limiter key => maximum attempts
     */
    protected static function limits(Cart $cart): array
    {
        $customer = Auth::id() ? 'user:' . Auth::id() : 'cart:' . $cart->token;

        return [
            'shop-oos:' . $customer => (int) ShopSetting::value('out_of_stock_limit_customer'),
            'shop-oos:ip:' . Request::ip() => (int) ShopSetting::value('out_of_stock_limit_ip'),
        ];
    }
}
