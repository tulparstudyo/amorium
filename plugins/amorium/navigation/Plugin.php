<?php namespace Amorium\Navigation;

use System\Classes\PluginBase;

class Plugin extends PluginBase
{
    public function pluginDetails()
    {
        return [
            'name' => 'Amorium Navigation',
            'description' => 'Nested navigation menus managed with blueprints',
            'author' => 'Amorium',
            'icon' => 'icon-sitemap'
        ];
    }

    public function registerComponents()
    {
        return [
            \Amorium\Navigation\Components\Navigation::class => 'navigation'
        ];
    }
}
