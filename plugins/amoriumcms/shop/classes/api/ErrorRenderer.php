<?php namespace AmoriumCms\Shop\Classes\Api;

use Event;
use Config;
use Throwable;
use Illuminate\Http\Request;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use October\Rain\Exception\ForbiddenException;

/**
 * ErrorRenderer turns every exception thrown under the API paths into the API error format.
 * The rest of the site keeps its own error pages.
 */
class ErrorRenderer
{
    /**
     * register
     */
    public static function register()
    {
        app(ExceptionHandler::class)->renderable(function (Throwable $e, $request) {
            return static::isApiRequest($request) ? static::render($e) : null;
        });

        Event::listen('exception.beforeReport', function ($exception) {
            if ($exception instanceof ApiException) {
                return false;
            }
        });
    }

    /**
     * isApiRequest
     */
    public static function isApiRequest($request): bool
    {
        return $request instanceof Request
            && ($request->is('api/v1/*') || $request->is('api/admin/v1/*'));
    }

    /**
     * render
     */
    public static function render(Throwable $e)
    {
        if ($e instanceof ApiException) {
            $response = ApiResponse::error($e->apiCode, $e->getMessage(), $e->data, $e->errors);
            foreach ($e->headers as $name => $value) {
                $response->headers->set($name, $value);
            }
            return $response;
        }

        if ($e instanceof ValidationException) {
            return ApiResponse::error(ApiCode::VALIDATION_FAILED, null, null, $e->errors());
        }

        if ($e instanceof AuthenticationException) {
            return ApiResponse::error(ApiCode::UNAUTHENTICATED);
        }

        if ($e instanceof AuthorizationException || $e instanceof AccessDeniedHttpException || $e instanceof ForbiddenException) {
            return ApiResponse::error(ApiCode::FORBIDDEN);
        }

        if ($e instanceof ThrottleRequestsException) {
            $response = ApiResponse::error(ApiCode::TOO_MANY_REQUESTS);
            foreach ($e->getHeaders() as $name => $value) {
                $response->headers->set($name, $value);
            }
            return $response;
        }

        if ($e instanceof MethodNotAllowedHttpException) {
            return ApiResponse::error(ApiCode::METHOD_NOT_ALLOWED);
        }

        if ($e instanceof NotFoundHttpException) {
            return ApiResponse::error(ApiCode::NOT_FOUND);
        }

        if ($e instanceof HttpExceptionInterface) {
            $code = match ($e->getStatusCode()) {
                400 => ApiCode::INVALID_JSON,
                401 => ApiCode::UNAUTHENTICATED,
                403 => ApiCode::FORBIDDEN,
                404 => ApiCode::NOT_FOUND,
                405 => ApiCode::METHOD_NOT_ALLOWED,
                419 => ApiCode::CSRF_MISMATCH,
                429 => ApiCode::TOO_MANY_REQUESTS,
                503 => ApiCode::SERVICE_UNAVAILABLE,
                default => ApiCode::SERVER_ERROR,
            };

            return ApiResponse::error($code);
        }

        $meta = null;
        if (Config::get('app.debug')) {
            $meta = ['debug' => [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile() . ':' . $e->getLine(),
            ]];
        }

        return ApiResponse::error(ApiCode::SERVER_ERROR, null, null, null, $meta);
    }
}
