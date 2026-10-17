<?php namespace AmoriumCms\Shop\Classes\Import;

use Db;
use Str;
use Site;
use System\Models\File;
use AmoriumCms\Shop\Classes\Money;
use AmoriumCms\Shop\Classes\VariantService;
use AmoriumCms\Shop\Classes\Api\ApiException;
use AmoriumCms\Shop\Models\Spec;
use AmoriumCms\Shop\Models\Brand;
use AmoriumCms\Shop\Models\Color;
use AmoriumCms\Shop\Models\Category;
use AmoriumCms\Shop\Models\Product;
use AmoriumCms\Shop\Models\SpecOption;
use AmoriumCms\Shop\Models\ShopSetting;
use AmoriumCms\Shop\Models\ProductGroup;
use AmoriumCms\Shop\Models\ProductImage;
use AmoriumCms\Shop\Models\ProductPrice;
use AmoriumCms\Shop\Models\SpecTranslation;
use AmoriumCms\Shop\Models\ProductSpecValue;
use AmoriumCms\Shop\Models\BrandTranslation;
use AmoriumCms\Shop\Models\SpecOptionTranslation;
use AmoriumCms\Shop\Models\ProductGroupTranslation;

/**
 * ProductImporter stores a shop-neutral product: the group, barcodes, specs and images.
 * Stock is left untouched. Running the import again updates the same group code.
 */
class ProductImporter
{
    /**
     * @var int languageSiteId is the site whose language receives the imported texts
     */
    protected int $languageSiteId;

    /**
     * import
     */
    public function import(ImportedProduct $product, int $siteId, ?int $categoryId = null): ImportResult
    {
        $this->assertProduct($product);
        $this->languageSiteId = $siteId;
        $category = $this->category($categoryId);
        $images = $this->downloadImages($product->images);
        $warnings = $this->currencyWarning($product);

        return Db::transaction(function () use ($product, $images, $warnings, $category) {
            $brand = $this->brand($product);
            $color = $this->color($product);
            [$group, $created] = $this->group($product, $brand, $category, $color);
            $this->translations($group, $product);

            $specs = $this->specs($product);
            $barcodes = $this->barcodes($product, $group, $specs);
            $this->syncVariants($group);
            $this->images($group, $images);

            return new ImportResult(
                groupId: $group->id,
                code: $group->code,
                name: $product->name,
                created: $created,
                createdBarcodes: $barcodes['created'],
                updatedBarcodes: $barcodes['updated'],
                removedBarcodes: $barcodes['removed'],
                images: count($images),
                warnings: $warnings,
                category: $category?->trans('name')
            );
        });
    }

    /**
     * assertProduct checks the neutral product before anything is downloaded
     */
    protected function assertProduct(ImportedProduct $product): void
    {
        if ($product->name === '' || $product->code === '' || $product->externalId === '') {
            throw new ImportException('The product is missing a name or a code.');
        }

        if (trim((string) $product->color) === '') {
            throw new ImportException(__('The product has no color.'));
        }

        if (!$product->variants) {
            throw new ImportException('The product has no barcodes.');
        }

        $barcodes = [];
        $combinations = [];

        foreach ($product->variants as $variant) {
            $barcode = trim($variant->barcode);

            if ($barcode === '' || strlen($barcode) > 64) {
                throw new ImportException('A barcode is missing or longer than 64 characters.');
            }

            if (isset($barcodes[$barcode])) {
                throw new ImportException("The barcode {$barcode} is repeated.");
            }

            $barcodes[$barcode] = true;
            $combination = $this->combination($variant);

            if (isset($combinations[$combination])) {
                throw new ImportException('Two barcodes have the same variant options.');
            }

            $combinations[$combination] = true;
        }
    }

    /**
     * combination is the variant identity of one barcode
     */
    protected function combination(ImportedVariant $variant): string
    {
        $parts = [];

        foreach ($variant->attributes as $attribute) {
            $parts[$attribute->name] = $attribute->value;
        }

        ksort($parts);

        return json_encode($parts, JSON_UNESCAPED_UNICODE);
    }

    /**
     * downloadImages fetches the pictures before the database transaction starts
     *
     * @param string[] $urls
     * @return array<int, array{bytes: string, name: string}>
     */
    protected function downloadImages(array $urls): array
    {
        $images = [];

        foreach (array_values($urls) as $index => $url) {
            $response = (new RemoteGet)->fetch($url, 'image/avif,image/webp,image/apng,image/*,*/*;q=0.8');
            $type = $response->mediaType;

            if (!$response->successful() || !str_starts_with($type, 'image/')) {
                throw new ImportException("The product image could not be downloaded: {$url}");
            }

            $extension = [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp',
                'image/gif' => 'gif',
            ][$type] ?? 'jpg';

            $images[] = [
                'bytes' => $response->body,
                'name' => 'product-' . ($index + 1) . '.' . $extension,
            ];
        }

        return $images;
    }

    /**
     * currencyWarning
     *
     * @return string[]
     */
    protected function currencyWarning(ImportedProduct $product): array
    {
        if (!$product->currency) {
            return [];
        }

        $warnings = [];

        foreach (Site::listEnabled() as $site) {
            $currency = Site::withContext($site->id, fn () => ShopSetting::value('currency'));

            if (strtoupper((string) $currency) !== $product->currency) {
                $warnings[] = __('The source price is in :currency and :site uses :site_currency. The number was stored as given.', [
                    'currency' => $product->currency,
                    'site' => $site->name,
                    'site_currency' => $currency,
                ]);
            }
        }

        return $warnings;
    }

    /**
     * brand finds or creates the brand by its name
     */
    protected function brand(ImportedProduct $product): ?Brand
    {
        $name = trim((string) $product->brand);

        if ($name === '') {
            return null;
        }

        $brand = Brand::whereHas('translations', fn ($query) => $query->where('name', $name))->first();

        if ($brand) {
            return $brand;
        }

        $brand = new Brand;
        $brand->code = $this->uniqueCode(Brand::class, $this->code($name));
        $brand->is_active = true;
        $brand->save();

        foreach (array_keys($this->locales($product)) as $locale) {
            BrandTranslation::create([
                'brand_id' => $brand->id,
                'locale' => $locale,
                'name' => $name,
                'slug' => $this->uniqueSlug(BrandTranslation::class, $locale, $name),
            ]);
        }

        return $brand;
    }

    /**
     * group finds the previous import of this color, or opens a new group.
     * The common code is shared by the other colors of the product.
     *
     * @return array{0: ProductGroup, 1: bool}
     */
    protected function group(ImportedProduct $product, ?Brand $brand, ?Category $category, Color $color): array
    {
        $group = ProductGroup::where('code', $product->code)->where('color_id', $color->id)->first();

        if (!$group) {
            $barcodes = array_values(array_filter(array_map(fn ($variant) => trim($variant->barcode), $product->variants)));
            $existing = $barcodes ? Product::with('group')->whereIn('barcode', $barcodes)->first() : null;

            if ($existing?->group && (!$existing->group->color_id || (int) $existing->group->color_id === (int) $color->id)) {
                $group = $existing->group;
            }
        }

        $group = $group ?: new ProductGroup;
        $created = !$group->exists;

        $group->code = $product->code;
        $group->color_id = $color->id;

        if ($brand) {
            $group->brand_id = $brand->id;
        }

        if ($category) {
            $group->primary_category_id = $category->id;
        }

        if ($created) {
            $group->is_active = true;
        }

        $group->save();

        if ($category) {
            $group->categories()->syncWithoutDetaching([$category->id]);
        }

        return [$group, $created];
    }

    /**
     * category
     */
    protected function category(?int $categoryId): ?Category
    {
        if (!$categoryId) {
            return null;
        }

        $category = Category::with('translations')->find($categoryId);

        if (!$category) {
            throw new ImportException(__('The category was not found.'));
        }

        return $category;
    }

    /**
     * color finds or creates the fixed color of the product
     */
    protected function color(ImportedProduct $product): Color
    {
        $name = trim((string) $product->color);

        if ($name === '') {
            throw new ImportException(__('The product has no color.'));
        }

        return Color::ensure($name, null, array_keys($this->locales($product)));
    }

    /**
     * translations writes the name in the language of the selected site.
     * An existing slug and description are kept.
     */
    protected function translations(ProductGroup $group, ImportedProduct $product): void
    {
        foreach ($this->locales($product) as $locale => $name) {
            $row = ProductGroupTranslation::where('group_id', $group->id)->where('locale', $locale)->first();

            if ($row) {
                $row->name = $name;
                $row->save();
                continue;
            }

            ProductGroupTranslation::create([
                'group_id' => $group->id,
                'locale' => $locale,
                'name' => $name,
                'slug' => $this->uniqueSlug(ProductGroupTranslation::class, $locale, $name, $group->id, 'group_id'),
            ]);
        }
    }

    /**
     * specs returns attribute name => spec, creating missing specs and options.
     * An attribute that differs between barcodes becomes a variant spec.
     *
     * @return array<string, Spec>
     */
    protected function specs(ImportedProduct $product): array
    {
        $definitions = [];

        foreach ($product->attributes as $attribute) {
            if (Color::isLabel($attribute->name)) {
                continue;
            }

            $definitions[$attribute->name]['values'][$attribute->value] = true;
            $definitions[$attribute->name]['variant'] = false;
        }

        foreach ($product->variants as $variant) {
            foreach ($variant->attributes as $attribute) {
                if (Color::isLabel($attribute->name)) {
                    continue;
                }

                $definitions[$attribute->name]['values'][$attribute->value] = true;
                $definitions[$attribute->name]['variant'] = true;
            }
        }

        $specs = [];

        foreach ($definitions as $name => $definition) {
            $spec = $this->spec($name, (bool) $definition['variant'], $product);

            foreach (array_keys($definition['values']) as $value) {
                $this->option($spec, (string) $value, $product);
            }

            $specs[$name] = $spec;
        }

        return $specs;
    }

    /**
     * spec finds a spec by name, or creates a select spec
     */
    protected function spec(string $name, bool $variant, ImportedProduct $product): Spec
    {
        $spec = Spec::whereHas('translations', fn ($query) => $query->where('name', $name))->first()
            ?: Spec::where('code', $this->code($name))->first();

        if (!$spec) {
            $spec = new Spec;
            $spec->code = $this->uniqueCode(Spec::class, $this->code($name));
            $spec->type = Spec::TYPE_SELECT;
            $spec->is_variant = $variant;
            $spec->is_filterable = $variant;
            $spec->sort_order = ((int) Spec::max('sort_order')) + 1;
            $spec->save();
        } elseif ($variant && !$spec->is_variant) {
            $this->promoteVariant($spec);
        } elseif ($variant && $spec->type !== Spec::TYPE_SELECT) {
            throw new ImportException("The spec {$spec->code} has to be a choice list to be used as a variant.");
        }

        foreach (array_keys($this->locales($product)) as $locale) {
            SpecTranslation::firstOrCreate(
                ['spec_id' => $spec->id, 'locale' => $locale],
                ['name' => $name]
            );
        }

        return $spec;
    }

    /**
     * promoteVariant turns a choice spec into a variant axis when no other product would break
     */
    protected function promoteVariant(Spec $spec): void
    {
        if ($spec->type !== Spec::TYPE_SELECT) {
            throw new ImportException("The spec {$spec->code} has to be a choice list to be used as a variant.");
        }

        $problems = VariantService::impact($spec, true);

        if ($problems) {
            $codes = implode(', ', array_filter(array_column($problems, 'code')));

            throw new ImportException("The spec {$spec->code} cannot become a variant because these products would break: {$codes}");
        }

        $spec->is_variant = true;
        $spec->save();
        VariantService::refreshSpec($spec);
    }

    /**
     * option finds or creates a choice of a select spec. Other spec types store the text directly.
     */
    protected function option(Spec $spec, string $value, ImportedProduct $product): ?SpecOption
    {
        if ($spec->type !== Spec::TYPE_SELECT) {
            return null;
        }

        $spec->load('options.translations');
        $value = mb_substr($value, 0, 255);

        $option = $spec->options->first(function ($option) use ($value) {
            return $option->translations->contains(fn ($row) => $row->label === $value);
        });

        if (!$option) {
            $code = $this->code($value);
            $taken = $spec->options->firstWhere('code', $code);

            if ($taken) {
                $code = substr($code, 0, 54) . '_' . substr(md5($value), 0, 8);
            }

            $option = new SpecOption;
            $option->spec_id = $spec->id;
            $option->code = $this->fit($code, 64);
            $option->sort_order = ((int) $spec->options->max('sort_order')) + 1;
            $option->save();
            $spec->options->push($option);
        }

        foreach (array_keys($this->locales($product)) as $locale) {
            SpecOptionTranslation::firstOrCreate(
                ['spec_option_id' => $option->id, 'locale' => $locale],
                ['label' => $value]
            );
        }

        return $option;
    }

    /**
     * barcodes creates or updates the barcodes and replaces their spec values and the price on every site.
     * Stock and the active flag of an existing barcode stay as they are.
     *
     * @param array<string, Spec> $specs
     * @return array{created: int, updated: int, removed: int}
     */
    protected function barcodes(ImportedProduct $product, ProductGroup $group, array $specs): array
    {
        $keep = [];
        $created = 0;
        $updated = 0;

        foreach ($product->variants as $variant) {
            $barcode = trim($variant->barcode);
            $existing = Product::where('barcode', $barcode)->first();

            if ($existing && (int) $existing->group_id !== (int) $group->id) {
                throw new ImportException("The barcode {$barcode} already belongs to another product.");
            }

            $record = $existing ?: new Product;

            if (!$record->exists) {
                $record->variant_key = '~' . Str::random(20);
                $record->is_active = true;
                $created++;
            } else {
                $updated++;
            }

            $record->group_id = $group->id;
            $record->barcode = $barcode;
            $record->sku = $variant->sku !== null && $variant->sku !== '' ? $this->fit($variant->sku, 64) : null;
            $record->tax_rate = $product->taxRate;
            $record->sort_order = $variant->sortOrder;
            $record->save();

            $this->values($record, $this->valuesOf($product, $variant), $specs, $product);
            $this->price($record, $variant);
            $keep[] = $barcode;
        }

        $removed = Product::where('group_id', $group->id)->whereNotIn('barcode', $keep)->get();
        $removed->each->delete();

        return [
            'created' => $created,
            'updated' => $updated,
            'removed' => $removed->count(),
        ];
    }

    /**
     * valuesOf merges the shared properties with the barcode's own options
     *
     * @return ImportedAttribute[]
     */
    protected function valuesOf(ImportedProduct $product, ImportedVariant $variant): array
    {
        $values = [];

        foreach ($variant->attributes as $attribute) {
            if (!Color::isLabel($attribute->name)) {
                $values[$attribute->name] = $attribute;
            }
        }

        foreach ($product->attributes as $attribute) {
            if (!Color::isLabel($attribute->name) && !isset($values[$attribute->name])) {
                $values[$attribute->name] = $attribute;
            }
        }

        return array_values($values);
    }

    /**
     * values replaces the spec values of one barcode
     *
     * @param ImportedAttribute[] $attributes
     * @param array<string, Spec> $specs
     */
    protected function values(Product $record, array $attributes, array $specs, ImportedProduct $product): void
    {
        ProductSpecValue::where('product_id', $record->id)->get()->each->delete();

        foreach ($attributes as $attribute) {
            $spec = $specs[$attribute->name];
            $row = [
                'spec_option_id' => null,
                'value_number' => null,
                'value_text' => null,
                'value_boolean' => null,
            ];

            if ($spec->type === Spec::TYPE_SELECT) {
                $option = $this->option($spec, $attribute->value, $product);

                if (!$option) {
                    throw new ImportException("The option {$attribute->value} could not be stored on {$spec->code}.");
                }

                $row['spec_option_id'] = $option->id;
            } elseif ($spec->type === Spec::TYPE_NUMBER && is_numeric($attribute->value)) {
                $row['value_number'] = $attribute->value;
            } elseif ($spec->type === Spec::TYPE_BOOLEAN) {
                $row['value_boolean'] = filter_var($attribute->value, FILTER_VALIDATE_BOOLEAN);
            } else {
                $row['value_text'] = mb_substr($attribute->value, 0, 255);
            }

            ProductSpecValue::create($row + [
                'product_id' => $record->id,
                'spec_id' => $spec->id,
            ]);
        }
    }

    /**
     * price writes the same imported price on every site. A site can change its own price later.
     */
    protected function price(Product $record, ImportedVariant $variant): void
    {
        $siteIds = Site::listEnabled()->pluck('id')->map(fn ($id) => (int) $id)->all();

        if (!$siteIds) {
            throw new ImportException(__('The site was not found.'));
        }

        if ($variant->price === null || $variant->price === '') {
            ProductPrice::where('product_id', $record->id)->whereIn('site_id', $siteIds)->get()->each->delete();
            return;
        }

        $price = Money::round($variant->price);
        $compareAt = $variant->compareAtPrice;

        if ($compareAt !== null && Money::compare($compareAt, $price) <= 0) {
            $compareAt = null;
        }

        foreach ($siteIds as $siteId) {
            ProductPrice::updateOrCreate(
                ['product_id' => $record->id, 'site_id' => $siteId],
                ['price' => $price, 'compare_at_price' => $compareAt]
            );
        }
    }

    /**
     * syncVariants checks that the barcodes of the group differ and writes their variant keys
     */
    protected function syncVariants(ProductGroup $group): void
    {
        $products = Product::with('spec_values.spec')->where('group_id', $group->id)->orderBy('id')->get();
        $first = $products->first();

        if (!$first) {
            throw new ImportException('The product has no barcodes.');
        }

        try {
            VariantService::assertValid($first);
        } catch (ApiException $exception) {
            throw new ImportException($this->apiMessage($exception));
        }

        VariantService::refreshGroup($products);
    }

    /**
     * images replaces the pictures of the group
     *
     * @param array<int, array{bytes: string, name: string}> $images
     */
    protected function images(ProductGroup $group, array $images): void
    {
        ProductImage::where('group_id', $group->id)->get()->each->delete();

        foreach ($images as $index => $image) {
            $record = new ProductImage;
            $record->group_id = $group->id;
            $record->sort_order = $index;
            $record->save();

            $file = new File;
            $file->fromData($image['bytes'], $image['name']);
            $record->image()->add($file);
        }
    }

    /**
     * locales is the language of the selected site. The imported texts are saved only there.
     *
     * @return array<string, string>
     */
    protected function locales(ImportedProduct $product): array
    {
        $locale = Site::getSiteFromId($this->languageSiteId)?->hard_locale
            ?: Site::getPrimarySite()?->hard_locale
            ?: ($product->locale ?: 'tr');

        return [$locale => $product->name];
    }

    /**
     * uniqueSlug keeps the locale slug unique. An existing row of the same record is ignored.
     */
    protected function uniqueSlug(string $model, string $locale, string $name, ?int $ownerId = null, ?string $ownerColumn = null): string
    {
        $base = Str::slug($name) ?: 'item';
        $slug = $base;
        $i = 2;

        while ($model::where('locale', $locale)->where('slug', $slug)
            ->when($ownerId && $ownerColumn, fn ($query) => $query->where($ownerColumn, '<>', $ownerId))
            ->exists()) {
            $suffix = '-' . $i++;
            $slug = substr($base, 0, 255 - strlen($suffix)) . $suffix;
        }

        return $slug;
    }

    /**
     * uniqueCode
     */
    protected function uniqueCode(string $model, string $base): string
    {
        $base = $base !== '' ? $base : 'item';
        $code = $this->fit($base, 64);
        $i = 2;

        while ($model::where('code', $code)->exists()) {
            $suffix = '_' . $i++;
            $code = $this->fit($base, 64 - strlen($suffix)) . $suffix;
        }

        return $code;
    }

    /**
     * code is a short ascii handle
     */
    protected function code(string $value): string
    {
        $code = Str::slug($value, '_');

        return $this->fit($code !== '' ? $code : 'item', 64);
    }

    /**
     * fit
     */
    protected function fit(string $value, int $length): string
    {
        return substr($value, 0, $length);
    }

    /**
     * apiMessage
     */
    protected function apiMessage(ApiException $exception): string
    {
        $messages = [];

        foreach ($exception->errors ?? [] as $field) {
            foreach ((array) $field as $message) {
                $messages[] = $message;
            }
        }

        return $messages ? implode(' ', $messages) : $exception->getMessage();
    }
}
