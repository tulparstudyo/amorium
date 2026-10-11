<?php namespace Amorium\Business\Classes;

use Backend;
use BackendMenu;
use Flash;
use Lang;
use System\Classes\SettingsManager;
use System\Controllers\Sites;
use Backend\Widgets\Form;
use Amorium\Business\Models\BusinessSetting;
use Exception;

/**
 * Adds the business information form under the sites settings page.
 */
class SitesExtension
{
    public static function boot()
    {
        Sites::extend(function ($controller) {
            $controller->addViewPath('$/amorium/business/controllers/sites');

            $controller->addDynamicMethod('business', function () {
            });

            $controller->bindEvent('page.beforeDisplay', function ($action) use ($controller) {
                if ($action !== 'business') {
                    return;
                }

                BackendMenu::setContext('October.System', 'system', 'settings');
                SettingsManager::setContext('Amorium.Business', 'business');
                $controller->pageTitle = 'Business Information';

                try {
                    $controller->vars['businessForm'] = static::makeFormWidget($controller);
                }
                catch (Exception $ex) {
                    $controller->handleError($ex);
                }
            });

            $controller->addDynamicMethod('business_onSave', function () use ($controller) {
                $widget = $controller->vars['businessForm'] ?? null;
                $model = $widget ? $widget->model : null;

                if (!$widget || !$model) {
                    return;
                }

                foreach ($widget->getSaveData() as $attribute => $value) {
                    $model->{$attribute} = $value;
                }

                $model->save([
                    'propagate' => true,
                    'sessionKey' => $widget->getSessionKey()
                ]);

                Flash::success(__(':name settings updated', ['name' => __('Business Information')]));

                if (post('redirect', true)) {
                    return Backend::redirect('system/sites');
                }
            });

            $controller->addDynamicMethod('business_onResetDefault', function () {
                BusinessSetting::instance()->resetDefault();

                Flash::success(Lang::get('backend::lang.form.reset_success'));

                return Backend::redirect('system/sites/business');
            });
        });
    }

    /**
     * makeFormWidget
     */
    protected static function makeFormWidget($controller)
    {
        $model = BusinessSetting::instance();
        $config = $model->getFieldConfig();
        $config->model = $model;
        $config->arrayName = class_basename($model);
        $config->alias = 'businessForm';
        $config->context = 'update';

        $widget = $controller->makeWidget(Form::class, $config);
        $widget->bindToController();

        return $widget;
    }
}
