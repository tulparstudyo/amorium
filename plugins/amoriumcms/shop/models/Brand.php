<?php namespace AmoriumCms\Shop\Models;

use Model;

/**
 * Brand of a product group.
 */
class Brand extends Model
{
    use \AmoriumCms\Shop\Models\Concerns\HasTranslations;
    use \AmoriumCms\Shop\Models\Concerns\BumpsCatalogCache;

    public $table = 'amoriumcms_shop_brands';

    protected $fillable = ['code', 'is_active', 'sort_order'];

    protected $casts = ['is_active' => 'boolean', 'sort_order' => 'integer'];

    public $hasMany = [
        'translations' => [BrandTranslation::class, 'key' => 'brand_id', 'delete' => true],
        'groups' => [ProductGroup::class, 'key' => 'brand_id'],
    ];

    public $attachOne = [
        'logo' => [\System\Models\File::class, 'delete' => true],
    ];

    /**
     * scopeIsActive
     */
    public function scopeIsActive($query)
    {
        return $query->where('is_active', true);
    }
}
