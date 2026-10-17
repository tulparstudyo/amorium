<?php namespace AmoriumCms\Shop\Controllers\Api\Admin;

use Db;
use AmoriumCms\Shop\Classes\Api\ApiCode;
use AmoriumCms\Shop\Classes\Api\ApiResponse;
use AmoriumCms\Shop\Classes\Api\ApiException;
use AmoriumCms\Shop\Models\Category;
use AmoriumCms\Shop\Models\ProductGroup;
use AmoriumCms\Shop\Models\CategoryTranslation;

/**
 * CategoryController manages the category tree and the specs of each category.
 */
class CategoryController extends AdminController
{
    /**
     * index returns the whole tree as a flat list in tree order
     */
    public function index()
    {
        $categories = Category::with(['translations', 'specs'])
            ->withCount('groups')
            ->orderBy('nest_left')
            ->get();

        return ApiResponse::ok($categories->map(fn ($category) => $this->present($category))->values()->all());
    }

    /**
     * show
     */
    public function show(int $id)
    {
        return ApiResponse::ok($this->present($this->findOrFail(Category::class, $id, ['translations', 'specs'])));
    }

    /**
     * store
     */
    public function store()
    {
        $category = Db::transaction(function () {
            $category = new Category;
            $this->save($category);

            return $category;
        });

        return ApiResponse::created($this->present($category->fresh(['translations', 'specs'])));
    }

    /**
     * update
     */
    public function update(int $id)
    {
        $category = $this->findOrFail(Category::class, $id);

        Db::transaction(fn () => $this->save($category));

        return ApiResponse::ok($this->present($category->fresh(['translations', 'specs'])));
    }

    /**
     * reorder moves a category before, after or into another one
     */
    public function reorder()
    {
        $input = $this->validate([
            'id' => 'required|integer',
            'target_id' => 'nullable|integer',
            'position' => 'required|in:before,after,child,root',
        ]);

        $category = $this->findOrFail(Category::class, $input['id']);

        if ($input['position'] === 'root') {
            $category->makeRoot();
        }
        else {
            $target = $this->findOrFail(Category::class, $input['target_id'] ?? 0);

            if ($target->id === $category->id || $target->isDescendantOf($category)) {
                throw ApiException::validation(['target_id' => __('A category cannot be moved into itself.')]);
            }

            match ($input['position']) {
                'before' => $category->moveBefore($target),
                'after' => $category->moveAfter($target),
                'child' => $category->makeChildOf($target),
            };
        }

        return $this->index();
    }

    /**
     * destroy is refused while the category or a subcategory has products
     */
    public function destroy(int $id)
    {
        $category = $this->findOrFail(Category::class, $id);
        $ids = $category->getAllChildrenAndSelf()->pluck('id')->all();

        $inUse = Db::table('amoriumcms_shop_category_group')->whereIn('category_id', $ids)->exists()
            || ProductGroup::whereIn('primary_category_id', $ids)->exists();

        if ($inUse) {
            throw ApiException::make(ApiCode::CONFLICT, null, __('The category or one of its subcategories has products.'));
        }

        Db::transaction(function () use ($category, $ids) {
            Db::table('amoriumcms_shop_category_spec')->whereIn('category_id', $ids)->delete();
            $category->delete();
        });

        return ApiResponse::ok();
    }

    /**
     * save
     */
    protected function save(Category $category)
    {
        $input = $this->validate([
            'parent_id' => 'nullable|integer|exists:amoriumcms_shop_categories,id',
            'is_active' => 'boolean',
            'specs' => 'array',
            'specs.*.id' => 'required|integer|exists:amoriumcms_shop_specs,id',
            'specs.*.is_required' => 'boolean',
        ]);

        $translations = $this->translations([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255',
            'description' => 'nullable|string',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:512',
        ], CategoryTranslation::class, $category->id, !$category->exists);

        $parentId = $input['parent_id'] ?? null;
        if ($category->exists && $parentId) {
            $parent = Category::find($parentId);
            if ($parent->id === $category->id || $parent->isDescendantOf($category)) {
                throw ApiException::validation(['parent_id' => __('A category cannot be moved into itself.')]);
            }
        }

        if (array_key_exists('is_active', $input)) {
            $category->is_active = $input['is_active'];
        }

        if (!$category->exists || array_key_exists('parent_id', $input)) {
            $category->parent_id = $parentId;
        }

        $category->save();

        if ($translations !== null) {
            $category->saveTranslations($translations);
        }

        if (array_key_exists('specs', $input)) {
            $category->specs()->sync(collect($input['specs'])->mapWithKeys(fn ($spec) => [
                $spec['id'] => ['is_required' => (bool) ($spec['is_required'] ?? false)],
            ])->all());
        }
    }

    /**
     * present
     */
    protected function present(Category $category): array
    {
        return [
            'id' => $category->id,
            'parent_id' => $category->parent_id,
            'depth' => (int) $category->nest_depth,
            'is_active' => $category->is_active,
            'name' => $category->trans('name'),
            'translations' => (object) $category->translationsArray(),
            'specs' => $category->specs->map(fn ($spec) => [
                'id' => $spec->id,
                'is_required' => (bool) $spec->pivot->is_required,
            ])->values()->all(),
            'groups_count' => $category->groups_count ?? $category->groups()->count(),
        ];
    }
}
