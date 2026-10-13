<?php namespace AmoriumCms\Amorium\Classes;

use Db;
use File;
use Site;
use Schema;
use Storage;
use Artisan;
use Cms\Classes\Theme;
use System\Models\Parameter;
use AmoriumCms\Amorium\Classes\BaseSections;
use AmoriumCms\Amorium\Classes\SectionClassExporter;
use ApplicationException;

/**
 * DemoContent saves the content of this site into the theme and installs it
 * on another site. Rows keep their IDs so the links between the sites of a
 * record and the october://entry links stay valid.
 */
class DemoContent
{
    public const THEME = 'amorium';

    /**
     * Tailor tables are named xc_{uuid}c|j|r and every Amorium uuid starts with "amorium_".
     */
    public const TAILOR_PREFIX = 'xc_amorium_';

    public const SITE_TABLES = ['system_site_groups', 'system_site_definitions'];

    public const SETTINGS_PREFIX = 'amorium_';

    public const INSTALLED_PARAMETER = 'amorium::demo.installed_at';

    /**
     * theme finds the Amorium theme by its code, because a marketplace install
     * places it in themes/{vendor}-amorium instead of themes/amorium.
     */
    public static function theme(): Theme
    {
        $theme = Theme::load(static::THEME);

        if (!$theme || !$theme->isValid()) {
            $theme = collect(Theme::all())->first(
                fn ($theme) => $theme->getConfigValue('code') === static::THEME
            );
        }

        if (!$theme) {
            throw new ApplicationException(__('The :theme theme was not found.', ['theme' => static::THEME]));
        }

        return $theme;
    }

    /**
     * path of the demo folder in the theme
     */
    public static function path(string $file = ''): string
    {
        return static::theme()->getPath() . '/demo' . ($file ? '/' . $file : '');
    }

    /**
     * exists
     */
    public static function exists(): bool
    {
        return File::isFile(static::path('content.json'));
    }

    /**
     * canExport is only allowed in the local environment so the demo files of
     * the theme are not overwritten on a live site.
     */
    public static function canExport(): bool
    {
        return app()->environment('local');
    }

    /**
     * summary returns the counts stored in the demo file, or null when there is none.
     */
    public static function summary(): ?array
    {
        if (!static::exists()) {
            return null;
        }

        $content = static::load();

        return [
            'exported_at' => $content['exported_at'] ?? null,
            'counts' => static::countContent($content),
            'installed_at' => Parameter::get(static::INSTALLED_PARAMETER)
        ];
    }

    /**
     * export writes the content of this site to the theme and returns the counts.
     */
    public static function export(): array
    {
        if (!static::canExport()) {
            throw new ApplicationException(__('Demo content can only be saved in the local environment.'));
        }

        $tables = [];

        foreach (array_merge(static::SITE_TABLES, static::tailorTables()) as $table) {
            $tables[$table] = Db::table($table)
                ->get()
                ->map(fn ($row) => array_filter(
                    (array) $row,
                    fn ($column) => !str_starts_with($column, 'x_'),
                    ARRAY_FILTER_USE_KEY
                ))
                ->all();
        }

        $content = [
            'exported_at' => now()->toDateTimeString(),
            'tables' => $tables,
            'settings' => Db::table('system_settings')
                ->where('item', 'like', static::SETTINGS_PREFIX . '%')
                ->orderBy('id')
                ->get()
                ->map(fn ($row) => (array) $row)
                ->all(),
            'theme_data' => Db::table('cms_theme_data')->where('theme', static::theme()->getDirName())->value('data'),
            'media' => static::exportMedia()
        ];

        File::put(
            static::path('content.json'),
            json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n"
        );

        BaseSections::export();

        return static::countContent($content);
    }

    /**
     * install replaces the sites, Amorium content, Amorium settings and theme
     * settings of this site with the demo content and returns the counts.
     */
    public static function install(): array
    {
        if (!static::exists()) {
            throw new ApplicationException(__('The theme does not contain demo content.'));
        }

        $content = static::load();

        $missing = array_filter(array_keys($content['tables']), fn ($table) => !Schema::hasTable($table));
        if ($missing) {
            throw new ApplicationException(__('Tables are missing: :tables. Run "php artisan tailor:migrate" first.', [
                'tables' => implode(', ', $missing)
            ]));
        }

        Db::transaction(function () use ($content) {
            foreach ($content['tables'] as $table => $rows) {
                static::replaceRows($table, $rows);
            }

            static::installSettings($content['settings'] ?? []);

            if (isset($content['theme_data'])) {
                Db::table('cms_theme_data')->updateOrInsert(
                    ['theme' => static::theme()->getDirName()],
                    ['data' => $content['theme_data'], 'created_at' => now(), 'updated_at' => now()]
                );
            }
        });

        static::installMedia();

        PageSlugs::sync();

        Parameter::set(static::INSTALLED_PARAMETER, now()->toDateTimeString());

        Site::resetCache();
        Artisan::call('cache:clear');

        SectionClassExporter::export();

        return static::countContent($content);
    }

    /**
     * tailorTables of the Amorium blueprints in this database
     */
    protected static function tailorTables(): array
    {
        return collect(Schema::getTables())
            ->pluck('name')
            ->filter(fn ($table) => str_starts_with($table, static::TAILOR_PREFIX))
            ->sort()
            ->values()
            ->all();
    }

    /**
     * replaceRows empties the table and inserts the rows, writing only the
     * columns the table has on this site.
     */
    protected static function replaceRows(string $table, array $rows): void
    {
        $columns = array_flip(Schema::getColumnListing($table));

        Db::table($table)->delete();

        foreach (array_chunk($rows, 100) as $chunk) {
            Db::table($table)->insert(array_map(
                fn ($row) => array_intersect_key($row, $columns),
                $chunk
            ));
        }
    }

    /**
     * installSettings inserts the settings with new IDs because the IDs may be in
     * use by other settings, then points site_root_id to the new IDs.
     */
    protected static function installSettings(array $settings): void
    {
        Db::table('system_settings')->where('item', 'like', static::SETTINGS_PREFIX . '%')->delete();

        $ids = [];

        foreach ($settings as $row) {
            $oldId = $row['id'];
            unset($row['id']);

            $ids[$oldId] = Db::table('system_settings')->insertGetId($row);
        }

        foreach ($settings as $row) {
            if (!empty($row['site_root_id']) && isset($ids[$row['site_root_id']])) {
                Db::table('system_settings')
                    ->where('id', $ids[$row['id']])
                    ->update(['site_root_id' => $ids[$row['site_root_id']]]);
            }
        }
    }

    /**
     * exportMedia copies the media library to the theme and returns the file paths.
     */
    protected static function exportMedia(): array
    {
        $target = static::path('media');

        File::deleteDirectory($target);
        File::makeDirectory($target, 0755, true, true);

        $disk = Storage::disk('media');
        $files = array_values(array_filter(
            $disk->allFiles(),
            fn ($file) => !str_starts_with(basename($file), '.')
        ));

        foreach ($files as $file) {
            File::makeDirectory(dirname($target . '/' . $file), 0755, true, true);
            File::put($target . '/' . $file, $disk->get($file));
        }

        return $files;
    }

    /**
     * installMedia copies the media files of the demo to the media library.
     */
    protected static function installMedia(): void
    {
        $source = static::path('media');
        if (!File::isDirectory($source)) {
            return;
        }

        $disk = Storage::disk('media');

        foreach (File::allFiles($source) as $file) {
            $disk->put(str_replace('\\', '/', $file->getRelativePathname()), File::get($file->getPathname()));
        }
    }

    /**
     * load
     */
    protected static function load(): array
    {
        $content = json_decode(File::get(static::path('content.json')), true);

        if (!is_array($content) || !is_array($content['tables'] ?? null)) {
            throw new ApplicationException(__('The demo content file is not valid.'));
        }

        return $content;
    }

    /**
     * countContent
     */
    protected static function countContent(array $content): array
    {
        $count = fn ($table) => collect($content['tables'][$table] ?? [])
            ->filter(fn ($row) => empty($row['deleted_at']) && empty($row['is_version']))
            ->count();

        return [
            'sites' => $count('system_site_definitions'),
            'pages' => $count('xc_amorium_pagec'),
            'posts' => $count('xc_amorium_postc'),
            'categories' => $count('xc_amorium_post_categoryc'),
            'menu_items' => $count('xc_amorium_navigationc'),
            'sections' => $count('xc_amorium_sectionc'),
            'media' => count($content['media'] ?? [])
        ];
    }
}
