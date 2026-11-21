<?php

namespace App\Services\PlanningIntelligence;

use JsonException;
use RuntimeException;

/**
 * Read-only, hash-pinned evidence excerpt lookup over the validated R70C/R70D
 * documents. This is a review aid, not a statement of legal applicability.
 */
final class RegulatoryEvidenceResolver
{
    public function __construct(private readonly RegulatoryChainProjector $projector) {}

    /** @return array<string, mixed>|null */
    public function resolve(string $ledgerPath, string $scopePath, string $sourceId, string $excerptSha256): ?array
    {
        $model = $this->projector->project($ledgerPath, $scopePath);
        $document = null;
        foreach ($model['documents'] as $candidate) {
            if ($candidate['id'] === $sourceId) {
                $document = $candidate;
                break;
            }
        }
        if ($document === null) {
            return null;
        }

        // Re-read from the configured local paths and pin their exact bytes to
        // the hashes already validated by the Laravel projector.
        $ledger = $this->readPinned($ledgerPath, $model['source_integrity']['ledger_sha256']);
        $scope = $this->readPinned($scopePath, $model['source_integrity']['scope_sha256']);
        $wanted = strtoupper($excerptSha256);
        $matches = [];

        foreach ($ledger['source_chain'] as $entry) {
            if ($entry['id'] !== $sourceId) {
                continue;
            }
            $this->collect($matches, $entry['evidence'], $wanted, 'document', null);
        }

        foreach ($scope['timeline'] as $row) {
            if ($row['id'] !== $sourceId) {
                continue;
            }
            foreach ($row['targets'] as $target) {
                $this->collect($matches, $target['evidence'], $wanted, 'operative_target',
                    $target['target']);
            }
            foreach (($row['dates'] ?? []) as $date) {
                $this->collect($matches, [$date['evidence']], $wanted, 'date_candidate',
                    $date['type'] ?? null);
            }
        }

        if ($matches === []) {
            return null;
        }

        return [
            'schema' => 'PI_R70H_VERIFIED_EVIDENCE_LOOKUP_V1',
            'source_id' => $sourceId,
            'source_title' => $document['title'],
            'source_pdf_sha256' => $document['pdf_sha256'],
            'source_url' => $document['source_url'],
            'metadata_url' => $document['metadata_url'],
            'excerpt_sha256' => $wanted,
            'matches' => $matches,
            'source_integrity' => $model['source_integrity'],
            'review_status' => 'REVIEW_REQUIRED',
            'legal_harmonization' => 'NOT_ASSESSED',
            'numeric_comparison' => 'BLOCKED',
            'production_admission' => 'DENIED',
        ];
    }

    /** @return array<string, mixed> */
    private function readPinned(string $path, string $expectedSha256): array
    {
        if ($path === '' || ! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException('EVIDENCE_SOURCE_UNREADABLE');
        }
        $size = filesize($path);
        if ($size === false || $size > 32 * 1024 * 1024) {
            throw new RuntimeException('EVIDENCE_SOURCE_SIZE_OUT_OF_QA_LIMIT');
        }
        $raw = file_get_contents($path);
        if (! is_string($raw) || strtoupper(hash('sha256', $raw)) !== $expectedSha256) {
            throw new RuntimeException('EVIDENCE_SOURCE_HASH_DRIFT');
        }
        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            throw new RuntimeException('EVIDENCE_SOURCE_INVALID_JSON', 0, $error);
        }
        if (! is_array($decoded) || array_is_list($decoded)) {
            throw new RuntimeException('EVIDENCE_SOURCE_INVALID_OBJECT');
        }

        return $decoded;
    }

    /** @param list<array<string, mixed>> $matches
     *  @param list<array<string, mixed>> $items
     */
    private function collect(array &$matches, array $items, string $wanted, string $context, mixed $detail): void
    {
        foreach ($items as $item) {
            if (strtoupper((string) $item['excerpt_sha256']) !== $wanted) {
                continue;
            }
            // Hash, PDF hash, source identity and page were validated by the
            // projector. Never accept a free-form excerpt from the request.
            $matches[] = [
                'context' => $context,
                'context_detail' => $detail,
                'pdf_page' => $item['pdf_page'],
                'evidence_type' => $item['evidence_type'] ?? 'UNCLASSIFIED',
                'excerpt' => $item['excerpt'],
                'excerpt_sha256' => $wanted,
                'review_status' => $item['review_status'] ?? ($item['status'] ?? 'REVIEW_REQUIRED'),
            ];
        }
    }
}
