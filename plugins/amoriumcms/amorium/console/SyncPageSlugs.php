<?php namespace AmoriumCms\Amorium\Console;

use Illuminate\Console\Command;
use AmoriumCms\Amorium\Classes\PageSlugs;

class SyncPageSlugs extends Command
{
    protected $signature = 'amorium:pages-sync-slugs';

    protected $description = 'Rebuild the full slugs of the pages of every site.';

    public function handle()
    {
        $count = PageSlugs::sync();

        $this->info("{$count} page full slugs updated.");
    }
}
