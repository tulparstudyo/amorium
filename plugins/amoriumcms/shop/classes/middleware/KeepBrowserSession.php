<?php namespace AmoriumCms\Shop\Classes\Middleware;

use Closure;
use Config;

/**
 * KeepBrowserSession stops a cross-site payment return from replacing the browser session.
 * PayTR and iyzico post the customer back. That request does not include a SameSite=Lax
 * session cookie, so a new session cookie would sign the customer out.
 */
class KeepBrowserSession
{
    public function handle($request, Closure $next)
    {
        $name = (string) Config::get('session.cookie');
        $response = $next($request);

        if ($name === '' || $request->cookies->has($name)) {
            return $response;
        }

        foreach ($response->headers->getCookies() as $cookie) {
            if ($cookie->getName() === $name) {
                $response->headers->removeCookie($cookie->getName(), $cookie->getPath(), $cookie->getDomain());
            }
        }

        return $response;
    }
}
