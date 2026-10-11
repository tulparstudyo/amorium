<?php namespace Amorium\Sections;

use Tailor\Models\EntryRecord;
use System\Classes\PluginBase;
use Amorium\Sections\Classes\SectionClassExporter;
use Amorium\Sections\Classes\EntriesExtension;
use Amorium\Sections\Classes\EntryCreateGuard;
use Amorium\Sections\Classes\EntrySiteSync;
use Amorium\Sections\Classes\PrimaryCopy;

class Plugin extends PluginBase
{
    public function pluginDetails()
    {
        return [
            'name' => 'Amorium Section Creator',
            'description' => 'Reusable page sections built from HTML, CSS and JS',
            'author' => 'Amorium',
            'icon' => 'icon-puzzle-piece'
        ];
    }

    public function register()
    {
        $this->registerConsoleCommand('amorium.sections-export', \Amorium\Sections\Console\ExportSections::class);
        $this->registerConsoleCommand('amorium.sections-save-base', \Amorium\Sections\Console\SaveBaseSections::class);
    }

    public function registerFormWidgets()
    {
        return [
            \Amorium\Sections\FormWidgets\SectionFields::class => 'sectionfields',
            \Amorium\Sections\FormWidgets\RemixIcon::class => 'remixicon',
            \Amorium\Sections\FormWidgets\FontPicker::class => 'fontpicker',
        ];
    }

    public function registerContentFields()
    {
        return [
            \Amorium\Sections\ContentFields\SectionFieldsField::class => 'sectionfields',
        ];
    }

    public function registerMarkupTags()
    {
        return [
            'filters' => [
                'sectionHtml' => [\Amorium\Sections\Classes\SectionRenderer::class, 'render'],
            ],
        ];
    }

    public function boot()
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
}
