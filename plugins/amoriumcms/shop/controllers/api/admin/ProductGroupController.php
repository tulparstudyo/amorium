<?php namespace AmoriumCms\Shop\Controllers\Api\Admin;

use Db;
use Request;
use AmoriumCms\Shop\Classes\Money;
use AmoriumCms\Shop\Classes\Api\ApiCode;
use AmoriumCms\Shop\Classes\Api\ApiResponse;
use AmoriumCms\Shop\Classes\Api\ApiException;
use AmoriumCms\Shop\Models\SpecOption;
use AmoriumCms\Shop\Models\ProductGroup;
use AmoriumCms\Shop\Models\ProductImage;
use AmoriumCms\Shop\Models\ProductGroupTranslation;

/**
 * ProductGroupController manages the product pages: texts, brand, categories and images.
 * The barcodes of a group are managed by the ProductController.
 */
class ProductGroupController extends AdminController
{
    /**
     * index
     */
    public function index()
    {
        $paginator = ProductGroup::with(['translations', 'brand.translations', 'color.translations', 'primary_category.translations', 'images.image'])
            ->withCount('products')
            ->searchAdmin(Request::query('q'))
            ->when(Request::query('brand_id'), fn ($q, $id) => $q->where('brand_id', $id))
            ->when(Request::query('category_id'), fn ($q, $id) => $q->whereHas('categories', fn ($q) => $q->where('id', $id)))
            ->when(Request::has('is_active'), fn ($q) => $q->where('is_active', Request::boolean('is_active')))
            ->orderBy('id', 'desc')
            ->paginate($this->perPage());

        return ApiResponse::paginated($paginator, fn ($group) => $this->presentSummary($group));
    }

    /**
     * show
     */
    public function show(int $id)
    {
        return ApiResponse::ok($this->present($this->load($id)));
    }

    /**
     * store
     */
    public function store()
    {
        $group = Db::transaction(fn () => $this->save(new ProductGroup, Request::all()));

        return ApiResponse::created($this->present($this->load($group->id)));
    }

    /**
     * update
     */
    public function update(int $id)
    {
        $group = $this->findOrFail(ProductGroup::class, $id);

        Db::transaction(fn () => $this->save($group, Request::all()));

        return ApiResponse::ok($this->present($this->load($group->id)));
    }

    /**
     * destroy removes the group with its barcodes, images and reviews.
     * Orders keep their own copy of the products.
     */
    public function destroy(int $id)
    {
        $group = $this->findOrFail(ProductGroup::class, $id);

        Db::transaction(fn () => $group->delete());

        return ApiResponse::ok();
    }

    /**
     * storeImage uploads an image as multipart form data
     */
    public function storeImage(int $id)
    {
        $group = $this->findOrFail(ProductGroup::class, $id);

        $input = $this->validate([
            'image' => 'required|image|max:8192',
            'spec_option_id' => 'nullable|integer|exists:amoriumcms_shop_spec_options,id',
            'sort_order' => 'nullable|integer',
        ]);

        $image = new ProductImage;
        $image->group_id = $group->id;
        $image->spec_option_id = $input['spec_option_id'] ?? null;
        $image->sort_order = $input['sort_order'] ?? ((int) $group->images()->max('sort_order') + 1);
        $image->image = Request::file('image');
        $image->save();

        return ApiResponse::created($this->presentImage($image->fresh(['image'])));
    }

    /**
     * updateImage changes the option and order of an image
     */
    public function updateImage(int $id, int $imageId)
    {
        $image = $this->findImage($id, $imageId);

        $input = $this->validate([
            'spec_option_id' => 'nullable|integer|exists:amoriumcms_shop_spec_options,id',
            'sort_order' => 'integer',
        ]);

        $image->fill($input);
        $image->save();

        return ApiResponse::ok($this->presentImage($image->fresh(['image'])));
    }

    /**
     * destroyImage
     */
    public function destroyImage(int $id, int $imageId)
    {
        $image = $this->findImage($id, $imageId);

        $image->delete();

        return ApiResponse::ok();
    }

    /**
     * save validates and stores a group. Used by the ProductController too, when a barcode
     * is created without a group. Errors are reported under the given prefix.
     */
    public function save(ProductGroup $group, array $input, string $prefix = ''): ProductGroup
    {
        $validator = \Validator::make($input, [
            'code' => ($group->exists ? 'sometimes|' : '') . 'required|string|max:64|alpha_dash',
            'brand_id' => 'nullable|integer|exists:amoriumcms_shop_brands,id',
            'color_id' => ($group->exists ? 'sometimes|' : '') . 'required|integer|exists:amoriumcms_shop_colors,id',
            'primary_category_id' => 'nullable|integer|exists:amoriumcms_shop_categories,id',
            'category_ids' => 'array',
            'category_ids.*' => 'integer|exists:amoriumcms_shop_categories,id',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            $errors = [];
            foreach ($validator->errors()->toArray() as $field => $messages) {
                $errors[$prefix . $field] = $messages;
            }
            throw ApiException::validation($errors);
        }

        $data = $validator->validated();
        $code = $data['code'] ?? $group->code;
        $colorId = $data['color_id'] ?? $group->color_id;
        $taken = ProductGroup::where('code', $code)->where('color_id', $colorId)
            ->when($group->exists, fn ($query) => $query->where('id', '<>', $group->id))
            ->exists();

        if ($taken) {
            throw ApiException::validation([$prefix . 'code' => __('This code is already used for this color.')]);
        }

        $translations = $this->withInput($input, fn () => $this->translations([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255',
            'description' => 'nullable|string',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:512',
        ], ProductGroupTranslation::class, $group->id, !$group->exists), $prefix);

        $group->fill(array_only($data, ['code', 'brand_id', 'color_id', 'primary_category_id', 'is_active']));
        $group->save();

        if ($translations !== null) {
            $group->saveTranslations($translations);
        }

        if (array_key_exists('category_ids', $data) || array_key_exists('primary_category_id', $data)) {
            $categoryIds = array_key_exists('category_ids', $data)
                ? $data['category_ids']
                : $group->categories()->pluck('id')->all();

            if ($group->primary_category_id) {
                $categoryIds[] = $group->primary_category_id;
            }

            $group->categories()->sync(array_values(array_unique(array_map('intval', $categoryIds))));
        }

        return $group;
    }

    /**
     * withInput runs the translations validation on another input array and prefixes its errors
     */
    protected function withInput(array $input, callable $callback, string $prefix)
    {
        $request = Request::instance();
        $original = $request->all();

        if ($prefix !== '') {
            $request->replace($input);
        }

        try {
            return $callback();
        }
        catch (ApiException $ex) {
            if ($prefix !== '' && $ex->errors) {
                $ex->errors = collect($ex->errors)->mapWithKeys(fn ($messages, $field) => [$prefix . $field => $messages])->all();
            }
            throw $ex;
        }
        finally {
            if ($prefix !== '') {
                $request->replace($original);
            }
        }
    }

    /**
     * load
     */
    protected function load(int $id): ProductGroup
    {
        return $this->findOrFail(ProductGroup::class, $id, [
            'translations',
            'brand.translations',
            'color.translations',
            'primary_category.translations',
            'categories.translations',
            'images.image',
            'images.option.translations',
            'products.prices',
            'products.spec_values.spec.translations',
            'products.spec_values.option.translations',
        ]);
    }

    /**
     * findImage
     */
    protected function findImage(int $groupId, int $imageId): ProductImage
    {
        $image = ProductImage::with('image')->where('group_id', $groupId)->find($imageId);
        if (!$image) {
            throw ApiException::make(ApiCode::NOT_FOUND);
        }

        return $image;
    }

    /**
     * presentSummary for lists
     */
    protected function presentSummary(ProductGroup $group): array
    {
        $image = $group->images->first();

        return [
            'id' => $group->id,
            'code' => $group->code,
            'name' => $group->trans('name'),
            'is_active' => $group->is_active,
            'brand' => $group->brand ? ['id' => $group->brand->id, 'name' => $group->brand->trans('name')] : null,
            'color' => $group->color ? ['id' => $group->color->id, 'name' => $group->color->trans('name'), 'hex' => $group->color->hex] : null,
            'primary_category' => $group->primary_category ? ['id' => $group->primary_category->id, 'name' => $group->primary_category->trans('name')] : null,
            'thumb' => $image?->image ? $image->image->getThumb(160, 160, ['mode' => 'crop']) : null,
            'products_count' => $group->products_count ?? $group->products()->count(),
            'updated_at' => $group->updated_at?->toIso8601String(),
        ];
    }

    /**
     * present
     */
    protected function present(ProductGroup $group): array
    {
        return array_merge($this->presentSummary($group), [
            'brand_id' => $group->brand_id,
            'color_id' => $group->color_id,
            'primary_category_id' => $group->primary_category_id,
            'category_ids' => $group->categories->pluck('id')->all(),
            'translations' => (object) $group->translationsArray(),
            'images' => $group->images->map(fn ($image) => $this->presentImage($image))->values()->all(),
            'products' => $group->products->map(fn ($product) => [
                'id' => $product->id,
                'barcode' => $product->barcode,
                'sku' => $product->sku,
                'variant' => (object) $product->variantLabel(),
                'stock_quantity' => $product->stock_quantity,
                'is_active' => $product->is_active,
                'prices' => $product->prices->map(fn ($price) => [
                    'site_id' => $price->site_id,
                    'price' => Money::toFloat($price->price),
                ])->values()->all(),
            ])->values()->all(),
        ]);
    }

    /**
     * presentImage
     */
    protected function presentImage(ProductImage $image): array
    {
        return [
            'id' => $image->id,
            'spec_option_id' => $image->spec_option_id,
            'option' => $image->option instanceof SpecOption ? $image->option->trans('label') : null,
            'sort_order' => (int) $image->sort_order,
            'url' => $image->image?->getPath(),
            'thumb' => $image->image?->getThumb(240, 240, ['mode' => 'crop']),
        ];
    }
}
