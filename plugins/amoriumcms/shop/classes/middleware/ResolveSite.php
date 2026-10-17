<?php namespace AmoriumCms\Shop\Classes\Middleware;

use Site;
use Closure;
use AmoriumCms\Shop\Classes\Api\ApiCode;
use AmoriumCms\Shop\Classes\Api\ApiException;

/**
 * ResolveSite activates the site given by the {site} prefix, so translations,
 * prices and the site of new records follow it.
 */
class ResolveSite
{
    public function handle($request, Closure $next)
    {
        $route = $request->route();
        $code = (string) $route->parameter('site');

        $site = Site::listEnabled()->first(fn ($site) => $site->code === $code);
        if (!$site) {
            throw ApiException::make(ApiCode::SITE_NOT_FOUND);
        }

        Site::applyActiveSite($site);
        $route->forgetParameter('site');

        return $next($request);
    }
}
