<?php namespace Amorium\Pages;

use Event;
use Backend;
use BackendMenu;
use Cms\Classes\Theme;
use Cms\Controllers\ThemeOptions;
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

        $this->addThemeOptionsMenu();
    }

    /**
     * addThemeOptionsMenu lists the theme customization page under the Amorium menu
     * and keeps that menu active while the page is open.
     */
    protected function addThemeOptionsMenu()
    {
        $owner = 'October.Tailor';
        $code = 'entry_amorium-page';

        Event::listen('backend.menu.extendItems', function ($manager) use ($owner, $code) {
            $pages = $manager->getMainMenuItem($owner, $code);
            $theme = Theme::getActiveTheme();
            if (!$pages || !$theme || !$theme->hasCustomData()) {
                return;
            }

            $permissions = $pages->permissions;
            if (!in_array('cms.theme_customize', $permissions, true)) {
                $permissions[] = 'cms.theme_customize';
                $pages->permissions($permissions);
            }

            $manager->addSideMenuItem($owner, $code, 'themeoptions', [
                'label' => 'Theme Settings',
                'url' => Backend::url('cms/themeoptions/update/' . $theme->getDirName()),
                'icon' => 'icon-paint-brush',
                'permissions' => ['cms.theme_customize'],
                'order' => 80
            ]);
        });

        ThemeOptions::extend(function ($controller) use ($owner, $code) {
            $controller->bindEvent('page.beforeDisplay', function () use ($owner, $code) {
                BackendMenu::setContext($owner, $code, 'themeoptions');
            });
        });
    }
}
