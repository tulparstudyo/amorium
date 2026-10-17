<?php namespace AmoriumCms\Shop\Controllers\Api;

use Db;
use Auth;
use Request;
use Validator;
use AmoriumCms\Shop\Classes\Money;
use AmoriumCms\Shop\Classes\Presenter;
use AmoriumCms\Shop\Classes\StockStatus;
use AmoriumCms\Shop\Classes\Api\ApiCode;
use AmoriumCms\Shop\Classes\Api\ApiResponse;
use AmoriumCms\Shop\Classes\Api\ApiException;
use AmoriumCms\Shop\Models\Product;

/**
 * FavoriteController keeps the favorite barcodes of the customer, so the chosen variant is kept.
 * Adding a favorite twice or removing a missing one is not an error.
 */
class FavoriteController
{
    const TABLE = 'amoriumcms_shop_favorites';

    /**
     * index
     */
    public function index()
    {
        $productIds = Db::table(static::TABLE)->where('user_id', Auth::id())->orderBy('created_at', 'desc')->pluck('product_id');

        $products = Product::with([
            'group.translations',
            'group.images.image',
            'prices',
            'spec_values.spec.translations',
            'spec_values.option.translations',
        ])->whereIn('id', $productIds)->get()->keyBy('id');

        $data = [];
        foreach ($productIds as $id) {
            if (!$product = $products->get($id)) {
                continue;
            }

            $price = $product->priceFor();
            $image = $product->group?->images->first(fn ($image) => $image->image);

            $data[] = [
                'product_id' => $product->id,
                'barcode' => $product->barcode,
                'group_id' => $product->group_id,
                'slug' => $product->group?->trans('slug'),
                'name' => $product->group?->trans('name'),
                'variant' => (object) $product->variantLabel(),
                'image' => $image ? Presenter::image($image) : null,
                'price' => Money::toFloat($price?->price),
                'compare_at_price' => Money::toFloat($price?->compare_at_price),
                'currency' => Presenter::currency(),
                'stock_status' => StockStatus::forProduct($product),
            ];
        }

        return ApiResponse::ok($data);
    }

    /**
     * store
     */
    public function store()
    {
        $data = Validator::make(Request::all(), ['product_id' => 'required|integer'])->validate();

        if (!Product::where('id', $data['product_id'])->exists()) {
            throw ApiException::make(ApiCode::NOT_FOUND);
        }

        Db::table(static::TABLE)->insertOrIgnore([
            'user_id' => Auth::id(),
            'product_id' => $data['product_id'],
            'created_at' => now(),
        ]);

        return ApiResponse::ok(['product_id' => (int) $data['product_id']]);
    }

    /**
     * destroy
     */
    public function destroy(int $productId)
    {
        Db::table(static::TABLE)->where('user_id', Auth::id())->where('product_id', $productId)->delete();

        return ApiResponse::ok();
    }
}
