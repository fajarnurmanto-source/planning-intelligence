<?php

namespace App\Services\PlanningIntelligence;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use JsonException;
use RuntimeException;

/** Tenant-isolated, encrypted cloud credentials; never exposed by public APIs. */
final class AiProviderCredentialVault
{
    public const PROVIDERS = [
        'gemini' => ['key_env' => 'GEMINI_API_KEY', 'model_env' => 'PI_AI_GEMINI_MODEL', 'default' => 'gemini-3.5-flash-lite'],
        'deepseek' => ['key_env' => 'DEEPSEEK_API_KEY', 'model_env' => 'PI_AI_DEEPSEEK_MODEL', 'default' => 'deepseek-flash'],
        'groq' => ['key_env' => 'GROQ_API_KEY', 'model_env' => 'PI_AI_GROQ_MODEL', 'default' => ''],
        'openrouter' => ['key_env' => 'OPENROUTER_API_KEY', 'model_env' => 'PI_AI_OPENROUTER_MODEL', 'default' => ''],
        'openai' => ['key_env' => 'OPENAI_API_KEY', 'model_env' => 'PI_AI_OPENAI_MODEL', 'default' => ''],
    ];

    /** @return array{api_key:string,model:string,source:string,stored_key:bool} */
    public function resolve(string $tenantId, string $provider): array
    {
        $definition = self::PROVIDERS[$provider] ?? null;
        if ($definition === null) {
            throw new RuntimeException('PI_AI_PROVIDER_UNSUPPORTED');
        }

        $stored = $this->stored($tenantId, $provider);
        $storedKey = trim((string) ($stored['api_key'] ?? ''));
        $environmentKey = trim((string) (getenv($definition['key_env']) ?: ''));
        $model = trim((string) ($stored['model'] ?? (getenv($definition['model_env']) ?: $definition['default'])));

        return [
            'api_key' => $storedKey !== '' ? $storedKey : $environmentKey,
            'model' => $model,
            'source' => $storedKey !== '' ? 'encrypted_vault' : ($environmentKey !== '' ? 'environment' : 'none'),
            'stored_key' => $storedKey !== '',
        ];
    }

    /** @return array<string, string> */
    public function stored(string $tenantId, string $provider): array
    {
        $data = $this->load($tenantId);
        $setting = $data['providers'][$provider] ?? [];

        return is_array($setting) ? $setting : [];
    }

    public function save(string $tenantId, string $provider, string $apiKey, string $model): void
    {
        if (! array_key_exists($provider, self::PROVIDERS)) {
            throw new RuntimeException('PI_AI_PROVIDER_UNSUPPORTED');
        }

        $data = $this->load($tenantId);
        $data['providers'][$provider] = ['api_key' => $apiKey, 'model' => $model];
        $this->persist($tenantId, $data);
    }

    public function forget(string $tenantId, string $provider): void
    {
        $data = $this->load($tenantId);
        unset($data['providers'][$provider]);
        $this->persist($tenantId, $data);
    }

    private function path(string $tenantId): string
    {
        return 'pi-ai/tenant-'.hash('sha256', $tenantId).'.vault';
    }

    /** @return array{schema:string,tenant_digest:string,providers:array<string,mixed>} */
    private function load(string $tenantId): array
    {
        $path = $this->path($tenantId);
        $disk = Storage::disk('local');
        $digest = hash('sha256', $tenantId);

        if (! $disk->exists($path)) {
            return [
                'schema' => 'PI_AI_CREDENTIAL_VAULT_V1',
                'tenant_digest' => $digest,
                'providers' => [],
            ];
        }

        $ciphertext = $disk->get($path);
        if (! is_string($ciphertext) || strlen($ciphertext) > 65536) {
            throw new RuntimeException('PI_AI_VAULT_UNAVAILABLE');
        }

        try {
            $data = json_decode(Crypt::decryptString($ciphertext), true, 8, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            throw new RuntimeException('PI_AI_VAULT_UNAVAILABLE');
        }

        if (! is_array($data)
            || ($data['schema'] ?? null) !== 'PI_AI_CREDENTIAL_VAULT_V1'
            || ! is_string($data['tenant_digest'] ?? null)
            || ! hash_equals($digest, $data['tenant_digest'])
            || ! is_array($data['providers'] ?? null)) {
            throw new RuntimeException('PI_AI_VAULT_UNAVAILABLE');
        }

        return $data;
    }

    /** @param array<string,mixed> $data */
    private function persist(string $tenantId, array $data): void
    {
        try {
            $ciphertext = Crypt::encryptString(json_encode($data, JSON_THROW_ON_ERROR));
        } catch (JsonException) {
            throw new RuntimeException('PI_AI_VAULT_UNAVAILABLE');
        }

        if (Storage::disk('local')->put($this->path($tenantId), $ciphertext) !== true) {
            throw new RuntimeException('PI_AI_VAULT_WRITE_FAILED');
        }
    }
}
