<?php namespace AmoriumCms\Shop\Models;

use Model;

/**
 * Address is an entry of the shipping or billing address book of a customer.
 */
class Address extends Model
{
    const TYPE_SHIPPING = 'shipping';
    const TYPE_BILLING = 'billing';

    public $table = 'amoriumcms_shop_addresses';

    protected $fillable = [
        'user_id', 'type', 'title', 'first_name', 'last_name', 'phone', 'country_code', 'city',
        'district', 'address_line', 'postal_code', 'company_name', 'tax_number', 'tax_office', 'is_default',
    ];

    protected $casts = ['is_default' => 'boolean'];

    /**
     * @var array snapshotFields are copied to the order
     */
    const SNAPSHOT_FIELDS = [
        'first_name', 'last_name', 'phone', 'country_code', 'city', 'district',
        'address_line', 'postal_code', 'company_name', 'tax_number', 'tax_office',
    ];

    public $belongsTo = [
        'user' => \RainLab\User\Models\User::class,
    ];

    /**
     * rules are shared by the address book and the guest checkout
     */
    public static function rules(string $prefix = '', bool $billing = false): array
    {
        $rules = [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'phone' => 'required|string|max:32',
            'country_code' => 'nullable|string|size:2',
            'city' => 'required|string|max:255',
            'district' => 'nullable|string|max:255',
            'address_line' => 'required|string|max:512',
            'postal_code' => 'nullable|string|max:16',
            'company_name' => 'nullable|string|max:255',
            'tax_number' => 'nullable|string|max:32',
            'tax_office' => 'nullable|string|max:255',
        ];

        if (!$billing) {
            unset($rules['company_name'], $rules['tax_number'], $rules['tax_office']);
        }

        $result = [];
        foreach ($rules as $field => $rule) {
            $result[$prefix . $field] = $rule;
        }

        return $result;
    }

    /**
     * snapshot returns the fields copied to an order
     */
    public function snapshot(): array
    {
        return array_only($this->attributesToArray(), static::SNAPSHOT_FIELDS);
    }

    /**
     * afterSave keeps one default address per type
     */
    public function afterSave()
    {
        if ($this->is_default) {
            static::where('user_id', $this->user_id)
                ->where('type', $this->type)
                ->where('id', '<>', $this->id)
                ->update(['is_default' => false]);
        }
    }
}
