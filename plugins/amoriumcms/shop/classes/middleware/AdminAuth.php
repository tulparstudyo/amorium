<?php namespace AmoriumCms\Shop\Classes\Middleware;

use Closure;
use BackendAuth;
use Backend\Models\Preference;
use AmoriumCms\Shop\Classes\Api\ApiCode;
use AmoriumCms\Shop\Classes\Api\ApiException;

/**
 * AdminAuth lets backend users call the admin API with their backend session.
 * Writing requests must send the X-CSRF-TOKEN header of the backend page.
 * Usage: AdminAuth::class . ':shop.orders.manage'
 */
class AdminAuth
{
    public function handle($request, Closure $next, ...$permissions)
    {
        $user = BackendAuth::getUser();
        if (!$user) {
            throw ApiException::make(ApiCode::UNAUTHENTICATED);
        }

        Preference::setAppLocale();
        Preference::setAppFallbackLocale();

        if (!$request->isMethodSafe()) {
            $token = (string) $request->header('X-CSRF-TOKEN');
            $sessionToken = $request->hasSession() ? (string) $request->session()->token() : '';

            if ($token === '' || $sessionToken === '' || !hash_equals($sessionToken, $token)) {
                throw ApiException::make(ApiCode::CSRF_MISMATCH);
            }
        }

        if ($permissions && !$user->hasAnyAccess($permissions)) {
            throw ApiException::make(ApiCode::FORBIDDEN);
        }

        return $next($request);
    }
}
