<?php namespace AmoriumCms\Shop\Classes;

use Db;
use AmoriumCms\Shop\Classes\Api\ApiCode;
use AmoriumCms\Shop\Classes\Api\ApiException;
use AmoriumCms\Shop\Models\Order;
use AmoriumCms\Shop\Models\Product;
use AmoriumCms\Shop\Models\StockMovement;

/**
 * StockService changes stock on locked rows and records every change. The quantity is
 * written with a query so the storefront cache is not dropped on every order.
 */
class StockService
{
    /**
     * adjust changes the stock of one barcode by hand
     */
    public static function adjust(int $productId, int $change, ?int $backendUserId = null, ?string $note = null): int
    {
        return Db::transaction(function () use ($productId, $change, $backendUserId, $note) {
            $product = Product::lockForUpdate()->findOrFail($productId);
            $stock = (int) $product->stock_quantity + $change;

            if ($stock < 0) {
                throw ApiException::validation(['quantity_change' => __('Stock cannot go below zero.')]);
            }

            static::write($product->id, $change, $stock, StockMovement::REASON_MANUAL, null, $backendUserId, $note);

            return $stock;
        });
    }

    /**
     * reserve takes the stock of an order. It must run inside the order transaction,
     * so a shortage rolls the order back. Shortages are reported by status, never by quantity.
     *
     * @param array $lines product id => quantity
     */
    public static function reserve(array $lines, int $orderId)
    {
        ksort($lines);

        $products = Product::with(['group', 'prices'])
            ->whereIn('id', array_keys($lines))
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        $shortages = [];
        foreach ($lines as $productId => $quantity) {
            $product = $products->get($productId);

            if (!$product || !$product->isSellable() || $product->stock_quantity < $quantity) {
                $shortages[] = static::shortage($product, $productId);
            }
        }

        if ($shortages) {
            throw ApiException::make(ApiCode::OUT_OF_STOCK, ['items' => $shortages]);
        }

        foreach ($lines as $productId => $quantity) {
            $product = $products->get($productId);
            static::write($product->id, -$quantity, $product->stock_quantity - $quantity, StockMovement::REASON_ORDER, $orderId);
        }
    }

    /**
     * release returns the stock of a cancelled order
     */
    public static function release(Order $order, ?int $backendUserId = null)
    {
        Db::transaction(function () use ($order, $backendUserId) {
            foreach ($order->items()->whereNotNull('product_id')->orderBy('product_id')->get() as $item) {
                $product = Product::lockForUpdate()->find($item->product_id);
                if (!$product) {
                    continue;
                }

                static::write($product->id, $item->quantity, $product->stock_quantity + $item->quantity, StockMovement::REASON_CANCEL, $order->id, $backendUserId);
            }
        });
    }

    /**
     * shortage describes a barcode that cannot be delivered in the requested quantity
     */
    public static function shortage(?Product $product, int $productId): array
    {
        return [
            'product_id' => $productId,
            'barcode' => $product?->barcode,
            'stock_status' => $product ? StockStatus::forProduct($product) : StockStatus::OUT_OF_STOCK,
        ];
    }

    /**
     * write
     */
    protected static function write(int $productId, int $change, int $stock, string $reason, ?int $orderId = null, ?int $backendUserId = null, ?string $note = null)
    {
        Product::where('id', $productId)->update(['stock_quantity' => $stock]);

        StockMovement::create([
            'product_id' => $productId,
            'quantity_change' => $change,
            'stock_after' => $stock,
            'reason' => $reason,
            'order_id' => $orderId,
            'backend_user_id' => $backendUserId,
            'note' => $note,
        ]);
    }
}
