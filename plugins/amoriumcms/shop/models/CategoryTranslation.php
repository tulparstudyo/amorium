<?php namespace AmoriumCms\Shop\Models;

class CategoryTranslation extends TranslationModel
{
    public $table = 'amoriumcms_shop_category_translations';

    public function getForeignKeyName(): string
    {
        return 'category_id';
    }
}
