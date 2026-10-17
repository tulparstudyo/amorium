<?php namespace AmoriumCms\Shop\Controllers;

use AmoriumCms\Shop\Classes\BackendListPage;
use AmoriumCms\Shop\Models\Order;

/**
 * Customers lists the RainLab users with their order totals. Accounts are edited on the RainLab.User pages.
 */
class Customers extends BackendListPage
{
    public $requiredPermissions = ['shop.customers.view'];

    protected $section = 'customers';

    protected $title = 'Customers';

    public function listExtendColumns($list)
    {
        $orders = "from amoriumcms_shop_orders o where o.user_id = @id and o.status not in ('" . implode("', '", Order::NOT_COUNTED) . "')";

        $list->getColumn('orders_count')->sqlSelect("(select count(*) {$orders})");
        $list->getColumn('orders_total')->sqlSelect("(select coalesce(sum(o.grand_total), 0) {$orders})");
    }
}
