<?php namespace Amorium\Form\Models;

use Model;
use System\Models\SiteDefinition;

/**
 * Submission is a form posted from a Form section. The "data" column holds
 * every field as label, type and value, in the order defined on the form.
 */
class Submission extends Model
{
    /**
     * @var string table
     */
    public $table = 'amorium_form_submissions';

    /**
     * @var array fillable
     */
    protected $fillable = [
        'site_id',
        'section_id',
        'form_name',
        'data',
        'page_url',
        'ip_address',
        'user_agent'
    ];

    /**
     * @var array jsonable
     */
    protected $jsonable = ['data'];

    /**
     * @var array dates
     */
    protected $dates = ['read_at'];

    /**
     * @var array belongsTo
     */
    public $belongsTo = [
        'site' => SiteDefinition::class
    ];

    /**
     * countUnread is shown next to the Forms menu item.
     */
    public static function countUnread()
    {
        return static::whereNull('read_at')->count() ?: null;
    }

    /**
     * getFormNameOptions lists the form names that have submissions, for the list filter.
     * The count is part of the label because filter labels are passed through the
     * translator, which would otherwise translate names such as "Contact Us".
     */
    public static function getFormNameOptions()
    {
        return static::whereNotNull('form_name')
            ->where('form_name', '<>', '')
            ->groupBy('form_name')
            ->orderBy('form_name')
            ->selectRaw('form_name, count(*) as total')
            ->get()
            ->mapWithKeys(fn ($row) => [$row->form_name => "{$row->form_name} ({$row->total})"])
            ->all();
    }

    /**
     * markAsRead
     */
    public function markAsRead()
    {
        if (!$this->read_at) {
            $this->read_at = $this->freshTimestamp();
            $this->save();
        }
    }

    /**
     * getFields returns label and display value pairs.
     */
    public function getFields(): array
    {
        $fields = [];

        foreach (is_array($this->data) ? $this->data : [] as $row) {
            if (!is_array($row)) {
                continue;
            }

            $fields[] = [
                'label' => (string) ($row['label'] ?? ''),
                'type' => (string) ($row['type'] ?? 'text'),
                'value' => static::formatValue($row['value'] ?? null),
            ];
        }

        return $fields;
    }

    /**
     * formatValue
     */
    public static function formatValue($value): string
    {
        if (is_bool($value)) {
            return $value ? __("Yes") : __("No");
        }

        if (is_array($value)) {
            return implode(', ', array_map('strval', $value));
        }

        return (string) $value;
    }

    public function getField1Attribute()
    {
        return $this->getFieldSummary(0);
    }

    public function getField2Attribute()
    {
        return $this->getFieldSummary(1);
    }

    public function getField3Attribute()
    {
        return $this->getFieldSummary(2);
    }

    /**
     * getFieldSummary returns "Label: value" for the list columns, since
     * every form can have different fields.
     */
    protected function getFieldSummary(int $index): ?string
    {
        $field = $this->getFields()[$index] ?? null;
        if (!$field) {
            return null;
        }

        return $field['label'] . ': ' . str($field['value'])->limit(60);
    }
}
