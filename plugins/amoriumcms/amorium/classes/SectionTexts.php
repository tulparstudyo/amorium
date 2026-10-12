<?php namespace AmoriumCms\Amorium\Classes;

use Yaml;
use Throwable;

/**
 * SectionTexts finds the translatable text values of a page section by
 * following the field definition of the section. Repeaters and nested forms
 * are walked recursively, every other value is kept as it is.
 */
class SectionTexts
{
    public const TEXT_TYPES = ['text', 'textarea', 'richeditor', 'markdown'];

    /**
     * fieldsFor returns the field definitions of a section record, including tab fields.
     */
    public static function fieldsFor($section): array
    {
        try {
            $config = Yaml::parse(trim((string) ($section->fields_definition ?? '')));
        }
        catch (Throwable $ex) {
            return [];
        }

        if (!is_array($config)) {
            return [];
        }

        if (!isset($config['fields']) && !isset($config['tabs']) && !isset($config['secondaryTabs'])) {
            return $config;
        }

        return array_merge(
            (array) ($config['fields'] ?? []),
            (array) ($config['tabs']['fields'] ?? []),
            (array) ($config['secondaryTabs']['fields'] ?? [])
        );
    }

    /**
     * texts returns the translatable text values of the data.
     */
    public static function texts(array $data, array $fields): array
    {
        $texts = [];

        static::walk($data, $fields, function (string $text) use (&$texts) {
            $texts[] = $text;
            return $text;
        });

        return $texts;
    }

    /**
     * apply returns the data with its texts replaced from the translation map,
     * or null when a text has no translation.
     */
    public static function apply(array $data, array $fields, array $map): ?array
    {
        $complete = true;

        $data = static::walk($data, $fields, function (string $text) use ($map, &$complete) {
            if (!isset($map[$text])) {
                $complete = false;
                return $text;
            }

            return $map[$text];
        });

        return $complete ? $data : null;
    }

    /**
     * normalized returns the data as JSON with its texts normalized, so that
     * whitespace changes in a text do not count as a change of the section.
     */
    public static function normalized(array $data, array $fields): string
    {
        if (!$data) {
            return '';
        }

        $data = static::walk($data, $fields, fn (string $text) => TextKey::normalize($text));

        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    protected static function walk(array $data, array $fields, callable $callback): array
    {
        foreach ($fields as $name => $definition) {
            if (!is_array($definition) || !array_key_exists($name, $data)) {
                continue;
            }

            if (($definition['translatable'] ?? true) === false) {
                continue;
            }

            $type = $definition['type'] ?? 'text';
            $value = $data[$name];

            if (in_array($type, static::TEXT_TYPES) && is_string($value) && trim($value) !== '') {
                $data[$name] = $callback($value);
            }
            elseif ($type === 'repeater' && is_array($value)) {
                $subFields = static::formFields($definition);
                foreach ($value as $index => $item) {
                    if (is_array($item)) {
                        $data[$name][$index] = static::walk($item, $subFields, $callback);
                    }
                }
            }
            elseif ($type === 'nestedform' && is_array($value)) {
                $data[$name] = static::walk($value, static::formFields($definition), $callback);
            }
        }

        return $data;
    }

    protected static function formFields(array $definition): array
    {
        $form = (array) ($definition['form'] ?? []);

        return (array) ($form['fields'] ?? $form);
    }
}
