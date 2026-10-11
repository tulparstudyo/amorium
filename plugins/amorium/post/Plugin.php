<?php namespace Amorium\Post;

use System\Classes\PluginBase;

class Plugin extends PluginBase
{
    public $require = ['Amorium.Pages'];

    public function pluginDetails()
    {
        return [
            'name' => 'Amorium Post',
            'description' => 'Posts and categories managed with blueprints',
            'author' => 'Amorium',
            'icon' => 'icon-pencil'
        ];
    }

    public function registerMarkupTags()
    {
        return [
            'functions' => [
                'selectedPosts' => [\Amorium\Post\Classes\Posts::class, 'selected'],
            ],
        ];
    }
}
