<?php namespace AmoriumCms\Shop\Models;

use Model;

/**
 * Category is a node of the category tree. Product groups and spec sets are linked to it.
 */
class Category extends Model
{
    use \October\Rain\Database\Traits\NestedTree;
    use \AmoriumCms\Shop\Models\Concerns\HasTranslations;
    use \AmoriumCms\Shop\Models\Concerns\BumpsCatalogCache;

    public $table = 'amoriumcms_shop_categories';

    protected $fillable = ['parent_id', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public $hasMany = [
        'translations' => [CategoryTranslation::class, 'key' => 'category_id', 'delete' => true],
    ];

    public $belongsToMany = [
        'groups' => [
            ProductGroup::class,
            'table' => 'amoriumcms_shop_category_group',
            'key' => 'category_id',
            'otherKey' => 'group_id',
        ],
        'specs' => [
            Spec::class,
            'table' => 'amoriumcms_shop_category_spec',
            'key' => 'category_id',
            'otherKey' => 'spec_id',
            'pivot' => ['is_required'],
        ],
    ];

    /**
     * scopeIsActive
     */
    public function scopeIsActive($query)
    {
        return $query->where('is_active', true);
    }
}
