<?php namespace AmoriumCms\Shop\Console;

use Illuminate\Console\Command;
use AmoriumCms\Shop\Models\Cart;

/**
 * PurgeCarts deletes the expired carts that were never ordered.
 */
class PurgeCarts extends Command
{
    protected $signature = 'shop:purge-carts';

    protected $description = 'Delete expired carts.';

    public function handle()
    {
        $count = 0;

        Cart::isOpen()->where('expires_at', '<', now())->chunkById(200, function ($carts) use (&$count) {
            foreach ($carts as $cart) {
                $cart->delete();
                $count++;
            }
        });

        $this->info("Deleted {$count} expired carts.");
    }
}
