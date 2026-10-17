<?php namespace AmoriumCms\Shop\Classes;

use AmoriumCms\Shop\Models\Spec;
use AmoriumCms\Shop\Models\Brand;
use AmoriumCms\Shop\Models\Color;
use AmoriumCms\Shop\Models\Product;
use AmoriumCms\Shop\Models\Category;
use AmoriumCms\Shop\Models\ProductGroup;
use AmoriumCms\Shop\Models\ProductImage;
use AmoriumCms\Shop\Models\ShopSetting;
use AmoriumCms\Shop\Models\ShippingMethod;

/**
 * Presenter turns storefront models into API arrays. Nothing here depends on the customer,
 * so the results can be cached and shared. Stock quantities are never included.
 */
class Presenter
{
    /**
     * currency of the active site
     */
    public static function currency(): string
    {
        return (string) ShopSetting::value('currency');
    }

    /**
     * category
     */
    public static function category(Category $category, bool $full = false): array
    {
        $data = [
            'id' => $category->id,
            'parent_id' => $category->parent_id,
            'slug' => $category->trans('slug'),
            'name' => $category->trans('name'),
        ];

        if ($full) {
            $data['description'] = $category->trans('description');
            $data['meta_title'] = $category->trans('meta_title');
            $data['meta_description'] = $category->trans('meta_description');
        }

        return $data;
    }

    /**
     * brand
     */
    public static function brand(?Brand $brand, bool $full = false): ?array
    {
        if (!$brand) {
            return null;
        }

        $data = [
            'id' => $brand->id,
            'code' => $brand->code,
            'slug' => $brand->trans('slug'),
            'name' => $brand->trans('name'),
            'logo' => $brand->logo ? $brand->logo->getPath() : null,
        ];

        if ($full) {
            $data['description'] = $brand->trans('description');
            $data['meta_title'] = $brand->trans('meta_title');
            $data['meta_description'] = $brand->trans('meta_description');
        }

        return $data;
    }

    /**
     * image
     */
    public static function image(ProductImage $image): ?array
    {
        if (!$image->image) {
            return null;
        }

        return [
            'id' => $image->id,
            'option_id' => $image->spec_option_id,
            'url' => $image->image->getPath(),
            'thumb' => $image->image->getThumb(480, 480, ['mode' => 'crop']),
        ];
    }

    /**
     * groupCard is a product group in a listing
     */
    public static function groupCard(ProductGroup $group): array
    {
        $sellable = $group->products->filter(fn ($product) => $product->priceFor() !== null);
        $cheapest = $sellable->sortBy(fn ($product) => (float) $product->priceFor()->price)->first();
        $price = $cheapest?->priceFor();
        $image = $group->images->first(fn ($image) => $image->image);
        $presented = $image ? static::image($image) : null;

        if ($presented) {
            $presented['thumb'] = $image->image->getThumb(480, 720, ['mode' => 'auto']);
        }

        return [
            'id' => $group->id,
            'code' => $group->code,
            'slug' => $group->trans('slug'),
            'name' => $group->trans('name'),
            'brand' => static::brand($group->brand),
            'color' => static::color($group->color),
            'image' => $presented,
            'price' => Money::toFloat($price?->price),
            'compare_at_price' => Money::toFloat($price?->compare_at_price),
            'currency' => static::currency(),
            'variant_count' => $sellable->count(),
        ];
    }

    /**
     * groupDetail is the product page: variant axes, barcodes and specs of a group
     */
    public static function groupDetail(ProductGroup $group): array
    {
        $products = $group->products->filter(fn ($product) => $product->isSellable())->values();

        $axes = [];
        $variants = [];
        $specRows = [];

        foreach ($products as $product) {
            $options = [];
            foreach ($product->variantOptions() as $value) {
                $spec = $value->spec;
                $axes[$spec->code] ??= [
                    'code' => $spec->code,
                    'name' => $spec->trans('name'),
                    'sort' => [$spec->sort_order, $spec->id],
                    'options' => [],
                ];
                $axes[$spec->code]['options'][$value->option->code] ??= [
                    'id' => $value->option->id,
                    'code' => $value->option->code,
                    'label' => $value->option->trans('label'),
                    'color_hex' => $value->option->color_hex,
                    'sort' => [$value->option->sort_order, $value->option->id],
                ];
                $options[$spec->code] = $value->option->code;
            }

            $price = $product->priceFor();
            $variants[] = [
                'product_id' => $product->id,
                'barcode' => $product->barcode,
                'sku' => $product->sku,
                'options' => (object) $options,
                'price' => Money::toFloat($price->price),
                'compare_at_price' => Money::toFloat($price->compare_at_price),
                'specs' => static::productSpecs($product, false),
            ];

            foreach (static::productSpecs($product, false) as $row) {
                $specRows[$row['code']][] = $row;
            }
        }

        // Specs with the same value on every barcode are shown once for the group
        $commonSpecs = [];
        foreach ($specRows as $code => $rows) {
            if (count($rows) === count($products) && count(array_unique(array_map(fn ($row) => json_encode($row['value']), $rows))) === 1) {
                $commonSpecs[] = $rows[0];
                foreach ($variants as &$variant) {
                    $variant['specs'] = array_values(array_filter($variant['specs'], fn ($row) => $row['code'] !== $code));
                }
                unset($variant);
            }
        }

        $axes = array_values(array_map(function ($axis) {
            $options = array_values($axis['options']);
            usort($options, fn ($a, $b) => $a['sort'] <=> $b['sort']);
            $axis['options'] = array_map(fn ($option) => array_except($option, 'sort'), $options);
            return $axis;
        }, $axes));
        usort($axes, fn ($a, $b) => $a['sort'] <=> $b['sort']);
        $axes = array_map(fn ($axis) => array_except($axis, 'sort'), $axes);

        return [
            'id' => $group->id,
            'code' => $group->code,
            'slug' => $group->trans('slug'),
            'name' => $group->trans('name'),
            'description' => $group->trans('description'),
            'meta_title' => $group->trans('meta_title'),
            'meta_description' => $group->trans('meta_description'),
            'brand' => static::brand($group->brand),
            'color' => static::color($group->color),
            'colors' => static::colors($group),
            'categories' => $group->categories->where('is_active', true)->map(fn ($category) => static::category($category))->values()->all(),
            'breadcrumbs' => static::breadcrumbs($group->primary_category),
            'images' => array_values(array_filter($group->images->map(fn ($image) => static::image($image))->all())),
            'currency' => static::currency(),
            'axes' => $axes,
            'variants' => $variants,
            'specs' => $commonSpecs,
        ];
    }

    /**
     * color
     */
    public static function color(?Color $color): ?array
    {
        if (!$color) {
            return null;
        }

        return [
            'id' => $color->id,
            'name' => $color->trans('name'),
            'hex' => Color::hex($color->hex),
        ];
    }

    /**
     * colors lists the active products that share this common code, one for each color
     */
    protected static function colors(ProductGroup $group): array
    {
        if (!$group->color_id || $group->code === '') {
            return [];
        }

        return ProductGroup::with(['translations', 'color.translations'])
            ->isActive()
            ->where('code', $group->code)
            ->whereNotNull('color_id')
            ->get()
            ->sortBy(fn ($item) => [$item->color->sort_order ?? 0, $item->id])
            ->map(function ($item) {
                $color = static::color($item->color);

                return $color ? $color + ['slug' => $item->trans('slug')] : null;
            })
            ->filter(fn ($item) => !empty($item['slug']))
            ->values()
            ->all();
    }

    /**
     * productSpecs lists the spec values of a barcode, optionally with the variant specs
     */
    public static function productSpecs(Product $product, bool $withVariants = true): array
    {
        return $product->spec_values
            ->filter(fn ($value) => $value->spec && ($withVariants || !$value->spec->is_variant))
            ->sortBy(fn ($value) => [$value->spec->sort_order, $value->spec_id])
            ->map(fn ($value) => [
                'code' => $value->spec->code,
                'name' => $value->spec->trans('name'),
                'value' => $value->rawValue(),
                'display' => $value->displayValue(),
            ])
            ->filter(fn ($row) => $row['value'] !== null)
            ->values()
            ->all();
    }

    /**
     * breadcrumbs from the root to the given category
     */
    public static function breadcrumbs(?Category $category): array
    {
        if (!$category) {
            return [];
        }

        return $category->getParentsAndSelf()
            ->load('translations')
            ->map(fn ($node) => static::category($node))
            ->values()
            ->all();
    }

    /**
     * shippingMethod
     */
    public static function shippingMethod(ShippingMethod $method, ?string $price = null): array
    {
        $rate = $method->rateFor();

        return [
            'id' => $method->id,
            'code' => $method->code,
            'name' => $method->trans('name'),
            'description' => $method->trans('description'),
            'price' => Money::toFloat($price ?? $rate?->price),
            'free_over' => Money::toFloat($rate?->free_over),
            'currency' => static::currency(),
        ];
    }

    /**
     * spec is used by the filters and the admin API
     */
    public static function spec(Spec $spec): array
    {
        return [
            'id' => $spec->id,
            'code' => $spec->code,
            'type' => $spec->type,
            'unit' => $spec->unit,
            'name' => $spec->trans('name'),
            'is_variant' => $spec->is_variant,
            'is_filterable' => $spec->is_filterable,
        ];
    }
}
