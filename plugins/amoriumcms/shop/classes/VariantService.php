<?php namespace AmoriumCms\Shop\Classes;

use Db;
use AmoriumCms\Shop\Classes\Api\ApiCode;
use AmoriumCms\Shop\Classes\Api\ApiException;
use AmoriumCms\Shop\Models\Spec;
use AmoriumCms\Shop\Models\Product;

/**
 * VariantService builds the variant key of a barcode from its options on the specs flagged
 * with is_variant. Barcodes of a group must use the same variant specs with different options.
 */
class VariantService
{
    /**
     * keyFor returns "specId:optionId|specId:optionId" for the variant options of a barcode
     *
     * @param array|null $variantSpecIds overrides the flagged specs, used to preview a flag change
     */
    public static function keyFor(Product $product, ?array $variantSpecIds = null): string
    {
        $parts = [];
        foreach (static::optionsOf($product, $variantSpecIds) as $specId => $optionId) {
            $parts[] = "{$specId}:{$optionId}";
        }

        return implode('|', $parts);
    }

    /**
     * axesOf returns the variant spec ids of a barcode
     */
    public static function axesOf(Product $product, ?array $variantSpecIds = null): array
    {
        return array_keys(static::optionsOf($product, $variantSpecIds));
    }

    /**
     * assertValid checks a barcode against the other barcodes of its group and returns its key
     */
    public static function assertValid(Product $product): string
    {
        $product->load('spec_values.spec');

        $key = static::keyFor($product);
        $axes = static::axesOf($product);

        $siblings = Product::with('spec_values.spec')
            ->where('group_id', $product->group_id)
            ->when($product->exists, fn ($q) => $q->where('id', '<>', $product->id))
            ->get();

        foreach ($siblings as $sibling) {
            if (static::axesOf($sibling) !== $axes) {
                throw ApiException::validation(['spec_values' => __('Every product in a group must have values for the same variant specs.')]);
            }

            if (static::keyFor($sibling) === $key) {
                throw ApiException::make(ApiCode::VARIANT_CONFLICT, [
                    'product_id' => $sibling->id,
                    'barcode' => $sibling->barcode,
                ]);
            }
        }

        return $key;
    }

    /**
     * impact lists the groups that would break if the variant flag of a spec changed
     */
    public static function impact(Spec $spec, bool $isVariant): array
    {
        $specIds = static::variantSpecIds($spec, $isVariant);
        $problems = [];

        foreach (static::affectedGroups($spec) as $groupId => $products) {
            $axes = null;
            $keys = [];
            $reason = null;

            foreach ($products as $product) {
                $productAxes = static::axesOf($product, $specIds);
                if ($axes !== null && $productAxes !== $axes) {
                    $reason = 'axes_mismatch';
                    break;
                }

                $axes = $productAxes;
                $key = static::keyFor($product, $specIds);
                if (isset($keys[$key])) {
                    $reason = 'duplicate_variant';
                    break;
                }

                $keys[$key] = true;
            }

            if ($reason) {
                $group = $products->first()->group;
                $problems[] = [
                    'group_id' => $groupId,
                    'code' => $group?->code,
                    'name' => $group?->trans('name'),
                    'reason' => $reason,
                ];
            }
        }

        return $problems;
    }

    /**
     * refreshSpec rewrites the keys of the groups that use a spec, after its flag changed
     */
    public static function refreshSpec(Spec $spec)
    {
        Db::transaction(function () use ($spec) {
            foreach (static::affectedGroups($spec) as $groupId => $products) {
                static::refreshGroup($products);
            }
        });
    }

    /**
     * refreshGroup writes temporary keys first, so swapping keys does not hit the unique index
     */
    public static function refreshGroup($products)
    {
        foreach ($products as $product) {
            Product::where('id', $product->id)->update(['variant_key' => '~' . $product->id]);
        }

        foreach ($products as $product) {
            Product::where('id', $product->id)->update(['variant_key' => static::keyFor($product)]);
        }
    }

    /**
     * optionsOf returns spec id => option id for the variant specs of a barcode
     */
    protected static function optionsOf(Product $product, ?array $variantSpecIds = null): array
    {
        $options = [];

        foreach ($product->spec_values as $value) {
            if (!$value->spec_option_id || !$value->spec || $value->spec->type !== Spec::TYPE_SELECT) {
                continue;
            }

            $isVariant = $variantSpecIds === null
                ? $value->spec->is_variant
                : in_array($value->spec_id, $variantSpecIds);

            if ($isVariant) {
                $options[$value->spec_id] = $value->spec_option_id;
            }
        }

        ksort($options);

        return $options;
    }

    /**
     * variantSpecIds returns the flagged spec ids, with one spec flag replaced
     */
    protected static function variantSpecIds(Spec $spec, bool $isVariant): array
    {
        $ids = Spec::where('is_variant', true)
            ->where('type', Spec::TYPE_SELECT)
            ->where('id', '<>', $spec->id)
            ->pluck('id')
            ->all();

        if ($isVariant && $spec->type === Spec::TYPE_SELECT) {
            $ids[] = $spec->id;
        }

        return $ids;
    }

    /**
     * affectedGroups returns group id => products for every group with a barcode using the spec
     */
    protected static function affectedGroups(Spec $spec)
    {
        $groupIds = Product::whereHas('spec_values', fn ($q) => $q->where('spec_id', $spec->id))
            ->distinct()
            ->pluck('group_id');

        return Product::with(['spec_values.spec', 'group.translations'])
            ->whereIn('group_id', $groupIds)
            ->orderBy('id')
            ->get()
            ->groupBy('group_id');
    }
}
