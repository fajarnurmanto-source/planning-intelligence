<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final class LoginDiagnostics
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('POST') && $request->is('api/auth/login')) {
            $request->attributes->set('pi_login_correlation', (string) Str::uuid());
        }
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        $id = $request->attributes->get('pi_login_correlation');
        if (! is_string($id)) return;
        $status = $response->getStatusCode();
        $data = json_decode((string) $response->getContent(), true);
        $allowed = ['INVALID_CREDENTIALS', 'LOGIN_RATE_LIMITED'];
        $code = is_array($data) && in_array($data['code'] ?? null, $allowed, true)
            ? $data['code'] : match ($status) {
                200 => 'AUTHENTICATED', 419 => 'CSRF_REJECTED',
                422 => 'VALIDATION_REJECTED', 429 => 'THROTTLED', default => 'OTHER_RESPONSE',
            };
        $fields = $status === 422 && is_array($data['errors'] ?? null)
            ? array_values(array_intersect(['username', 'password'], array_keys($data['errors']))) : [];
        // Strict allowlist: never log request data, exception messages, headers or identities.
        Log::info('PI_LOGIN_DIAGNOSTIC', [
            'correlation_id' => $id, 'http_status' => $status,
            'rejection_code' => $code, 'validation_fields' => $fields,
        ]);
    }
}
