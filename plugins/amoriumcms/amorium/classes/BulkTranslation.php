<?php namespace AmoriumCms\Amorium\Classes;

use Site;
use Event;
use Config;
use ApplicationException;
use Tailor\Models\EntryRecord;
use Tailor\Models\StructureRecord;
use Tailor\Controllers\Entries;
use Tailor\Classes\Blueprint\StructureBlueprint;
use Tailor\Classes\Scopes\DraftableScope;

/**
 * BulkTranslation adds a "Translate All" or "Translate Selected" button to
 * the entry list of the primary site. The browser requests the record list
 * once, then translates the records one by one so the progress can be shown
 * in a popup.
 */
class BulkTranslation
{
    public const MODE_ALL = 'all';

    public const MODE_SELECTED = 'selected';

    /**
     * @var array BLUEPRINTS lists the blueprint uuids with the button and
     * whether it translates every record or the records checked in the list.
     */
    public const BLUEPRINTS = [
        'amorium_navigation' => self::MODE_ALL,
        'amorium_post' => self::MODE_SELECTED,
    ];

    public static function boot(): void
    {
        if (!Config::get('amoriumcms.amorium::translate.enabled')) {
            return;
        }

        Event::listen('amorium.sections.extendListToolbar', function ($controller, $section) {
            if (!static::allows($controller)) {
                return null;
            }

            return $controller->makePartial('$/amoriumcms/amorium/partials/_translate_all.php', [
                'mode' => static::mode($controller),
            ]);
        });

        Entries::extend(function ($controller) {
            $script = '/plugins/amoriumcms/amorium/assets/js/translate-all.js';
            $controller->addJs($script . '?v=' . @filemtime(base_path(ltrim($script, '/'))));

            $controller->addDynamicMethod('onTranslateAllList', function () use ($controller) {
                static::guard($controller);

                $query = static::query($controller);

                if (static::mode($controller) === static::MODE_SELECTED) {
                    $ids = array_values(array_filter(array_map('intval', (array) post('ids'))));
                    if (!$ids) {
                        throw new ApplicationException(__("Select the records to translate."));
                    }

                    $query->whereIn($query->getModel()->getQualifiedKeyName(), $ids);
                }

                $records = $query->get()->map(fn ($record) => [
                    'id' => $record->getKey(),
                    'title' => (string) $record->title,
                ]);

                return ['records' => $records->values()];
            });

            $controller->addDynamicMethod('onTranslateAllRecord', function () use ($controller) {
                static::guard($controller);

                $record = static::query($controller)->find((int) post('id'));
                if (!$record) {
                    throw new ApplicationException(__("Record not found."));
                }

                return ['written' => EntryTranslator::translateEntry($record, (bool) post('overwrite'))];
            });
        });
    }

    /**
     * allows returns true on the primary site list of a supported blueprint.
     */
    public static function allows($controller): bool
    {
        $source = static::source($controller);

        return $source
            && isset(static::BLUEPRINTS[$source->uuid])
            && Site::getEditSite()?->is_primary
            && $controller->hasSourcePermission('publish');
    }

    /**
     * mode returns whether the active blueprint translates all or the selected records.
     */
    public static function mode($controller): string
    {
        return static::BLUEPRINTS[static::source($controller)?->uuid] ?? static::MODE_ALL;
    }

    protected static function guard($controller): void
    {
        if (!static::allows($controller)) {
            throw new ApplicationException(__("Access denied."));
        }
    }

    /**
     * query returns the published primary site records of the active blueprint.
     */
    protected static function source($controller)
    {
        return (fn () => $this->activeSource)->call($controller);
    }

    protected static function query($controller)
    {
        $source = static::source($controller);
        $model = $source instanceof StructureBlueprint
            ? StructureRecord::inSection($source->handle)
            : EntryRecord::inSection($source->handle);

        $query = $model->newQuery()
            ->where('site_id', Site::getEditSite()->id)
            ->where($model->getDraftModeColumnName(), DraftableScope::MODE_PUBLISHED)
            ->whereNull('primary_id');

        return $source instanceof StructureBlueprint
            ? $query->orderBy('nest_left')
            : $query->orderBy('id');
    }
}
