<?php namespace AmoriumCms\Shop\Controllers\Api\Admin;

use Db;
use Str;
use Request;
use AmoriumCms\Shop\Classes\Money;
use AmoriumCms\Shop\Classes\StockStatus;
use AmoriumCms\Shop\Classes\StockService;
use AmoriumCms\Shop\Classes\VariantService;
use AmoriumCms\Shop\Classes\Api\ApiResponse;
use AmoriumCms\Shop\Classes\Api\ApiException;
use AmoriumCms\Shop\Models\Spec;
use AmoriumCms\Shop\Models\Product;
use AmoriumCms\Shop\Models\ShopSetting;
use AmoriumCms\Shop\Models\ProductGroup;
use AmoriumCms\Shop\Models\ProductPrice;
use AmoriumCms\Shop\Models\StockMovement;
use AmoriumCms\Shop\Models\ProductSpecValue;

/**
 * ProductController manages barcodes: spec values, prices per site and stock.
 * A barcode created without a group gets a group of its own.
 */
class ProductController extends AdminController
{
    /**
     * index. Filters: q, group_id, is_active, stock=out|low, missing_price_site_id
     */
    public function index()
    {
        $threshold = (int) ShopSetting::value('low_stock_threshold');

        $paginator = Product::with(['group.translations', 'prices', 'spec_values.spec.translations', 'spec_values.option.translations'])
            ->when(Request::query('q'), fn ($q, $term) => $q->where(fn ($q) => $q
                ->where('barcode', 'like', "%{$term}%")
                ->orWhere('sku', 'like', "%{$term}%")
                ->orWhereHas('group', fn ($q) => $q->where('code', 'like', "%{$term}%"))
                ->orWhereHas('group.translations', fn ($q) => $q->where('name', 'like', "%{$term}%"))))
            ->when(Request::query('group_id'), fn ($q, $id) => $q->where('group_id', $id))
            ->when(Request::has('is_active'), fn ($q) => $q->where('is_active', Request::boolean('is_active')))
            ->when(Request::query('stock') === 'out', fn ($q) => $q->where('stock_quantity', '<=', 0))
            ->when(Request::query('stock') === 'low', fn ($q) => $q->where('stock_quantity', '>', 0)->where('stock_quantity', '<', $threshold))
            ->when(Request::query('missing_price_site_id'), fn ($q, $siteId) => $q->whereDoesntHave('prices', fn ($q) => $q->where('site_id', $siteId)))
            ->orderBy('id', 'desc')
            ->paginate($this->perPage());

        return ApiResponse::paginated($paginator, fn ($product) => $this->present($product));
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
        $input = $this->validateProduct(null);

        $product = Db::transaction(function () use ($input) {
            $groupId = $input['group_id'] ?? null;

            if (!$groupId) {
                $groupInput = (array) Request::input('group', []);
                $groupInput['code'] = ($groupInput['code'] ?? '') !== '' ? $groupInput['code'] : $input['barcode'];
                $groupId = (new ProductGroupController)->save(new ProductGroup, $groupInput, 'group.')->id;
            }

            $product = new Product;
            $product->fill(array_only($input, ['barcode', 'sku', 'tax_rate', 'weight', 'is_active', 'sort_order']));
            $product->group_id = $groupId;
            $product->variant_key = '~' . Str::random(32);
            $product->save();

            $this->saveDetails($product, $input);

            if (!empty($input['stock_quantity'])) {
                StockService::adjust($product->id, (int) $input['stock_quantity'], $this->backendUserId(), __('Initial stock'));
            }

            return $product;
        });

        return ApiResponse::created($this->present($this->load($product->id)));
    }

    /**
     * update. Moving a barcode to another group is allowed when its variant fits there.
     */
    public function update(int $id)
    {
        $product = $this->findOrFail(Product::class, $id);
        $input = $this->validateProduct($product);

        Db::transaction(function () use ($product, $input) {
            $product->fill(array_only($input, ['group_id', 'barcode', 'sku', 'tax_rate', 'weight', 'is_active', 'sort_order']));
            $product->save();

            $this->saveDetails($product, $input);
        });

        return ApiResponse::ok($this->present($this->load($product->id)));
    }

    /**
     * destroy. A group left without barcodes stays, so it can get new ones.
     */
    public function destroy(int $id)
    {
        $product = $this->findOrFail(Product::class, $id);

        Db::transaction(fn () => $product->delete());

        return ApiResponse::ok();
    }

    /**
     * bulkPrices sets the prices of many barcodes on one site:
     * { site_id, items: [{ barcode | product_id, price, compare_at_price }] }
     */
    public function bulkPrices()
    {
        $input = $this->validate([
            'site_id' => 'required|integer|in:' . implode(',', $this->siteIds()),
            'items' => 'required|array|max:1000',
            'items.*.product_id' => 'nullable|integer',
            'items.*.barcode' => 'nullable|string|max:64',
            'items.*.price' => 'nullable|numeric|min:0',
            'items.*.compare_at_price' => 'nullable|numeric|min:0',
        ]);

        $updated = 0;
        $notFound = [];

        Db::transaction(function () use ($input, &$updated, &$notFound) {
            foreach ($input['items'] as $item) {
                $product = !empty($item['product_id'])
                    ? Product::find($item['product_id'])
                    : (!empty($item['barcode']) ? Product::where('barcode', $item['barcode'])->first() : null);

                if (!$product) {
                    $notFound[] = $item['barcode'] ?? $item['product_id'] ?? null;
                    continue;
                }

                $this->savePrice($product, (int) $input['site_id'], $item['price'] ?? null, $item['compare_at_price'] ?? null);
                $updated++;
            }
        });

        return ApiResponse::ok([
            'updated' => $updated,
            'not_found' => array_values(array_filter($notFound, fn ($value) => $value !== null)),
        ]);
    }

    /**
     * adjustStock adds or removes stock by hand: { quantity_change, note }
     */
    public function adjustStock(int $id)
    {
        $product = $this->findOrFail(Product::class, $id);

        $input = $this->validate([
            'quantity_change' => 'required|integer|not_in:0|between:-1000000,1000000',
            'note' => 'nullable|string|max:255',
        ]);

        $stock = StockService::adjust($product->id, (int) $input['quantity_change'], $this->backendUserId(), $input['note'] ?? null);

        return ApiResponse::ok([
            'stock_quantity' => $stock,
            'stock_status' => StockStatus::forQuantity($stock),
        ]);
    }

    /**
     * stockMovements lists the stock history of a barcode
     */
    public function stockMovements(int $id)
    {
        $product = $this->findOrFail(Product::class, $id);

        $paginator = StockMovement::with(['backend_user', 'order'])
            ->where('product_id', $product->id)
            ->orderBy('id', 'desc')
            ->paginate($this->perPage(50));

        return ApiResponse::paginated($paginator, fn ($movement) => [
            'id' => $movement->id,
            'quantity_change' => $movement->quantity_change,
            'stock_after' => $movement->stock_after,
            'reason' => $movement->reason,
            'order' => $movement->order ? ['id' => $movement->order->id, 'number' => $movement->order->number] : null,
            'backend_user' => $movement->backend_user?->full_name,
            'note' => $movement->note,
            'created_at' => $movement->created_at?->toIso8601String(),
        ]);
    }

    /**
     * validateProduct
     */
    protected function validateProduct(?Product $product): array
    {
        $exists = $product !== null;

        $rules = [
            'group_id' => ($exists ? 'sometimes|required|' : 'nullable|') . 'integer|exists:amoriumcms_shop_product_groups,id',
            'barcode' => ($exists ? 'sometimes|' : '') . 'required|string|max:64|unique:amoriumcms_shop_products,barcode' . ($exists ? ",{$product->id}" : ''),
            'sku' => 'nullable|string|max:64',
            'tax_rate' => 'numeric|min:0|max:100',
            'weight' => 'nullable|numeric|min:0',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'prices' => 'array',
            'prices.*.site_id' => 'required|integer|in:' . implode(',', $this->siteIds()),
            'prices.*.price' => 'nullable|numeric|min:0',
            'prices.*.compare_at_price' => 'nullable|numeric|min:0',
            'spec_values' => 'array',
            'spec_values.*.spec_id' => 'required|integer|distinct|exists:amoriumcms_shop_specs,id',
            'spec_values.*.value' => 'nullable',
        ];

        if (!$exists) {
            $rules['stock_quantity'] = 'nullable|integer|min:0|max:1000000';
            $rules['group'] = 'required_without:group_id|array';
        }

        return $this->validate($rules);
    }

    /**
     * saveDetails stores prices and spec values, then checks the variant and the required specs
     */
    protected function saveDetails(Product $product, array $input)
    {
        foreach ($input['prices'] ?? [] as $price) {
            $this->savePrice($product, (int) $price['site_id'], $price['price'] ?? null, $price['compare_at_price'] ?? null);
        }

        if (array_key_exists('spec_values', $input)) {
            $this->saveSpecValues($product, $input['spec_values']);
        }

        $this->assertRequiredSpecs($product);

        $product->unsetRelation('spec_values');
        $key = VariantService::assertValid($product);
        Product::where('id', $product->id)->update(['variant_key' => $key]);
    }

    /**
     * savePrice removes the price when it is empty
     */
    protected function savePrice(Product $product, int $siteId, $price, $compareAt)
    {
        if ($price === null || $price === '') {
            ProductPrice::where('product_id', $product->id)->where('site_id', $siteId)->get()->each->delete();
            return;
        }

        $price = Money::round((string) $price);
        $compareAt = $compareAt === null || $compareAt === '' ? null : Money::round((string) $compareAt);

        if ($compareAt !== null && Money::compare($compareAt, $price) <= 0) {
            throw ApiException::validation(['prices' => __('The old price must be higher than the price.')]);
        }

        ProductPrice::updateOrCreate(
            ['product_id' => $product->id, 'site_id' => $siteId],
            ['price' => $price, 'compare_at_price' => $compareAt]
        );
    }

    /**
     * saveSpecValues replaces the spec values of a barcode. The value is an option id
     * for select specs; an empty value removes the spec.
     */
    protected function saveSpecValues(Product $product, array $items)
    {
        $specs = Spec::with('options')->whereIn('id', array_column($items, 'spec_id'))->get()->keyBy('id');
        $keep = [];
        $errors = [];

        foreach ($items as $index => $item) {
            $spec = $specs->get($item['spec_id']);
            $value = $item['value'] ?? null;

            if ($value === null || $value === '') {
                continue;
            }

            $row = ['spec_option_id' => null, 'value_number' => null, 'value_text' => null, 'value_boolean' => null];

            switch ($spec->type) {
                case Spec::TYPE_SELECT:
                    if (!$spec->options->firstWhere('id', (int) $value)) {
                        $errors["spec_values.{$index}.value"] = __('The option does not belong to this spec.');
                        continue 2;
                    }
                    $row['spec_option_id'] = (int) $value;
                    break;

                case Spec::TYPE_NUMBER:
                    if (!is_numeric($value)) {
                        $errors["spec_values.{$index}.value"] = __('The value must be a number.');
                        continue 2;
                    }
                    $row['value_number'] = $value;
                    break;

                case Spec::TYPE_BOOLEAN:
                    $bool = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                    if ($bool === null) {
                        $errors["spec_values.{$index}.value"] = __('The value must be yes or no.');
                        continue 2;
                    }
                    $row['value_boolean'] = $bool;
                    break;

                default:
                    if (!is_scalar($value) || mb_strlen((string) $value) > 255) {
                        $errors["spec_values.{$index}.value"] = __('The value may not be longer than 255 characters.');
                        continue 2;
                    }
                    $row['value_text'] = (string) $value;
            }

            ProductSpecValue::updateOrCreate(['product_id' => $product->id, 'spec_id' => $spec->id], $row);
            $keep[] = $spec->id;
        }

        if ($errors) {
            throw ApiException::validation($errors);
        }

        ProductSpecValue::where('product_id', $product->id)->whereNotIn('spec_id', $keep ?: [0])->get()->each->delete();
    }

    /**
     * assertRequiredSpecs checks the specs marked as required on the categories of the group
     * and on their parent categories
     */
    protected function assertRequiredSpecs(Product $product)
    {
        $group = ProductGroup::with('categories')->find($product->group_id);

        $categoryIds = [];
        foreach ($group->categories as $category) {
            foreach ($category->getParentsAndSelf() as $node) {
                $categoryIds[] = $node->id;
            }
        }

        if (!$categoryIds) {
            return;
        }

        $required = Db::table('amoriumcms_shop_category_spec')
            ->whereIn('category_id', array_unique($categoryIds))
            ->where('is_required', true)
            ->pluck('spec_id')
            ->unique();

        $missing = $required->diff(ProductSpecValue::where('product_id', $product->id)->pluck('spec_id'));

        if ($missing->isNotEmpty()) {
            $names = Spec::with('translations')->whereIn('id', $missing)->get()->map(fn ($spec) => $spec->trans('name') ?: $spec->code)->implode(', ');

            throw ApiException::validation(['spec_values' => __('These specs are required by the categories: :specs', ['specs' => $names])]);
        }
    }

    /**
     * load
     */
    protected function load(int $id): Product
    {
        return $this->findOrFail(Product::class, $id, [
            'group.translations',
            'prices',
            'spec_values.spec.translations',
            'spec_values.option.translations',
        ]);
    }

    /**
     * present shows the real stock quantity, which only the admin API does
     */
    protected function present(Product $product): array
    {
        return [
            'id' => $product->id,
            'group' => $product->group ? [
                'id' => $product->group->id,
                'code' => $product->group->code,
                'name' => $product->group->trans('name'),
            ] : null,
            'barcode' => $product->barcode,
            'sku' => $product->sku,
            'variant' => (object) $product->variantLabel(),
            'tax_rate' => (float) $product->tax_rate,
            'weight' => $product->weight === null ? null : (float) $product->weight,
            'is_active' => $product->is_active,
            'sort_order' => $product->sort_order,
            'stock_quantity' => $product->stock_quantity,
            'stock_status' => StockStatus::forQuantity((int) $product->stock_quantity),
            'prices' => $product->prices->sortBy('site_id')->map(fn ($price) => [
                'site_id' => $price->site_id,
                'price' => Money::toFloat($price->price),
                'compare_at_price' => $price->compare_at_price === null ? null : Money::toFloat($price->compare_at_price),
            ])->values()->all(),
            'spec_values' => $product->spec_values->map(fn ($value) => [
                'spec_id' => $value->spec_id,
                'code' => $value->spec?->code,
                'type' => $value->spec?->type,
                'is_variant' => (bool) $value->spec?->is_variant,
                'value' => $value->spec?->type === Spec::TYPE_SELECT ? $value->spec_option_id : $value->rawValue(),
                'display' => $value->displayValue(),
            ])->values()->all(),
            'created_at' => $product->created_at?->toIso8601String(),
            'updated_at' => $product->updated_at?->toIso8601String(),
        ];
    }
}
