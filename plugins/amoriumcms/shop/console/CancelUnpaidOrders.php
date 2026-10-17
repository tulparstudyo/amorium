<?php namespace AmoriumCms\Shop\Console;

use Site;
use Illuminate\Console\Command;
use AmoriumCms\Shop\Classes\OrderService;
use AmoriumCms\Shop\Models\Order;
use AmoriumCms\Shop\Models\ShopSetting;

/**
 * CancelUnpaidOrders cancels bank transfer orders that were not paid in time and returns their stock.
 */
class CancelUnpaidOrders extends Command
{
    protected $signature = 'shop:cancel-unpaid';

    protected $description = 'Cancel bank transfer orders that were not paid in time.';

    public function handle()
    {
        $count = 0;

        foreach (Site::listEnabled() as $site) {
            Site::withContext($site->id, function () use ($site, &$count) {
                $days = (int) ShopSetting::value('bank_transfer_days');

                $orders = Order::where('site_id', $site->id)
                    ->where('status', Order::STATUS_PENDING_PAYMENT)
                    ->where('payment_method', 'bank_transfer')
                    ->where('placed_at', '<', now()->subDays($days))
                    ->get();

                foreach ($orders as $order) {
                    OrderService::changeStatus($order, Order::STATUS_CANCELLED, __('Cancelled because the payment was not received in time.'), null, true);
                    $count++;
                }
            });
        }

        $this->info("Cancelled {$count} unpaid orders.");
    }
}
