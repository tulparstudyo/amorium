<?php

use October\Rain\Database\Updates\Migration;

/**
 * Removes the records of the separate Amorium plugins merged into this plugin,
 * their tables and settings are used by this plugin as they are.
 */
return new class extends Migration
{
    protected $codes = [
        'Amorium.Pages',
        'Amorium.Sections',
        'Amorium.Post',
        'Amorium.Navigation',
        'Amorium.Form',
        'Amorium.Business',
        'Amorium.Demo',
        'Amorium.Translate',
    ];

    public function up()
    {
        foreach (['system_plugin_versions', 'system_plugin_history'] as $table) {
            if (Schema::hasTable($table)) {
                Db::table($table)->whereIn('code', $this->codes)->delete();
            }
        }
    }

    public function down()
    {
    }
};
