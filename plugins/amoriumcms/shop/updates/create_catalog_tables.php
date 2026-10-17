<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * Catalog: categories, brands, product groups, products (one per barcode), prices,
 * specs and stock movements. Texts are kept per locale, prices per site.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::create('amoriumcms_shop_categories', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('parent_id')->unsigned()->nullable()->index();
            $table->integer('nest_left')->nullable();
            $table->integer('nest_right')->nullable();
            $table->integer('nest_depth')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('amoriumcms_shop_category_translations', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('category_id')->unsigned();
            $table->string('locale', 16);
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->string('meta_title')->nullable();
            $table->string('meta_description', 512)->nullable();
            $table->unique(['category_id', 'locale'], 'shop_cat_trans_unique');
            $table->unique(['locale', 'slug'], 'shop_cat_trans_slug');
        });

        Schema::create('amoriumcms_shop_brands', function (Blueprint $table) {
            $table->increments('id');
            $table->string('code', 64)->unique();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('amoriumcms_shop_brand_translations', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('brand_id')->unsigned();
            $table->string('locale', 16);
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->string('meta_title')->nullable();
            $table->string('meta_description', 512)->nullable();
            $table->unique(['brand_id', 'locale'], 'shop_brand_trans_unique');
            $table->unique(['locale', 'slug'], 'shop_brand_trans_slug');
        });

        Schema::create('amoriumcms_shop_product_groups', function (Blueprint $table) {
            $table->increments('id');
            $table->string('code', 64)->unique();
            $table->integer('brand_id')->unsigned()->nullable()->index();
            $table->integer('primary_category_id')->unsigned()->nullable()->index();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('amoriumcms_shop_product_group_translations', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('group_id')->unsigned();
            $table->string('locale', 16);
            $table->string('name');
            $table->string('slug');
            $table->mediumText('description')->nullable();
            $table->string('meta_title')->nullable();
            $table->string('meta_description', 512)->nullable();
            $table->unique(['group_id', 'locale'], 'shop_group_trans_unique');
            $table->unique(['locale', 'slug'], 'shop_group_trans_slug');
        });

        Schema::create('amoriumcms_shop_category_group', function (Blueprint $table) {
            $table->integer('category_id')->unsigned();
            $table->integer('group_id')->unsigned()->index();
            $table->primary(['category_id', 'group_id']);
        });

        Schema::create('amoriumcms_shop_products', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('group_id')->unsigned()->index();
            $table->string('barcode', 64)->unique();
            $table->string('sku', 64)->nullable()->index();
            $table->string('variant_key', 191)->default('');
            $table->integer('stock_quantity')->default(0);
            $table->decimal('tax_rate', 5, 2)->default(20);
            $table->decimal('weight', 10, 3)->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['group_id', 'variant_key'], 'shop_products_variant');
        });

        Schema::create('amoriumcms_shop_product_prices', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('product_id')->unsigned();
            $table->integer('site_id')->unsigned()->index();
            $table->decimal('price', 12, 2);
            $table->decimal('compare_at_price', 12, 2)->nullable();
            $table->unique(['product_id', 'site_id'], 'shop_prices_unique');
        });

        Schema::create('amoriumcms_shop_product_images', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('group_id')->unsigned()->index();
            $table->integer('spec_option_id')->unsigned()->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('amoriumcms_shop_specs', function (Blueprint $table) {
            $table->increments('id');
            $table->string('code', 64)->unique();
            $table->string('type', 16)->default('select');
            $table->string('unit', 16)->nullable();
            $table->boolean('is_variant')->default(false);
            $table->boolean('is_filterable')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('amoriumcms_shop_spec_translations', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('spec_id')->unsigned();
            $table->string('locale', 16);
            $table->string('name');
            $table->unique(['spec_id', 'locale'], 'shop_spec_trans_unique');
        });

        Schema::create('amoriumcms_shop_spec_options', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('spec_id')->unsigned();
            $table->string('code', 64);
            $table->string('color_hex', 7)->nullable();
            $table->integer('sort_order')->default(0);
            $table->unique(['spec_id', 'code'], 'shop_spec_options_code');
        });

        Schema::create('amoriumcms_shop_spec_option_translations', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('spec_option_id')->unsigned();
            $table->string('locale', 16);
            $table->string('label');
            $table->unique(['spec_option_id', 'locale'], 'shop_spec_opt_trans_unique');
        });

        Schema::create('amoriumcms_shop_category_spec', function (Blueprint $table) {
            $table->integer('category_id')->unsigned();
            $table->integer('spec_id')->unsigned()->index();
            $table->boolean('is_required')->default(false);
            $table->primary(['category_id', 'spec_id']);
        });

        Schema::create('amoriumcms_shop_product_spec_values', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('product_id')->unsigned();
            $table->integer('spec_id')->unsigned();
            $table->integer('spec_option_id')->unsigned()->nullable();
            $table->decimal('value_number', 14, 4)->nullable();
            $table->string('value_text')->nullable();
            $table->boolean('value_boolean')->nullable();
            $table->unique(['product_id', 'spec_id'], 'shop_spec_values_unique');
            $table->index(['spec_id', 'spec_option_id'], 'shop_spec_values_option');
            $table->index(['spec_id', 'value_number'], 'shop_spec_values_number');
        });

        Schema::create('amoriumcms_shop_stock_movements', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('product_id')->unsigned()->index();
            $table->integer('quantity_change');
            $table->integer('stock_after');
            $table->string('reason', 16);
            $table->integer('order_id')->unsigned()->nullable()->index();
            $table->integer('backend_user_id')->unsigned()->nullable();
            $table->string('note')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down()
    {
        Schema::dropIfExists('amoriumcms_shop_stock_movements');
        Schema::dropIfExists('amoriumcms_shop_product_spec_values');
        Schema::dropIfExists('amoriumcms_shop_category_spec');
        Schema::dropIfExists('amoriumcms_shop_spec_option_translations');
        Schema::dropIfExists('amoriumcms_shop_spec_options');
        Schema::dropIfExists('amoriumcms_shop_spec_translations');
        Schema::dropIfExists('amoriumcms_shop_specs');
        Schema::dropIfExists('amoriumcms_shop_product_images');
        Schema::dropIfExists('amoriumcms_shop_product_prices');
        Schema::dropIfExists('amoriumcms_shop_products');
        Schema::dropIfExists('amoriumcms_shop_category_group');
        Schema::dropIfExists('amoriumcms_shop_product_group_translations');
        Schema::dropIfExists('amoriumcms_shop_product_groups');
        Schema::dropIfExists('amoriumcms_shop_brand_translations');
        Schema::dropIfExists('amoriumcms_shop_brands');
        Schema::dropIfExists('amoriumcms_shop_category_translations');
        Schema::dropIfExists('amoriumcms_shop_categories');
    }
};
