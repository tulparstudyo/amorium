<?php namespace AmoriumCms\Shop\Classes\Import;

/**
 * ImportedProduct is a shop-neutral product. A client reads a page and an adapter fills this.
 * The importer does not know which shop the product came from.
 */
class ImportedProduct
{
    /**
     * @param ImportedAttribute[] $attributes shared by every barcode
     * @param ImportedVariant[] $variants
     * @param string[] $images
     */
    public function __construct(
        public string $source,
        public string $externalId,
        public string $code,
        public string $name,
        public string $locale,
        public ?string $brand = null,
        public ?string $color = null,
        public ?string $currency = null,
        public string $taxRate = '20',
        public array $attributes = [],
        public array $variants = [],
        public array $images = []
    ) {
    }
}
