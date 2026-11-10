<?php

namespace App\Services\PlanningIntelligence;

use RuntimeException;

/**
 * Structural amendment chronology built only from R70F's hash-verified projection.
 * "Target mentioned in an amending instrument" is NOT an annex-value difference,
 * legal effectiveness finding, or verified relationship to superior regulation.
 */
final class RegulatoryChangeTraceProjector
{
    /** @return array<string, mixed> */
    public function build(array $model): array
    {
        $guards = $model['interpretation'] ?? [];
        $integrity = $model['source_integrity'] ?? [];
        if (($model['schema'] ?? null) !== 'PI_R70F_LARAVEL_REGULATORY_READ_MODEL_V1'
            || ! is_array($guards)
            || ($guards['reviewer_gate'] ?? null) !== 'REQUIRED'
            || ($guards['legal_harmonization'] ?? null) !== 'NOT_ASSESSED'
            || ($guards['annex_content_diff'] ?? null) !== 'NOT_PERFORMED'
            || ($guards['numeric_comparison'] ?? null) !== 'BLOCKED'
            || ($guards['production_admission'] ?? null) !== 'DENIED'
            || ! is_array($integrity)
            || ($integrity['scope_links_to_ledger'] ?? null) !== true
            || ! $this->isHash($integrity['ledger_sha256'] ?? null)
            || ! $this->isHash($integrity['scope_sha256'] ?? null)
            || ! is_string($model['tenant_id'] ?? null)
            || ! is_array($model['documents'] ?? null)) {
            throw new RuntimeException('REGULATORY_CHANGE_TRACE_SOURCE_INVALID');
        }

        $groups = [];
        $lastByTarget = [];
        $seen = [];
        $events = 0;
        $amendments = 0;
        foreach ($model['documents'] as $document) {
            if (! is_array($document)
                || ! is_string($document['id'] ?? null)
                || ! preg_match('/^[A-Z0-9_]+$/', $document['id'])
                || isset($seen[$document['id']])
                || ! $this->isHash($document['pdf_sha256'] ?? null)
                || ! is_array($document['scope_targets'] ?? null)) {
                throw new RuntimeException('REGULATORY_CHANGE_TRACE_DOCUMENT_INVALID');
            }
            $seen[$document['id']] = true;
            if (($document['document_role'] ?? null) !== 'AMENDING_REGULATION') {
                if ($document['scope_targets'] !== []) {
                    throw new RuntimeException('REGULATORY_CHANGE_TRACE_BASE_TARGET_UNEXPECTED');
                }
                continue;
            }
            $sequence = $document['sequence'] ?? null;
            if (! is_int($sequence) || $sequence <= 0 || ! is_string($document['title'] ?? null)) {
                throw new RuntimeException('REGULATORY_CHANGE_TRACE_SEQUENCE_INVALID');
            }
            $amendments++;
            foreach ($document['scope_targets'] as $target) {
                if (! is_array($target)
                    || ! is_string($target['target'] ?? null)
                    || $target['target'] === ''
                    || ! is_int($target['start_pdf_page'] ?? null)
                    || ! is_int($target['verb_pdf_page'] ?? null)
                    || $target['start_pdf_page'] < 1
                    || $target['verb_pdf_page'] < $target['start_pdf_page']
                    || ! is_array($target['evidence'] ?? null)
                    || $target['evidence'] === []) {
                    throw new RuntimeException('REGULATORY_CHANGE_TRACE_TARGET_INVALID');
                }
                $anchors = [];
                foreach ($target['evidence'] as $evidence) {
                    if (! is_array($evidence)
                        || ($evidence['source_id'] ?? null) !== $document['id']
                        || ($evidence['pdf_sha256'] ?? null) !== $document['pdf_sha256']
                        || ! is_int($evidence['pdf_page'] ?? null)
                        || $evidence['pdf_page'] < 1
                        || ! $this->isHash($evidence['excerpt_sha256'] ?? null)) {
                        throw new RuntimeException('REGULATORY_CHANGE_TRACE_EVIDENCE_INVALID');
                    }
                    $anchors[] = [
                        'source_id' => $document['id'],
                        'pdf_page' => $evidence['pdf_page'],
                        'excerpt_sha256' => $evidence['excerpt_sha256'],
                    ];
                }
                $name = $target['target'];
                $previous = $lastByTarget[$name] ?? null;
                if (is_array($previous) && $sequence <= $previous['sequence']) {
                    throw new RuntimeException('REGULATORY_CHANGE_TRACE_ORDER_INVALID');
                }
                $event = [
                    'source_id' => $document['id'],
                    'source_title' => $document['title'],
                    'source_pdf_sha256' => $document['pdf_sha256'],
                    'sequence' => $sequence,
                    'target' => $name,
                    'target_item' => $target['item'] ?? null,
                    'change_verb_as_extracted' => $target['change_verb'] ?? null,
                    'start_pdf_page' => $target['start_pdf_page'],
                    'operative_verb_pdf_page' => $target['verb_pdf_page'],
                    'previous_target_source_id' => $previous['source_id'] ?? null,
                    'evidence_refs' => $anchors,
                    'review_status' => 'REVIEW_REQUIRED',
                    'structural_relation' => 'SUCCESSIVE_TARGET_MENTIONS_ONLY',
                ];
                $groups[$name][] = $event;
                $lastByTarget[$name] = $event;
                $events++;
            }
        }

        $targetGroups = [];
        foreach ($groups as $name => $items) {
            $targetGroups[] = [
                'target' => $name,
                'event_count' => count($items),
                'events' => $items,
                'interpretation' => 'CHRONOLOGICAL_TARGET_MENTIONS_NOT_CONTENT_DIFF',
            ];
        }

        return [
            'schema' => 'PI_R72_STRUCTURAL_AMENDMENT_TRACE_V1',
            'tenant_id' => $model['tenant_id'],
            'source_schema' => $model['schema'],
            'source_integrity' => $integrity,
            'summary' => [
                'document_count' => count($model['documents']),
                'amending_instrument_count' => $amendments,
                'distinct_target_count' => count($targetGroups),
                'structural_change_candidate_count' => $events,
            ],
            'target_groups' => $targetGroups,
            'review_status' => 'CANDIDATE_TRACE_ONLY',
            'legal_harmonization' => 'NOT_ASSESSED',
            'annex_content_diff' => 'NOT_PERFORMED',
            'numeric_comparison' => 'BLOCKED',
            'reviewer_gate' => 'REQUIRED',
            'production_admission' => 'DENIED',
        ];
    }

    private function isHash(mixed $value): bool
    {
        return is_string($value) && preg_match('/^[A-F0-9]{64}$/D', $value) === 1;
    }
}
