<?php namespace AmoriumCms\Shop\Classes\Import;

/**
 * ImportedAttribute is one product property in the source language, such as Color: Black.
 */
class ImportedAttribute
{
    public function __construct(
        public string $name,
        public string $value
    ) {
    }
}
