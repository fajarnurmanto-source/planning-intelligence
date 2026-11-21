<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PlanningIntelligence\AiProviderCredentialVault;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/** Administrative secret management; values never leave the server after save. */
final class AiProviderSettingsController extends Controller
{
    public function index(Request $request, AiProviderCredentialVault $vault): JsonResponse
    {
        $denial = $this->denyNonAdmin($request);
        if ($denial !== null) {
            return $denial;
        }

        return $this->overview($request, $vault);
    }

    public function save(Request $request, string $provider, AiProviderCredentialVault $vault): JsonResponse
    {
        $denial = $this->denyNonAdmin($request) ?? $this->denyInsecureTransport($request);
        if ($denial !== null) {
            return $denial;
        }
        if (! array_key_exists($provider, AiProviderCredentialVault::PROVIDERS)) {
            return $this->reject('PROVIDER_UNSUPPORTED', 404);
        }

        $input = $request->validate([
            'api_key' => ['nullable', 'string', 'max:512'],
            'model' => ['required', 'string', 'max:100'],
            'password' => ['required', 'string'],
        ]);
        if (! Hash::check($input['password'], (string) $request->user()->password)) {
            return $this->reject('ADMIN_PASSWORD_INVALID', 403);
        }

        $newKey = trim((string) ($input['api_key'] ?? ''));
        $model = trim($input['model']);
        if (($newKey !== '' && preg_match('~\A[\x21-\x7E]{10,512}\z~D', $newKey) !== 1)
            || preg_match('~\A[A-Za-z0-9_.:/-]{1,100}\z~D', $model) !== 1) {
            return $this->reject('MODEL_OR_KEY_INVALID', 422);
        }

        $tenantId = (string) $request->user()->tenant_id;
        $stored = $vault->stored($tenantId, $provider);
        $key = $newKey !== '' ? $newKey : (string) ($stored['api_key'] ?? '');
        $definition = AiProviderCredentialVault::PROVIDERS[$provider];
        if ($key === '' && trim((string) (getenv($definition['key_env']) ?: '')) === '') {
            return $this->reject('API_KEY_REQUIRED', 422);
        }

        $vault->save($tenantId, $provider, $key, $model);

        return $this->overview($request, $vault);
    }

    public function forget(Request $request, string $provider, AiProviderCredentialVault $vault): JsonResponse
    {
        $denial = $this->denyNonAdmin($request) ?? $this->denyInsecureTransport($request);
        if ($denial !== null) {
            return $denial;
        }
        if (! array_key_exists($provider, AiProviderCredentialVault::PROVIDERS)) {
            return $this->reject('PROVIDER_UNSUPPORTED', 404);
        }

        $input = $request->validate(['password' => ['required', 'string']]);
        if (! Hash::check($input['password'], (string) $request->user()->password)) {
            return $this->reject('ADMIN_PASSWORD_INVALID', 403);
        }

        $vault->forget((string) $request->user()->tenant_id, $provider);

        return $this->overview($request, $vault);
    }

    private function overview(Request $request, AiProviderCredentialVault $vault): JsonResponse
    {
        $providers = [];
        foreach (array_keys(AiProviderCredentialVault::PROVIDERS) as $id) {
            $resolved = $vault->resolve((string) $request->user()->tenant_id, $id);
            $stored = $vault->stored((string) $request->user()->tenant_id, $id);
            $providers[] = [
                'id' => $id,
                'model' => $resolved['model'],
                'configured' => $resolved['api_key'] !== '' && $resolved['model'] !== '',
                'source' => $resolved['source'],
                'stored_key' => $resolved['stored_key'],
                'stored_config' => $stored !== [],
            ];
        }

        return response()->json([
            'schema' => 'PI_AI_PROVIDER_SETTINGS_V1',
            'providers' => $providers,
            'api_keys_returned' => false,
            'production_admission' => 'DENIED',
        ])->header('Cache-Control', 'private, no-store');
    }

    private function denyNonAdmin(Request $request): ?JsonResponse
    {
        if (! in_array($request->user()?->role, ['superadmin', 'institution_admin'], true)) {
            return $this->reject('AI_SETTINGS_ADMIN_REQUIRED', 403);
        }

        return null;
    }

    private function denyInsecureTransport(Request $request): ?JsonResponse
    {
        if (! $request->isSecure()
            && ! in_array(strtolower($request->getHost()), ['localhost', '127.0.0.1', '::1'], true)) {
            return $this->reject('AI_SETTINGS_HTTPS_REQUIRED', 403);
        }

        return null;
    }

    private function reject(string $code, int $status): JsonResponse
    {
        return response()->json(['status' => 'REJECTED', 'code' => $code], $status)
            ->header('Cache-Control', 'private, no-store');
    }
}
