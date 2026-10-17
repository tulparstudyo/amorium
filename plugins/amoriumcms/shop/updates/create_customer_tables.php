<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * Customer data next to the RainLab.User users table: company details,
 * address book, favorites and reviews.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::create('amoriumcms_shop_customers', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('user_id')->unsigned()->unique();
            $table->string('phone', 32)->nullable();
            $table->string('type', 16)->default('individual');
            $table->string('company_name')->nullable();
            $table->string('tax_number', 32)->nullable();
            $table->string('tax_office')->nullable();
            $table->timestamps();
        });

        Schema::create('amoriumcms_shop_addresses', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('user_id')->unsigned()->index();
            $table->string('type', 16);
            $table->string('title')->nullable();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('phone', 32);
            $table->string('country_code', 2)->default('TR');
            $table->string('city');
            $table->string('district')->nullable();
            $table->string('address_line', 512);
            $table->string('postal_code', 16)->nullable();
            $table->string('company_name')->nullable();
            $table->string('tax_number', 32)->nullable();
            $table->string('tax_office')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        Schema::create('amoriumcms_shop_favorites', function (Blueprint $table) {
            $table->integer('user_id')->unsigned();
            $table->integer('product_id')->unsigned()->index();
            $table->timestamp('created_at')->nullable();
            $table->primary(['user_id', 'product_id']);
        });

        Schema::create('amoriumcms_shop_reviews', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('product_id')->unsigned()->index();
            $table->integer('group_id')->unsigned();
            $table->integer('user_id')->unsigned();
            $table->integer('order_item_id')->unsigned()->nullable();
            $table->tinyInteger('rating')->unsigned();
            $table->string('title')->nullable();
            $table->text('body')->nullable();
            $table->string('locale', 16)->nullable();
            $table->string('status', 16)->default('pending');
            $table->timestamps();
            $table->unique(['user_id', 'group_id'], 'shop_reviews_user_group');
            $table->index(['group_id', 'status'], 'shop_reviews_group_status');
        });
    }

    public function down()
    {
        Schema::dropIfExists('amoriumcms_shop_reviews');
        Schema::dropIfExists('amoriumcms_shop_favorites');
        Schema::dropIfExists('amoriumcms_shop_addresses');
        Schema::dropIfExists('amoriumcms_shop_customers');
    }
};
