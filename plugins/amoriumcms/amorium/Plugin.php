<?php namespace AmoriumCms\Amorium;

use Event;
use Backend;
use BackendMenu;
use Cms\Classes\Theme;
use Cms\Controllers\ThemeOptions;
use Tailor\Models\EntryRecord;
use Tailor\Models\RepeaterItem;
use Tailor\Controllers\Entries;
use System\Classes\PluginBase;
use System\Classes\SettingsManager;
use AmoriumCms\Amorium\Classes\BulkTranslation;
use AmoriumCms\Amorium\Classes\ContentReviewer;
use AmoriumCms\Amorium\Classes\EntriesExtension;
use AmoriumCms\Amorium\Classes\EntryCreateGuard;
use AmoriumCms\Amorium\Classes\EntrySiteSync;
use AmoriumCms\Amorium\Classes\EntryTranslator;
use AmoriumCms\Amorium\Classes\PrimaryCopy;
use AmoriumCms\Amorium\Classes\SectionClassExporter;
use AmoriumCms\Amorium\Classes\SitesExtension;
use AmoriumCms\Amorium\Classes\SiteTranslation;
use AmoriumCms\Amorium\Models\BusinessSetting;
use AmoriumCms\Amorium\Models\Submission;

class Plugin extends PluginBase
{
    /**
     * Menu of the Amorium pages blueprint, the other Amorium screens are listed under it.
     */
    const MENU_OWNER = 'October.Tailor';
    const MENU_CODE = 'entry_amorium-page';

    public function pluginDetails()
    {
        return [
            'name' => 'Amorium',
            'description' => 'Pages, sections, posts, navigation, forms, business details, demo content and AI translation for the Amorium theme',
            'author' => 'AmoriumCms',
            'icon' => 'icon-file-text-o'
        ];
    }

    public function register()
    {
        $this->registerConsoleCommand('amorium.sections-export', \AmoriumCms\Amorium\Console\ExportSections::class);
        $this->registerConsoleCommand('amorium.sections-save-base', \AmoriumCms\Amorium\Console\SaveBaseSections::class);
        $this->registerConsoleCommand('amorium.demo-export', \AmoriumCms\Amorium\Console\DemoExport::class);
        $this->registerConsoleCommand('amorium.demo-install', \AmoriumCms\Amorium\Console\DemoInstall::class);
    }

    public function boot()
    {
        $this->bootPages();
        $this->bootSections();
        $this->bootBusiness();
        $this->bootPagesMenu();

        EntryTranslator::boot();
        BulkTranslation::boot();
        ContentReviewer::boot();
        SiteTranslation::boot();
    }

    public function registerComponents()
    {
        return [
            \AmoriumCms\Amorium\Components\Navigation::class => 'navigation'
        ];
    }

    public function registerFormWidgets()
    {
        return [
            \AmoriumCms\Amorium\FormWidgets\SectionFields::class => 'sectionfields',
            \AmoriumCms\Amorium\FormWidgets\RemixIcon::class => 'remixicon',
            \AmoriumCms\Amorium\FormWidgets\FontPicker::class => 'fontpicker',
        ];
    }

    public function registerContentFields()
    {
        return [
            \AmoriumCms\Amorium\ContentFields\SectionFieldsField::class => 'sectionfields',
        ];
    }

    public function registerMarkupTags()
    {
        return [
            'filters' => [
                'themeAsset' => [\AmoriumCms\Amorium\Classes\ThemeAsset::class, 'url'],
                'sectionHtml' => [\AmoriumCms\Amorium\Classes\SectionRenderer::class, 'render'],
            ],
            'functions' => [
                'selectedPosts' => [\AmoriumCms\Amorium\Classes\Posts::class, 'selected'],
                'postList' => [\AmoriumCms\Amorium\Classes\Posts::class, 'paginate'],
                'currentPost' => [\AmoriumCms\Amorium\Classes\Posts::class, 'current'],
                'recentPosts' => [\AmoriumCms\Amorium\Classes\Posts::class, 'recent'],
                'relatedPosts' => [\AmoriumCms\Amorium\Classes\Posts::class, 'related'],
                'postUrl' => [\AmoriumCms\Amorium\Classes\Posts::class, 'url'],
                'postListUrl' => [\AmoriumCms\Amorium\Classes\Posts::class, 'listUrl'],
                'business' => [BusinessSetting::class, 'instance'],
                'amoriumForm' => [\AmoriumCms\Amorium\Classes\FormDefinition::class, 'make'],
            ],
        ];
    }

    public function registerPermissions()
    {
        return [
            'amorium.form.submissions' => [
                'tab' => 'Forms',
                'label' => 'View and delete form submissions'
            ],
            'amorium.demo.manage' => [
                'tab' => 'Demo Content',
                'label' => 'Save and install demo content'
            ]
        ];
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
            ],
            'demo' => [
                'label' => 'Demo Content',
                'description' => 'Install the demo pages, posts, menus and sections of the Amorium theme.',
                'category' => SettingsManager::CATEGORY_CMS,
                'icon' => 'icon-magic',
                'url' => Backend::url('amoriumcms/amorium/demo'),
                'permissions' => ['amorium.demo.manage'],
                'order' => 600,
                'keywords' => 'demo content seed install export'
            ]
        ];
    }

    /**
     * bootPages adds the section titles and the enable toggle to the page editor.
     */
    protected function bootPages()
    {
        RepeaterItem::extend(function ($model) {
            $model->addDynamicMethod('getSectionTitleAttribute', function () use ($model) {
                return $model->host_field === 'sections' ? $model->section?->title : null;
            });
        });

        Entries::extend(function ($controller) {
            $controller->addJs('/plugins/amoriumcms/amorium/assets/js/section-title.js');
            $controller->addCss('/plugins/amoriumcms/amorium/assets/css/sections-repeater.css');
        });

        Event::listen('backend.form.extendFields', function ($widget) {
            if ($widget->isNested || !$widget->getController() instanceof Entries) {
                return;
            }

            $widget->getController()->addJs('/plugins/amoriumcms/amorium/assets/js/section-toggle.js', [
                'data-label-enabled' => __("Enabled: shown on the site"),
                'data-label-disabled' => __("Disabled: hidden on the site"),
            ]);
        });
    }

    /**
     * bootSections keeps the sections shared by every site and the section classes exported.
     */
    protected function bootSections()
    {
        EntriesExtension::boot();
        EntryCreateGuard::boot();
        EntrySiteSync::boot();
        PrimaryCopy::boot();

        EntryRecord::extend(function ($model) {
            $export = function () use ($model) {
                if ($model->blueprint_uuid === SectionClassExporter::BLUEPRINT_UUID) {
                    SectionClassExporter::export();
                }
            };

            $model->bindEvent('model.afterSave', $export);
            $model->bindEvent('model.afterDelete', $export);
        });
    }

    /**
     * bootBusiness lists the business information page under the sites menu.
     */
    protected function bootBusiness()
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

    /**
     * bootPagesMenu lists the forms, theme settings and demo content pages under the
     * Amorium pages menu and keeps that menu active while the theme settings are open.
     */
    protected function bootPagesMenu()
    {
        $owner = static::MENU_OWNER;
        $code = static::MENU_CODE;

        Event::listen('backend.menu.extendItems', function ($manager) use ($owner, $code) {
            $pages = $manager->getMainMenuItem($owner, $code);
            if (!$pages) {
                return;
            }

            $theme = Theme::getActiveTheme();
            $hasThemeOptions = $theme && $theme->hasCustomData();

            $permissions = $pages->permissions;
            $required = ['amorium.form.submissions', 'amorium.demo.manage'];
            if ($hasThemeOptions) {
                $required[] = 'cms.theme_customize';
            }
            foreach ($required as $permission) {
                if (!in_array($permission, $permissions, true)) {
                    $permissions[] = $permission;
                }
            }
            $pages->permissions($permissions);

            $manager->addSideMenuItem($owner, $code, 'submissions', [
                'label' => 'Forms',
                'url' => Backend::url('amoriumcms/amorium/submissions'),
                'icon' => 'icon-inbox',
                'permissions' => ['amorium.form.submissions'],
                'order' => 60,
                'counter' => [Submission::class, 'countUnread'],
                'counterLabel' => 'Unread submissions'
            ]);

            if ($hasThemeOptions) {
                $manager->addSideMenuItem($owner, $code, 'themeoptions', [
                    'label' => 'Theme Settings',
                    'url' => Backend::url('cms/themeoptions/update/' . $theme->getDirName()),
                    'icon' => 'icon-paint-brush',
                    'permissions' => ['cms.theme_customize'],
                    'order' => 80
                ]);
            }

            $manager->addSideMenuItem($owner, $code, 'demo', [
                'label' => 'Demo Content',
                'url' => Backend::url('amoriumcms/amorium/demo'),
                'icon' => 'icon-magic',
                'permissions' => ['amorium.demo.manage'],
                'order' => 90
            ]);
        });

        ThemeOptions::extend(function ($controller) use ($owner, $code) {
            $controller->bindEvent('page.beforeDisplay', function () use ($owner, $code) {
                BackendMenu::setContext($owner, $code, 'themeoptions');
            });
        });
    }
}
