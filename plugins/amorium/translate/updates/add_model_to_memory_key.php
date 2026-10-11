<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up()
    {
        Db::table('amorium_translate_memory')->whereNull('model')->update(['model' => '']);

        Schema::table('amorium_translate_memory', function (Blueprint $table) {
            $table->dropUnique('amorium_translate_memory_unique');
            $table->string('model', 128)->default('')->nullable(false)->change();
            $table->unique(['source_key', 'from_language', 'to_language', 'model'], 'amorium_translate_memory_unique');
        });
    }

    public function down()
    {
        Schema::table('amorium_translate_memory', function (Blueprint $table) {
            $table->dropUnique('amorium_translate_memory_unique');
            $table->string('model', 128)->nullable()->default(null)->change();
            $table->unique(['source_key', 'from_language', 'to_language'], 'amorium_translate_memory_unique');
        });
    }
};
