<?php namespace Amorium\Business\Models;

use Site;
use Exception;
use System\Models\SettingModel;

/**
 * Business details for the active site.
 *
 * Phone, email, WhatsApp, opening hours and social links are copied to the
 * other sites in the same group. The business name and address stay on the
 * site they were entered for.
 */
class BusinessSetting extends SettingModel
{
    use \October\Rain\Database\Traits\Multisite;
    use \October\Rain\Database\Traits\Validation;

    /**
     * @var string settingsCode
     */
    public $settingsCode = 'amorium_business';

    /**
     * @var string settingsFields
     */
    public $settingsFields = 'fields.yaml';

    /**
     * @var array rules
     */
    public $rules = [
        'email' => 'nullable|email'
    ];

    /**
     * @var array propagatable
     */
    protected $propagatable = [
        'phone',
        'whatsapp',
        'email',
        'hours',
        'social_links'
    ];

    /**
     * @var bool propagatableSync
     */
    protected $propagatableSync = true;

    /**
     * isMultisiteEnabled
     */
    public function isMultisiteEnabled()
    {
        return Site::hasFeature();
    }

    /**
     * beforeValidate stores opening and closing times as hours and minutes.
     */
    public function beforeValidate()
    {
        $hours = $this->hours;

        if (!is_array($hours)) {
            return;
        }

        foreach ($hours as &$row) {
            if (!is_array($row)) {
                continue;
            }

            $row['opens_at'] = $this->normalizeTime($row['opens_at'] ?? null);
            $row['closes_at'] = $this->normalizeTime($row['closes_at'] ?? null);
        }

        $this->hours = $hours;
    }

    /**
     * normalizeTime
     */
    protected function normalizeTime($value)
    {
        if (!$value) {
            return null;
        }

        try {
            return \Carbon\Carbon::parse($value)->format('H:i');
        }
        catch (Exception $ex) {
            return $value;
        }
    }

    /**
     * initSettingsData
     */
    public function initSettingsData()
    {
        $this->business_name = '';
        $this->phone = '';
        $this->whatsapp = '';
        $this->email = '';
        $this->address = '';
        $this->hours = [];
        $this->social_links = [];
    }
}
