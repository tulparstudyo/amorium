<?php namespace AmoriumCms\Shop\Classes\Import\Contracts;

/**
 * ProductClient downloads the raw product document of one shop.
 */
interface ProductClient
{
    /**
     * fetch returns the raw payload the paired adapter understands
     */
    public function fetch(string $url): array;
}
