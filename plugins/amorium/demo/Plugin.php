<?php namespace Amorium\Demo;

use Backend;
use System\Classes\PluginBase;
use System\Classes\SettingsManager;

class Plugin extends PluginBase
{
    public $require = ['Amorium.Pages', 'Amorium.Sections', 'Amorium.Post', 'Amorium.Navigation', 'Amorium.Business'];

    public function pluginDetails()
    {
        return [
            'name' => 'Amorium Demo Content',
            'description' => 'Saves the content of a site as demo content and installs it on a new site',
            'author' => 'Amorium',
            'icon' => 'icon-magic'
        ];
    }

    public function register()
    {
        $this->registerConsoleCommand('amorium.demo-export', \Amorium\Demo\Console\DemoExport::class);
        $this->registerConsoleCommand('amorium.demo-install', \Amorium\Demo\Console\DemoInstall::class);
    }

    public function registerPermissions()
    {
        return [
            'amorium.demo.manage' => [
                'tab' => 'Demo Content',
                'label' => 'Save and install demo content'
            ]
        ];
    }

    public function registerSettings()
    {
        return [
            'demo' => [
                'label' => 'Demo Content',
                'description' => 'Install the demo pages, posts, menus and sections of the Amorium theme.',
                'category' => SettingsManager::CATEGORY_CMS,
                'icon' => 'icon-magic',
                'url' => Backend::url('amorium/demo/content'),
                'permissions' => ['amorium.demo.manage'],
                'order' => 600,
                'keywords' => 'demo content seed install export'
            ]
        ];
    }
}
