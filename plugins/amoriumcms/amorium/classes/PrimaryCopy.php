<?php namespace AmoriumCms\Amorium\Classes;

use Site;
use Flash;
use ApplicationException;
use Tailor\Models\EntryRecord;
use Tailor\Models\StructureRecord;
use Tailor\Controllers\Entries;
use Tailor\Classes\BlueprintIndexer;
use Tailor\Classes\Blueprint\StructureBlueprint;
use Tailor\Classes\Scopes\DraftableScope;
use October\Contracts\Database\SoftDeleteInterface;

/**
 * PrimaryCopy copies the records of the primary site to the site selected in
 * the backend. Missing records are created, existing records are left alone
 * unless overwriting is requested, then their content is replaced with the
 * content of the primary site.
 */
class PrimaryCopy
{
    /**
     * @var array KEEP_ATTRIBUTES belong to the target row and are never copied.
     */
    public const KEEP_ATTRIBUTES = [
        'id', 'site_id', 'site_root_id', 'created_at', 'updated_at', 'deleted_at',
        'primary_id', 'primary_attrs', 'draft_mode', 'is_version',
        'parent_id', 'nest_left', 'nest_right', 'nest_depth',
        'host_id', 'host_field',
    ];

    public static function boot(): void
    {
        Entries::extend(function ($controller) {
            $controller->addDynamicMethod('onCopyFromPrimaryPopup', function () use ($controller) {
                static::guard($controller);

                return $controller->makePartial('$/amoriumcms/amorium/partials/_copy_from_primary_popup.php', [
                    'primarySite' => Site::getPrimarySite(),
                    'editSite' => Site::getEditSite(),
                ]);
            });

            $controller->addDynamicMethod('onCopyFromPrimary', function () use ($controller) {
                static::guard($controller);

                $result = static::copy(static::source($controller), Site::getEditSite()->id, (bool) post('overwrite'));

                Flash::success(__('Created :created, updated :updated, skipped :skipped records.', $result));

                return $controller->listRefresh();
            });
        });
    }

    /**
     * allows returns true on the list of a multisite Amorium blueprint while another site than the primary is active.
     */
    public static function allows($controller): bool
    {
        $source = static::source($controller);
        $site = Site::getEditSite();

        return $source
            && str_starts_with((string) $source->handleSlug, 'amorium-')
            && $source->useMultisite()
            && $site && !$site->is_primary
            && $controller->hasSourcePermission('create');
    }

    /**
     * copy copies the published records of the primary site to the given site.
     * The blueprints referenced by entries fields are copied first, only their
     * missing records, because the references resolve to the record of the same site.
     */
    public static function copy($source, int $siteId, bool $overwrite, array &$visited = []): array
    {
        $primarySiteId = Site::getPrimarySite()?->id;
        $result = ['created' => 0, 'updated' => 0, 'skipped' => 0];

        if (!$primarySiteId || $primarySiteId === $siteId || isset($visited[$source->handle])) {
            return $result;
        }

        $visited[$source->handle] = true;

        foreach (static::dependencies($source) as $dependency) {
            $dependencyResult = static::copy($dependency, $siteId, false, $visited);
            $result['created'] += $dependencyResult['created'];
        }

        Site::withGlobalContext(function () use ($source, $siteId, $primarySiteId, $overwrite, &$result) {
            $records = static::query($source, $primarySiteId)->get();

            foreach ($records as $record) {
                $target = static::findTarget($record, $siteId);

                if (!$target) {
                    $record->propagateToSite($siteId);
                    $result['created']++;
                    continue;
                }

                $trashed = $target->isClassInstanceOf(SoftDeleteInterface::class) && $target->trashed();
                if (!$overwrite && !$trashed) {
                    $result['skipped']++;
                    continue;
                }

                if ($trashed) {
                    $target->restore();
                }

                $record->propagateToSite($siteId, $target);
                static::copyContent($record, $target);
                $result['updated']++;
            }

            if ($records->count() && $source instanceof StructureBlueprint) {
                EntrySiteSync::syncStructure($records->first());
            }
        });

        return $result;
    }

    /**
     * copyContent replaces the content of the target row with the content of
     * the source row, including the items of repeaters shared by site_root_id.
     */
    protected static function copyContent($source, $target): void
    {
        $content = array_filter(
            $source->getAttributes(),
            fn ($attribute) => !in_array($attribute, static::KEEP_ATTRIBUTES, true) && !$source->getRelationType($attribute),
            ARRAY_FILTER_USE_KEY
        );

        $target->setRawAttributes(array_merge($target->getAttributes(), $content));
        $target->save(['force' => true]);

        foreach ($source->hasMany as $name => $definition) {
            if (!($definition['multisiteSync'] ?? false)) {
                continue;
            }

            $targetItems = $target->$name()->withoutGlobalScopes()->where('site_id', $target->site_id)->get()->keyBy('site_root_id');

            foreach ($source->$name()->where('site_id', $source->site_id)->get() as $item) {
                if ($targetItem = $targetItems->get($item->site_root_id ?: $item->id)) {
                    static::copyContent($item, $targetItem);
                }
            }
        }
    }

    /**
     * dependencies returns the multisite Amorium blueprints used as the source of entries fields.
     */
    protected static function dependencies($source): array
    {
        $handles = [];
        $walk = function ($node) use (&$walk, &$handles) {
            if (!is_array($node)) {
                return;
            }

            if (($node['type'] ?? null) === 'entries' && is_string($node['source'] ?? null)) {
                $handles[$node['source']] = true;
            }

            foreach ($node as $child) {
                $walk($child);
            }
        };
        $walk($source->toArray()['fields'] ?? []);

        $dependencies = [];
        foreach (array_keys($handles) as $handle) {
            $blueprint = BlueprintIndexer::instance()->findByHandle($handle);
            if ($blueprint && $handle !== $source->handle && str_starts_with((string) $blueprint->handleSlug, 'amorium-') && $blueprint->useMultisite()) {
                $dependencies[] = $blueprint;
            }
        }

        return $dependencies;
    }

    protected static function findTarget($record, int $siteId)
    {
        return $record->newOtherSiteQuery()
            ->where($record->getSiteIdColumn(), $siteId)
            ->whereNull('primary_id')
            ->first();
    }

    protected static function query($source, int $siteId)
    {
        $model = $source instanceof StructureBlueprint
            ? StructureRecord::inSection($source->handle)
            : EntryRecord::inSection($source->handle);

        $query = $model->newQuery()
            ->where('site_id', $siteId)
            ->where($model->getDraftModeColumnName(), DraftableScope::MODE_PUBLISHED)
            ->whereNull('primary_id');

        return $source instanceof StructureBlueprint
            ? $query->orderBy('nest_left')
            : $query->orderBy('id');
    }

    protected static function guard($controller): void
    {
        if (!static::allows($controller)) {
            throw new ApplicationException(__('Access denied.'));
        }
    }

    protected static function source($controller)
    {
        return (fn () => $this->activeSource)->call($controller);
    }
}
