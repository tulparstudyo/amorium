<?php namespace AmoriumCms\Shop\Controllers;

use AmoriumCms\Shop\Classes\BackendListPage;

/**
 * Brands lists the brands. Brands are edited in the Vue page.
 */
class Brands extends BackendListPage
{
    public $requiredPermissions = ['shop.catalog.manage'];

    protected $section = 'brands';

    protected $title = 'Brands';

    protected $newLabel = 'New brand';

    public function listExtendColumns($list)
    {
        $list->getColumn('name')->sqlSelect($this->translatedSelect('amoriumcms_shop_brand_translations', 'brand_id'));
    }

    public function listExtendQuery($query)
    {
        $query->with('logo');
    }
}
