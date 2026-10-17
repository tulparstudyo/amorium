<?php namespace AmoriumCms\Shop\Controllers;

use AmoriumCms\Shop\Classes\BackendListPage;

/**
 * Shipping lists the shipping methods with their rates. Methods are edited in the Vue page.
 */
class Shipping extends BackendListPage
{
    public $requiredPermissions = ['shop.settings.manage'];

    protected $section = 'shipping';

    protected $title = 'Shipping Methods';

    protected $newLabel = 'New shipping method';

    public function listExtendColumns($list)
    {
        $list->getColumn('name')->sqlSelect($this->translatedSelect('amoriumcms_shop_shipping_method_translations', 'shipping_method_id'));
    }

    public function listExtendQuery($query)
    {
        $query->with('rates');
    }
}
