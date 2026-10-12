<?php namespace AmoriumCms\Amorium\Console;

use Illuminate\Console\Command;
use AmoriumCms\Amorium\Classes\DemoContent;

class DemoExport extends Command
{
    protected $signature = 'amorium:demo-export';

    protected $description = 'Save the content of this site as the demo content of the Amorium theme.';

    public function handle()
    {
        $counts = DemoContent::export();

        $this->info('Demo content saved to ' . DemoContent::path());
        $this->table(array_keys($counts), [$counts]);
    }
}
