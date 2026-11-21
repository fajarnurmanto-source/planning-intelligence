<?php

namespace App\Services\Pi\Development;

/**
 * PI-HYBRID-001: untrusted semantic suggestions only, never legal decisions.
 * The orchestrator supplies evidence and reconstructs canonical graph identifiers.
 */
interface SemanticCandidateProvider
{
    /** @param array<string,mixed> $context
     *  @return array<string,mixed>
     */
    public function propose(array $context, string $provider, string $model, string $apiKey): array;
}
