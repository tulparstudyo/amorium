<?php namespace AmoriumCms\Shop\Classes\Import\Adapters;

use AmoriumCms\Shop\Models\Color;
use AmoriumCms\Shop\Classes\Money;
use AmoriumCms\Shop\Classes\Import\ImportedProduct;
use AmoriumCms\Shop\Classes\Import\ImportedVariant;
use AmoriumCms\Shop\Classes\Import\ImportedAttribute;
use AmoriumCms\Shop\Classes\Import\ImportException;
use AmoriumCms\Shop\Classes\Import\Contracts\ProductAdapter;

/**
 * TrendyolAdapter maps a Trendyol product document to a shop-neutral product.
 * Size rows become barcodes. Properties that are the same for every size stay on the product.
 */
class TrendyolAdapter implements ProductAdapter
{
    /**
     * adapt
     */
    public function adapt(string $url, array $payload): ImportedProduct
    {
        $product = $payload['product'] ?? null;

        if (!is_array($product) || empty($product['id'])) {
            throw new ImportException('The Trendyol product is missing an id.');
        }

        $id = (string) $product['id'];
        $this->assertLinkMatches($url, $id);

        $axes = $this->axes($product);
        $currency = $product['merchantListing']['winnerVariant']['price']['currency'] ?? null;
        $shared = $product['productGroupId'] ?? null;

        return new ImportedProduct(
            source: 'trendyol',
            externalId: $id,
            code: $shared ? 'ty-' . $shared : 'ty-' . $id,
            name: $this->text($product['name'] ?? '') ?: ('Trendyol ' . $id),
            locale: $this->locale((string) ($payload['culture'] ?? 'tr-TR')),
            brand: $this->text($product['brand']['name'] ?? ''),
            color: $this->color($product),
            currency: is_string($currency) && $currency !== '' ? strtoupper($currency) : null,
            taxRate: $this->tax($product['tax'] ?? null),
            attributes: $this->attributes($product, $axes),
            variants: $this->variants($product, $axes),
            images: $this->images($product)
        );
    }

    /**
     * assertLinkMatches refuses a page whose product id is not the one in the link
     */
    protected function assertLinkMatches(string $url, string $id): void
    {
        if (preg_match('~-p-(\d+)~', $url, $match) && $match[1] !== $id) {
            throw new ImportException('The Trendyol page does not match the product in the link.');
        }
    }

    /**
     * axes are the attribute names that differ between barcodes, such as Beden
     *
     * @return array<string, true>
     */
    protected function axes(array $product): array
    {
        $axes = [];

        foreach ($product['merchantListing']['winnerVariant']['variantAttributes'] ?? [] as $attribute) {
            $name = $this->text($attribute['attributeName'] ?? '');

            if ($name !== '' && !Color::isLabel($name)) {
                $axes[$name] = true;
            }
        }

        if (!$axes && count($product['variants'] ?? []) > 1) {
            $axes['Beden'] = true;
        }

        return $axes;
    }

    /**
     * attributes are the properties shared by every size
     *
     * @param array<string, true> $axes
     * @return ImportedAttribute[]
     */
    protected function attributes(array $product, array $axes): array
    {
        $attributes = [];

        foreach ($product['attributes'] ?? [] as $attribute) {
            $name = $this->text($attribute['key']['name'] ?? '');
            $value = $this->text($attribute['value']['name'] ?? '');

            if ($name === '' || $value === '' || isset($axes[$name]) || Color::isLabel($name)) {
                continue;
            }

            $attributes[] = new ImportedAttribute($name, $value);
        }

        return $attributes;
    }

    /**
     * color is the fixed color of this page, not a size
     */
    protected function color(array $product): ?string
    {
        foreach ($product['attributes'] ?? [] as $attribute) {
            $name = $this->text($attribute['key']['name'] ?? '');
            $value = $this->text($attribute['value']['name'] ?? '');

            if ($value !== '' && Color::isLabel($name)) {
                return $value;
            }
        }

        return null;
    }

    /**
     * variants
     *
     * @param array<string, true> $axes
     * @return ImportedVariant[]
     */
    protected function variants(array $product, array $axes): array
    {
        $rows = $product['variants'] ?? [];
        $winner = $product['merchantListing']['winnerVariant']['price'] ?? [];
        $compareAt = $this->compareAt($winner);
        $fallbackPrice = $this->money($winner['sellingPrice']['value'] ?? null);
        $axisName = array_key_first($axes);
        $variants = [];

        foreach (array_values($rows) as $index => $row) {
            if (!is_array($row)) {
                continue;
            }

            $value = $this->text($row['value'] ?? '');
            $barcode = $this->text($row['barcode'] ?? '');

            if ($barcode === '') {
                $barcode = 'ty-' . $product['id'] . ($value !== '' ? '-' . $value : '');
            }

            $attributes = [];

            if ($axisName && $value !== '') {
                $attributes[] = new ImportedAttribute($axisName, $value);
            }

            $variants[] = new ImportedVariant(
                barcode: $barcode,
                sku: isset($row['itemNumber']) ? (string) $row['itemNumber'] : null,
                price: $this->money($row['price']['value'] ?? null) ?? $fallbackPrice,
                compareAtPrice: $compareAt,
                attributes: $attributes,
                sortOrder: $index
            );
        }

        if ($variants) {
            return $variants;
        }

        $barcode = $this->text($product['productCode'] ?? '') ?: ('ty-' . $product['id']);

        return [
            new ImportedVariant(
                barcode: $barcode,
                sku: null,
                price: $fallbackPrice,
                compareAtPrice: $compareAt
            ),
        ];
    }

    /**
     * compareAt is the old price when it is higher than the selling price
     */
    protected function compareAt(array $price): ?string
    {
        $selling = $this->money($price['sellingPrice']['value'] ?? null);
        $original = $this->money($price['originalPrice']['value'] ?? null);

        if ($selling === null || $original === null || Money::compare($original, $selling) <= 0) {
            return null;
        }

        return $original;
    }

    /**
     * images keeps the original picture urls, in page order
     *
     * @return string[]
     */
    protected function images(array $product): array
    {
        $images = [];

        foreach ($product['images'] ?? [] as $image) {
            if (!is_string($image) || !str_starts_with($image, 'https://')) {
                continue;
            }

            $images[] = $image;
        }

        return array_values(array_unique($images));
    }

    /**
     * money
     */
    protected function money($value): ?string
    {
        if (!is_numeric($value)) {
            return null;
        }

        $rounded = Money::round((string) $value);

        return Money::compare($rounded, '0') < 0 ? null : $rounded;
    }

    /**
     * tax
     */
    protected function tax($value): string
    {
        if (!is_numeric($value)) {
            return '20';
        }

        $rate = (float) $value;

        return ($rate < 0 || $rate > 100) ? '20' : (string) $rate;
    }

    /**
     * locale is the language of the page, such as tr-TR
     */
    protected function locale(string $culture): string
    {
        $locale = strtolower(substr($culture, 0, 2));

        return preg_match('/^[a-z]{2}$/', $locale) ? $locale : 'tr';
    }

    /**
     * text
     */
    protected function text($value): string
    {
        return trim((string) $value);
    }
}
