<?php namespace AmoriumCms\Shop\Models;

class SpecTranslation extends TranslationModel
{
    public $table = 'amoriumcms_shop_spec_translations';

    public function getForeignKeyName(): string
    {
        return 'spec_id';
    }
}
