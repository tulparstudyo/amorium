<?php namespace AmoriumCms\Shop\Classes\Api;

use Exception;

/**
 * ApiException carries an API result code from the service layer to the response.
 */
class ApiException extends Exception
{
    /**
     * @var string apiCode
     */
    public $apiCode;

    /**
     * @var mixed data
     */
    public $data;

    /**
     * @var array|null errors
     */
    public $errors;

    /**
     * @var array headers added to the response
     */
    public $headers = [];

    /**
     * make
     */
    public static function make(string $apiCode, $data = null, ?string $message = null, ?array $errors = null): static
    {
        $exception = new static($message ?? ApiCode::message($apiCode));
        $exception->apiCode = $apiCode;
        $exception->data = $data;
        $exception->errors = $errors;

        return $exception;
    }

    /**
     * validation throws a field error with the VALIDATION_FAILED code
     */
    public static function validation(array $errors): static
    {
        $errors = array_map(fn ($messages) => (array) $messages, $errors);

        return static::make(ApiCode::VALIDATION_FAILED, null, null, $errors);
    }
}
