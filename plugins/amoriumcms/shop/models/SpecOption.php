<?php namespace AmoriumCms\Shop\Models;

use Model;

/**
 * SpecOption is a value of a "select" spec, such as Red or XL.
 */
class SpecOption extends Model
{
    use \AmoriumCms\Shop\Models\Concerns\HasTranslations;
    use \AmoriumCms\Shop\Models\Concerns\BumpsCatalogCache;

    public $table = 'amoriumcms_shop_spec_options';

    public $timestamps = false;

    protected $fillable = ['spec_id', 'code', 'color_hex', 'sort_order'];

    protected $casts = ['sort_order' => 'integer'];

    public $belongsTo = [
        'spec' => Spec::class,
    ];

    public $hasMany = [
        'translations' => [SpecOptionTranslation::class, 'key' => 'spec_option_id', 'delete' => true],
    ];
}
