<?php namespace AmoriumCms\Shop\Classes;

use AmoriumCms\Shop\Models\Brand;
use AmoriumCms\Shop\Models\Review;
use AmoriumCms\Shop\Models\Category;
use AmoriumCms\Shop\Models\ProductGroup;
use AmoriumCms\Shop\Models\ShippingMethod;

/**
 * Storefront reads the catalog of the active site through the cache. The API and the
 * CMS components share it, so a page and an API answer always show the same data.
 * A missing record is returned as null.
 */
class Storefront
{
    /**
     * products returns a page of product cards: ['data' => [...], 'meta' => [...]]
     */
    public static function products(array $input): array
    {
        $params = CatalogQuery::normalize($input);

        return CatalogCache::remember('products', $params, 3600, function () use ($params) {
            $page = (new CatalogQuery($params))->paginate();

            return [
                'data' => array_map(fn ($group) => Presenter::groupCard($group), $page->items()),
                'meta' => [
                    'page' => $page->currentPage(),
                    'per_page' => $page->perPage(),
                    'total' => $page->total(),
                    'last_page' => $page->lastPage(),
                    'sort' => $params['sort'],
                ],
            ];
        });
    }

    /**
     * filters returns the filter options of a listing
     */
    public static function filters(array $input): array
    {
        $params = array_only(CatalogQuery::normalize($input), ['category', 'brand', 'q']);

        return CatalogCache::remember('filters', $params, 900, function () use ($params) {
            return (new CatalogQuery($params + ['sort' => 'newest', 'page' => 1, 'per_page' => 1]))->facets();
        });
    }

    /**
     * product returns the product page of a group
     */
    public static function product(string $slug): ?array
    {
        return CatalogCache::remember('product', ['slug' => $slug], 3600, function () use ($slug) {
            $group = ProductGroup::findBySlug($slug, fn ($q) => $q->isActive()->with([
                'translations',
                'brand.translations',
                'brand.logo',
                'color.translations',
                'categories.translations',
                'primary_category',
                'images.image',
                'products' => fn ($p) => $p->isActive()->with(['prices', 'spec_values.spec.translations', 'spec_values.option.translations']),
            ]));

            if (!$group) {
                return null;
            }

            // The group relation of each barcode is the group itself
            $group->products->each(fn ($product) => $product->setRelation('group', $group));

            $detail = Presenter::groupDetail($group);

            return $detail['variants'] ? $detail : null;
        });
    }

    /**
     * categories returns the active category tree; a hidden category hides its children
     */
    public static function categories(): array
    {
        return CatalogCache::remember('categories', [], 3600, function () {
            $nodes = Category::with('translations')->orderBy('nest_left')->get();
            $byParent = $nodes->groupBy(fn ($node) => (int) $node->parent_id);

            $build = function ($parentId) use (&$build, $byParent) {
                $result = [];
                foreach ($byParent->get($parentId, collect()) as $node) {
                    if (!$node->is_active) {
                        continue;
                    }

                    $result[] = Presenter::category($node) + ['children' => $build($node->id)];
                }
                return $result;
            };

            return $build(0);
        });
    }

    /**
     * category
     */
    public static function category(string $slug): ?array
    {
        return CatalogCache::remember('category', ['slug' => $slug], 3600, function () use ($slug) {
            $category = Category::findBySlug($slug, fn ($q) => $q->isActive());
            if (!$category || $category->getParents()->contains(fn ($parent) => !$parent->is_active)) {
                return null;
            }

            $children = $category->children()->isActive()->with('translations')->orderBy('nest_left')->get();

            return Presenter::category($category, true) + [
                'breadcrumbs' => Presenter::breadcrumbs($category),
                'children' => $children->map(fn ($child) => Presenter::category($child))->values()->all(),
            ];
        });
    }

    /**
     * brands
     */
    public static function brands(): array
    {
        return CatalogCache::remember('brands', [], 3600, function () {
            return Brand::with(['translations', 'logo'])
                ->isActive()
                ->orderBy('sort_order')
                ->get()
                ->map(fn ($brand) => Presenter::brand($brand))
                ->values()
                ->all();
        });
    }

    /**
     * brand
     */
    public static function brand(string $slug): ?array
    {
        return CatalogCache::remember('brand', ['slug' => $slug], 3600, function () use ($slug) {
            $brand = Brand::findBySlug($slug, fn ($q) => $q->isActive()->with(['translations', 'logo']));

            return $brand ? Presenter::brand($brand, true) : null;
        });
    }

    /**
     * shippingMethods lists the methods with their base price on the active site
     */
    public static function shippingMethods(): array
    {
        return CatalogCache::remember('shipping-methods', [], 3600, function () {
            return ShippingMethod::available()
                ->get()
                ->map(fn ($method) => Presenter::shippingMethod($method))
                ->values()
                ->all();
        });
    }

    /**
     * reviews returns a page of the approved reviews of a group with the rating summary
     */
    public static function reviews(string $slug, int $page = 1, int $perPage = 10): ?array
    {
        $page = max(1, $page);
        $perPage = min(50, max(1, $perPage));

        return CatalogCache::remember('reviews', compact('slug', 'page', 'perPage'), 3600, function () use ($slug, $page, $perPage) {
            $group = ProductGroup::findBySlug($slug, fn ($q) => $q->isActive());
            if (!$group) {
                return null;
            }

            $approved = Review::isApproved()->where('group_id', $group->id);

            $distribution = (clone $approved)->groupBy('rating')->selectRaw('rating, count(*) as total')->pluck('total', 'rating');
            $count = (int) $distribution->sum();

            $paginator = (clone $approved)->with(['user', 'product.spec_values.spec.translations', 'product.spec_values.option.translations'])
                ->orderBy('id', 'desc')
                ->paginate($perPage, ['*'], 'page', $page);

            return [
                'data' => array_map(fn ($review) => static::review($review), $paginator->items()),
                'meta' => [
                    'page' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'last_page' => $paginator->lastPage(),
                    'rating' => [
                        'average' => $count ? round($distribution->map(fn ($total, $rating) => $total * $rating)->sum() / $count, 1) : null,
                        'count' => $count,
                        'distribution' => (object) collect(range(5, 1))->mapWithKeys(fn ($rating) => [$rating => (int) ($distribution[$rating] ?? 0)])->all(),
                    ],
                ],
            ];
        }, CatalogCache::REVIEWS);
    }

    /**
     * review shows the reviewer as first name and last initial
     */
    public static function review(Review $review, bool $own = false): array
    {
        $user = $review->user;
        $name = $user ? trim($user->first_name . ' ' . mb_substr((string) $user->last_name, 0, 1) . ($user->last_name ? '.' : '')) : null;

        $data = [
            'id' => $review->id,
            'rating' => $review->rating,
            'title' => $review->title,
            'body' => $review->body,
            'author' => $name,
            'is_verified_purchase' => (bool) $review->order_item_id,
            'variant' => $review->product ? (object) $review->product->variantLabel() : null,
            'created_at' => $review->created_at?->toIso8601String(),
        ];

        if ($own) {
            $data['status'] = $review->status;
            $data['product'] = [
                'id' => $review->group_id,
                'slug' => $review->group?->trans('slug'),
                'name' => $review->group?->trans('name'),
            ];
        }

        return $data;
    }
}
