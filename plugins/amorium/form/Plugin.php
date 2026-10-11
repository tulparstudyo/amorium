<?php namespace Amorium\Form;

use Backend;
use Event;
use System\Classes\PluginBase;
use Amorium\Form\Classes\FormDefinition;
use Amorium\Form\Models\Submission;

class Plugin extends PluginBase
{
    public function pluginDetails()
    {
        return [
            'name' => 'Amorium Form',
            'description' => 'Stores the submissions of the forms built with the Form section',
            'author' => 'Amorium',
            'icon' => 'icon-inbox'
        ];
    }

    public function boot()
    {
        Event::listen('backend.menu.extendItems', function ($manager) {
            $owner = 'October.Tailor';
            $code = 'entry_amorium-page';

            $pages = $manager->getMainMenuItem($owner, $code);
            if (!$pages) {
                return;
            }

            $permissions = $pages->permissions;
            if (!in_array('amorium.form.submissions', $permissions, true)) {
                $permissions[] = 'amorium.form.submissions';
                $pages->permissions($permissions);
            }

            $manager->addSideMenuItem($owner, $code, 'submissions', [
                'label' => 'Forms',
                'url' => Backend::url('amorium/form/submissions'),
                'icon' => 'icon-inbox',
                'permissions' => ['amorium.form.submissions'],
                'order' => 60,
                'counter' => [Submission::class, 'countUnread'],
                'counterLabel' => 'Unread submissions'
            ]);
        });
    }

    public function registerPermissions()
    {
        return [
            'amorium.form.submissions' => [
                'tab' => 'Forms',
                'label' => 'View and delete form submissions'
            ]
        ];
    }

    public function registerMarkupTags()
    {
        return [
            'functions' => [
                'amoriumForm' => [FormDefinition::class, 'make']
            ]
        ];
    }
}
