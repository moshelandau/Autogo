<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\AuditLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuditLogMiddleware
{
    private const SCRUB_KEYS = [
        'password','password_confirmation','_token','cvc','cvv','card_number',
        'secret_key','api_key','client_secret','signature_data_url','auth_token',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $start = microtime(true);

        /** @var Response $response */
        $response = $next($request);

        // Log state-changing requests (and auth events)
        $method = $request->method();
        $isStateChange = in_array($method, ['POST','PUT','PATCH','DELETE'], true);
        $isLoginFlow = in_array($request->path(), ['login','logout','two-factor-challenge']);
        if (!$isStateChange && !$isLoginFlow) return $response;

        try {
            AuditLog::create([
                'user_id'     => $request->user()?->id,
                'user_name'   => $request->user()?->name,
                'method'      => $method,
                'path'        => '/' . ltrim($request->path(), '/'),
                'action'      => $this->actionFromPath($request->path(), $method),
                'params'      => $this->scrub($request->all()),
                'ip_address'  => $request->ip(),
                'user_agent'  => substr((string) $request->userAgent(), 0, 255),
                'source'      => $request->header('X-Client-Source', str_contains($request->path(), 'api/') ? 'api' : 'web'),
                'status_code' => $response->getStatusCode(),
                'duration_ms' => (int) ((microtime(true) - $start) * 1000),
            ]);
        } catch (\Throwable) { /* never block a request on audit failure */ }

        return $response;
    }

    private function scrub(array $data): array
    {
        foreach ($data as $k => $v) {
            if (is_array($v))             $data[$k] = $this->scrub($v);
            elseif (in_array(strtolower((string)$k), self::SCRUB_KEYS, true)) $data[$k] = '[redacted]';
            elseif (is_string($v) && strlen($v) > 2000) $data[$k] = substr($v, 0, 2000) . '…[truncated]';
        }
        return $data;
    }

    private function actionFromPath(string $path, string $method): string
    {
        // Pull a human label from the final slug of the path + method
        $last = basename($path);
        return strtolower("$method " . $last);
    }
}
