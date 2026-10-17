<?php namespace AmoriumCms\Shop\Controllers\Api\Admin;

use Db;
use AmoriumCms\Shop\Classes\Money;
use AmoriumCms\Shop\Classes\Api\ApiResponse;
use AmoriumCms\Shop\Models\Cart;
use AmoriumCms\Shop\Models\ShippingMethod;
use AmoriumCms\Shop\Models\ShippingMethodRate;

/**
 * ShippingMethodController manages shipping methods and their VAT inclusive rate on each site.
 * A method without a rate on a site is not offered there.
 */
class ShippingMethodController extends AdminController
{
    /**
     * index
     */
    public function index()
    {
        $methods = ShippingMethod::with(['translations', 'rates'])->orderBy('sort_order')->orderBy('id')->get();

        return ApiResponse::ok($methods->map(fn ($method) => $this->present($method))->values()->all());
    }

    /**
     * show
     */
    public function show(int $id)
    {
        return ApiResponse::ok($this->present($this->findOrFail(ShippingMethod::class, $id, ['translations', 'rates'])));
    }

    /**
     * store
     */
    public function store()
    {
        $method = Db::transaction(function () {
            $method = new ShippingMethod;
            $this->save($method);

            return $method;
        });

        return ApiResponse::created($this->present($method->fresh(['translations', 'rates'])));
    }

    /**
     * update
     */
    public function update(int $id)
    {
        $method = $this->findOrFail(ShippingMethod::class, $id);

        Db::transaction(fn () => $this->save($method));

        return ApiResponse::ok($this->present($method->fresh(['translations', 'rates'])));
    }

    /**
     * destroy. Orders keep the method name, open carts drop the method.
     */
    public function destroy(int $id)
    {
        $method = $this->findOrFail(ShippingMethod::class, $id);

        Db::transaction(function () use ($method) {
            Cart::where('shipping_method_id', $method->id)->whereNull('order_id')->update(['shipping_method_id' => null]);
            $method->delete();
        });

        return ApiResponse::ok();
    }

    /**
     * save. "rates" replaces every rate: [{ site_id, price, free_over }]
     */
    protected function save(ShippingMethod $method)
    {
        $input = $this->validate([
            'code' => ($method->exists ? 'sometimes|' : '') . 'required|string|max:64|alpha_dash|unique:amoriumcms_shop_shipping_methods,code' . ($method->exists ? ",{$method->id}" : ''),
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'rates' => 'array',
            'rates.*.site_id' => 'required|integer|distinct|in:' . implode(',', $this->siteIds()),
            'rates.*.price' => 'required|numeric|min:0',
            'rates.*.free_over' => 'nullable|numeric|min:0',
        ]);

        $translations = $this->translations([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:512',
        ], null, null, !$method->exists);

        $method->fill(array_only($input, ['code', 'is_active', 'sort_order']));
        $method->save();

        if ($translations !== null) {
            $method->saveTranslations($translations);
        }

        if (array_key_exists('rates', $input)) {
            $siteIds = [];

            foreach ($input['rates'] as $rate) {
                ShippingMethodRate::updateOrCreate(
                    ['shipping_method_id' => $method->id, 'site_id' => (int) $rate['site_id']],
                    [
                        'price' => Money::round((string) $rate['price']),
                        'free_over' => isset($rate['free_over']) && $rate['free_over'] !== '' ? Money::round((string) $rate['free_over']) : null,
                    ]
                );
                $siteIds[] = (int) $rate['site_id'];
            }

            ShippingMethodRate::where('shipping_method_id', $method->id)->whereNotIn('site_id', $siteIds ?: [0])->delete();
        }
    }

    /**
     * present
     */
    protected function present(ShippingMethod $method): array
    {
        return [
            'id' => $method->id,
            'code' => $method->code,
            'is_active' => $method->is_active,
            'sort_order' => $method->sort_order,
            'name' => $method->trans('name'),
            'translations' => (object) $method->translationsArray(),
            'rates' => $method->rates->sortBy('site_id')->map(fn ($rate) => [
                'site_id' => $rate->site_id,
                'price' => Money::toFloat($rate->price),
                'free_over' => $rate->free_over === null ? null : Money::toFloat($rate->free_over),
            ])->values()->all(),
        ];
    }
}
