<?php namespace AmoriumCms\Shop\Models;

use Model;

/**
 * ProductSpecValue is the value of one spec on one barcode. The column used depends on the spec type.
 */
class ProductSpecValue extends Model
{
    use \AmoriumCms\Shop\Models\Concerns\BumpsCatalogCache;

    public $table = 'amoriumcms_shop_product_spec_values';

    public $timestamps = false;

    protected $fillable = ['product_id', 'spec_id', 'spec_option_id', 'value_number', 'value_text', 'value_boolean'];

    protected $casts = [
        'value_number' => 'float',
        'value_boolean' => 'boolean',
    ];

    public $belongsTo = [
        'product' => Product::class,
        'spec' => Spec::class,
        'option' => [SpecOption::class, 'key' => 'spec_option_id'],
    ];

    /**
     * displayValue returns the value as text in the active locale
     */
    public function displayValue(): ?string
    {
        return match ($this->spec?->type) {
            Spec::TYPE_SELECT => $this->option?->trans('label') ?: $this->option?->code,
            Spec::TYPE_NUMBER => $this->value_number === null ? null : rtrim(rtrim(number_format($this->value_number, 4, '.', ''), '0'), '.') . ($this->spec->unit ? ' ' . $this->spec->unit : ''),
            Spec::TYPE_BOOLEAN => $this->value_boolean === null ? null : ($this->value_boolean ? __('Yes') : __('No')),
            default => $this->value_text,
        };
    }

    /**
     * rawValue returns the stored value for the API
     */
    public function rawValue()
    {
        return match ($this->spec?->type) {
            Spec::TYPE_SELECT => $this->option?->code,
            Spec::TYPE_NUMBER => $this->value_number,
            Spec::TYPE_BOOLEAN => $this->value_boolean,
            default => $this->value_text,
        };
    }
}
