<?php namespace AmoriumCms\Shop\Models;

class SpecOptionTranslation extends TranslationModel
{
    public $table = 'amoriumcms_shop_spec_option_translations';

    public function getForeignKeyName(): string
    {
        return 'spec_option_id';
    }
}
