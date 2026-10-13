<?php namespace AmoriumCms\Amorium\Classes;

use Event;
use Tailor\Models\EntryRecord;

/**
 * PageSlugs keeps the full slug of the pages of every site up to date. Tailor
 * only updates it for the record saved in the editor, not for the rows of the
 * other sites that are created, translated or moved by code.
 */
class PageSlugs
{
    public const HANDLE = 'Amorium\Page';

    public const BLUEPRINT_UUID = 'amorium_page';

    public static function boot(): void
    {
        EntryRecord::extend(function ($model) {
            $model->bindEvent('model.saveComplete', function () use ($model) {
                if ($model->blueprint_uuid === static::BLUEPRINT_UUID) {
                    static::sync();
                }
            });
        });

        Event::listen('backend.list.reorderStructure', function ($widget, $record) {
            if ($record instanceof EntryRecord && $record->blueprint_uuid === static::BLUEPRINT_UUID) {
                static::sync();
            }
        });
    }

    /**
     * sync writes the full slug built from the parents of every page row of
     * every site and returns the number of updated rows.
     */
    public static function sync(): int
    {
        $query = fn () => EntryRecord::inSection(static::HANDLE)->newQueryWithoutScopes();

        $rows = $query()->where('is_version', false)->get(['id', 'parent_id', 'slug', 'fullslug'])->keyBy('id');

        $updated = 0;
        foreach ($rows as $row) {
            $fullslug = static::fullslug($row, $rows);
            if ($fullslug !== '' && $row->fullslug !== $fullslug) {
                $query()->where('id', $row->id)->update(['fullslug' => $fullslug]);
                $updated++;
            }
        }

        return $updated;
    }

    protected static function fullslug($row, $rows): string
    {
        $slugs = [];
        $visited = [];

        while ($row && !isset($visited[$row->id])) {
            $visited[$row->id] = true;
            array_unshift($slugs, (string) $row->slug);
            $row = $row->parent_id ? $rows->get($row->parent_id) : null;
        }

        return implode('/', array_filter($slugs, fn ($slug) => $slug !== ''));
    }
}
