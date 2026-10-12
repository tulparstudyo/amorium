<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * The table may already exist when the site is upgraded from the Amorium.Translate plugin.
 */
return new class extends Migration
{
    public function up()
    {
        if (Schema::hasTable('amorium_translate_logs')) {
            return;
        }

        Schema::create('amorium_translate_logs', function (Blueprint $table) {
            $table->increments('id');
            $table->string('blueprint_uuid', 64);
            $table->integer('record_id')->unsigned();
            $table->string('field', 64);
            $table->char('source_hash', 32);
            $table->char('output_hash', 32);
            $table->timestamps();
            $table->unique(['blueprint_uuid', 'record_id', 'field']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('amorium_translate_logs');
    }
};
