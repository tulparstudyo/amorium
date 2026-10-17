<?php namespace AmoriumCms\Shop\Controllers\Api\Admin;

use Site;
use AmoriumCms\Shop\Classes\Money;
use AmoriumCms\Shop\Classes\Api\ApiResponse;
use AmoriumCms\Shop\Models\Order;
use AmoriumCms\Shop\Models\Review;
use AmoriumCms\Shop\Models\Payment;
use AmoriumCms\Shop\Models\Product;
use AmoriumCms\Shop\Models\ShopSetting;

/**
 * DashboardController sums up the orders of one site: ?site_id=, primary site by default.
 * Totals are not converted between currencies, so they are always given for one site.
 */
class DashboardController extends AdminController
{
    /**
     * index
     */
    public function index()
    {
        $siteId = $this->siteId(true);
        $orders = fn () => Order::where('site_id', $siteId);
        $valid = fn () => $orders()->isCounted();

        $today = now()->startOfDay();
        $from = now()->subDays(29)->startOfDay();

        $daily = $valid()
            ->where('placed_at', '>=', $from)
            ->groupByRaw('date(placed_at)')
            ->selectRaw('date(placed_at) as day, count(*) as orders_count, sum(grand_total) as revenue')
            ->get()
            ->keyBy('day');

        $days = [];
        for ($date = $from->copy(); $date <= $today; $date->addDay()) {
            $row = $daily->get($date->toDateString());
            $days[] = [
                'date' => $date->toDateString(),
                'orders_count' => (int) ($row->orders_count ?? 0),
                'revenue' => Money::toFloat($row->revenue ?? 0),
            ];
        }

        $threshold = (int) Site::withContext($siteId, fn () => ShopSetting::value('low_stock_threshold'));

        return ApiResponse::ok([
            'site_id' => $siteId,
            'currency' => Site::withContext($siteId, fn () => ShopSetting::value('currency')),
            'today' => [
                'orders_count' => $valid()->where('placed_at', '>=', $today)->count(),
                'revenue' => Money::toFloat($valid()->where('placed_at', '>=', $today)->sum('grand_total')),
            ],
            'last_30_days' => [
                'orders_count' => array_sum(array_column($days, 'orders_count')),
                'revenue' => Money::toFloat(Money::add(...array_map(fn ($day) => (string) $day['revenue'], $days))),
                'days' => $days,
            ],
            'status_counts' => (object) $orders()->groupBy('status')->selectRaw('status, count(*) as total')->pluck('total', 'status')->map(fn ($total) => (int) $total)->all(),
            'awaiting_bank_transfer' => $orders()
                ->where('status', Order::STATUS_PENDING_PAYMENT)
                ->where('payment_method', 'bank_transfer')
                ->whereHas('payments', fn ($q) => $q->where('status', Payment::STATUS_PENDING))
                ->count(),
            'pending_reviews' => Review::where('status', Review::STATUS_PENDING)->count(),
            'stock' => [
                'out_of_stock' => Product::isActive()->where('stock_quantity', '<=', 0)->count(),
                'low_stock' => Product::isActive()->where('stock_quantity', '>', 0)->where('stock_quantity', '<', $threshold)->count(),
            ],
        ]);
    }
}
