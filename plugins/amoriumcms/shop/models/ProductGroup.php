<?php namespace AmoriumCms\Shop\Models;

use Model;

/**
 * ProductGroup joins the barcodes that share a common code. The product page,
 * categories, brand, images and reviews belong to the group.
 */
class ProductGroup extends Model
{
    use \AmoriumCms\Shop\Models\Concerns\HasTranslations;
    use \AmoriumCms\Shop\Models\Concerns\BumpsCatalogCache;

    public $table = 'amoriumcms_shop_product_groups';

    protected $fillable = ['code', 'brand_id', 'color_id', 'primary_category_id', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public $belongsTo = [
        'brand' => Brand::class,
        'color' => Color::class,
        'primary_category' => Category::class,
    ];

    public $hasMany = [
        'translations' => [ProductGroupTranslation::class, 'key' => 'group_id', 'delete' => true],
        'products' => [Product::class, 'key' => 'group_id', 'order' => 'sort_order', 'delete' => true],
        'images' => [ProductImage::class, 'key' => 'group_id', 'order' => 'sort_order', 'delete' => true],
        'reviews' => [Review::class, 'key' => 'group_id', 'delete' => true],
    ];

    public $belongsToMany = [
        'categories' => [
            Category::class,
            'table' => 'amoriumcms_shop_category_group',
            'key' => 'group_id',
            'otherKey' => 'category_id',
        ],
    ];

    /**
     * scopeIsActive
     */
    public function scopeIsActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * scopeSearchAdmin matches the common code, a name in any locale, or an exact barcode or SKU
     */
    public function scopeSearchAdmin($query, ?string $term)
    {
        $term = trim((string) $term);
        if ($term === '') {
            return $query;
        }

        return $query->where(fn ($q) => $q
            ->where('code', 'like', "%{$term}%")
            ->orWhereHas('translations', fn ($q) => $q->where('name', 'like', "%{$term}%"))
            ->orWhereHas('products', fn ($q) => $q->where('barcode', $term)->orWhere('sku', $term)));
    }

    /**
     * getBrandFilterOptions for the backend list filter
     */
    public function getBrandFilterOptions(): array
    {
        return Brand::with('translations')->orderBy('sort_order')->get()
            ->mapWithKeys(fn ($brand) => [$brand->id => $brand->trans('name') ?: $brand->code])
            ->all();
    }

    /**
     * getCategoryFilterOptions for the backend list filter, indented by depth
     */
    public function getCategoryFilterOptions(): array
    {
        return Category::with('translations')->orderBy('nest_left')->get()
            ->mapWithKeys(fn ($category) => [
                $category->id => str_repeat('— ', (int) $category->nest_depth) . ($category->trans('name') ?: '#' . $category->id),
            ])
            ->all();
    }

    /**
     * afterDelete
     */
    public function afterDelete()
    {
        $this->categories()->detach();
    }
}
