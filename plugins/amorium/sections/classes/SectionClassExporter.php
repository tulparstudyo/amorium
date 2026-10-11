<?php namespace Amorium\Sections\Classes;

use Db;
use File;
use Cms\Classes\Theme;
use Tailor\Models\EntryRecord;

/**
 * SectionClassExporter writes section HTML to the active theme so the
 * Tailwind build can see the classes that only exist in the database.
 */
class SectionClassExporter
{
    const BLUEPRINT_UUID = 'amorium_section';

    public static function export(): ?string
    {
        $theme = Theme::getActiveTheme();
        if (!$theme) {
            return null;
        }

        $directory = $theme->getPath() . '/assets/src';
        if (!File::isDirectory($directory)) {
            return null;
        }

        $table = EntryRecord::inSection('Amorium\Section')->getTable();

        $html = Db::table($table)
            ->whereNull('deleted_at')
            ->where('is_version', false)
            ->orderBy('id')
            ->get(['id', 'html'])
            ->map(fn ($row) => "<!-- section {$row->id} -->\n" . $row->html)
            ->implode("\n\n");

        $path = $directory . '/sections.html';
        File::put($path, $html . "\n");

        return $path;
    }
}
