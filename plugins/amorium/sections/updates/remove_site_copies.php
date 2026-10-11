<?php

use October\Rain\Database\Updates\Migration;

/**
 * Sections are shared by every site, only the root row of each section is kept.
 * Page sections reference the site_root_id, which is the id of the root row.
 */
return new class extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('xc_amorium_sectionc') || !Schema::hasColumn('xc_amorium_sectionc', 'site_root_id')) {
            return;
        }

        Db::table('xc_amorium_sectionc')
            ->whereNotNull('site_root_id')
            ->whereColumn('id', '<>', 'site_root_id')
            ->delete();
    }

    public function down()
    {
    }
};
