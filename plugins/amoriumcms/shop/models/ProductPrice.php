<?php namespace AmoriumCms\Shop\Models;

use Model;

/**
 * ProductPrice is the VAT inclusive price of a barcode on one site.
 */
class ProductPrice extends Model
{
    use \AmoriumCms\Shop\Models\Concerns\BumpsCatalogCache;

    public $table = 'amoriumcms_shop_product_prices';

    public $timestamps = false;

    protected $fillable = ['product_id', 'site_id', 'price', 'compare_at_price'];

    public $belongsTo = [
        'product' => Product::class,
    ];
}
