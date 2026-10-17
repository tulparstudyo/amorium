<?php namespace AmoriumCms\Shop\Controllers;

use AmoriumCms\Shop\Classes\BackendListPage;

/**
 * Specs lists the product specs. Specs and their options are edited in the Vue page.
 */
class Specs extends BackendListPage
{
    public $requiredPermissions = ['shop.catalog.manage'];

    protected $section = 'specs';

    protected $title = 'Specs';

    protected $newLabel = 'New spec';

    public function listExtendColumns($list)
    {
        $list->getColumn('name')->sqlSelect($this->translatedSelect('amoriumcms_shop_spec_translations', 'spec_id'));
    }
}
