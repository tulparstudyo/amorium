<?php namespace AmoriumCms\Shop\Classes\Middleware;

use Closure;

/**
 * PublicCache lets browsers and CDNs keep storefront responses for a short time.
 * The ETag lets a client revalidate without downloading the body again.
 */
class PublicCache
{
    public function handle($request, Closure $next)
    {
        $response = $next($request);

        if (!$request->isMethod('GET') || $response->getStatusCode() !== 200) {
            return $response;
        }

        $etag = '"' . md5((string) $response->getContent()) . '"';

        $response->headers->set('Cache-Control', 'public, max-age=60, stale-while-revalidate=300');
        $response->headers->set('ETag', $etag);

        $sent = array_map('trim', explode(',', (string) $request->header('If-None-Match')));
        if (in_array($etag, $sent, true)) {
            $response->setNotModified();
        }

        return $response;
    }
}
