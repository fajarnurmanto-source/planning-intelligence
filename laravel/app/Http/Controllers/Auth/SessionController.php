<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use App\Services\PlanningIntelligence\LegacyPasswordVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class SessionController extends Controller
{
    public function setupStatus(): JsonResponse
    {
        return response()->json([
            'needs_setup' => User::query()->doesntExist(),
            'csrf' => csrf_token(),
        ]);
    }

    public function store(LoginRequest $request, LegacyPasswordVerifier $legacyVerifier): JsonResponse
    {
        $username = Str::lower($request->string('username')->toString());
        $rateLimitKey = Str::transliterate($username.'|'.$request->ip());

        if (RateLimiter::tooManyAttempts($rateLimitKey, 5)) {
            return response()->json(['status' => 'REJECTED', 'code' => 'LOGIN_RATE_LIMITED'], 429);
        }

        $user = User::query()->where('username', $username)->first();
        $password = $request->string('password')->toString();
        $valid = $user !== null && (
            (is_string($user->password) && Hash::check($password, $user->password))
            || $legacyVerifier->verify($user, $password)
        );

        if (! $valid || $user === null) {
            RateLimiter::hit($rateLimitKey, 60);

            return response()->json(['status' => 'REJECTED', 'code' => 'INVALID_CREDENTIALS'], 401);
        }

        if ($legacyVerifier->verify($user, $password)) {
            $user->forceFill([
                'password' => $password,
                'legacy_password_salt' => null,
                'legacy_password_digest' => null,
                'legacy_password_iterations' => null,
            ])->save();
        }

        RateLimiter::clear($rateLimitKey);
        Auth::login($user);
        $request->session()->regenerate();

        return response()->json([
            'status' => 'AUTHENTICATED',
            'user' => ['username' => $user->username, 'tenant_id' => $user->tenant_id, 'role' => $user->role],
        ]);
    }

    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'status' => 'AUTHENTICATED',
            'username' => $user?->username,
            'tenant_id' => $user?->tenant_id,
            'role' => $user?->role,
            'csrf' => csrf_token(),
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['status' => 'SIGNED_OUT']);
    }
}
