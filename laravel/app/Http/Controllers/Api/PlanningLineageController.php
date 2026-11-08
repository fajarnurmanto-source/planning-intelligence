<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use RuntimeException;
use Throwable;

final class PlanningLineageController extends Controller
{
    public function __invoke(): JsonResponse
    {
        try {
            // This config key is server-side only and makes fail-closed fixture QA possible.
            $path = (string) config('pi.planning_lineage.registry_path');
            $raw = is_file($path) ? file_get_contents($path) : false;
            if (!is_string($raw) || $raw === '') {
                throw new RuntimeException('Planning registry unavailable.');
            }

            $registry = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($registry)) {
                throw new RuntimeException('Planning registry is not an object.');
            }
            $this->assertRegistry($registry);

            $documents = $registry['documents'];
            $rpjmd = $this->documentOfKind($documents, 'RPJMD');
            $rkpd = $this->documentOfKind($documents, 'RKPD');
            $draftReferencePredatesFinal = $rkpd['issued_on'] < $rpjmd['issued_on'];

            return response()->json([
                'schema' => 'PI_R70J_DOCUMENT_LINEAGE_READ_MODEL_V1',
                'tenant_id' => $registry['jurisdiction_id'],
                'planning_year' => $registry['planning_year'],
                'source_registry_sha256' => strtoupper(hash('sha256', $raw)),
                'source_integrity' => 'REGISTRY_SHA256_ONLY_PDF_NOT_PINNED',
                'source_access_policy' => $registry['access_policy'],
                'documents' => $documents,
                'relations' => $registry['relations'],
                'chronology' => [
                    'rkpd_issued_on' => $rkpd['issued_on'],
                    'rpjmd_final_issued_on' => $rpjmd['issued_on'],
                    'rkpd_predates_rpjmd_final' => $draftReferencePredatesFinal,
                    'reference_type_in_rkpd' => 'DRAFT_RPJMD',
                    'interpretation' => 'VERSION_REVIEW_REQUIRED_NOT_VIOLATION',
                ],
                'interpretation' => [
                    'mapping_status' => 'MAPPING_CANDIDATE',
                    'review_status' => 'REVIEW_REQUIRED',
                    'legal_harmonization' => 'NOT_ASSESSED',
                    'substantive_alignment' => 'NOT_ASSESSED',
                    'numeric_comparison' => 'BLOCKED',
                    'production_admission' => 'DENIED',
                ],
            ])->header('Cache-Control', 'private, no-store');
        } catch (Throwable $error) {
            report($error);

            return response()->json([
                'status' => 'REJECTED',
                'code' => 'PLANNING_LINEAGE_SOURCE_UNAVAILABLE',
            ], 503)->header('Cache-Control', 'private, no-store');
        }
    }

    private function assertRegistry(array $registry): void
    {
        if (($registry['schema'] ?? null) !== 'PI_R70J_PLANNING_LINEAGE_SOURCE_REGISTRY_V1'
            || ($registry['jurisdiction_id'] ?? null) !== 'ID-DIY-YOG'
            || ($registry['jurisdiction_id'] ?? null) !== config('pi.tenant_id')
            || ($registry['planning_year'] ?? null) !== 2026
            || ($registry['access_policy'] ?? null) !== 'PUBLIC_OFFICIAL_METADATA_READ_ONLY_NO_PDF_REPUBLICATION'
            || ($registry['review_required'] ?? null) !== true
            || ($registry['legal_harmonization'] ?? null) !== 'NOT_ASSESSED'
            || ($registry['numeric_comparison'] ?? null) !== 'BLOCKED'
            || ($registry['production_admission'] ?? null) !== 'DENIED'
            || !isset($registry['documents'], $registry['relations'])
            || !is_array($registry['documents']) || count($registry['documents']) !== 2
            || !is_array($registry['relations']) || count($registry['relations']) !== 2) {
            throw new RuntimeException('Planning registry contract mismatch.');
        }

        $expected = [
            'RPJMD' => ['ID-DIY-YOG-RPJMD-2025-2029-PERDA-2-2025', '2025-08-15', 2025, 2029],
            'RKPD' => ['ID-DIY-YOG-RKPD-2026-PERWALI-34-2025', '2025-07-07', 2026, 2026],
        ];
        foreach ($registry['documents'] as $doc) {
            if (!is_array($doc) || !isset($expected[$doc['kind'] ?? ''])) {
                throw new RuntimeException('Unknown planning document.');
            }
            [$id, $issued, $from, $to] = $expected[$doc['kind']];
            if (($doc['id'] ?? null) !== $id || ($doc['issued_on'] ?? null) !== $issued
                || ($doc['period_from'] ?? null) !== $from || ($doc['period_to'] ?? null) !== $to
                || !array_key_exists('pdf_sha256', $doc) || $doc['pdf_sha256'] !== null
                || ($doc['pdf_snapshot_state'] ?? null) !== 'NOT_CAPTURED_NO_HASH'
                || !is_string($doc['verified_locator'] ?? null) || trim($doc['verified_locator']) === ''
                || !is_string($doc['source_observation'] ?? null) || trim($doc['source_observation']) === ''
                || !is_string($doc['title'] ?? null) || trim($doc['title']) === '') {
                throw new RuntimeException('Planning document metadata is incomplete or unpinned.');
            }
            $this->assertOfficialUrl($doc['metadata_url'] ?? null, ['jdih.jogjakota.go.id']);
            $this->assertOfficialUrl($doc['pdf_url'] ?? null, ['jdih.jogjakota.go.id', 'peraturan.bpk.go.id']);
        }

        foreach ($expected as $kind => $metadata) {
            if (count(array_filter($registry['documents'], static fn ($doc): bool => $doc['kind'] === $kind)) !== 1) {
                throw new RuntimeException('Duplicate planning document.');
            }
        }
        $ids = array_column($registry['documents'], 'id');
        $requiredTypes = ['PLANNING_DOCUMENT_NORMATIVE_REFERENCE', 'DRAFT_VERSION_REFERENCE_REQUIRES_RECONCILIATION'];
        foreach ($registry['relations'] as $relation) {
            if (!is_array($relation) || !in_array($relation['type'] ?? null, $requiredTypes, true)
                || !in_array($relation['from'] ?? null, $ids, true)
                || !in_array($relation['to'] ?? null, $ids, true)
                || ($relation['from'] ?? null) === ($relation['to'] ?? null)
                || !in_array($relation['source_document_id'] ?? null, $ids, true)
                || ($relation['mapping_status'] ?? null) !== 'MAPPING_CANDIDATE'
                || ($relation['substantive_alignment'] ?? null) !== 'NOT_ASSESSED'
                || !is_string($relation['source_locator'] ?? null)
                || trim($relation['source_locator']) === '') {
                throw new RuntimeException('Planning relation contract mismatch.');
            }
        }
        if (count(array_unique(array_column($registry['relations'], 'type'))) !== 2) {
            throw new RuntimeException('Missing normative or draft-version relationship.');
        }
    }

    private function documentOfKind(array $documents, string $kind): array
    {
        foreach ($documents as $document) {
            if ($document['kind'] === $kind) {
                return $document;
            }
        }
        throw new RuntimeException('Planning document kind missing.');
    }

    private function assertOfficialUrl(mixed $url, array $allowedHosts): void
    {
        if (!is_string($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            throw new RuntimeException('Invalid official source URL.');
        }
        $parts = parse_url($url);
        if (!is_array($parts) || ($parts['scheme'] ?? '') !== 'https'
            || !in_array($parts['host'] ?? '', $allowedHosts, true)
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['port'])) {
            throw new RuntimeException('Unexpected source origin.');
        }
    }
}
