<?php namespace AmoriumCms\Shop\Classes\Import;

use AmoriumCms\Shop\Classes\Import\Contracts\ProductAdapter;
use AmoriumCms\Shop\Classes\Import\Contracts\ProductClient;

/**
 * Source is the client and adapter pair of one product domain.
 */
class Source
{
    public function __construct(
        public ProductClient $client,
        public ProductAdapter $adapter
    ) {
    }
}
