<?php namespace AmoriumCms\Shop\Models;

use Db;
use Site;
use Model;

/**
 * Product is one barcode. Stock and price belong to the barcode; the stock
 * quantity only changes through the StockService.
 */
class Product extends Model
{
    use \AmoriumCms\Shop\Models\Concerns\BumpsCatalogCache;

    public $table = 'amoriumcms_shop_products';

    protected $fillable = ['group_id', 'barcode', 'sku', 'tax_rate', 'weight', 'is_active', 'sort_order'];

    protected $casts = [
        'is_active' => 'boolean',
        'stock_quantity' => 'integer',
        'sort_order' => 'integer',
    ];

    public $belongsTo = [
        'group' => ProductGroup::class,
    ];

    public $hasMany = [
        'prices' => [ProductPrice::class, 'key' => 'product_id', 'delete' => true],
        'spec_values' => [ProductSpecValue::class, 'key' => 'product_id', 'delete' => true],
    ];

    /**
     * afterDelete removes the barcode from carts and favorites. Orders keep their own copy.
     */
    public function afterDelete()
    {
        CartItem::where('product_id', $this->id)->delete();
        Db::table('amoriumcms_shop_favorites')->where('product_id', $this->id)->delete();
    }

    /**
     * scopeIsActive
     */
    public function scopeIsActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * priceFor returns the price row of the given or active site
     */
    public function priceFor(?int $siteId = null): ?ProductPrice
    {
        $siteId = $siteId ?: Site::getActiveSite()?->id;

        return $this->prices->firstWhere('site_id', $siteId);
    }

    /**
     * isSellable on the given or active site
     */
    public function isSellable(?int $siteId = null): bool
    {
        return $this->is_active
            && $this->group
            && $this->group->is_active
            && $this->priceFor($siteId) !== null;
    }

    /**
     * variantOptions returns the variant spec options of the barcode, ordered by spec
     */
    public function variantOptions()
    {
        return $this->spec_values
            ->filter(fn ($value) => $value->spec && $value->spec->is_variant && $value->option)
            ->sortBy(fn ($value) => [$value->spec->sort_order, $value->spec_id])
            ->values();
    }

    /**
     * variantLabel returns the variant as spec name => option label
     */
    public function variantLabel(?string $locale = null): array
    {
        $label = [];

        foreach ($this->variantOptions() as $value) {
            $label[$value->spec->trans('name', $locale) ?: $value->spec->code] = $value->option->trans('label', $locale) ?: $value->option->code;
        }

        return $label;
    }
}
