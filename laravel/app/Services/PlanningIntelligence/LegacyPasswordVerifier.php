<?php

namespace App\Services\PlanningIntelligence;

use App\Models\User;

class LegacyPasswordVerifier
{
    public const Iterations = 600000;

    public function verify(User $user, string $password): bool
    {
        if ($user->legacy_password_iterations !== self::Iterations
            || ! is_string($user->legacy_password_salt)
            || ! is_string($user->legacy_password_digest)
            || preg_match('/^[0-9a-f]{32}$/i', $user->legacy_password_salt) !== 1
            || preg_match('/^[0-9a-f]{64}$/i', $user->legacy_password_digest) !== 1) {
            return false;
        }

        $derived = hash_pbkdf2(
            'sha256',
            $password,
            hex2bin($user->legacy_password_salt),
            self::Iterations,
            64,
        );

        return hash_equals(strtolower($user->legacy_password_digest), strtolower($derived));
    }
}
