<?php namespace AmoriumCms\Shop\Models;

class ColorTranslation extends TranslationModel
{
    public $table = 'amoriumcms_shop_color_translations';

    public function getForeignKeyName(): string
    {
        return 'color_id';
    }
}
