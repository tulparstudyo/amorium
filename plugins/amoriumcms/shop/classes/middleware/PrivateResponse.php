<?php namespace AmoriumCms\Shop\Classes\Middleware;

use Closure;

/**
 * PrivateResponse keeps responses that depend on the customer, cart or admin out of every cache.
 */
class PrivateResponse
{
    public function handle($request, Closure $next)
    {
        $response = $next($request);

        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }
}
