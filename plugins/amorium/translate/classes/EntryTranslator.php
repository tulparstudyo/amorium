<?php namespace Amorium\Translate\Classes;

use App;
use Db;
use Log;
use Site;
use Event;
use Config;
use Locale;
use Tailor\Models\EntryRecord;
use Tailor\Controllers\Entries;
use Tailor\Classes\Scopes\DraftableScope;
use Throwable;

/**
 * EntryTranslator translates the text fields of a primary site entry to the
 * other sites when the "Translate on save" checkbox is checked on save, or
 * when translateEntry is called. Fields edited by hand on the other sites
 * are left untouched unless overwrite is requested.
 */
class EntryTranslator
{
    public const CHECKBOX = '_translate_now';

    public const OVERWRITE = '_translate_overwrite';

    public const LOG_TABLE = 'amorium_translate_logs';

    /**
     * @var array FIELDS lists the translated fields of each blueprint uuid.
     */
    public const FIELDS = [
        'amorium_post' => ['title', 'content', 'excerpt', 'meta_title', 'meta_description'],
        'amorium_post_category' => ['title', 'description', 'meta_title', 'meta_description'],
        'amorium_navigation' => ['title'],
        'amorium_page' => ['title', 'meta_title', 'meta_description'],
    ];

    /**
     * @var array SECTIONS lists the section repeaters of each blueprint uuid,
     * as repeater name => data field name. The item structure is propagated
     * by the repeater itself, only the section data is translated here.
     */
    public const SECTIONS = [
        'amorium_page' => ['sections' => 'section_data'],
    ];

    /**
     * @var bool running prevents the saves of the other sites from translating again.
     */
    protected static $running = false;

    public static function boot(): void
    {
        if (!Config::get('amorium.translate::enabled')) {
            return;
        }

        Event::listen('backend.form.extendFields', function ($widget) {
            static::extendForm($widget);
        });

        EntryRecord::extend(function ($model) {
            $originals = [];

            $model->bindEvent('model.beforeSave', function () use ($model, &$originals) {
                $originals = $model->getOriginal();
            });

            $model->bindEvent('model.saveComplete', function () use ($model, &$originals) {
                static::translate($model, $originals);
            });
        });
    }

    /**
     * extendForm adds the checkbox to the entry form while the primary site is active.
     */
    protected static function extendForm($widget): void
    {
        if (
            $widget->isNested ||
            !$widget->getController() instanceof Entries ||
            !$widget->model instanceof EntryRecord ||
            !isset(static::FIELDS[$widget->model->blueprint_uuid]) ||
            !Site::getEditSite()?->is_primary
        ) {
            return;
        }

        $widget->addFields([
            static::CHECKBOX => [
                'type' => 'partial',
                'path' => '$/amorium/translate/partials/_translate_now.php',
                'translatable' => false,
            ],
        ]);
    }

    /**
     * translate writes the translations of the saved entry to the other sites.
     */
    protected static function translate($model, array $originals): void
    {
        if (!App::runningInBackend() || !static::isRequested()) {
            return;
        }

        static::run($model, $originals, static::isRequested(static::OVERWRITE));
    }

    /**
     * translateEntry translates a saved primary site entry without a form
     * request and returns the number of values written to the other sites.
     */
    public static function translateEntry($model, bool $overwrite = false): int
    {
        return static::run($model, [], $overwrite);
    }

    /**
     * translateEntryToSite translates a saved primary site entry to one other
     * site only and returns the number of values written.
     */
    public static function translateEntryToSite($model, int $siteId, bool $overwrite = false): int
    {
        return static::run($model, [], $overwrite, $siteId);
    }

    protected static function run($model, array $originals, bool $overwrite, ?int $siteId = null): int
    {
        if (static::$running || !static::applies($model)) {
            return 0;
        }

        static::$running = true;
        @set_time_limit(0);
        $written = 0;

        try {
            $fields = static::FIELDS[$model->blueprint_uuid];
            $from = static::languageName(Site::getSiteFromId($model->site_id));
            $client = TranslatorFactory::make();

            foreach (static::otherRows($model) as $other) {
                if ($siteId !== null && (int) $other->site_id !== $siteId) {
                    continue;
                }

                $written += Site::withContext($other->site_id, function () use ($model, $other, $fields, $originals, $from, $client, $overwrite) {
                    return static::translateRow($model, $other, $fields, $originals, $from, $client, $overwrite);
                });
            }
        }
        catch (Throwable $ex) {
            static::logError($model, null, null, $ex);
        }
        finally {
            static::$running = false;
        }

        return $written;
    }

    /**
     * translateRow translates the fields of one other site row and saves it,
     * returning the number of values written. With overwrite, every field is
     * translated even if it was edited by hand.
     */
    protected static function translateRow($model, $other, array $fields, array $originals, string $from, TranslatorClient $client, bool $overwrite = false): int
    {
        $to = static::languageName(Site::getSiteFromId($other->site_id));
        $logs = Db::table(static::LOG_TABLE)
            ->where('blueprint_uuid', $model->blueprint_uuid)
            ->where('record_id', $other->getKey())
            ->get()
            ->keyBy('field');

        $pending = [];

        foreach ($fields as $field) {
            $source = (string) $model->{$field};
            $target = (string) $other->{$field};

            if (trim($source) === '') {
                continue;
            }

            if ($overwrite || static::needsTranslation($source, $target, $originals[$field] ?? null, $logs->get($field))) {
                $pending[$field] = $source;
            }
        }

        $items = static::pendingSections($model, $other, $overwrite, $logs);

        $texts = array_values($pending);
        foreach ($items as $item) {
            array_push($texts, ...SectionTexts::texts($item['data'], $item['fields']));
        }

        if (!$texts && !$items) {
            return 0;
        }

        $written = 0;

        $map = (new BatchTranslator($client, $from, $to, fn ($ex) => static::logError($model, $other, null, $ex)))->translate($texts);

        $translated = [];
        foreach ($pending as $field => $source) {
            if (isset($map[$source])) {
                $other->{$field} = $map[$source];
                $translated[$field] = $source;
            }
        }

        if ($translated) {
            try {
                $other->save(['force' => true]);

                foreach ($translated as $field => $source) {
                    static::writeLog($model, $other, $field, $source, (string) $other->{$field}, $logs);
                }

                $written += count($translated);
            }
            catch (Throwable $ex) {
                static::logError($model, $other, null, $ex);
            }
        }

        foreach ($items as $item) {
            $data = SectionTexts::apply($item['data'], $item['fields'], $map);
            if ($data === null) {
                continue;
            }

            try {
                $item['target']->{$item['dataField']} = $data;
                $item['target']->save();

                static::writeLog($model, $other, $item['logKey'], $item['source'], SectionTexts::normalized((array) $item['target']->{$item['dataField']}, $item['fields']), $logs);
                $written++;
            }
            catch (Throwable $ex) {
                static::logError($model, $other, $item['logKey'], $ex);
            }
        }

        return $written;
    }

    /**
     * pendingSections returns the section items to translate, each matched to
     * the item of the other site by site_root_id. Items edited by hand are
     * protected unless overwrite is requested.
     */
    protected static function pendingSections($model, $other, bool $overwrite, $logs): array
    {
        $pending = [];

        foreach (static::SECTIONS[$model->blueprint_uuid] ?? [] as $relation => $dataField) {
            $sourceItems = $model->{$relation}()->withoutGlobalScopes()->where('site_id', $model->site_id)->get();
            $targetItems = $other->{$relation}()->withoutGlobalScopes()->where('site_id', $other->site_id)->get()->keyBy('site_root_id');

            foreach ($sourceItems as $item) {
                $rootId = $item->site_root_id ?: $item->getKey();
                $target = $targetItems->get($rootId);
                $sourceData = (array) $item->{$dataField};

                if (!$target || !$sourceData) {
                    continue;
                }

                $section = $item->section;
                $section = $section instanceof \Illuminate\Support\Collection ? $section->first() : $section;
                $fields = SectionTexts::fieldsFor($section);

                $logKey = $relation.'.'.$rootId;
                $targetData = (array) $target->{$dataField};
                $source = SectionTexts::normalized($sourceData, $fields);
                $current = SectionTexts::normalized($targetData, $fields);
                $legacy = [json_encode($sourceData), $targetData ? json_encode($targetData) : ''];

                if (!$overwrite && !static::needsTranslation($source, $current, null, $logs->get($logKey), $legacy)) {
                    continue;
                }

                $pending[] = [
                    'target' => $target,
                    'dataField' => $dataField,
                    'data' => $sourceData,
                    'fields' => $fields,
                    'source' => $source,
                    'logKey' => $logKey,
                ];
            }
        }

        return $pending;
    }

    /**
     * writeLog stores the hashes of a translated value to detect later manual edits.
     */
    protected static function writeLog($model, $other, string $field, string $source, string $output, $logs): void
    {
        Db::table(static::LOG_TABLE)->updateOrInsert(
            [
                'blueprint_uuid' => $model->blueprint_uuid,
                'record_id' => $other->getKey(),
                'field' => $field,
            ],
            [
                'source_hash' => TextKey::hash($source),
                'output_hash' => TextKey::hash($output),
                'updated_at' => now(),
                'created_at' => $logs->get($field)->created_at ?? now(),
            ]
        );
    }

    /**
     * needsTranslation returns true when the target is empty, still holds an
     * untranslated copy of the source, or holds an outdated automatic translation.
     * A target that was edited by hand is never overwritten. Values are compared
     * normalized, so whitespace changes count neither as a new source nor as a
     * manual edit. Legacy holds the raw source and target the old logs hashed.
     */
    protected static function needsTranslation(string $source, string $target, $previous, $log, ?array $legacy = null): bool
    {
        $normalizedTarget = TextKey::normalize($target);

        if (
            $normalizedTarget === '' ||
            $normalizedTarget === TextKey::normalize($source) ||
            ($previous !== null && $normalizedTarget === TextKey::normalize((string) $previous))
        ) {
            return true;
        }

        if (!$log) {
            return false;
        }

        if (TextKey::hash($target) === $log->output_hash) {
            return TextKey::hash($source) !== $log->source_hash;
        }

        [$legacySource, $legacyTarget] = $legacy ?? [$source, $target];
        if (md5($legacyTarget) === $log->output_hash) {
            return md5($legacySource) !== $log->source_hash;
        }

        return false;
    }

    /**
     * applies returns true for published primary site rows of a translated blueprint.
     */
    protected static function applies($model): bool
    {
        if (!isset(static::FIELDS[$model->blueprint_uuid]) || $model->isDraftStatus() || $model->primary_id) {
            return false;
        }

        $primary = Site::getPrimarySite();

        return $primary && (int) $model->site_id === (int) $primary->id && $model->isMultisiteSyncEnabled();
    }

    /**
     * isRequested checks the checkbox value, which is posted inside the form array name.
     */
    protected static function isRequested(string $name = self::CHECKBOX): bool
    {
        if (post($name)) {
            return true;
        }

        foreach ((array) post() as $value) {
            if (is_array($value) && !empty($value[$name])) {
                return true;
            }
        }

        return false;
    }

    /**
     * otherRows returns the published rows of the other sites.
     */
    protected static function otherRows($model)
    {
        return Site::withGlobalContext(function () use ($model) {
            return $model->newOtherSiteQuery()
                ->where($model->getKeyName(), '<>', $model->getKey())
                ->where($model->getDraftModeColumnName(), DraftableScope::MODE_PUBLISHED)
                ->whereNull('primary_id')
                ->get();
        });
    }

    /**
     * languageName returns the English name of the site language, such as "Turkish".
     */
    protected static function languageName($site): string
    {
        $locale = (string) ($site->hard_locale ?? $site->locale ?? '');

        return Locale::getDisplayLanguage($locale, 'en') ?: $locale;
    }

    /**
     * logError records a failed translation without stopping the save.
     */
    protected static function logError($model, $other, ?string $field, Throwable $ex): void
    {
        Log::warning('Amorium translate failed: '.$ex->getMessage(), [
            'blueprint' => $model->blueprint_uuid,
            'record_id' => $model->getKey(),
            'target_id' => $other?->getKey(),
            'site_id' => $other?->site_id,
            'field' => $field,
        ]);
    }
}
