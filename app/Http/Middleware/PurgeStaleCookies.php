<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Deletes cookies left over from a previous deployment / different Laravel
 * app on this origin so users don't hit 419 CSRF errors.
 */
class PurgeStaleCookies
{
    private const STALE_PREFIXES = [
        'attendancesystem_',
        'autogo-session',      // old hyphenated cookie name; new one is autogo_session
        'laravel_session',
        'XSRF-TOKEN_OLD',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $stale = [];
        foreach ($request->cookies->all() as $name => $_) {
            foreach (self::STALE_PREFIXES as $prefix) {
                if ($name === $prefix || str_starts_with($name, $prefix)) {
                    $stale[] = $name;
                    break;
                }
            }
        }

        /** @var Response $response */
        $response = $next($request);

        foreach ($stale as $name) {
            foreach (['/', '/login', '/dashboard'] as $path) {
                foreach ([null, $request->getHost(), '.' . ltrim($request->getHost(), '.'), '.autogoco.com'] as $domain) {
                    $response->headers->clearCookie($name, $path, $domain);
                }
            }
        }

        return $response;
    }
}
