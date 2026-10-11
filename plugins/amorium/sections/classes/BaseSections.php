<?php namespace Amorium\Sections\Classes;

use File;
use Tailor\Models\EntryRecord;

/**
 * BaseSections keeps the default sections in a JSON file so they can be
 * created again on a fresh install or after they were deleted.
 */
class BaseSections
{
    public const HANDLE = 'Amorium\Section';

    public const ATTRIBUTES = ['title', 'slug', 'html', 'fields_definition', 'css', 'js'];

    /**
     * path of the JSON file
     */
    public static function path(): string
    {
        return plugins_path('amorium/sections/data/base-sections.json');
    }

    /**
     * export writes the current sections to the JSON file and returns their count.
     */
    public static function export(): int
    {
        $sections = EntryRecord::inSection(static::HANDLE)
            ->orderBy('id')
            ->get()
            ->map(fn ($record) => array_map(
                fn ($value) => str_replace("\r\n", "\n", (string) $value),
                $record->only(static::ATTRIBUTES)
            ))
            ->values()
            ->all();

        File::makeDirectory(dirname(static::path()), 0755, true, true);
        File::put(static::path(), json_encode($sections, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");

        return count($sections);
    }

    /**
     * create adds the sections from the JSON file whose slug is not in use
     * and returns the titles of the created and skipped sections.
     */
    public static function create(): array
    {
        $result = ['created' => [], 'skipped' => []];

        foreach (static::load() as $data) {
            $record = EntryRecord::inSection(static::HANDLE)->where('slug', $data['slug'])->withTrashed()->first();

            if ($record && !$record->trashed()) {
                $result['skipped'][] = $record->title;
                continue;
            }

            if ($record) {
                $record->restore();
            }
            else {
                $record = EntryRecord::inSection(static::HANDLE);
            }

            foreach (static::ATTRIBUTES as $attribute) {
                $record->{$attribute} = $data[$attribute] ?? '';
            }

            $record->is_enabled = true;
            $record->save();

            $result['created'][] = $record->title;
        }

        return $result;
    }

    /**
     * load
     */
    protected static function load(): array
    {
        if (!File::exists(static::path())) {
            return [];
        }

        $sections = json_decode(File::get(static::path()), true);

        return array_values(array_filter(
            is_array($sections) ? $sections : [],
            fn ($data) => is_array($data) && !empty($data['slug']) && !empty($data['title'])
        ));
    }
}
