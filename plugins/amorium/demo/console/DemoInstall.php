<?php namespace Amorium\Demo\Console;

use Illuminate\Console\Command;
use Amorium\Demo\Classes\DemoContent;

class DemoInstall extends Command
{
    protected $signature = 'amorium:demo-install {--force : Install without asking for confirmation.}';

    protected $description = 'Replace the content of this site with the demo content of the Amorium theme.';

    public function handle()
    {
        if (!$this->option('force') && !$this->confirm('The sites, pages, posts, menus and sections of this site will be replaced. Continue?')) {
            return;
        }

        $counts = DemoContent::install();

        $this->info('Demo content installed.');
        $this->table(array_keys($counts), [$counts]);
    }
}
