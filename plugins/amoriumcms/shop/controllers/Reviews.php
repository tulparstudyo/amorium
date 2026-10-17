<?php namespace AmoriumCms\Shop\Controllers;

use AmoriumCms\Shop\Classes\BackendListPage;

/**
 * Reviews lists the reviews. Approving, rejecting and deleting are done in the Vue page.
 */
class Reviews extends BackendListPage
{
    public $requiredPermissions = ['shop.reviews.moderate'];

    protected $section = 'reviews';

    protected $title = 'Reviews';

    public function listExtendColumns($list)
    {
        $list->getColumn('product_name')->sqlSelect($this->translatedSelect('amoriumcms_shop_product_group_translations', 'group_id', 'group_id'));
    }
}
