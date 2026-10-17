<?php namespace AmoriumCms\Shop\Controllers;

use AmoriumCms\Shop\Classes\BackendListPage;

/**
 * Categories shows the category tree. Categories are edited and reordered in the Vue page.
 */
class Categories extends BackendListPage
{
    public $requiredPermissions = ['shop.catalog.manage'];

    protected $section = 'categories';

    protected $title = 'Categories';

    protected $newLabel = 'New category';

    public function listExtendColumns($list)
    {
        $list->getColumn('name')->sqlSelect($this->translatedSelect('amoriumcms_shop_category_translations', 'category_id'));
    }
}
