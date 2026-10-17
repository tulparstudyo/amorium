<?php namespace AmoriumCms\Shop\Classes\Middleware;

use Closure;

/**
 * NoStoreResponse is for shared data that must always be fresh, such as stock status.
 */
class NoStoreResponse
{
    public function handle($request, Closure $next)
    {
        $response = $next($request);

        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }
}
