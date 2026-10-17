<?php namespace AmoriumCms\Shop\Classes\Api;

use Str;
use Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * ApiResponse builds every API response with the same fields.
 */
class ApiResponse
{
    /**
     * ok
     */
    public static function ok($data = null, ?array $meta = null, int $status = 200, string $code = ApiCode::OK, ?string $message = null): JsonResponse
    {
        return static::make(true, $code, $message ?? ApiCode::message($code), $data, $meta, null, $status);
    }

    /**
     * created
     */
    public static function created($data = null): JsonResponse
    {
        return static::ok($data, null, 201);
    }

    /**
     * paginated returns a page of records, transformed by the callback
     */
    public static function paginated(LengthAwarePaginator $paginator, callable $transform, array $meta = []): JsonResponse
    {
        $data = array_map($transform, $paginator->items());

        return static::ok($data, array_merge([
            'page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'last_page' => $paginator->lastPage(),
        ], $meta));
    }

    /**
     * error is never cached
     */
    public static function error(string $code, ?string $message = null, $data = null, ?array $errors = null, ?array $meta = null): JsonResponse
    {
        $response = static::make(false, $code, $message ?? ApiCode::message($code), $data, $meta, $errors, ApiCode::status($code));
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }

    /**
     * requestId is shared by the response and the log entries of the request
     */
    public static function requestId(): string
    {
        $request = Request::instance();

        if (!$id = $request->attributes->get('shop_request_id')) {
            $id = (string) Str::ulid();
            $request->attributes->set('shop_request_id', $id);
        }

        return $id;
    }

    /**
     * make
     */
    protected static function make(bool $success, string $code, string $message, $data, ?array $meta, ?array $errors, int $status): JsonResponse
    {
        $requestId = static::requestId();

        $response = new JsonResponse([
            'success' => $success,
            'code' => $code,
            'message' => $message,
            'data' => $data,
            'meta' => $meta,
            'errors' => $errors,
            'request_id' => $requestId,
        ], $status, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);

        $response->headers->set('X-Request-Id', $requestId);

        return $response;
    }
}
