<?php namespace Amorium\Sections\Console;

use Illuminate\Console\Command;
use Amorium\Sections\Classes\SectionClassExporter;

class ExportSections extends Command
{
    protected $signature = 'amorium:sections-export';

    protected $description = 'Write section HTML to the active theme for the Tailwind build.';

    public function handle()
    {
        $path = SectionClassExporter::export();

        $path
            ? $this->info("Section HTML written to {$path}")
            : $this->warn('The active theme has no assets/src folder.');
    }
}
