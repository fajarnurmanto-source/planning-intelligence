<?php

namespace App\Services\PlanningIntelligence;

use JsonException;
use RuntimeException;

/**
 * R70F: Laravel-native, source-anchored Regulatory read model.
 * Reads R70C/R70D only; never interprets legal force, diffs annex values,
 * edits official PDFs, or changes database state.
 */
final class RegulatoryChainProjector
{
    private int $evidenceVerified = 0;

    /** @return array<string, mixed> */
    public function project(string $ledgerPath, string $scopePath, bool $includeVerifiedExcerpts = false): array
    {
        $this->evidenceVerified = 0;
        $ledger = $this->load($ledgerPath);
        $scope = $this->load($scopePath);
        $ledgerHash = strtoupper(hash_file('sha256', $ledgerPath));
        $scopeHash = strtoupper(hash_file('sha256', $scopePath));

        $this->requireSame('ledger.schema', 'PI_R70C_AMENDMENT_LEDGER_QA_V1', $ledger['schema'] ?? null);
        $this->requireSame('scope.schema', 'PI_R70D_OPERATIVE_SCOPE_QA_V1', $scope['schema'] ?? null);
        $this->requireSame('scope.source_ledger_schema', $ledger['schema'], $scope['source_ledger_schema'] ?? null);
        $this->requireSame('scope.source_ledger_sha256', $ledgerHash,
            strtoupper((string) ($scope['source_ledger_sha256'] ?? '')));
        $this->requireSame('tenant_id', $ledger['tenant_id'] ?? null, $scope['tenant_id'] ?? null);
        $this->requireSame('pilot', $ledger['pilot'] ?? null, $scope['pilot'] ?? null);
        $this->requireSame('ledger.numeric_comparison', 'BLOCKED', $ledger['numeric_comparison'] ?? null);
        $this->requireSame('scope.numeric_comparison', 'BLOCKED', $scope['numeric_comparison'] ?? null);
        $this->requireSame('ledger.legal_harmonization', 'NOT_ASSESSED', $ledger['legal_harmonization'] ?? null);
        $this->requireSame('scope.legal_harmonization', 'NOT_ASSESSED', $scope['legal_harmonization'] ?? null);
        $this->requireSame('ledger.version_reconciliation', 'NOT_PERFORMED',
            $ledger['full_annex_version_reconciliation'] ?? null);
        $this->requireSame('scope.annex_content_diff', 'NOT_PERFORMED', $scope['annex_content_diff'] ?? null);
        $this->requireSame('ledger.reviewer_gate', 'REQUIRED', $ledger['reviewer_gate'] ?? null);
        $this->requireSame('scope.reviewer_gate', 'REQUIRED', $scope['reviewer_gate'] ?? null);
        $this->requireSame('ledger.production_admission', 'DENIED', $ledger['production_admission'] ?? null);
        $this->requireSame('scope.production_admission', 'DENIED', $scope['production_admission'] ?? null);

        $sourceChain = $ledger['source_chain'] ?? null;
        $timeline = $scope['timeline'] ?? null;
        $this->requireTrue(is_array($sourceChain) && array_is_list($sourceChain), 'LEDGER_SOURCE_CHAIN_NOT_LIST');
        $this->requireTrue(is_array($timeline) && array_is_list($timeline), 'SCOPE_TIMELINE_NOT_LIST');
        $sourceCount = count($sourceChain);
        $this->requireSame('source_count', $sourceCount, count($timeline));
        $this->requireSame('ledger.official_sources', $sourceCount, $ledger['official_sources'] ?? null);
        $this->requireSame('scope.source_count', $sourceCount, $scope['source_count'] ?? null);
        $this->requireSame('ledger.identity_verified', $sourceCount, $ledger['identity_verified'] ?? null);

        $seen = [];
        $projected = [];
        $amendmentCount = 0;
        $ledgerTargetCount = 0;
        $scopeTargetCount = 0;
        $metadataAnomalies = 0;
        $expandedTargets = 0;

        foreach ($sourceChain as $index => $entry) {
            $row = $timeline[$index] ?? null;
            $this->requireTrue(is_array($entry) && is_array($row), 'DOCUMENT_ROW_NOT_OBJECT');
            $id = $entry['id'] ?? null;
            $this->requireTrue(is_string($id) && preg_match('/^[A-Z0-9_]+$/', $id) === 1,
                'INVALID_SOURCE_ID');
            $this->requireTrue(! isset($seen[$id]), 'DUPLICATE_SOURCE_ID:'.$id);
            $seen[$id] = true;
            $this->requireSame($id.'.id', $id, $row['id'] ?? null);
            $this->requireSame($id.'.sequence', $entry['sequence'] ?? null, $row['sequence'] ?? null);

            $pdfHash = $this->fingerprint($entry['pdf_sha256'] ?? null, $id.'.pdf_sha256');
            $ledgerEvidence = $this->evidenceRefs($entry['evidence'] ?? null, $id, $pdfHash, $includeVerifiedExcerpts);
            $ledgerTargets = $entry['explicit_targets'] ?? null;
            $scopeTargets = $row['targets'] ?? null;
            $this->requireTrue(is_array($ledgerTargets) && array_is_list($ledgerTargets),
                $id.':LEDGER_TARGETS_NOT_LIST');
            $this->requireTrue(is_array($scopeTargets) && array_is_list($scopeTargets),
                $id.':SCOPE_TARGETS_NOT_LIST');

            $mappedTargets = [];
            $targetNames = [];
            foreach ($scopeTargets as $target) {
                $this->requireTrue(is_array($target), $id.':TARGET_NOT_OBJECT');
                $name = $target['target'] ?? null;
                $this->requireTrue(in_array($name, ['LAMPIRAN I', 'LAMPIRAN II'], true),
                    $id.':UNKNOWN_TARGET');
                $this->requireTrue(! in_array($name, $targetNames, true), $id.':DUPLICATE_TARGET');
                $targetNames[] = $name;

                $start = $target['start_pdf_page'] ?? null;
                $verb = $target['verb_pdf_page'] ?? null;
                $span = $target['operative_page_span'] ?? null;
                $this->requireTrue(is_int($start) && $start > 0 && is_int($verb) && $verb >= $start,
                    $id.':INVALID_PAGE_SPAN');
                $this->requireSame($id.':OPERATIVE_SPAN', $verb - $start + 1, $span);

                $codes = $target['organizational_codes'] ?? null;
                $this->requireTrue(is_array($codes) && array_is_list($codes), $id.':ORG_CODES_NOT_LIST');
                $this->requireSame($id.':ORG_CODES_COUNT', count($codes),
                    $target['organizational_code_count'] ?? null);
                $mappedTargets[] = [
                    'target' => $name,
                    'item' => $target['item'] ?? null,
                    'change_verb' => $target['detected_change_verb'] ?? null,
                    'start_pdf_page' => $start,
                    'verb_pdf_page' => $verb,
                    'operative_page_span' => $span,
                    'organizational_code_count' => count($codes),
                    'organizational_codes' => $codes,
                    'review_status' => $target['review_status'] ?? 'REVIEW_REQUIRED',
                    'evidence' => $this->evidenceRefs($target['evidence'] ?? null, $id, $pdfHash, $includeVerifiedExcerpts),
                ];
            }

            foreach ($ledgerTargets as $name) {
                $this->requireTrue(is_string($name) && in_array($name, $targetNames, true),
                    $id.':LEDGER_TARGET_NOT_SUPPORTED_BY_SCOPE');
            }
            $scopeTargetCount += count($targetNames);
            $ledgerTargetCount += count($ledgerTargets);
            $expandedTargets += count(array_diff($targetNames, $ledgerTargets));
            $sequence = $entry['sequence'] ?? null;
            $isAmendment = is_int($sequence) && $sequence >= 1;
            if ($isAmendment) {
                $amendmentCount++;
                $this->requireSame($id.':EXPECTED_TWO_TARGETS', ['LAMPIRAN I', 'LAMPIRAN II'],
                    $targetNames);
            } else {
                $this->requireSame($id.':BASE_HAS_NO_TARGETS', [], $targetNames);
            }

            $warning = $entry['metadata_warning'] ?? null;
            $this->requireSame($id.'.metadata_warning', $warning,
                $row['metadata_warning'] ?? null);
            if (is_string($warning) && trim($warning) !== '') {
                $metadataAnomalies++;
            }
            $dates = [];
            foreach (($row['dates'] ?? []) as $date) {
                $this->requireTrue(is_array($date), $id.':DATE_NOT_OBJECT');
                $dateEvidence = $this->evidenceRefs([$date['evidence'] ?? null], $id, $pdfHash, $includeVerifiedExcerpts);
                $dates[] = [
                    'type' => $date['type'] ?? null,
                    'date_as_printed' => $date['date_as_printed'] ?? null,
                    'evidence' => $dateEvidence,
                    'review_only' => true,
                ];
            }

            $projected[] = [
                'id' => $id,
                'title' => $entry['title'] ?? '',
                'sequence' => $sequence,
                'document_role' => $entry['document_role'] ?? '',
                'source_url' => $entry['source_url'] ?? null,
                'metadata_url' => $entry['metadata_url'] ?? null,
                'pdf_sha256' => $pdfHash,
                'ledger_explicit_targets' => $ledgerTargets,
                'scope_targets' => $mappedTargets,
                'source_evidence' => $ledgerEvidence,
                'date_candidates' => $dates,
                'metadata_warning' => $warning,
                'review_status' => $row['status'] ?? 'REVIEW_REQUIRED',
                'legal_status' => $entry['legal_status'] ?? 'REVIEW_REQUIRED',
            ];
        }

        $this->requireSame('amendment_count.ledger', $amendmentCount, $ledger['amendment_count'] ?? null);
        $this->requireSame('amendment_count.scope', $amendmentCount, $scope['amendment_count'] ?? null);
        $this->requireSame('scope.amendments_with_both_annex_targets', $amendmentCount,
            $scope['amendments_with_both_annex_targets'] ?? null);
        $this->requireSame('scope.explicit_target_clauses', $scopeTargetCount,
            $scope['explicit_target_clauses'] ?? null);
        $this->requireSame('ledger.targets_evidenced', $ledgerTargetCount,
            $ledger['targets_evidenced'] ?? null);
        $this->requireSame('ledger.metadata_anomalies', $metadataAnomalies,
            $ledger['metadata_anomalies'] ?? null);

        return [
            'schema' => 'PI_R70F_LARAVEL_REGULATORY_READ_MODEL_V1',
            'tenant_id' => $ledger['tenant_id'],
            'pilot' => $ledger['pilot'],
            'source_integrity' => [
                'ledger_sha256' => $ledgerHash,
                'scope_sha256' => $scopeHash,
                'scope_links_to_ledger' => true,
            ],
            'summary' => [
                'source_count' => $sourceCount,
                'amendment_count' => $amendmentCount,
                'ledger_targets_first_45_pages' => $ledgerTargetCount,
                'scope_targets_first_95_pages' => $scopeTargetCount,
                'scope_only_targets' => $expandedTargets,
                'metadata_anomalies_for_review' => $metadataAnomalies,
                'evidence_excerpt_sha256_verified' => $this->evidenceVerified,
            ],
            'documents' => $projected,
            'interpretation' => [
                'legal_harmonization' => 'NOT_ASSESSED',
                'annex_content_diff' => 'NOT_PERFORMED',
                'numeric_comparison' => 'BLOCKED',
                'version_reconciliation' => 'NOT_PERFORMED',
                'reviewer_gate' => 'REQUIRED',
                'production_admission' => 'DENIED',
                'note' => 'SOURCE_TRACE_ONLY_NOT_LEGAL_FINDING',
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function load(string $path): array
    {
        if ($path === '' || ! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException('SOURCE_UNREADABLE');
        }
        $length = filesize($path);
        if ($length === false || $length > 32 * 1024 * 1024) {
            throw new RuntimeException('SOURCE_SIZE_OUT_OF_QA_LIMIT');
        }
        $raw = file_get_contents($path);
        if (! is_string($raw)) {
            throw new RuntimeException('SOURCE_READ_FAILED');
        }
        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            throw new RuntimeException('INVALID_SOURCE_JSON', 0, $error);
        }
        if (! is_array($decoded) || array_is_list($decoded)) {
            throw new RuntimeException('INVALID_TOP_LEVEL_OBJECT');
        }

        return $decoded;
    }

    private function fingerprint(mixed $value, string $where): string
    {
        $this->requireTrue(is_string($value) && preg_match('/^[0-9a-fA-F]{64}$/', $value) === 1,
            $where.':INVALID_HASH');

        return strtoupper($value);
    }

    /** @return list<array<string, mixed>> */
    private function evidenceRefs(mixed $items, string $id, string $pdfHash, bool $includeVerifiedExcerpts): array
    {
        $this->requireTrue(is_array($items) && array_is_list($items), $id.':EVIDENCE_NOT_LIST');
        $refs = [];
        foreach ($items as $item) {
            $this->requireTrue(is_array($item), $id.':EVIDENCE_NOT_OBJECT');
            $this->requireSame($id.':EVIDENCE_SOURCE', $id, $item['source_id'] ?? null);
            $this->requireSame($id.':EVIDENCE_PDF_HASH', $pdfHash,
                $this->fingerprint($item['pdf_sha256'] ?? null, $id.':EVIDENCE_PDF'));
            $page = $item['pdf_page'] ?? null;
            $this->requireTrue(is_int($page) && $page > 0, $id.':EVIDENCE_PAGE_INVALID');
            $excerpt = $item['excerpt'] ?? null;
            $this->requireTrue(is_string($excerpt) && $excerpt !== '', $id.':EXCERPT_MISSING');
            $excerptHash = $this->fingerprint($item['excerpt_sha256'] ?? null,
                $id.':EXCERPT_HASH');
            $this->requireSame($id.':EXCERPT_HASH_MISMATCH', $excerptHash,
                strtoupper(hash('sha256', $excerpt)));
            $this->evidenceVerified++;
            $ref = [
                'source_id' => $id,
                'pdf_sha256' => $pdfHash,
                'pdf_page' => $page,
                'evidence_type' => $item['evidence_type'] ?? 'UNCLASSIFIED',
                'excerpt_sha256' => $excerptHash,
                'review_status' => $item['review_status'] ?? ($item['status'] ?? 'REVIEW_REQUIRED'),
            ];
            if ($includeVerifiedExcerpts) {
                $ref['excerpt'] = $excerpt;
            }
            $refs[] = $ref;
        }

        return $refs;
    }

    private function requireSame(string $label, mixed $expected, mixed $actual): void
    {
        if ($expected !== $actual) {
            throw new RuntimeException('CONTRACT_MISMATCH:'.$label);
        }
    }

    private function requireTrue(bool $predicate, string $message): void
    {
        if (! $predicate) {
            throw new RuntimeException('CONTRACT_INVALID:'.$message);
        }
    }
}
