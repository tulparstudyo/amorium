<?php namespace AmoriumCms\Shop\Classes\Import;

/**
 * ImportResult is what the importer stored.
 */
class ImportResult
{
    /**
     * @param string[] $warnings
     */
    public function __construct(
        public int $groupId,
        public string $code,
        public string $name,
        public bool $created,
        public int $createdBarcodes,
        public int $updatedBarcodes,
        public int $removedBarcodes,
        public int $images,
        public array $warnings = [],
        public ?string $category = null
    ) {
    }
}
