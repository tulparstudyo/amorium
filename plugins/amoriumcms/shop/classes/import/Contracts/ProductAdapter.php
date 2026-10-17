<?php namespace AmoriumCms\Shop\Classes\Import\Contracts;

use AmoriumCms\Shop\Classes\Import\ImportedProduct;

/**
 * ProductAdapter turns one shop's payload into a shop-neutral product.
 */
interface ProductAdapter
{
    /**
     * adapt
     */
    public function adapt(string $url, array $payload): ImportedProduct;
}
