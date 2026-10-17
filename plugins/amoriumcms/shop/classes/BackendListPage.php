<?php namespace AmoriumCms\Shop\Classes;

/**
 * BackendListPage shows a backend list widget, the same table Tailor uses, and opens the
 * Vue editor of a record with #/{id}. The list only reads; changes go through the admin API.
 */
abstract class BackendListPage extends BackendPage
{
    public $implement = [
        \Backend\Behaviors\ListController::class,
    ];

    public $listConfig = 'config_list.yaml';

    /**
     * @var string section is the Vue page and the menu item
     */
    protected $section;

    /**
     * @var string title of the page
     */
    protected $title;

    /**
     * @var string|null newLabel shows a create button that opens #/new
     */
    protected $newLabel;

    public function index()
    {
        $this->bootPage($this->section, $this->title);
        $this->vars['newLabel'] = $this->newLabel;
        $this->makeLists();
    }
}
