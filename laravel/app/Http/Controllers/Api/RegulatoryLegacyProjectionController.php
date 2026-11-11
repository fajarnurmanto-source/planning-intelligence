<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PlanningIntelligence\RegulatoryChainProjector;
use Illuminate\Http\JsonResponse;
use JsonException;
use RuntimeException;
use Throwable;

/**
 * Temporary native Laravel compatibility for the read-only R70C and R70D QA
 * JSON contracts. No Python server, no arbitrary file query parameters.
 *
 * Each payload is emitted ONLY after the existing R70F projector verifies
 * the linked ledger/scope pair, excerpt hashes and the reviewer/production
 * gates. It is NOT a declaration of full R69E API parity.
 */
final class RegulatoryLegacyProjectionController extends Controller
{
    public function ledger(RegulatoryChainProjector $projector): JsonResponse
    {
        return $this->serve($projector, 'ledger');
    }

    public function scope(RegulatoryChainProjector $projector): JsonResponse
    {
        return $this->serve($projector, 'scope');
    }

    private function serve(RegulatoryChainProjector $projector, string $which): JsonResponse
    {
        try {
            $ledgerPath = (string) config('pi.regulatory.ledger_path');
            $scopePath = (string) config('pi.regulatory.scope_path');

            // Reuse the canonical chain validator, including strict source
            // identity, PDF excerpt hashes, ledger->scope hash and guards.
            $verified = $projector->project($ledgerPath, $scopePath);
            $integrity = $verified['source_integrity'] ?? [];
            $expected = [
                'ledger' => $integrity['ledger_sha256'] ?? null,
                'scope' => $integrity['scope_sha256'] ?? null,
            ];
            $paths = ['ledger' => $ledgerPath, 'scope' => $scopePath];

            if (! ($integrity['scope_links_to_ledger'] ?? false) ||
                ($verified['interpretation']['production_admission'] ?? null) !== 'DENIED' ||
                ($verified['interpretation']['numeric_comparison'] ?? null) !== 'BLOCKED' ||
                ($verified['interpretation']['legal_harmonization'] ?? null) !== 'NOT_ASSESSED' ||
                ($verified['tenant_id'] ?? null) !== config('pi.tenant_id')) {
                throw new RuntimeException('REGULATORY_GUARD_REJECTED');
            }

            // Verify both files again immediately before emission to avoid
            // returning a stale payload when source snapshots have changed.
            $raw = [];
            foreach ($paths as $kind => $path) {
                $contents = file_get_contents($path);
                if (! is_string($contents) ||
                    ! is_string($expected[$kind]) ||
                    strtoupper(hash('sha256', $contents)) !== $expected[$kind]) {
                    throw new RuntimeException('REGULATORY_SOURCE_CHANGED');
                }
                $raw[$kind] = $contents;
            }

            $decoded = json_decode($raw[$which], true, 512, JSON_THROW_ON_ERROR);
            if (! is_array($decoded) || array_is_list($decoded)) {
                throw new RuntimeException('REGULATORY_PAYLOAD_INVALID');
            }
            $schema = $which === 'ledger'
                ? 'PI_R70C_AMENDMENT_LEDGER_QA_V1'
                : 'PI_R70D_OPERATIVE_SCOPE_QA_V1';
            if (($decoded['schema'] ?? null) !== $schema ||
                ($decoded['tenant_id'] ?? null) !== $verified['tenant_id'] ||
                ($decoded['production_admission'] ?? null) !== 'DENIED' ||
                ($decoded['numeric_comparison'] ?? null) !== 'BLOCKED' ||
                ($decoded['legal_harmonization'] ?? null) !== 'NOT_ASSESSED') {
                throw new RuntimeException('REGULATORY_SCHEMA_OR_GUARD_MISMATCH');
            }

            return response()->json($decoded)
                ->header('Cache-Control', 'private, no-store')
                ->header('X-Content-Type-Options', 'nosniff');
        } catch (Throwable $error) {
            report($error);

            return response()->json([
                'status' => 'ABSTAIN',
                'code' => 'REGULATORY_LEGACY_PROJECTION_UNAVAILABLE',
            ], 503)->header('Cache-Control', 'private, no-store');
        }
    }
}
