<?php namespace AmoriumCms\Shop\Models;

class ShippingMethodTranslation extends TranslationModel
{
    public $table = 'amoriumcms_shop_shipping_method_translations';

    public function getForeignKeyName(): string
    {
        return 'shipping_method_id';
    }
}
