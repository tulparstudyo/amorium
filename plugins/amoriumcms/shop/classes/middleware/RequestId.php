<?php namespace AmoriumCms\Shop\Classes\Middleware;

use Log;
use Closure;
use AmoriumCms\Shop\Classes\Api\ApiResponse;

/**
 * RequestId adds the request id to every log entry written during the request.
 */
class RequestId
{
    public function handle($request, Closure $next)
    {
        Log::shareContext(['request_id' => ApiResponse::requestId()]);

        return $next($request);
    }
}
