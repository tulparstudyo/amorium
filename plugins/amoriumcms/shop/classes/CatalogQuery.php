<?php namespace AmoriumCms\Shop\Classes;

use Db;
use App;
use Site;
use AmoriumCms\Shop\Models\Spec;
use AmoriumCms\Shop\Models\Brand;
use AmoriumCms\Shop\Models\Category;
use AmoriumCms\Shop\Models\ProductGroup;
use AmoriumCms\Shop\Models\ProductPrice;
use AmoriumCms\Shop\Models\ProductGroupTranslation;

/**
 * CatalogQuery lists the product groups of the active site. A group is listed when at least
 * one of its barcodes is active, has a price on the site and matches every filter.
 *
 * Parameters: category, brand, q, price_min, price_max, sort, filter[spec_code]
 * - select spec: filter[color]=red,blue
 * - number spec: filter[ram][min]=8&filter[ram][max]=32
 * - boolean spec: filter[wifi]=1
 */
class CatalogQuery
{
    const SORTS = ['newest', 'price_asc', 'price_desc', 'name'];

    /**
     * @var array params
     */
    protected $params;

    /**
     * @var int siteId
     */
    protected $siteId;

    public function __construct(array $params)
    {
        $this->params = $params;
        $this->siteId = (int) Site::getActiveSite()?->id;
    }

    /**
     * normalize keeps the known parameters in a fixed form, so equal queries share a cache key
     */
    public static function normalize(array $input): array
    {
        $params = [];

        foreach (['category', 'brand', 'q'] as $key) {
            if (isset($input[$key]) && is_string($input[$key]) && trim($input[$key]) !== '') {
                $params[$key] = mb_substr(trim($input[$key]), 0, 100);
            }
        }

        foreach (['price_min', 'price_max'] as $key) {
            if (isset($input[$key]) && is_numeric($input[$key])) {
                $params[$key] = (float) $input[$key];
            }
        }

        $params['sort'] = in_array($input['sort'] ?? null, static::SORTS, true) ? $input['sort'] : 'newest';
        $params['page'] = max(1, (int) ($input['page'] ?? 1));
        $params['per_page'] = min(48, max(1, (int) ($input['per_page'] ?? 24)));

        $filters = [];
        foreach ((array) ($input['filter'] ?? []) as $code => $value) {
            if (!is_string($code) || !preg_match('/^[a-z0-9_-]{1,64}$/i', $code)) {
                continue;
            }

            if (is_array($value)) {
                $range = array_filter(array_only($value, ['min', 'max']), 'is_numeric');
                if ($range) {
                    ksort($range);
                    $filters[$code] = array_map('floatval', $range);
                }
            }
            elseif (is_string($value) && $value !== '') {
                $options = array_filter(array_map('trim', explode(',', $value)), 'strlen');
                sort($options);
                $filters[$code] = array_values(array_unique($options));
            }
        }

        ksort($filters);
        if ($filters) {
            $params['filter'] = $filters;
        }

        return $params;
    }

    /**
     * paginate
     */
    public function paginate()
    {
        $query = $this->groupQuery();
        $this->applyFilters($query);
        $this->applySort($query);

        return $query
            ->with(['translations', 'brand.translations', 'color.translations', 'images.image', 'products' => fn ($q) => $q->isActive()->with('prices')])
            ->paginate($this->params['per_page'], ['*'], 'page', $this->params['page']);
    }

    /**
     * facets returns the filter options of the listing, with the number of groups for each.
     * Counts use the category, brand and search only, not the other filters.
     */
    public function facets(): array
    {
        $groupIds = $this->groupQuery()->pluck('amoriumcms_shop_product_groups.id')->all();

        $result = [
            'price' => ['min' => null, 'max' => null],
            'brands' => [],
            'specs' => [],
        ];

        if (!$groupIds) {
            return $result;
        }

        $price = ProductPrice::join('amoriumcms_shop_products as p', 'p.id', '=', 'amoriumcms_shop_product_prices.product_id')
            ->whereIn('p.group_id', $groupIds)
            ->where('p.is_active', true)
            ->where('amoriumcms_shop_product_prices.site_id', $this->siteId)
            ->selectRaw('min(price) as min_price, max(price) as max_price')
            ->first();

        $result['price'] = [
            'min' => Money::toFloat($price?->min_price),
            'max' => Money::toFloat($price?->max_price),
        ];

        $brandCounts = ProductGroup::whereIn('id', $groupIds)
            ->whereNotNull('brand_id')
            ->groupBy('brand_id')
            ->selectRaw('brand_id, count(*) as total')
            ->pluck('total', 'brand_id');

        foreach (Brand::with('translations')->isActive()->whereIn('id', $brandCounts->keys())->orderBy('sort_order')->get() as $brand) {
            $result['brands'][] = [
                'slug' => $brand->trans('slug'),
                'name' => $brand->trans('name'),
                'count' => (int) $brandCounts[$brand->id],
            ];
        }

        foreach (Spec::with(['translations', 'options.translations'])->where('is_filterable', true)->orderBy('sort_order')->get() as $spec) {
            if ($facet = $this->specFacet($spec, $groupIds)) {
                $result['specs'][] = $facet;
            }
        }

        return $result;
    }

    /**
     * groupQuery applies the site, category, brand and search conditions
     */
    protected function groupQuery()
    {
        $query = ProductGroup::query()
            ->select('amoriumcms_shop_product_groups.*')
            ->where('amoriumcms_shop_product_groups.is_active', true)
            ->whereExists(function ($q) {
                $this->sellableProducts($q);
            });

        if ($slug = $this->params['category'] ?? null) {
            $category = Category::findBySlug($slug, fn ($q) => $q->isActive());
            $ids = $category ? $category->getAllChildrenAndSelf()->where('is_active', true)->pluck('id')->all() : [0];

            $query->whereExists(function ($q) use ($ids) {
                $q->from('amoriumcms_shop_category_group as cg')
                    ->whereColumn('cg.group_id', 'amoriumcms_shop_product_groups.id')
                    ->whereIn('cg.category_id', $ids);
            });
        }

        if ($slug = $this->params['brand'] ?? null) {
            $brand = Brand::findBySlug($slug, fn ($q) => $q->isActive());
            $query->where('brand_id', $brand?->id ?: 0);
        }

        if ($search = $this->params['q'] ?? null) {
            $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $search) . '%';

            $query->where(function ($q) use ($like, $search) {
                $q->whereExists(function ($t) use ($like) {
                    $t->from('amoriumcms_shop_product_group_translations as t')
                        ->whereColumn('t.group_id', 'amoriumcms_shop_product_groups.id')
                        ->where('t.name', 'like', $like);
                })
                ->orWhere('amoriumcms_shop_product_groups.code', $search)
                ->orWhereExists(function ($p) use ($search) {
                    $p->from('amoriumcms_shop_products as sp')
                        ->whereColumn('sp.group_id', 'amoriumcms_shop_product_groups.id')
                        ->where(fn ($w) => $w->where('sp.barcode', $search)->orWhere('sp.sku', $search));
                });
            });
        }

        return $query;
    }

    /**
     * applyFilters narrows the sellable barcodes by price and spec filters
     */
    protected function applyFilters($query)
    {
        $filters = $this->params['filter'] ?? [];
        $hasPrice = isset($this->params['price_min']) || isset($this->params['price_max']);

        if (!$filters && !$hasPrice) {
            return;
        }

        $specs = Spec::with('options')->whereIn('code', array_keys($filters))->get()->keyBy('code');

        $query->whereExists(function ($q) use ($filters, $specs) {
            $this->sellableProducts($q);

            if (isset($this->params['price_min'])) {
                $q->where('pp.price', '>=', $this->params['price_min']);
            }
            if (isset($this->params['price_max'])) {
                $q->where('pp.price', '<=', $this->params['price_max']);
            }

            foreach ($filters as $code => $value) {
                $spec = $specs->get($code);
                if (!$spec) {
                    $q->whereRaw('1 = 0');
                    continue;
                }

                $q->whereExists(function ($v) use ($spec, $value) {
                    $v->from('amoriumcms_shop_product_spec_values as sv')
                        ->whereColumn('sv.product_id', 'p.id')
                        ->where('sv.spec_id', $spec->id);

                    match ($spec->type) {
                        Spec::TYPE_SELECT => $v->whereIn('sv.spec_option_id', $spec->options->whereIn('code', (array) $value)->pluck('id')->all() ?: [0]),
                        Spec::TYPE_NUMBER => $v
                            ->when(isset($value['min']), fn ($w) => $w->where('sv.value_number', '>=', $value['min']))
                            ->when(isset($value['max']), fn ($w) => $w->where('sv.value_number', '<=', $value['max'])),
                        Spec::TYPE_BOOLEAN => $v->where('sv.value_boolean', in_array('1', (array) $value, true)),
                        default => $v->whereIn('sv.value_text', (array) $value),
                    };
                });
            }
        });
    }

    /**
     * applySort
     */
    protected function applySort($query)
    {
        $sort = $this->params['sort'];

        if ($sort === 'price_asc' || $sort === 'price_desc') {
            $query->addSelect(['min_price' => function ($q) {
                $q->from('amoriumcms_shop_products as p')
                    ->join('amoriumcms_shop_product_prices as pp', 'pp.product_id', '=', 'p.id')
                    ->whereColumn('p.group_id', 'amoriumcms_shop_product_groups.id')
                    ->where('p.is_active', true)
                    ->where('pp.site_id', $this->siteId)
                    ->selectRaw('min(pp.price)');
            }]);

            $query->orderBy('min_price', $sort === 'price_asc' ? 'asc' : 'desc');
        }
        elseif ($sort === 'name') {
            $query->addSelect(['sort_name' => ProductGroupTranslation::select('name')
                ->whereColumn('group_id', 'amoriumcms_shop_product_groups.id')
                ->whereIn('locale', [App::getLocale(), App::getFallbackLocale()])
                ->orderByRaw('locale = ? desc', [App::getLocale()])
                ->limit(1)
            ]);

            $query->orderBy('sort_name');
        }

        $query->orderBy('amoriumcms_shop_product_groups.id', 'desc');
    }

    /**
     * sellableProducts adds the barcode conditions to a "where exists" query, aliased as p and pp
     */
    protected function sellableProducts($q)
    {
        $q->from('amoriumcms_shop_products as p')
            ->join('amoriumcms_shop_product_prices as pp', 'pp.product_id', '=', 'p.id')
            ->whereColumn('p.group_id', 'amoriumcms_shop_product_groups.id')
            ->where('p.is_active', true)
            ->where('pp.site_id', $this->siteId)
            ->select(Db::raw(1));
    }

    /**
     * specFacet
     */
    protected function specFacet(Spec $spec, array $groupIds): ?array
    {
        $base = Db::table('amoriumcms_shop_product_spec_values as sv')
            ->join('amoriumcms_shop_products as p', 'p.id', '=', 'sv.product_id')
            ->whereIn('p.group_id', $groupIds)
            ->where('p.is_active', true)
            ->where('sv.spec_id', $spec->id);

        $facet = [
            'code' => $spec->code,
            'name' => $spec->trans('name'),
            'type' => $spec->type,
            'unit' => $spec->unit,
        ];

        if ($spec->type === Spec::TYPE_SELECT) {
            $counts = (clone $base)
                ->whereNotNull('sv.spec_option_id')
                ->groupBy('sv.spec_option_id')
                ->selectRaw('sv.spec_option_id, count(distinct p.group_id) as total')
                ->pluck('total', 'spec_option_id');

            $facet['options'] = $spec->options
                ->filter(fn ($option) => isset($counts[$option->id]))
                ->map(fn ($option) => [
                    'code' => $option->code,
                    'label' => $option->trans('label'),
                    'color_hex' => $option->color_hex,
                    'count' => (int) $counts[$option->id],
                ])
                ->values()
                ->all();

            return $facet['options'] ? $facet : null;
        }

        if ($spec->type === Spec::TYPE_NUMBER) {
            $range = (clone $base)->selectRaw('min(sv.value_number) as min_value, max(sv.value_number) as max_value')->first();
            if ($range->min_value === null) {
                return null;
            }

            $facet['min'] = (float) $range->min_value;
            $facet['max'] = (float) $range->max_value;

            return $facet;
        }

        if ($spec->type === Spec::TYPE_BOOLEAN) {
            $count = (clone $base)->where('sv.value_boolean', true)->distinct()->count('p.group_id');

            $facet['count'] = $count;

            return $count ? $facet : null;
        }

        return null;
    }
}
