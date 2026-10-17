<?php

use Db;
use Schema;
use October\Rain\Database\Updates\Migration;

/**
 * Renames the draft order status from incomplete to payment_not_made.
 */
return new class extends Migration
{
    public function up()
    {
        if (Schema::hasTable('amoriumcms_shop_orders')) {
            Db::table('amoriumcms_shop_orders')->where('status', 'incomplete')->update(['status' => 'payment_not_made']);
        }

        if (Schema::hasTable('amoriumcms_shop_order_status_history')) {
            Db::table('amoriumcms_shop_order_status_history')->where('from_status', 'incomplete')->update(['from_status' => 'payment_not_made']);
            Db::table('amoriumcms_shop_order_status_history')->where('to_status', 'incomplete')->update(['to_status' => 'payment_not_made']);
        }
    }

    public function down()
    {
        if (Schema::hasTable('amoriumcms_shop_orders')) {
            Db::table('amoriumcms_shop_orders')->where('status', 'payment_not_made')->update(['status' => 'incomplete']);
        }

        if (Schema::hasTable('amoriumcms_shop_order_status_history')) {
            Db::table('amoriumcms_shop_order_status_history')->where('from_status', 'payment_not_made')->update(['from_status' => 'incomplete']);
            Db::table('amoriumcms_shop_order_status_history')->where('to_status', 'payment_not_made')->update(['to_status' => 'incomplete']);
        }
    }
};
