<?php namespace Amorium\Business;

use Event;
use Backend;
use System\Classes\PluginBase;
use System\Classes\SettingsManager;
use Amorium\Business\Classes\SitesExtension;
use Amorium\Business\Models\BusinessSetting;

class Plugin extends PluginBase
{
    public function pluginDetails()
    {
        return [
            'name' => 'Amorium Business',
            'description' => 'Business details stored for each site',
            'author' => 'Amorium',
            'icon' => 'icon-building'
        ];
    }

    public function boot()
    {
        SitesExtension::boot();

        Event::listen('backend.menu.extendItems', function ($manager) {
            $manager->addSideMenuItems(SitesExtension::MENU_OWNER, SitesExtension::MENU_CODE, [
                'business' => [
                    'label' => 'Business Information',
                    'icon' => 'icon-building',
                    'url' => Backend::url('system/sites/business'),
                    'permissions' => ['settings.manage_sites'],
                    'order' => 70
                ]
            ]);
        });
    }

    public function registerSettings()
    {
        return [
            'business' => [
                'label' => 'Business Information',
                'description' => 'Business name, contact details, opening hours and social media for each site.',
                'category' => SettingsManager::CATEGORY_SYSTEM,
                'icon' => 'icon-building',
                'url' => Backend::url('system/sites/business'),
                'permissions' => ['settings.manage_sites'],
                'order' => 260,
                'keywords' => 'business phone email whatsapp address hours social'
            ]
        ];
    }

    public function registerMarkupTags()
    {
        return [
            'functions' => [
                'business' => [BusinessSetting::class, 'instance']
            ]
        ];
    }
}
