<?php namespace AmoriumCms\Shop\Models\Concerns;

use App;
use Site;
use AmoriumCms\Shop\Classes\CatalogCache;

/**
 * HasTranslations reads texts from the "translations" relation, one row per locale.
 * A missing locale falls back to the site fallback locale, then the primary site locale.
 */
trait HasTranslations
{
    /**
     * translation returns the row of the given or active locale
     */
    public function translation(?string $locale = null)
    {
        $rows = $this->translations;

        foreach (static::localeChain($locale) as $code) {
            if ($row = $rows->firstWhere('locale', $code)) {
                return $row;
            }
        }

        return $rows->first();
    }

    /**
     * trans returns one translated field
     */
    public function trans(string $field, ?string $locale = null)
    {
        return $this->translation($locale)?->{$field};
    }

    /**
     * translationsArray returns every locale as locale => fields, for the admin API
     */
    public function translationsArray(): array
    {
        $result = [];

        foreach ($this->translations as $row) {
            $result[$row->locale] = array_except($row->attributesToArray(), ['id', 'locale', $row->getForeignKeyName()]);
        }

        return $result;
    }

    /**
     * saveTranslations stores locale => fields rows and removes the locales that are not given
     */
    public function saveTranslations(array $translations)
    {
        $relation = $this->translations();
        $foreignKey = $relation->getForeignKeyName();

        foreach ($translations as $locale => $fields) {
            if (!is_array($fields)) {
                continue;
            }

            $relation->getRelated()->newQuery()->updateOrCreate(
                [$foreignKey => $this->getKey(), 'locale' => $locale],
                $fields
            );
        }

        $relation->whereNotIn('locale', array_keys($translations))->delete();

        $this->unsetRelation('translations');

        CatalogCache::bump();
    }

    /**
     * findBySlug looks in the active locale chain first, then in any locale.
     * The callback can narrow the query, for example to active records.
     */
    public static function findBySlug(string $slug, ?callable $scope = null)
    {
        foreach ([static::localeChain(), null] as $locales) {
            $query = static::query()->whereHas('translations', function ($q) use ($slug, $locales) {
                $q->where('slug', $slug);
                if ($locales) {
                    $q->whereIn('locale', $locales);
                }
            });

            if ($scope) {
                $scope($query);
            }

            if ($record = $query->first()) {
                return $record;
            }
        }

        return null;
    }

    /**
     * localeChain
     */
    public static function localeChain(?string $locale = null): array
    {
        $chain = [$locale ?: App::getLocale(), App::getFallbackLocale()];

        if ($primary = Site::getPrimarySite()) {
            $chain[] = $primary->locale;
        }

        return array_values(array_unique(array_filter($chain)));
    }
}
