<?php namespace AmoriumCms\Shop\Controllers\Api\Admin;

use Db;
use Request;
use AmoriumCms\Shop\Classes\Api\ApiCode;
use AmoriumCms\Shop\Classes\Api\ApiResponse;
use AmoriumCms\Shop\Classes\Api\ApiException;
use AmoriumCms\Shop\Models\Brand;
use AmoriumCms\Shop\Models\BrandTranslation;

/**
 * BrandController manages brands. The logo is uploaded as multipart form data,
 * so updates also accept POST.
 */
class BrandController extends AdminController
{
    /**
     * index
     */
    public function index()
    {
        $brands = Brand::with(['translations', 'logo'])
            ->withCount('groups')
            ->when(Request::query('q'), fn ($q, $term) => $q->where(fn ($q) => $q
                ->where('code', 'like', "%{$term}%")
                ->orWhereHas('translations', fn ($q) => $q->where('name', 'like', "%{$term}%"))))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return ApiResponse::ok($brands->map(fn ($brand) => $this->present($brand))->values()->all());
    }

    /**
     * show
     */
    public function show(int $id)
    {
        return ApiResponse::ok($this->present($this->findOrFail(Brand::class, $id, ['translations', 'logo'])));
    }

    /**
     * store
     */
    public function store()
    {
        $brand = Db::transaction(function () {
            $brand = new Brand;
            $this->save($brand);

            return $brand;
        });

        return ApiResponse::created($this->present($brand->fresh(['translations', 'logo'])));
    }

    /**
     * update
     */
    public function update(int $id)
    {
        $brand = $this->findOrFail(Brand::class, $id);

        Db::transaction(fn () => $this->save($brand));

        return ApiResponse::ok($this->present($brand->fresh(['translations', 'logo'])));
    }

    /**
     * destroy is refused while products use the brand
     */
    public function destroy(int $id)
    {
        $brand = $this->findOrFail(Brand::class, $id);

        if ($brand->groups()->exists()) {
            throw ApiException::make(ApiCode::CONFLICT, null, __('The brand is used by products.'));
        }

        $brand->delete();

        return ApiResponse::ok();
    }

    /**
     * save
     */
    protected function save(Brand $brand)
    {
        $input = $this->validate([
            'code' => ($brand->exists ? 'sometimes|' : '') . 'required|string|max:64|alpha_dash|unique:amoriumcms_shop_brands,code' . ($brand->exists ? ",{$brand->id}" : ''),
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'logo' => 'nullable|image|max:4096',
            'remove_logo' => 'boolean',
        ]);

        $translations = $this->translations([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255',
            'description' => 'nullable|string',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:512',
        ], BrandTranslation::class, $brand->id, !$brand->exists);

        $brand->fill(array_only($input, ['code', 'is_active', 'sort_order']));
        $brand->save();

        if ($translations !== null) {
            $brand->saveTranslations($translations);
        }

        if (Request::hasFile('logo')) {
            $brand->logo?->delete();
            $brand->logo = Request::file('logo');
            $brand->save();
        }
        elseif (!empty($input['remove_logo'])) {
            $brand->logo?->delete();
        }
    }

    /**
     * present
     */
    protected function present(Brand $brand): array
    {
        return [
            'id' => $brand->id,
            'code' => $brand->code,
            'is_active' => $brand->is_active,
            'sort_order' => $brand->sort_order,
            'name' => $brand->trans('name'),
            'logo' => $brand->logo ? [
                'url' => $brand->logo->getPath(),
                'thumb' => $brand->logo->getThumb(160, 160, ['mode' => 'auto']),
            ] : null,
            'translations' => (object) $brand->translationsArray(),
            'groups_count' => $brand->groups_count ?? $brand->groups()->count(),
        ];
    }
}
