<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * A queued import can name the category the product is placed in.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::table('amoriumcms_shop_import_jobs', function (Blueprint $table) {
            $table->integer('category_id')->unsigned()->nullable()->after('site_id');
        });
    }

    public function down()
    {
        Schema::table('amoriumcms_shop_import_jobs', function (Blueprint $table) {
            $table->dropColumn('category_id');
        });
    }
};
