<?php namespace AmoriumCms\Shop\Models;

class BrandTranslation extends TranslationModel
{
    public $table = 'amoriumcms_shop_brand_translations';

    public function getForeignKeyName(): string
    {
        return 'brand_id';
    }
}
