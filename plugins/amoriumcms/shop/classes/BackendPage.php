<?php namespace AmoriumCms\Shop\Classes;

use App;
use Db;
use Url;
use Lang;
use Backend;
use BackendMenu;
use Backend\Classes\Controller;
use AmoriumCms\Shop\Models\ProductGroup;

/**
 * BackendPage is the base of the shop admin pages. Pages load the Vue application,
 * which talks to the admin API; the shared views live in views/admin.
 */
abstract class BackendPage extends Controller
{
    public $requiredPermissions = ['shop.*'];

    public function __construct()
    {
        parent::__construct();

        $this->addViewPath(plugins_path('amoriumcms/shop/views/admin'), true);
    }

    /**
     * bootPage sets the menu, the title and the configuration of the Vue page
     */
    protected function bootPage(string $section, string $title, ?string $menuItem = null)
    {
        Lang::addJsonPath(plugins_path('amoriumcms/shop/lang'));
        BackendMenu::setContext('AmoriumCms.Shop', 'shop', $menuItem ?? $section);

        $this->pageTitle = $title;
        $pageJs = plugins_path("amoriumcms/shop/assets/js/admin/pages/{$section}.js");
        $this->vars['config'] = [
            'section' => $section,
            'apiBase' => Url::to('api/admin/v1'),
            'pageBase' => Backend::url('amoriumcms/shop'),
            'assetVersion' => is_file($pageJs) ? filemtime($pageJs) : time(),
            'messages' => (object) $this->messages(),
        ];
        $this->vars['appUrl'] = Url::asset('plugins/amoriumcms/shop/assets/js/admin/app.js') . '?v=' . filemtime(plugins_path('amoriumcms/shop/assets/js/admin/app.js'));
    }

    /**
     * translatedSelect returns a list column subquery reading a translated field in the
     * backend language, falling back like the API does. "@" is the list table.
     */
    protected function translatedSelect(string $table, string $foreignKey, string $localKey = 'id', string $field = 'name'): string
    {
        $locales = implode(', ', array_map(fn ($locale) => Db::getPdo()->quote($locale), ProductGroup::localeChain()));

        return "(select t.{$field} from {$table} t where t.{$foreignKey} = @{$localKey} order by field(t.locale, {$locales}) = 0, field(t.locale, {$locales}), t.id limit 1)";
    }

    /**
     * messages returns the translations of the backend language for the Vue pages
     */
    protected function messages(): array
    {
        $file = plugins_path('amoriumcms/shop/lang/' . App::getLocale() . '.json');

        return is_file($file) ? (json_decode(file_get_contents($file), true) ?: []) : [];
    }
}
