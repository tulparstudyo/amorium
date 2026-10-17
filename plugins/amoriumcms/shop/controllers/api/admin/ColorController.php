<?php namespace AmoriumCms\Shop\Controllers\Api\Admin;

use Validator;
use AmoriumCms\Shop\Classes\Api\ApiException;
use AmoriumCms\Shop\Classes\Api\ApiResponse;
use AmoriumCms\Shop\Models\Color;

/**
 * ColorController lists colors and adds one from the product form.
 */
class ColorController extends AdminController
{
    /**
     * index
     */
    public function index()
    {
        $colors = Color::with('translations')->orderBy('sort_order')->orderBy('id')->get();

        return ApiResponse::ok($colors->map(fn ($color) => $this->present($color))->values()->all());
    }

    /**
     * store
     */
    public function store()
    {
        $validator = Validator::make(request()->all(), [
            'name' => 'required|string|max:255',
            'hex' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ]);

        if ($validator->fails()) {
            throw ApiException::validation($validator->errors()->toArray());
        }

        $data = $validator->validated();
        $color = Color::ensure($data['name'], $data['hex'] ?? null, $this->locales());

        return ApiResponse::created($this->present($color));
    }

    /**
     * present
     */
    protected function present(Color $color): array
    {
        return [
            'id' => $color->id,
            'code' => $color->code,
            'name' => $color->trans('name'),
            'hex' => $color->hex,
        ];
    }
}
