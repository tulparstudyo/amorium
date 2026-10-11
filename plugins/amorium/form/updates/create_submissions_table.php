<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up()
    {
        Schema::create('amorium_form_submissions', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('site_id')->unsigned()->nullable()->index();
            $table->integer('section_id')->unsigned()->nullable()->index();
            $table->string('form_name')->nullable()->index();
            $table->mediumText('data')->nullable();
            $table->string('page_url', 2048)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->timestamp('read_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('amorium_form_submissions');
    }
};
