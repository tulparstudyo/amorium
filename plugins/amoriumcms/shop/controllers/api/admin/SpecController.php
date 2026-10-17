<?php namespace AmoriumCms\Shop\Controllers\Api\Admin;

use Db;
use Request;
use AmoriumCms\Shop\Classes\VariantService;
use AmoriumCms\Shop\Classes\Api\ApiCode;
use AmoriumCms\Shop\Classes\Api\ApiResponse;
use AmoriumCms\Shop\Classes\Api\ApiException;
use AmoriumCms\Shop\Models\Spec;
use AmoriumCms\Shop\Models\SpecOption;
use AmoriumCms\Shop\Models\ProductImage;
use AmoriumCms\Shop\Models\ProductSpecValue;

/**
 * SpecController manages specs and their options. Turning the variant flag on or off
 * rewrites the variant keys, and is refused when a group would end up with two equal variants.
 */
class SpecController extends AdminController
{
    /**
     * index
     */
    public function index()
    {
        $specs = Spec::with(['translations', 'options.translations'])
            ->withCount('values')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return ApiResponse::ok($specs->map(fn ($spec) => $this->present($spec))->values()->all());
    }

    /**
     * show
     */
    public function show(int $id)
    {
        return ApiResponse::ok($this->present($this->findOrFail(Spec::class, $id, ['translations', 'options.translations'])));
    }

    /**
     * store can create the options in the same request
     */
    public function store()
    {
        $input = $this->validate([
            'code' => 'required|string|max:64|alpha_dash|unique:amoriumcms_shop_specs,code',
            'type' => 'required|in:' . implode(',', Spec::TYPES),
            'unit' => 'nullable|string|max:16',
            'is_variant' => 'boolean',
            'is_filterable' => 'boolean',
            'sort_order' => 'integer',
            'options' => 'array',
        ]);

        if (!empty($input['is_variant']) && $input['type'] !== Spec::TYPE_SELECT) {
            throw ApiException::validation(['is_variant' => __('Only select specs can be variant specs.')]);
        }

        $translations = $this->translations(['name' => 'required|string|max:255']);

        $spec = Db::transaction(function () use ($input, $translations) {
            $spec = Spec::create(array_only($input, ['code', 'type', 'unit', 'is_variant', 'is_filterable', 'sort_order']));
            $spec->saveTranslations($translations);

            if ($spec->type === Spec::TYPE_SELECT) {
                foreach ($input['options'] ?? [] as $index => $option) {
                    $this->saveOption($spec, new SpecOption, is_array($option) ? $option : [], "options.{$index}.");
                }
            }

            return $spec;
        });

        return ApiResponse::created($this->present($spec->fresh(['translations', 'options.translations'])));
    }

    /**
     * update. A changed variant flag needs a clean impact report.
     */
    public function update(int $id)
    {
        $spec = $this->findOrFail(Spec::class, $id);

        $input = $this->validate([
            'code' => "sometimes|required|string|max:64|alpha_dash|unique:amoriumcms_shop_specs,code,{$spec->id}",
            'type' => 'sometimes|required|in:' . implode(',', Spec::TYPES),
            'unit' => 'nullable|string|max:16',
            'is_variant' => 'boolean',
            'is_filterable' => 'boolean',
            'sort_order' => 'integer',
        ]);

        $translations = $this->translations(['name' => 'required|string|max:255'], null, null, false);

        $type = $input['type'] ?? $spec->type;
        $isVariant = array_key_exists('is_variant', $input) ? (bool) $input['is_variant'] : $spec->is_variant;

        if ($type !== $spec->type && $spec->values()->exists()) {
            throw ApiException::validation(['type' => __('The type cannot change while products have values for this spec.')]);
        }

        if ($isVariant && $type !== Spec::TYPE_SELECT) {
            throw ApiException::validation(['is_variant' => __('Only select specs can be variant specs.')]);
        }

        $flagChanged = $isVariant !== $spec->is_variant;
        if ($flagChanged && ($problems = VariantService::impact($spec, $isVariant))) {
            throw ApiException::make(ApiCode::VARIANT_CONFLICT, ['groups' => $problems]);
        }

        Db::transaction(function () use ($spec, $input, $translations, $flagChanged) {
            $spec->fill(array_only($input, ['code', 'type', 'unit', 'is_variant', 'is_filterable', 'sort_order']));
            $spec->save();

            if ($translations !== null) {
                $spec->saveTranslations($translations);
            }

            if ($flagChanged) {
                VariantService::refreshSpec($spec);
            }
        });

        return ApiResponse::ok($this->present($spec->fresh(['translations', 'options.translations'])));
    }

    /**
     * variantImpact previews the groups that a flag change would break: ?is_variant=1
     */
    public function variantImpact(int $id)
    {
        $spec = $this->findOrFail(Spec::class, $id);
        $isVariant = Request::has('is_variant') ? Request::boolean('is_variant') : !$spec->is_variant;

        return ApiResponse::ok([
            'is_variant' => $isVariant,
            'groups' => $isVariant === $spec->is_variant ? [] : VariantService::impact($spec, $isVariant),
        ]);
    }

    /**
     * destroy is refused while products have values for the spec
     */
    public function destroy(int $id)
    {
        $spec = $this->findOrFail(Spec::class, $id);

        if ($spec->values()->exists()) {
            throw ApiException::make(ApiCode::CONFLICT, null, __('Products have values for this spec.'));
        }

        Db::transaction(function () use ($spec) {
            ProductImage::whereIn('spec_option_id', $spec->options()->pluck('id'))->update(['spec_option_id' => null]);
            Db::table('amoriumcms_shop_category_spec')->where('spec_id', $spec->id)->delete();
            $spec->delete();
        });

        return ApiResponse::ok();
    }

    /**
     * storeOption
     */
    public function storeOption(int $id)
    {
        $spec = $this->findOrFail(Spec::class, $id);

        if ($spec->type !== Spec::TYPE_SELECT) {
            throw ApiException::validation(['type' => __('Only select specs have options.')]);
        }

        $option = Db::transaction(fn () => $this->saveOption($spec, new SpecOption, Request::all()));

        return ApiResponse::created($this->presentOption($option->fresh('translations')));
    }

    /**
     * updateOption
     */
    public function updateOption(int $id, int $optionId)
    {
        $spec = $this->findOrFail(Spec::class, $id);
        $option = $spec->options()->find($optionId);
        if (!$option) {
            throw ApiException::make(ApiCode::NOT_FOUND);
        }

        Db::transaction(fn () => $this->saveOption($spec, $option, Request::all()));

        return ApiResponse::ok($this->presentOption($option->fresh('translations')));
    }

    /**
     * destroyOption is refused while products use the option
     */
    public function destroyOption(int $id, int $optionId)
    {
        $spec = $this->findOrFail(Spec::class, $id);
        $option = $spec->options()->find($optionId);
        if (!$option) {
            throw ApiException::make(ApiCode::NOT_FOUND);
        }

        if (ProductSpecValue::where('spec_option_id', $option->id)->exists()) {
            throw ApiException::make(ApiCode::CONFLICT, null, __('Products use this option.'));
        }

        Db::transaction(function () use ($option) {
            ProductImage::where('spec_option_id', $option->id)->update(['spec_option_id' => null]);
            $option->delete();
        });

        return ApiResponse::ok();
    }

    /**
     * saveOption validates and stores one option. Errors are reported under the given prefix.
     */
    protected function saveOption(Spec $spec, SpecOption $option, array $input, string $prefix = ''): SpecOption
    {
        $validator = \Validator::make($input, [
            'code' => ($option->exists ? 'sometimes|' : '') . 'required|string|max:64|alpha_dash',
            'color_hex' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'sort_order' => 'integer',
            'translations' => ($option->exists ? 'sometimes|' : '') . 'required|array',
            'translations.*.label' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            $errors = [];
            foreach ($validator->errors()->toArray() as $field => $messages) {
                $errors[$prefix . $field] = $messages;
            }
            throw ApiException::validation($errors);
        }

        $data = $validator->validated();

        if (isset($data['code'])) {
            $taken = $spec->options()->where('code', $data['code'])
                ->when($option->exists, fn ($q) => $q->where('id', '<>', $option->id))
                ->exists();

            if ($taken) {
                throw ApiException::validation([$prefix . 'code' => __('This code is already used.')]);
            }
        }

        $locales = $this->locales();
        foreach (array_keys($data['translations'] ?? []) as $locale) {
            if (!in_array($locale, $locales, true)) {
                throw ApiException::validation([$prefix . "translations.{$locale}" => __('The locale :locale is not used by any site.', ['locale' => $locale])]);
            }
        }

        $option->fill(array_only($data, ['code', 'color_hex', 'sort_order']));
        if (!$option->exists && !array_key_exists('sort_order', $data)) {
            $option->sort_order = (int) $spec->options()->max('sort_order') + 1;
        }
        $option->spec_id = $spec->id;
        $option->save();

        if (isset($data['translations'])) {
            $option->saveTranslations(array_map(fn ($fields) => ['label' => $fields['label']], $data['translations']));
        }

        return $option;
    }

    /**
     * present
     */
    protected function present(Spec $spec): array
    {
        return [
            'id' => $spec->id,
            'code' => $spec->code,
            'type' => $spec->type,
            'unit' => $spec->unit,
            'is_variant' => $spec->is_variant,
            'is_filterable' => $spec->is_filterable,
            'sort_order' => $spec->sort_order,
            'name' => $spec->trans('name'),
            'translations' => (object) $spec->translationsArray(),
            'options' => $spec->options->map(fn ($option) => $this->presentOption($option))->values()->all(),
            'values_count' => $spec->values_count ?? $spec->values()->count(),
        ];
    }

    /**
     * presentOption
     */
    protected function presentOption(SpecOption $option): array
    {
        return [
            'id' => $option->id,
            'code' => $option->code,
            'color_hex' => $option->color_hex,
            'sort_order' => $option->sort_order,
            'label' => $option->trans('label'),
            'translations' => (object) $option->translationsArray(),
        ];
    }
}
