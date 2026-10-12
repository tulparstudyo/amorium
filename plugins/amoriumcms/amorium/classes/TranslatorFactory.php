<?php namespace AmoriumCms\Amorium\Classes;

use Config;
use ApplicationException;

/**
 * TranslatorFactory creates the client that matches the configured model.
 */
class TranslatorFactory
{
    public static function make(): TranslatorClient
    {
        $model = (string) Config::get('amoriumcms.amorium::translate.model');

        foreach ((array) Config::get('amoriumcms.amorium::translate.clients') as $prefix => $options) {
            if (str_starts_with($model, $prefix)) {
                return new $options['class']($model, $options);
            }
        }

        throw new ApplicationException("No translation client is configured for the model \"{$model}\".");
    }
}
