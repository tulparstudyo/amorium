<?php namespace AmoriumCms\Shop\Classes\Middleware;

use Closure;
use AmoriumCms\Shop\Classes\Api\ApiCode;
use AmoriumCms\Shop\Classes\Api\ApiException;

/**
 * ValidateJson rejects a malformed JSON body, which Laravel would otherwise read as empty.
 */
class ValidateJson
{
    public function handle($request, Closure $next)
    {
        $content = $request->getContent();

        if ($request->isJson() && trim($content) !== '') {
            $decoded = json_decode($content, true);

            if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
                throw ApiException::make(ApiCode::INVALID_JSON);
            }
        }

        return $next($request);
    }
}
