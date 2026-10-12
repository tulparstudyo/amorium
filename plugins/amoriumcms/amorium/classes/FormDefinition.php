<?php namespace AmoriumCms\Amorium\Classes;

use App;
use Url;
use Site;
use Crypt;
use Request;
use Exception;

/**
 * FormDefinition turns the field repeater of a Form section into the fields
 * rendered on the page, together with an encrypted token that carries the
 * same definition to the submit route. Submissions are validated against the
 * token, never against field names sent by the visitor.
 */
class FormDefinition
{
    public const TYPES = ['text', 'email', 'number', 'textarea', 'select', 'radio', 'checkbox'];

    public const OPTION_TYPES = ['select', 'radio', 'checkbox'];

    /**
     * make is used in section markup as amoriumForm(data, section).
     */
    public static function make($data, $section = null): array
    {
        $data = is_array($data) ? $data : [];
        $section = is_array($section) ? $section : [];
        $fields = static::normalizeFields($data['fields'] ?? []);

        $payload = [
            'section' => $section['id'] ?? null,
            'name' => trim((string) ($data['form_name'] ?? '')) ?: ($section['title'] ?? ''),
            'site' => Site::getSiteIdFromContext(),
            'locale' => App::getLocale(),
            'page' => Request::url(),
            'fields' => array_map(fn ($field) => array_intersect_key($field, array_flip(['name', 'label', 'type', 'options', 'required'])), $fields),
        ];

        return [
            'action' => Url::to('amorium/form/submit'),
            'token' => Crypt::encryptString(json_encode($payload)),
            'csrf' => csrf_token(),
            'fields' => $fields,
        ];
    }

    /**
     * decode returns the payload of a token or null when it is not valid.
     */
    public static function decode($token): ?array
    {
        if (!is_string($token) || $token === '') {
            return null;
        }

        try {
            $payload = json_decode(Crypt::decryptString($token), true);
        }
        catch (Exception $ex) {
            return null;
        }

        return is_array($payload) && isset($payload['fields']) && is_array($payload['fields']) ? $payload : null;
    }

    /**
     * normalizeFields
     */
    protected static function normalizeFields($rows): array
    {
        $fields = [];

        foreach (is_array($rows) ? $rows : [] as $row) {
            if (!is_array($row)) {
                continue;
            }

            $label = trim((string) ($row['label'] ?? ''));
            if ($label === '') {
                continue;
            }

            $type = in_array($row['type'] ?? null, static::TYPES, true) ? $row['type'] : 'text';
            $options = [];

            if (in_array($type, static::OPTION_TYPES, true)) {
                $lines = preg_split('/\r\n|\r|\n/', (string) ($row['options'] ?? ''));
                $options = array_values(array_unique(array_filter(array_map('trim', $lines), 'strlen')));

                if (!$options && $type !== 'checkbox') {
                    continue;
                }
            }

            $fields[] = [
                'name' => 'f' . count($fields),
                'label' => $label,
                'type' => $type,
                'options' => $options,
                'placeholder' => trim((string) ($row['placeholder'] ?? '')),
                'required' => !empty($row['required']),
                'width' => ($row['width'] ?? '') === 'half' ? 'half' : 'full',
            ];
        }

        return $fields;
    }
}
