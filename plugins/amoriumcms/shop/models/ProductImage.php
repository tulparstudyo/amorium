<?php namespace AmoriumCms\Shop\Models;

use Model;

/**
 * ProductImage belongs to a group. When it is bound to a spec option (usually a color)
 * it is shown only for the barcodes that have that option.
 */
class ProductImage extends Model
{
    use \AmoriumCms\Shop\Models\Concerns\BumpsCatalogCache;

    public $table = 'amoriumcms_shop_product_images';

    protected $fillable = ['group_id', 'spec_option_id', 'sort_order'];

    public $belongsTo = [
        'group' => ProductGroup::class,
        'option' => [SpecOption::class, 'key' => 'spec_option_id'],
    ];

    public $attachOne = [
        'image' => [\System\Models\File::class, 'delete' => true],
    ];
}
