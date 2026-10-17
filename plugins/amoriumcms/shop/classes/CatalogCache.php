<?php namespace AmoriumCms\Shop\Classes;

use App;
use Site;
use Cache;

/**
 * CatalogCache keeps storefront results in the application cache. Every key holds a
 * version number, so increasing the version drops all entries at once without cache tags.
 */
class CatalogCache
{
    const CATALOG = 'catalog';
    const REVIEWS = 'reviews';

    /**
     * remember
     */
    public static function remember(string $name, array $params, int $seconds, callable $callback, string $scope = self::CATALOG)
    {
        ksort($params);

        $key = implode(':', [
            'shop',
            $scope,
            'v' . static::version($scope),
            Site::getActiveSite()?->id ?: 0,
            App::getLocale(),
            $name,
            md5(json_encode($params)),
        ]);

        return Cache::remember($key, $seconds, $callback);
    }

    /**
     * version
     */
    public static function version(string $scope = self::CATALOG): int
    {
        return (int) Cache::rememberForever("shop:{$scope}:version", fn () => 1);
    }

    /**
     * bump
     */
    public static function bump(string $scope = self::CATALOG)
    {
        Cache::forever("shop:{$scope}:version", static::version($scope) + 1);
    }
}
