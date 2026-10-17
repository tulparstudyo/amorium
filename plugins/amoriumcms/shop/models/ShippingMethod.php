<?php namespace AmoriumCms\Shop\Models;

use Site;
use Model;

/**
 * ShippingMethod has a VAT inclusive price per site and an optional free shipping limit.
 */
class ShippingMethod extends Model
{
    use \AmoriumCms\Shop\Models\Concerns\HasTranslations;
    use \AmoriumCms\Shop\Models\Concerns\BumpsCatalogCache;

    public $table = 'amoriumcms_shop_shipping_methods';

    protected $fillable = ['code', 'is_active', 'sort_order'];

    protected $casts = ['is_active' => 'boolean', 'sort_order' => 'integer'];

    public $hasMany = [
        'translations' => [ShippingMethodTranslation::class, 'key' => 'shipping_method_id', 'delete' => true],
        'rates' => [ShippingMethodRate::class, 'key' => 'shipping_method_id', 'delete' => true],
    ];

    /**
     * scopeAvailable lists the active methods that have a rate on the active site
     */
    public function scopeAvailable($query)
    {
        $siteId = Site::getActiveSite()?->id;

        return $query
            ->where('is_active', true)
            ->whereHas('rates', fn ($q) => $q->where('site_id', $siteId))
            ->with(['translations', 'rates'])
            ->orderBy('sort_order');
    }

    /**
     * rateFor
     */
    public function rateFor(?int $siteId = null): ?ShippingMethodRate
    {
        $siteId = $siteId ?: Site::getActiveSite()?->id;

        return $this->rates->firstWhere('site_id', $siteId);
    }

    /**
     * priceFor returns the shipping price for a VAT inclusive subtotal
     */
    public function priceFor(string $subtotal, ?int $siteId = null): ?string
    {
        if (!$rate = $this->rateFor($siteId)) {
            return null;
        }

        if ($rate->free_over !== null && bccomp($subtotal, (string) $rate->free_over, 2) >= 0) {
            return '0.00';
        }

        return bcadd((string) $rate->price, '0', 2);
    }
}
