<?php namespace AmoriumCms\Amorium\Classes;

use Site;
use Event;
use Tailor\Models\EntryRecord;
use Tailor\Classes\Scopes\DraftableScope;
use October\Contracts\Database\NestedSetInterface;
use Throwable;

/**
 * EntrySiteSync treats the per-site rows of an Amorium entry as one record.
 * Content stays translatable, while status and publishing dates, fields
 * marked translatable: false, restoring and the tree structure are shared
 * by every site. Creating and deleting are
 * already shared by the multisite sync mode of the blueprint.
 */
class EntrySiteSync
{
    public const SHARED_ATTRIBUTES = ['is_enabled', 'published_at', 'expired_at'];

    public const STRUCTURE_COLUMNS = ['parent_id', 'nest_left', 'nest_right', 'nest_depth'];

    /**
     * @var bool syncing prevents the saves of the other sites from syncing again.
     */
    protected static $syncing = false;

    public static function boot(): void
    {
        EntryRecord::extend(function ($model) {
            $model->bindEvent('model.afterSave', function () use ($model) {
                static::syncPublish($model);
                static::syncAttributes($model);
            });

            $model->bindEvent('model.saveComplete', function () use ($model) {
                static::syncStructure($model);
            });

            $model->bindEvent('model.afterRestore', function () use ($model) {
                static::syncRestore($model);
            });
        });

        Event::listen('backend.list.reorderStructure', function ($widget, $record) {
            static::syncStructure($record);
        });
    }

    /**
     * syncStructure copies the tree of the primary site to the other sites.
     * The parent of each row is mapped to the row of the same site, because
     * new rows copied to the other sites keep the parent of the primary site.
     */
    public static function syncStructure($model): void
    {
        if (!$model instanceof EntryRecord || !$model->isClassInstanceOf(NestedSetInterface::class) || !static::applies($model)) {
            return;
        }

        $primarySiteId = Site::getPrimarySite()?->id;
        if (!$primarySiteId) {
            return;
        }

        $rows = $model->newQueryWithoutScopes()
            ->where($model->getDraftModeColumnName(), DraftableScope::MODE_PUBLISHED)
            ->whereNull('primary_id')
            ->get(array_merge(['id', 'site_id', 'site_root_id'], static::STRUCTURE_COLUMNS));

        $rootKey = fn ($row) => $row->site_root_id ?: $row->id;
        $primaryRows = $rows->where('site_id', $primarySiteId)->keyBy('id');

        foreach ($rows->where('site_id', '<>', $primarySiteId)->groupBy('site_id') as $siteRows) {
            $siteRowsByRoot = $siteRows->keyBy($rootKey);

            foreach ($primaryRows as $primaryRow) {
                $other = $siteRowsByRoot->get($rootKey($primaryRow));
                if (!$other) {
                    continue;
                }

                $parent = $primaryRow->parent_id ? $primaryRows->get($primaryRow->parent_id) : null;
                $values = [
                    'parent_id' => $parent ? $siteRowsByRoot->get($rootKey($parent))?->id : null,
                    'nest_left' => $primaryRow->nest_left,
                    'nest_right' => $primaryRow->nest_right,
                    'nest_depth' => $primaryRow->nest_depth,
                ];

                $changed = array_filter($values, fn ($value, $column) => $other->{$column} != $value, ARRAY_FILTER_USE_BOTH);
                if ($changed) {
                    $model->newQueryWithoutScopes()->where('id', $other->id)->update($values);
                }
            }
        }
    }

    /**
     * syncPublish publishes the first drafts of the other sites when a first
     * draft is published, because Tailor only publishes the row of the active
     * site and the other sites would keep an unpublished copy.
     */
    public static function syncPublish($model): void
    {
        $column = $model->getDraftModeColumnName();

        if (
            !static::applies($model) ||
            !$model->wasChanged($column) ||
            !in_array((int) $model->getOriginal($column), [DraftableScope::MODE_NEW_SAVED, DraftableScope::MODE_NEW_UNSAVED], true)
        ) {
            return;
        }

        static::eachOtherSite($model, function ($other) use ($model, $column) {
            $other->{$column} = DraftableScope::MODE_PUBLISHED;

            foreach (static::SHARED_ATTRIBUTES as $attribute) {
                $other->{$attribute} = $model->{$attribute};
            }

            $other->save(['force' => true]);
        }, [DraftableScope::MODE_NEW_SAVED, DraftableScope::MODE_NEW_UNSAVED]);
    }

    /**
     * syncAttributes copies the changed shared attributes to the other sites.
     * Fields marked translatable: false are shared as well, because Tailor
     * only propagates them when a save passes the propagate option.
     */
    protected static function syncAttributes($model): void
    {
        if (!static::applies($model)) {
            return;
        }

        $changed = array_values(array_filter(
            array_keys($model->getChanges()),
            fn ($attribute) => in_array($attribute, static::SHARED_ATTRIBUTES, true)
                || ($model->isAttributePropagatable($attribute) && !$model->getRelationType($attribute))
        ));
        if (!$changed) {
            return;
        }

        static::eachOtherSite($model, function ($other) use ($model, $changed) {
            foreach ($changed as $attribute) {
                $other->{$attribute} = $model->{$attribute};
            }

            $other->save(['force' => true]);
        });
    }

    /**
     * syncRestore restores the other sites when one site is restored.
     */
    protected static function syncRestore($model): void
    {
        if (!static::applies($model)) {
            return;
        }

        static::eachOtherSite($model, function ($other) {
            if ($other->trashed()) {
                $other->restore();
            }
        });
    }

    /**
     * applies returns true for published rows of Amorium blueprints using multisite sync.
     */
    protected static function applies($model): bool
    {
        if (static::$syncing || !$model->blueprint_uuid || $model->isDraftStatus() || $model->primary_id) {
            return false;
        }

        try {
            $blueprint = $model->getBlueprintDefinition();
        }
        catch (Throwable $ex) {
            return false;
        }

        return str_starts_with((string) $blueprint->handleSlug, 'amorium-')
            && $model->isMultisiteSyncEnabled();
    }

    /**
     * eachOtherSite runs the callback for the rows of the other sites in the
     * given draft modes, which are the published rows by default.
     */
    protected static function eachOtherSite($model, callable $callback, array $modes = [DraftableScope::MODE_PUBLISHED]): void
    {
        static::$syncing = true;

        try {
            $others = Site::withGlobalContext(function () use ($model, $modes) {
                return $model->newOtherSiteQuery()
                    ->where($model->getKeyName(), '<>', $model->getKey())
                    ->whereIn($model->getDraftModeColumnName(), $modes)
                    ->whereNull('primary_id')
                    ->get();
            });

            foreach ($others as $other) {
                Site::withContext($other->{$other->getSiteIdColumn()}, function () use ($other, $callback) {
                    $callback($other);
                });
            }
        }
        finally {
            static::$syncing = false;
        }
    }
}
