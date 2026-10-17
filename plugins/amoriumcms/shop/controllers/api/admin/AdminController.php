<?php namespace AmoriumCms\Shop\Controllers\Api\Admin;

use Str;
use Site;
use Request;
use Validator;
use BackendAuth;
use AmoriumCms\Shop\Classes\Api\ApiCode;
use AmoriumCms\Shop\Classes\Api\ApiException;

/**
 * AdminController holds the helpers shared by the admin API controllers.
 */
abstract class AdminController
{
    /**
     * validate the request input
     */
    protected function validate(array $rules, ?array $input = null): array
    {
        return Validator::make($input ?? Request::all(), $rules)->validate();
    }

    /**
     * findOrFail throws NOT_FOUND
     */
    protected function findOrFail(string $class, $id, array $with = [])
    {
        $record = $class::with($with)->find($id);
        if (!$record) {
            throw ApiException::make(ApiCode::NOT_FOUND);
        }

        return $record;
    }

    /**
     * backendUserId
     */
    protected function backendUserId(): ?int
    {
        return BackendAuth::getUser()?->id;
    }

    /**
     * perPage
     */
    protected function perPage(int $default = 25): int
    {
        return min(100, max(1, (int) Request::query('per_page', $default)));
    }

    /**
     * siteIds lists the enabled sites
     */
    protected function siteIds(): array
    {
        return Site::listEnabled()->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    /**
     * siteId reads ?site_id= and falls back to the primary site
     */
    protected function siteId(bool $required = false): ?int
    {
        $siteId = Request::input('site_id');

        if ($siteId === null || $siteId === '') {
            if ($required) {
                return (int) Site::getPrimarySite()->id;
            }
            return null;
        }

        if (!in_array((int) $siteId, $this->siteIds(), true)) {
            throw ApiException::validation(['site_id' => __('The selected site is invalid.')]);
        }

        return (int) $siteId;
    }

    /**
     * locales lists the locales of the enabled sites, the primary site first
     */
    protected function locales(): array
    {
        $primary = Site::getPrimarySite()?->hard_locale;

        $locales = Site::listEnabled()->pluck('hard_locale')->filter()->unique()->values()->all();
        usort($locales, fn ($a, $b) => ($b === $primary) <=> ($a === $primary));

        return $locales;
    }

    /**
     * translations validates the "translations" input: locale => fields. The primary locale is required.
     * A missing slug is made from the name.
     *
     * @param array $rules field => rules, applied to every given locale
     * @param string|null $translationClass checks slug uniqueness per locale when given
     */
    protected function translations(array $rules, ?string $translationClass = null, ?int $ownerId = null, bool $required = true): ?array
    {
        $input = Request::input('translations');

        if ($input === null && !$required) {
            return null;
        }

        if (!is_array($input)) {
            throw ApiException::validation(['translations' => __('Translations are required.')]);
        }

        $locales = $this->locales();
        $errors = [];

        foreach (array_keys($input) as $locale) {
            if (!in_array($locale, $locales, true)) {
                $errors["translations.{$locale}"] = __('The locale :locale is not used by any site.', ['locale' => $locale]);
            }
        }

        $primary = $locales[0] ?? null;
        if ($primary && empty($input[$primary])) {
            $errors["translations.{$primary}"] = __('The primary language is required.');
        }

        if ($errors) {
            throw ApiException::validation($errors);
        }

        $result = [];
        foreach ($input as $locale => $fields) {
            $fields = is_array($fields) ? $fields : [];

            if (array_key_exists('slug', $rules)) {
                $fields['slug'] = Str::slug((string) (($fields['slug'] ?? '') !== '' ? $fields['slug'] : ($fields['name'] ?? '')));
            }

            $validator = Validator::make($fields, $rules);
            if ($validator->fails()) {
                foreach ($validator->errors()->toArray() as $field => $messages) {
                    $errors["translations.{$locale}.{$field}"] = $messages;
                }
                continue;
            }

            $result[$locale] = array_only($validator->validated(), array_keys($rules));

            if ($translationClass && isset($result[$locale]['slug'])) {
                $model = new $translationClass;
                $taken = $translationClass::where('locale', $locale)
                    ->where('slug', $result[$locale]['slug'])
                    ->when($ownerId, fn ($q) => $q->where($model->getForeignKeyName(), '<>', $ownerId))
                    ->exists();

                if ($taken) {
                    $errors["translations.{$locale}.slug"] = __('This slug is already used.');
                }
            }
        }

        if ($errors) {
            throw ApiException::validation($errors);
        }

        return $result;
    }

    /**
     * boolean reads a boolean input, also from multipart forms
     */
    protected function boolean(string $key, bool $default = false): bool
    {
        return Request::has($key) ? Request::boolean($key) : $default;
    }
}
