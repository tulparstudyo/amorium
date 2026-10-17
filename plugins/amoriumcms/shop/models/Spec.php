<?php namespace AmoriumCms\Shop\Models;

use Model;

/**
 * Spec is a product property such as color, size or memory. Only "select" specs can be
 * variant axes; the barcodes of a group must differ in their variant options.
 */
class Spec extends Model
{
    use \AmoriumCms\Shop\Models\Concerns\HasTranslations;
    use \AmoriumCms\Shop\Models\Concerns\BumpsCatalogCache;

    const TYPE_SELECT = 'select';
    const TYPE_NUMBER = 'number';
    const TYPE_TEXT = 'text';
    const TYPE_BOOLEAN = 'boolean';

    const TYPES = [self::TYPE_SELECT, self::TYPE_NUMBER, self::TYPE_TEXT, self::TYPE_BOOLEAN];

    public $table = 'amoriumcms_shop_specs';

    protected $fillable = ['code', 'type', 'unit', 'is_variant', 'is_filterable', 'sort_order'];

    protected $casts = [
        'is_variant' => 'boolean',
        'is_filterable' => 'boolean',
        'sort_order' => 'integer',
    ];

    public $hasMany = [
        'translations' => [SpecTranslation::class, 'key' => 'spec_id', 'delete' => true],
        'options' => [SpecOption::class, 'key' => 'spec_id', 'order' => 'sort_order', 'delete' => true],
        'values' => [ProductSpecValue::class, 'key' => 'spec_id', 'delete' => true],
    ];
}
