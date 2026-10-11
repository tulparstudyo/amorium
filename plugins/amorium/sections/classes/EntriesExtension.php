<?php namespace Amorium\Sections\Classes;

use Flash;
use ApplicationException;
use Tailor\Controllers\Entries;

/**
 * EntriesExtension adds the "Create Base Sections" button to the section list.
 */
class EntriesExtension
{
    public static function boot()
    {
        Entries::extend(function ($controller) {
            $controller->addViewPath('$/amorium/sections/controllers/entries');

            $controller->addDynamicMethod('onCreateBaseSections', function () use ($controller) {
                if (EntryCreateGuard::deniesHandle('amorium-section')) {
                    throw new ApplicationException(__('New records can only be created while the primary site is active.'));
                }

                if (!$controller->hasSourcePermission('create')) {
                    return;
                }

                $result = BaseSections::create();

                $result['created']
                    ? Flash::success(__('Created :count sections: :names', [
                        'count' => count($result['created']),
                        'names' => implode(', ', $result['created'])
                    ]))
                    : Flash::info(__('All base sections already exist.'));

                return $controller->listRefresh();
            });
        });
    }
}
