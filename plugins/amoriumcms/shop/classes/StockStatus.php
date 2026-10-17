<?php namespace AmoriumCms\Shop\Classes;

use AmoriumCms\Shop\Models\Product;
use AmoriumCms\Shop\Models\ShopSetting;

/**
 * StockStatus is the only stock information shown to customers. The quantity is never shown.
 */
class StockStatus
{
    const IN_STOCK = 'in_stock';
    const LOW_STOCK = 'low_stock';
    const OUT_OF_STOCK = 'out_of_stock';

    /**
     * forQuantity
     */
    public static function forQuantity(int $quantity): string
    {
        if ($quantity <= 0) {
            return static::OUT_OF_STOCK;
        }

        if ($quantity < (int) ShopSetting::value('low_stock_threshold')) {
            return static::LOW_STOCK;
        }

        return static::IN_STOCK;
    }

    /**
     * forProduct counts an inactive barcode or one without a price on the active site as out of stock
     */
    public static function forProduct(Product $product): string
    {
        if (!$product->isSellable()) {
            return static::OUT_OF_STOCK;
        }

        return static::forQuantity((int) $product->stock_quantity);
    }

    /**
     * combine returns the status of a group from the status of its barcodes
     */
    public static function combine(array $statuses): string
    {
        if (in_array(static::IN_STOCK, $statuses, true)) {
            return static::IN_STOCK;
        }

        if (in_array(static::LOW_STOCK, $statuses, true)) {
            return static::LOW_STOCK;
        }

        return static::OUT_OF_STOCK;
    }
}
