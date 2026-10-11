<?php namespace Amorium\Demo\Controllers;

use Flash;
use Backend;
use BackendMenu;
use Backend\Classes\Controller;
use System\Classes\SettingsManager;
use Amorium\Demo\Classes\DemoContent;

/**
 * Content shows the demo content of the theme with the buttons to save and install it.
 */
class Content extends Controller
{
    public $requiredPermissions = ['amorium.demo.manage'];

    public function __construct()
    {
        parent::__construct();

        BackendMenu::setContext('October.System', 'system', 'settings');
        SettingsManager::setContext('Amorium.Demo', 'demo');
    }

    public function index()
    {
        $this->pageTitle = 'Demo Content';
        $this->vars['summary'] = DemoContent::summary();
        $this->vars['canExport'] = DemoContent::canExport();
    }

    public function index_onInstall()
    {
        $counts = DemoContent::install();

        Flash::success(__('Demo content installed: :summary', ['summary' => $this->formatCounts($counts)]));

        return Backend::redirect('amorium/demo/content');
    }

    public function index_onExport()
    {
        $counts = DemoContent::export();

        Flash::success(__('Demo content saved: :summary', ['summary' => $this->formatCounts($counts)]));

        return Backend::redirect('amorium/demo/content');
    }

    /**
     * formatCounts
     */
    public function formatCounts(array $counts): string
    {
        return __(':sites sites, :pages pages, :posts posts, :categories categories, :menu_items menu items, :sections sections, :media media files', $counts);
    }
}
