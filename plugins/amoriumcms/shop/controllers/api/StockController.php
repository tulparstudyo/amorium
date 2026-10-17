<?php namespace AmoriumCms\Shop\Controllers\Api;

use Request;
use AmoriumCms\Shop\Classes\StockStatus;
use AmoriumCms\Shop\Classes\Api\ApiResponse;
use AmoriumCms\Shop\Classes\Api\ApiException;
use AmoriumCms\Shop\Models\Product;

/**
 * StockController returns stock status, never quantities. It is never cached.
 * Usage: ?products=12,13 for barcodes, ?groups=4,5 for product groups.
 */
class StockController
{
    const MAX_IDS = 100;

    public function index()
    {
        $productIds = $this->ids('products');
        $groupIds = $this->ids('groups');

        $result = ['products' => (object) [], 'groups' => (object) []];

        if ($productIds) {
            $products = Product::with(['group', 'prices'])->whereIn('id', $productIds)->get()->keyBy('id');

            $statuses = [];
            foreach ($productIds as $id) {
                $product = $products->get($id);
                $statuses[$id] = $product ? StockStatus::forProduct($product) : StockStatus::OUT_OF_STOCK;
            }
            $result['products'] = (object) $statuses;
        }

        if ($groupIds) {
            $products = Product::with(['group', 'prices'])->whereIn('group_id', $groupIds)->get()->groupBy('group_id');

            $statuses = [];
            foreach ($groupIds as $id) {
                $statuses[$id] = StockStatus::combine(
                    $products->get($id, collect())->map(fn ($product) => StockStatus::forProduct($product))->all()
                );
            }
            $result['groups'] = (object) $statuses;
        }

        return ApiResponse::ok($result);
    }

    /**
     * ids reads a comma separated id list
     */
    protected function ids(string $name): array
    {
        $value = (string) Request::query($name, '');
        if ($value === '') {
            return [];
        }

        $ids = array_values(array_unique(array_filter(array_map('intval', explode(',', $value)))));

        if (count($ids) > static::MAX_IDS) {
            throw ApiException::validation([$name => __('At most :count ids can be requested at once.', ['count' => static::MAX_IDS])]);
        }

        return $ids;
    }
}
