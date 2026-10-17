<?php namespace AmoriumCms\Shop\Console;

use Illuminate\Console\Command;
use AmoriumCms\Shop\Classes\Import\ImportQueue as Queue;

/**
 * ImportQueue runs the queued product links in order.
 */
class ImportQueue extends Command
{
    protected $signature = 'shop:import-queue';

    protected $description = 'Import the queued product links, one at a time.';

    public function handle()
    {
        $count = Queue::drain();

        $this->info("Processed {$count} imports.");
    }
}
