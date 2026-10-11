<?php namespace Amorium\Sections\Console;

use Illuminate\Console\Command;
use Amorium\Sections\Classes\BaseSections;

class SaveBaseSections extends Command
{
    protected $signature = 'amorium:sections-save-base';

    protected $description = 'Write the current sections to the base sections JSON file.';

    public function handle()
    {
        $count = BaseSections::export();

        $this->info("{$count} sections written to " . BaseSections::path());
    }
}
