<?php namespace AmoriumCms\Shop\Classes\Api;

/**
 * ApiCode lists every result code of the API with its HTTP status and default message.
 * Clients decide by the code; the message is only for display.
 */
class ApiCode
{
    const OK = 'OK';
    const DUPLICATE_REQUEST = 'DUPLICATE_REQUEST';

    const INVALID_JSON = 'INVALID_JSON';
    const VALIDATION_FAILED = 'VALIDATION_FAILED';
    const ENDPOINT_NOT_FOUND = 'ENDPOINT_NOT_FOUND';
    const METHOD_NOT_ALLOWED = 'METHOD_NOT_ALLOWED';
    const TOO_MANY_REQUESTS = 'TOO_MANY_REQUESTS';
    const IDEMPOTENCY_KEY_MISSING = 'IDEMPOTENCY_KEY_MISSING';

    const UNAUTHENTICATED = 'UNAUTHENTICATED';
    const TOKEN_EXPIRED = 'TOKEN_EXPIRED';
    const TOKEN_INVALID = 'TOKEN_INVALID';
    const INVALID_CREDENTIALS = 'INVALID_CREDENTIALS';
    const FORBIDDEN = 'FORBIDDEN';
    const ACCOUNT_NOT_ACTIVATED = 'ACCOUNT_NOT_ACTIVATED';
    const CSRF_MISMATCH = 'CSRF_MISMATCH';

    const NOT_FOUND = 'NOT_FOUND';
    const SITE_NOT_FOUND = 'SITE_NOT_FOUND';
    const CART_NOT_FOUND = 'CART_NOT_FOUND';
    const CONFLICT = 'CONFLICT';

    const PRODUCT_INACTIVE = 'PRODUCT_INACTIVE';
    const VARIANT_CONFLICT = 'VARIANT_CONFLICT';
    const PRICE_NOT_SET = 'PRICE_NOT_SET';

    const CART_EMPTY = 'CART_EMPTY';
    const OUT_OF_STOCK = 'OUT_OF_STOCK';
    const PRICE_CHANGED = 'PRICE_CHANGED';
    const SHIPPING_METHOD_UNAVAILABLE = 'SHIPPING_METHOD_UNAVAILABLE';
    const PAYMENT_METHOD_UNAVAILABLE = 'PAYMENT_METHOD_UNAVAILABLE';
    const PAYMENT_FAILED = 'PAYMENT_FAILED';

    const ORDER_NOT_CANCELLABLE = 'ORDER_NOT_CANCELLABLE';
    const INVALID_STATUS_TRANSITION = 'INVALID_STATUS_TRANSITION';
    const PAYMENT_ALREADY_CONFIRMED = 'PAYMENT_ALREADY_CONFIRMED';
    const REVIEW_ALREADY_EXISTS = 'REVIEW_ALREADY_EXISTS';

    const SERVER_ERROR = 'SERVER_ERROR';
    const SERVICE_UNAVAILABLE = 'SERVICE_UNAVAILABLE';

    /**
     * @var array definitions as code => [status, message]
     */
    protected static $definitions = [
        self::OK => [200, ''],
        self::DUPLICATE_REQUEST => [200, 'This request was already processed.'],

        self::INVALID_JSON => [400, 'The request body is not valid JSON.'],
        self::IDEMPOTENCY_KEY_MISSING => [400, 'The Idempotency-Key header is required.'],
        self::UNAUTHENTICATED => [401, 'You must sign in to continue.'],
        self::TOKEN_EXPIRED => [401, 'Your session has expired. Please sign in again.'],
        self::TOKEN_INVALID => [401, 'The access token is not valid.'],
        self::INVALID_CREDENTIALS => [401, 'These credentials do not match our records.'],
        self::FORBIDDEN => [403, 'You are not allowed to do this.'],
        self::ACCOUNT_NOT_ACTIVATED => [403, 'Your account is not active.'],
        self::NOT_FOUND => [404, 'The requested record was not found.'],
        self::ENDPOINT_NOT_FOUND => [404, 'The requested endpoint does not exist.'],
        self::SITE_NOT_FOUND => [404, 'The requested site does not exist.'],
        self::CART_NOT_FOUND => [404, 'The cart was not found.'],
        self::METHOD_NOT_ALLOWED => [405, 'This method is not allowed for the endpoint.'],
        self::CONFLICT => [409, 'The record conflicts with an existing one.'],
        self::VARIANT_CONFLICT => [409, 'Another product in this group has the same variant options.'],
        self::OUT_OF_STOCK => [409, 'Some products are not available in the requested quantity.'],
        self::PRICE_CHANGED => [409, 'Some prices have changed. Please review your cart.'],
        self::ORDER_NOT_CANCELLABLE => [409, 'This order can no longer be cancelled.'],
        self::INVALID_STATUS_TRANSITION => [409, 'The order cannot be moved to this status.'],
        self::PAYMENT_ALREADY_CONFIRMED => [409, 'This payment is already confirmed.'],
        self::REVIEW_ALREADY_EXISTS => [409, 'You have already reviewed this product.'],
        self::CSRF_MISMATCH => [419, 'The security token has expired. Please reload the page.'],
        self::VALIDATION_FAILED => [422, 'Some fields are invalid.'],
        self::CART_EMPTY => [422, 'Your cart is empty.'],
        self::PRODUCT_INACTIVE => [422, 'This product is not available.'],
        self::PRICE_NOT_SET => [422, 'This product has no price on this site.'],
        self::SHIPPING_METHOD_UNAVAILABLE => [422, 'The selected shipping method is not available.'],
        self::PAYMENT_METHOD_UNAVAILABLE => [422, 'The selected payment method is not available.'],
        self::PAYMENT_FAILED => [502, 'The payment could not be started. Please try again.'],
        self::TOO_MANY_REQUESTS => [429, 'Too many attempts. Please try again later.'],
        self::SERVER_ERROR => [500, 'Something went wrong. Please try again later.'],
        self::SERVICE_UNAVAILABLE => [503, 'The service is temporarily unavailable.'],
    ];

    /**
     * status returns the HTTP status of a code
     */
    public static function status(string $code): int
    {
        return static::$definitions[$code][0] ?? 500;
    }

    /**
     * message returns the translated default message of a code
     */
    public static function message(string $code): string
    {
        $message = static::$definitions[$code][1] ?? '';

        return $message === '' ? '' : __($message);
    }

    /**
     * all returns every code with its status, used by the admin pages
     */
    public static function all(): array
    {
        return array_map(fn ($row) => $row[0], static::$definitions);
    }
}
