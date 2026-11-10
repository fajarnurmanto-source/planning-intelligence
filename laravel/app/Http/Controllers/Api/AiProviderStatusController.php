<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PlanningIntelligence\AiProviderCredentialVault;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Laravel-native migration of R69A provider configuration discovery.
 * No inference, outbound requests, secrets, or provider connection tests.
 */
final class AiProviderStatusController extends Controller
{
    public function __invoke(Request $request, AiProviderCredentialVault $vault): JsonResponse
    {
        $providers = [];
        foreach ([
            'ollama' => ['model_key' => 'PI_AI_OLLAMA_MODEL', 'default' => 'gemma3:1b', 'key' => null],
            'gemini' => ['model_key' => 'PI_AI_GEMINI_MODEL', 'default' => 'gemini-3.5-flash-lite', 'key' => 'GEMINI_API_KEY'],
            'deepseek' => ['model_key' => 'PI_AI_DEEPSEEK_MODEL', 'default' => 'deepseek-flash', 'key' => 'DEEPSEEK_API_KEY'],
            'groq' => ['model_key' => 'PI_AI_GROQ_MODEL', 'default' => '', 'key' => 'GROQ_API_KEY'],
            'openrouter' => ['model_key' => 'PI_AI_OPENROUTER_MODEL', 'default' => '', 'key' => 'OPENROUTER_API_KEY'],
            'openai' => ['model_key' => 'PI_AI_OPENAI_MODEL', 'default' => '', 'key' => 'OPENAI_API_KEY'],
            'compatible' => ['model_key' => 'PI_AI_COMPAT_MODEL', 'default' => '', 'key' => 'PI_AI_COMPAT_API_KEY'],
        ] as $name => $settings) {
            $cloud = isset(AiProviderCredentialVault::PROVIDERS[$name])
                ? $vault->resolve((string) $request->user()->tenant_id, $name) : null;
            $model = trim((string) ($cloud['model'] ?? (getenv($settings['model_key']) ?: $settings['default'])));
            $reason = 'READY_TO_ATTEMPT_NOT_TESTED';
            $valid = preg_match('~^[A-Za-z0-9_.:/-]{1,100}$~D', $model) === 1;
            if (! $valid) {
                $reason = 'MODEL_NOT_CONFIGURED_OR_INVALID';
            } elseif ($name === 'ollama') {
                $base = trim((string) (getenv('PI_AI_OLLAMA_BASE_URL') ?: 'http://127.0.0.1:11434/v1'));
                if (! $this->validBase($base, true)) {
                    $valid = false;
                    $reason = 'OLLAMA_MUST_BE_LOCAL_LOOPBACK';
                }
            } elseif ($name === 'compatible') {
                $base = trim((string) (getenv('PI_AI_COMPAT_BASE_URL') ?: ''));
                if (! $this->validBase($base, false)) {
                    $valid = false;
                    $reason = 'COMPAT_PROVIDER_REQUIRES_EXPLICIT_HTTPS_HOST_ALLOWLIST';
                }
            }

            if ($valid && $settings['key'] !== null
                && trim((string) ($cloud['api_key'] ?? getenv($settings['key']))) === '') {
                $valid = false;
                $reason = 'API_KEY_OR_MODEL_NOT_CONFIGURED';
            }

            $providers[] = [
                'id' => $name,
                'model' => $valid ? $model : '',
                'configured' => $valid,
                'connection' => 'NOT_TESTED',
                'reason' => $reason,
                'remote' => $name !== 'ollama',
            ];
        }

        return response()->json([
            'schema' => 'PI_R69A_AI_PROVIDER_STATUS_V1',
            'providers' => $providers,
            'default_provider' => 'gemini',
            'cloud_providers' => ['gemini', 'deepseek', 'groq', 'openrouter', 'openai'],
            'mode' => 'USER_SELECTS_PER_REQUEST',
            'cloud_permission' => 'EXPLICIT_PER_REQUEST',
            'api_keys_returned' => false,
            'numeric_comparison' => 'BLOCKED',
            'production_admission' => 'DENIED',
        ])->header('Cache-Control', 'no-store');
    }

    private function validBase(string $base, bool $local): bool
    {
        $parts = parse_url($base);
        if (! is_array($parts) || isset($parts['user']) || isset($parts['pass']) ||
            isset($parts['query']) || isset($parts['fragment'])) {
            return false;
        }

        $host = strtolower((string) ($parts['host'] ?? ''));
        $scheme = (string) ($parts['scheme'] ?? '');
        $path = rtrim((string) ($parts['path'] ?? ''), '/');
        if (! in_array($path, ['/v1', '/openai/v1', '/api/v1'], true)) {
            return false;
        }

        if ($local) {
            return $scheme === 'http' &&
                in_array($host, ['localhost', '127.0.0.1', '::1'], true) &&
                isset($parts['port']) && $parts['port'] >= 1 && $parts['port'] <= 65535;
        }

        $allowed = array_filter(array_map('trim', explode(',', strtolower((string) getenv('PI_AI_COMPAT_ALLOWED_HOSTS')))));
        return $scheme === 'https' &&
            in_array($host, array_merge(['api.groq.com', 'openrouter.ai'], $allowed), true);
    }
}
