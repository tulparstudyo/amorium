<?php namespace Amorium\Pages;

use Event;
use Tailor\Models\RepeaterItem;
use Tailor\Controllers\Entries;
use System\Classes\PluginBase;

class Plugin extends PluginBase
{
    public function pluginDetails()
    {
        return [
            'name' => 'Amorium Pages',
            'description' => 'Site pages managed with blueprints',
            'author' => 'Amorium',
            'icon' => 'icon-file-text-o'
        ];
    }

    public function registerMarkupTags()
    {
        return [
            'filters' => [
                'themeAsset' => [\Amorium\Pages\Classes\ThemeAsset::class, 'url'],
            ],
        ];
    }

    public function boot()
    {
        RepeaterItem::extend(function ($model) {
            $model->addDynamicMethod('getSectionTitleAttribute', function () use ($model) {
                return $model->host_field === 'sections' ? $model->section?->title : null;
            });
        });

        Entries::extend(function ($controller) {
            $controller->addJs('/plugins/amorium/pages/assets/js/section-title.js');
            $controller->addCss('/plugins/amorium/pages/assets/css/sections-repeater.css');
        });

        Event::listen('backend.form.extendFields', function ($widget) {
            if ($widget->isNested || !$widget->getController() instanceof Entries) {
                return;
            }

            $widget->getController()->addJs('/plugins/amorium/pages/assets/js/section-toggle.js', [
                'data-label-enabled' => __("Enabled: shown on the site"),
                'data-label-disabled' => __("Disabled: hidden on the site"),
            ]);
        });
    }
}
