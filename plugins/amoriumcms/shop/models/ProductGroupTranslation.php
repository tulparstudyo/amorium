<?php namespace AmoriumCms\Shop\Models;

class ProductGroupTranslation extends TranslationModel
{
    public $table = 'amoriumcms_shop_product_group_translations';

    public function getForeignKeyName(): string
    {
        return 'group_id';
    }
}
