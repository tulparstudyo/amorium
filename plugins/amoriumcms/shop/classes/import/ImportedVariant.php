<?php namespace AmoriumCms\Shop\Classes\Import;

/**
 * ImportedVariant is one barcode. Attributes here are the axes that differ between barcodes.
 */
class ImportedVariant
{
    /**
     * @param ImportedAttribute[] $attributes
     */
    public function __construct(
        public string $barcode,
        public ?string $sku,
        public ?string $price,
        public ?string $compareAtPrice,
        public array $attributes = [],
        public int $sortOrder = 0
    ) {
    }
}
