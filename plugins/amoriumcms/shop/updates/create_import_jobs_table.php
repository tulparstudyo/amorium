<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * Import jobs are product links waiting to be imported, one artisan run at a time.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::create('amoriumcms_shop_import_jobs', function (Blueprint $table) {
            $table->increments('id');
            $table->string('url', 2048);
            $table->integer('site_id')->unsigned()->nullable();
            $table->string('status', 16)->default('pending');
            $table->mediumText('result')->nullable();
            $table->smallInteger('exit_code')->nullable();
            $table->integer('backend_user_id')->unsigned()->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'id'], 'shop_import_jobs_status');
        });
    }

    public function down()
    {
        Schema::dropIfExists('amoriumcms_shop_import_jobs');
    }
};
