<?php namespace AmoriumCms\Amorium\Classes;

use Site;
use Event;
use Flash;
use Config;
use Redirect;
use ApplicationException;
use Tailor\Models\EntryRecord;
use Tailor\Models\StructureRecord;
use Tailor\Controllers\Entries;
use Tailor\Classes\Blueprint\StructureBlueprint;
use Tailor\Classes\Scopes\DraftableScope;

/**
 * SiteTranslation adds a "Translate" button to the entry form toolbar while
 * another site than the primary is selected. The button translates the
 * primary site content of the entry into the language of the selected site.
 */
class SiteTranslation
{
    public static function boot(): void
    {
        if (!Config::get('amoriumcms.amorium::translate.enabled')) {
            return;
        }

        Event::listen('backend.form.extendFields', function ($widget) {
            $controller = $widget->getController();

            if ($widget->isNested || !$controller instanceof Entries || !$widget->model instanceof EntryRecord || !$widget->model->exists || !static::allows($controller)) {
                return;
            }

            $controller->addJs('/plugins/amoriumcms/amorium/assets/js/translate-site.js?v='.@filemtime(plugins_path('amoriumcms/amorium/assets/js/translate-site.js')), [
                'data-label' => __("Translate"),
            ]);
        });

        Event::listen('amorium.sections.extendListToolbar', function ($controller, $section) {
            if (!static::allows($controller)) {
                return null;
            }

            return $controller->makePartial('$/amoriumcms/amorium/partials/_translate_all.php', [
                'mode' => BulkTranslation::MODE_SELECTED,
                'templateId' => 'translateSiteTemplate',
                'handlerList' => 'onTranslateSiteList',
                'handlerRecord' => 'onTranslateSiteRecord',
                'description' => __("Translates the selected records from :from into the language of :to.", [
                    'from' => Site::getPrimarySite()?->name,
                    'to' => Site::getEditSite()?->name,
                ]),
            ]);
        });

        Entries::extend(function ($controller) {
            $controller->addDynamicMethod('onTranslateSiteList', function () use ($controller) {
                static::guard($controller);

                $ids = array_values(array_filter(array_map('intval', (array) post('ids'))));
                if (!$ids) {
                    throw new ApplicationException(__("Select the records to translate."));
                }

                $query = static::query($controller);
                $records = $query
                    ->whereIn($query->getModel()->getQualifiedKeyName(), $ids)
                    ->get()
                    ->map(fn ($record) => [
                        'id' => $record->getKey(),
                        'title' => (string) $record->title,
                    ]);

                return ['records' => $records->values()];
            });

            $controller->addDynamicMethod('onTranslateSiteRecord', function () use ($controller) {
                static::guard($controller);

                $record = static::query($controller)->find((int) post('id'));
                if (!$record) {
                    throw new ApplicationException(__("Record not found."));
                }

                $primary = static::primaryRecord($record);
                if (!$primary) {
                    throw new ApplicationException(__("The record of the primary site was not found."));
                }

                return ['written' => EntryTranslator::translateEntryToSite($primary, (int) $record->site_id, (bool) post('overwrite'))];
            });

            $controller->addDynamicMethod('onTranslateFromPrimaryPopup', function () use ($controller) {
                static::guard($controller);

                return $controller->makePartial('$/amoriumcms/amorium/partials/_translate_site_popup.php', [
                    'primarySite' => Site::getPrimarySite(),
                    'editSite' => Site::getEditSite(),
                ]);
            });

            $controller->addDynamicMethod('onTranslateFromPrimary', function () use ($controller) {
                static::guard($controller);

                $record = static::record($controller);
                $primary = $record ? static::primaryRecord($record) : null;
                if (!$primary) {
                    throw new ApplicationException(__("The record of the primary site was not found."));
                }

                $written = EntryTranslator::translateEntryToSite($primary, (int) $record->site_id, (bool) post('overwrite'));

                $written
                    ? Flash::success(__("Translated :count values.", ['count' => $written]))
                    : Flash::info(__("Nothing was translated."));

                return Redirect::refresh();
            });
        });
    }

    /**
     * allows returns true on a translated blueprint while another site than the primary is selected.
     */
    public static function allows($controller): bool
    {
        $source = static::source($controller);
        $site = Site::getEditSite();

        return $source
            && isset(EntryTranslator::FIELDS[$source->uuid])
            && $site && !$site->is_primary
            && $controller->hasSourcePermission('publish');
    }

    protected static function guard($controller): void
    {
        if (!static::allows($controller)) {
            throw new ApplicationException(__("Access denied."));
        }
    }

    /**
     * record returns the entry of the selected site opened in the form.
     */
    protected static function record($controller)
    {
        $source = static::source($controller);
        $recordId = (fn () => $this->params[0] ?? null)->call($controller);

        $model = $source instanceof StructureBlueprint
            ? StructureRecord::inSection($source->handle)
            : EntryRecord::inSection($source->handle);

        $record = $model->newQuery()->find($recordId);

        return $record && (int) $record->site_id === (int) Site::getEditSite()->id ? $record : null;
    }

    /**
     * query returns the published records of the selected site.
     */
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

    /**
     * primaryRecord returns the published primary site row of the entry.
     */
    protected static function primaryRecord($record)
    {
        return Site::withGlobalContext(fn () => $record->newOtherSiteQuery()
            ->where($record->getSiteIdColumn(), Site::getPrimarySite()?->id)
            ->where($record->getDraftModeColumnName(), DraftableScope::MODE_PUBLISHED)
            ->whereNull('primary_id')
            ->first());
    }

    protected static function source($controller)
    {
        return (fn () => $this->activeSource)->call($controller);
    }
}
