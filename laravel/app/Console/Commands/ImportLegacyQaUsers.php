<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\PlanningIntelligence\LegacyPasswordVerifier;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use JsonException;
use Throwable;

#[Signature('pi:import-legacy-users {--path= : Absolute R69D users.json path}')]
#[Description('Import R69D QA identities without exposing password material')]
class ImportLegacyQaUsers extends Command
{
    public function handle(): int
    {
        $path = $this->option('path') ?: config('pi.legacy_users_path');
        if (! is_string($path) || $path === '' || ! is_file($path) || is_link($path)) {
            $this->error('PI_LEGACY_IMPORT=SOURCE_UNAVAILABLE');

            return self::FAILURE;
        }

        try {
            $payload = json_decode((string) file_get_contents($path), true, 64, JSON_THROW_ON_ERROR);
            if (($payload['schema'] ?? null) !== 'PI_R69D_LOCAL_QA_USERS_V1'
                || ! is_array($payload['users'] ?? null)) {
                throw new JsonException('LEGACY_SCHEMA_INVALID');
            }

            $imported = 0;
            foreach ($payload['users'] as $username => $record) {
                if (! is_string($username) || preg_match('/^[a-zA-Z0-9._-]{3,32}$/', $username) !== 1
                    || ! is_array($record)
                    || ($record['tenant_id'] ?? null) !== config('pi.tenant_id')
                    || ! in_array($record['role'] ?? null, ['institution_admin', 'reviewer', 'viewer'], true)
                    || ($record['iterations'] ?? null) !== LegacyPasswordVerifier::Iterations
                    || preg_match('/^[0-9a-f]{32}$/i', (string) ($record['salt'] ?? '')) !== 1
                    || preg_match('/^[0-9a-f]{64}$/i', (string) ($record['digest'] ?? '')) !== 1) {
                    throw new JsonException('LEGACY_USER_INVALID');
                }

                User::query()->updateOrCreate(
                    ['username' => Str::lower($username)],
                    [
                        'name' => $username,
                        'email' => Str::lower($username).'@qa.pi.invalid',
                        'tenant_id' => $record['tenant_id'],
                        'role' => $record['role'],
                        'password' => Hash::make(Str::random(64)),
                        'legacy_password_salt' => Str::lower($record['salt']),
                        'legacy_password_digest' => Str::lower($record['digest']),
                        'legacy_password_iterations' => $record['iterations'],
                    ],
                );
                $imported++;
            }

            $this->info('PI_LEGACY_IMPORT=PASS');
            $this->line('PI_LEGACY_IMPORT_COUNT='.$imported);

            return self::SUCCESS;
        } catch (Throwable $error) {
            $this->error('PI_LEGACY_IMPORT=BLOCKED:'.$error->getMessage());

            return self::FAILURE;
        }
    }
}
