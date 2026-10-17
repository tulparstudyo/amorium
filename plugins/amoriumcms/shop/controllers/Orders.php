<?php namespace AmoriumCms\Shop\Controllers;

use AmoriumCms\Shop\Classes\BackendListPage;

/**
 * Orders lists the orders, including the drafts where payment was not made. Status changes and payment
 * confirmations are done in the Vue page.
 */
class Orders extends BackendListPage
{
    public $requiredPermissions = ['shop.orders.view', 'shop.orders.manage'];

    protected $section = 'orders';

    protected $title = 'Orders';
}
