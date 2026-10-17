<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * Carts, shipping methods, orders, order lines, status history and payments.
 * Orders keep a copy of the product and address data as it was when ordered.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::create('amoriumcms_shop_shipping_methods', function (Blueprint $table) {
            $table->increments('id');
            $table->string('code', 64)->unique();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('amoriumcms_shop_shipping_method_translations', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('shipping_method_id')->unsigned();
            $table->string('locale', 16);
            $table->string('name');
            $table->string('description', 512)->nullable();
            $table->unique(['shipping_method_id', 'locale'], 'shop_ship_trans_unique');
        });

        Schema::create('amoriumcms_shop_shipping_method_rates', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('shipping_method_id')->unsigned();
            $table->integer('site_id')->unsigned();
            $table->decimal('price', 12, 2)->default(0);
            $table->decimal('free_over', 12, 2)->nullable();
            $table->unique(['shipping_method_id', 'site_id'], 'shop_ship_rates_unique');
        });

        Schema::create('amoriumcms_shop_carts', function (Blueprint $table) {
            $table->increments('id');
            $table->string('token', 64)->unique();
            $table->integer('user_id')->unsigned()->nullable()->index();
            $table->integer('site_id')->unsigned();
            $table->string('email')->nullable();
            $table->integer('shipping_address_id')->unsigned()->nullable();
            $table->integer('billing_address_id')->unsigned()->nullable();
            $table->mediumText('guest_shipping_address')->nullable();
            $table->mediumText('guest_billing_address')->nullable();
            $table->integer('shipping_method_id')->unsigned()->nullable();
            $table->string('payment_method', 32)->nullable();
            $table->string('customer_note', 1000)->nullable();
            $table->integer('order_id')->unsigned()->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('amoriumcms_shop_cart_items', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('cart_id')->unsigned();
            $table->integer('product_id')->unsigned();
            $table->integer('quantity')->unsigned();
            $table->timestamps();
            $table->unique(['cart_id', 'product_id'], 'shop_cart_items_unique');
        });

        Schema::create('amoriumcms_shop_orders', function (Blueprint $table) {
            $table->increments('id');
            $table->string('number', 32)->nullable()->unique();
            $table->integer('user_id')->unsigned()->nullable()->index();
            $table->integer('cart_id')->unsigned()->nullable();
            $table->integer('site_id')->unsigned()->index();
            $table->string('locale', 16)->nullable();
            $table->string('currency', 3);
            $table->string('status', 32)->index();
            $table->string('payment_status', 16)->index();
            $table->string('payment_method', 32);
            $table->integer('shipping_method_id')->unsigned()->nullable();
            $table->string('shipping_method_name')->nullable();
            $table->decimal('subtotal', 12, 2);
            $table->decimal('tax_total', 12, 2);
            $table->decimal('shipping_total', 12, 2);
            $table->decimal('payment_fee', 12, 2)->default(0);
            $table->decimal('grand_total', 12, 2);
            $table->mediumText('shipping_address');
            $table->mediumText('billing_address');
            $table->string('customer_email');
            $table->string('customer_phone', 32)->nullable();
            $table->string('customer_note', 1000)->nullable();
            $table->text('admin_note')->nullable();
            $table->string('idempotency_key', 64)->nullable();
            $table->string('guest_access_token', 64)->nullable()->unique();
            $table->timestamp('placed_at')->nullable()->index();
            $table->timestamps();
            $table->unique(['cart_id', 'idempotency_key'], 'shop_orders_idempotency');
        });

        Schema::create('amoriumcms_shop_order_items', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('order_id')->unsigned()->index();
            $table->integer('product_id')->unsigned()->nullable()->index();
            $table->integer('group_id')->unsigned()->nullable();
            $table->string('barcode', 64);
            $table->string('sku', 64)->nullable();
            $table->string('name');
            $table->mediumText('variant_label')->nullable();
            $table->integer('quantity')->unsigned();
            $table->decimal('unit_price', 12, 2);
            $table->decimal('tax_rate', 5, 2);
            $table->decimal('tax_amount', 12, 2);
            $table->decimal('line_total', 12, 2);
        });

        Schema::create('amoriumcms_shop_order_status_history', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('order_id')->unsigned()->index();
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32);
            $table->string('note', 1000)->nullable();
            $table->integer('backend_user_id')->unsigned()->nullable();
            $table->boolean('customer_notified')->default(false);
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('amoriumcms_shop_payments', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('order_id')->unsigned()->index();
            $table->string('method', 32);
            $table->decimal('amount', 12, 2);
            $table->string('status', 16)->default('pending');
            $table->string('reference')->nullable();
            $table->mediumText('meta')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('amoriumcms_shop_payments');
        Schema::dropIfExists('amoriumcms_shop_order_status_history');
        Schema::dropIfExists('amoriumcms_shop_order_items');
        Schema::dropIfExists('amoriumcms_shop_orders');
        Schema::dropIfExists('amoriumcms_shop_cart_items');
        Schema::dropIfExists('amoriumcms_shop_carts');
        Schema::dropIfExists('amoriumcms_shop_shipping_method_rates');
        Schema::dropIfExists('amoriumcms_shop_shipping_method_translations');
        Schema::dropIfExists('amoriumcms_shop_shipping_methods');
    }
};
