<?php namespace AmoriumCms\Shop\Classes\Import\Clients;

use AmoriumCms\Shop\Classes\Import\RemoteGet;
use AmoriumCms\Shop\Classes\Import\ImportException;
use AmoriumCms\Shop\Classes\Import\Contracts\ProductClient;

/**
 * TrendyolClient downloads a product page and returns the embedded product document.
 */
class TrendyolClient implements ProductClient
{
    /**
     * fetch
     */
    public function fetch(string $url): array
    {
        $response = (new RemoteGet)->fetch($url, 'text/html,application/xhtml+xml');

        if (!$response->successful()) {
            throw new ImportException("Trendyol returned HTTP {$response->status} for the product link.");
        }

        $props = $this->sharedProps($response->body);
        $product = $props['product'] ?? null;

        if (!is_array($product) || empty($product['id'])) {
            throw new ImportException('The product data was not found on the Trendyol page.');
        }

        return [
            'url' => $url,
            'culture' => (string) ($props['culture'] ?? 'tr-TR'),
            'product' => $product,
        ];
    }

    /**
     * sharedProps reads window["__envoy__SHARED_PROPS"] from the page
     */
    protected function sharedProps(string $html): array
    {
        $marker = 'window["__envoy__SHARED_PROPS"]={';
        $start = strpos($html, $marker);

        if ($start === false) {
            throw new ImportException('The product data was not found on the Trendyol page.');
        }

        $start += strlen($marker) - 1;
        $json = $this->jsonObject($html, $start);
        $decoded = json_decode($json, true);

        if (!is_array($decoded)) {
            throw new ImportException('The product data on the Trendyol page could not be read.');
        }

        return $decoded;
    }

    /**
     * jsonObject returns the object that starts at the given brace, respecting strings
     */
    protected function jsonObject(string $html, int $start): string
    {
        $depth = 0;
        $inString = false;
        $escaped = false;
        $length = strlen($html);

        for ($i = $start; $i < $length; $i++) {
            $char = $html[$i];

            if ($inString) {
                if ($escaped) {
                    $escaped = false;
                    continue;
                }

                if ($char === '\\') {
                    $escaped = true;
                    continue;
                }

                if ($char === '"') {
                    $inString = false;
                }

                continue;
            }

            if ($char === '"') {
                $inString = true;
                continue;
            }

            if ($char === '{') {
                $depth++;
                continue;
            }

            if ($char === '}') {
                $depth--;

                if ($depth === 0) {
                    return substr($html, $start, $i - $start + 1);
                }
            }
        }

        throw new ImportException('The product data on the Trendyol page could not be read.');
    }
}
