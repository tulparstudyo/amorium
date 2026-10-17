<?php namespace AmoriumCms\Amorium\Classes;

use App;
use Flash;
use BackendAuth;
use System\Controllers\Settings;
use System\Models\MailSetting;
use Backend\Widgets\Form;
use ApplicationException;
use Throwable;

/**
 * Adds a test button to the mail settings page that sends an email with the values in the form.
 */
class MailSettingsTest
{
    public static function boot()
    {
        Settings::extend(function ($controller) {
            $controller->bindEvent('page.beforeDisplay', function ($action, $params) use ($controller) {
                if ($action !== 'update' || !static::isMailSettings(...array_values((array) $params))) {
                    return;
                }

                $controller->addJs('/plugins/amoriumcms/amorium/assets/js/mail-test.js', [
                    'data-label' => __("Send Test Email"),
                    'data-message' => __("Sending test email..."),
                ]);
            });

            $controller->addDynamicMethod('update_onTestMail', function ($author = null, $plugin = null, $code = null) use ($controller) {
                if (!static::isMailSettings($author, $plugin, $code)) {
                    throw new ApplicationException(__('Unable to find the specified settings.'));
                }

                $recipient = BackendAuth::getUser()?->email;
                if (!$recipient) {
                    throw new ApplicationException(__("Your account has no email address to send the test email to."));
                }

                $settings = MailSetting::instance();
                foreach (static::makeFormWidget($controller, $settings)->getSaveData() as $attribute => $value) {
                    $settings->{$attribute} = $value;
                }

                $settings->validate();

                try {
                    MailSetting::applyConfigValues();

                    $manager = App::make('mail.manager');
                    $manager->forgetMailers();

                    $manager->mailer($settings->send_mode)->raw(
                        __("This is a test email sent from the mail settings page. If you received it, the mail settings are working."),
                        function ($message) use ($recipient) {
                            $message->to($recipient)->subject(__("Test Email"));
                        }
                    );
                }
                catch (Throwable $ex) {
                    throw new ApplicationException(__("The test email could not be sent: :error", ['error' => $ex->getMessage()]));
                }

                if ($settings->send_mode === MailSetting::MODE_LOG) {
                    Flash::success(__("The test email was written to the log file because the send mode is Log File."));
                    return;
                }

                Flash::success(__("Test email sent to :email", ['email' => $recipient]));
            });
        });
    }

    protected static function makeFormWidget($controller, $model)
    {
        $config = $model->getFieldConfig();
        $config->model = $model;
        $config->arrayName = class_basename($model);
        $config->context = 'update';

        return $controller->makeWidget(Form::class, $config);
    }

    protected static function isMailSettings($author = null, $plugin = null, $code = null): bool
    {
        return strtolower("{$author}/{$plugin}/{$code}") === 'october/system/mail_settings';
    }
}
