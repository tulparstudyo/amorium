<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up()
    {
        Schema::create('amorium_translate_memory', function (Blueprint $table) {
            $table->increments('id');
            $table->char('source_key', 32);
            $table->string('from_language', 64);
            $table->string('to_language', 64);
            $table->mediumText('source');
            $table->mediumText('translation');
            $table->string('model', 128)->nullable();
            $table->timestamps();
            $table->unique(['source_key', 'from_language', 'to_language'], 'amorium_translate_memory_unique');
        });
    }

    public function down()
    {
        Schema::dropIfExists('amorium_translate_memory');
    }
};
