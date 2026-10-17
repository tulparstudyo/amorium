<?php namespace AmoriumCms\Shop\Classes\Middleware;

use Auth;
use Closure;
use AmoriumCms\Shop\Classes\BearerTokens;
use AmoriumCms\Shop\Classes\Api\ApiCode;
use AmoriumCms\Shop\Classes\Api\ApiException;

/**
 * CustomerAuth signs the customer in from the bearer token. With the "optional"
 * parameter guests pass through, but a sent token must still be valid.
 */
class CustomerAuth
{
    public function handle($request, Closure $next, ?string $mode = null)
    {
        $token = $request->bearerToken();

        if (!$token) {
            if ($mode === 'optional') {
                return $next($request);
            }

            throw ApiException::make(ApiCode::UNAUTHENTICATED);
        }

        if (BearerTokens::isRevoked($token) || !Auth::loginUsingBearerToken($token)) {
            throw ApiException::make(BearerTokens::isExpired($token) ? ApiCode::TOKEN_EXPIRED : ApiCode::TOKEN_INVALID);
        }

        return $next($request);
    }
}
