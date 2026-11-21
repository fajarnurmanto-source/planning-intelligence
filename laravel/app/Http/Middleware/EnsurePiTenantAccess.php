<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class EnsurePiTenantAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user === null || $user->tenant_id !== config('pi.tenant_id')) {
            return new JsonResponse(['status' => 'REJECTED', 'code' => 'TENANT_DENIED'], 403);
        }

        return $next($request);
    }
}
