<?php namespace Amorium\Translate;

use System\Classes\PluginBase;
use Amorium\Translate\Classes\EntryTranslator;
use Amorium\Translate\Classes\BulkTranslation;
use Amorium\Translate\Classes\ContentReviewer;
use Amorium\Translate\Classes\SiteTranslation;

class Plugin extends PluginBase
{
    public $require = ['Amorium.Pages', 'Amorium.Sections', 'Amorium.Post', 'Amorium.Navigation'];

    public function pluginDetails()
    {
        return [
            'name' => 'Amorium Translate',
            'description' => 'Translates entries from the primary site to the other sites',
            'author' => 'Amorium',
            'icon' => 'icon-language'
        ];
    }

    public function boot()
    {
        EntryTranslator::boot();
        BulkTranslation::boot();
        ContentReviewer::boot();
        SiteTranslation::boot();
    }
}
