<?php namespace AmoriumCms\Shop\Models\Concerns;

use AmoriumCms\Shop\Classes\CatalogCache;

/**
 * BumpsCatalogCache drops the storefront cache whenever the model changes.
 */
trait BumpsCatalogCache
{
    public static function bootBumpsCatalogCache()
    {
        static::saved(fn () => CatalogCache::bump());
        static::deleted(fn () => CatalogCache::bump());
    }
}
