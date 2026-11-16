<?php

namespace App\Services\Pi\Regulatory;

use RuntimeException;

/**
 * R84 fingerprint matches the immutable R82 candidate payload exactly.
 * The hash is a concurrency/source-staleness token, not a legal conclusion.
 */
final class R84EvidenceFingerprint
{
    /** @param array<string, mixed> $candidate */
    public function candidate(array $candidate): string
    {
        if (! is_string($candidate['target_provision_id'] ?? null)
            || ($candidate['review_status'] ?? null) !== 'REVIEW_REQUIRED'
            || ($candidate['relation_admission'] ?? null) !== 'NOT_PERFORMED') {
            throw new RuntimeException('R84_FINGERPRINT_INPUT_REJECTED');
        }

        return strtoupper(hash('sha256', json_encode(
            $candidate,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES
        )));
    }
}
