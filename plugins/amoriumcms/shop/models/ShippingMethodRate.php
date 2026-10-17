<?php namespace AmoriumCms\Shop\Models;

use Model;

/**
 * ShippingMethodRate is the price of a shipping method on one site.
 */
class ShippingMethodRate extends Model
{
    use \AmoriumCms\Shop\Models\Concerns\BumpsCatalogCache;

    public $table = 'amoriumcms_shop_shipping_method_rates';

    public $timestamps = false;

    protected $fillable = ['shipping_method_id', 'site_id', 'price', 'free_over'];
}
