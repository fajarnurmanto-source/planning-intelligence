<?php

namespace App\Services\Pi\Regulatory;

use JsonException;
use RuntimeException;

/**
 * R80: compare document-linked *candidate* delegations with the curated R74
 * graph. A source URL + locator is not a verified PDF excerpt or legal finding.
 * This operates independently of the already accepted R79 browser 8/8 gate.
 */
final class RegulatoryDelegationCoverage
{
    public function __construct(private readonly RegulatoryTaxonomyRegistry $registry)
    {
    }

    public function project(?string $candidatesPath = null, ?string $seedPath = null): array
    {
        $seed = $this->registry->load($seedPath);
        $path = $candidatesPath ?? resource_path('pi/regulatory-delegation-coverage-r80.json');
        if (! is_file($path) || ! is_readable($path) || filesize($path) > 1024 * 1024) {
            throw new RuntimeException('R80_CANDIDATE_REGISTRY_UNREADABLE');
        }
        $raw = file_get_contents($path);
        if (! is_string($raw)) {
            throw new RuntimeException('R80_CANDIDATE_REGISTRY_READ_FAILED');
        }
        try {
            $data = json_decode($raw, true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            throw new RuntimeException('R80_CANDIDATE_REGISTRY_INVALID_JSON', 0, $error);
        }
        if (! is_array($data)
            || ($data['schema'] ?? null) !== 'PI_R80_DELEGATION_COVERAGE_CANDIDATES_V1'
            || ($data['policy'] ?? null) !== 'OFFICIAL_PDF_LOCATOR_NOT_EXCERPT_OR_LEGAL_VALIDATION'
            || ! is_array($data['sources'] ?? null) || ! array_is_list($data['sources'])
            || count($data['sources']) > 30 || count($data['sources']) < 1) {
            throw new RuntimeException('R80_CANDIDATE_REGISTRY_CONTRACT_INVALID');
        }

        $instruments = array_column($seed['instruments'], null, 'id');
        $provisions = array_column($seed['provisions'], null, 'id');
        $curated = [];
        foreach ($seed['relations'] as $rel) {
            if (($rel['type'] ?? null) === 'IMPLEMENTS_PROVISION_OF') {
                $curated[($rel['from'] ?? '').'|'.($rel['to'] ?? '')] = $rel;
            }
        }

        $rows = [];
        $expected = 0;
        $curatedCount = 0;
        $missing = 0;
        $seen = [];
        foreach ($data['sources'] as $source) {
            if (! is_array($source)) {
                throw new RuntimeException('R80_SOURCE_NOT_OBJECT');
            }
            $from = $source['source_instrument_id'] ?? null;
            $to = $source['target_instrument_id'] ?? null;
            if (! is_string($from) || ! is_string($to)
                || $from === $to || ! isset($instruments[$from], $instruments[$to])
                || isset($seen[$from.'|'.$to])
                || ! is_int($source['pdf_page'] ?? null) || $source['pdf_page'] < 1
                || ! is_string($source['locator'] ?? null) || trim($source['locator']) === ''
                || ! $this->registry->officialUrl($source['official_pdf_url'] ?? null)
                || $source['official_pdf_url'] !== $instruments[$from]['source_url']
                || ! array_key_exists('document_sha256', $source)
                || $source['document_sha256'] !== null
                || ($source['evidence_state'] ?? null) !== 'OFFICIAL_PDF_LINK_ONLY_NOT_HASH_PINNED'
                || ! is_array($source['provisions'] ?? null)
                || ! array_is_list($source['provisions'])
                || count($source['provisions']) < 1 || count($source['provisions']) > 100) {
                throw new RuntimeException('R80_SOURCE_PROVENANCE_CONTRACT_INVALID');
            }
            $seen[$from.'|'.$to] = true;
            $articleSeen = [];
            $items = [];
            foreach ($source['provisions'] as $entry) {
                if (! is_array($entry) || ! is_int($entry['article'] ?? null)
                    || $entry['article'] < 1 || $entry['article'] > 999
                    || ! is_array($entry['paragraphs'] ?? null)
                    || ! array_is_list($entry['paragraphs'])
                    || count($entry['paragraphs']) > 50
                    || isset($articleSeen[$entry['article']])) {
                    throw new RuntimeException('R80_PROVISION_CANDIDATE_INVALID');
                }
                $articleSeen[$entry['article']] = true;
                $paraSeen = [];
                foreach ($entry['paragraphs'] as $paragraph) {
                    if (! is_int($paragraph) || $paragraph < 1 || $paragraph > 99
                        || isset($paraSeen[$paragraph])) {
                        throw new RuntimeException('R80_PARAGRAPH_CANDIDATE_INVALID');
                    }
                    $paraSeen[$paragraph] = true;
                }
                $target = $to.':PASAL-'.$entry['article'];
                $curatedRelation = $curated[$from.'|'.$target] ?? null;
                $inSeed = isset($provisions[$target]) && $curatedRelation !== null;
                if ($inSeed) {
                    $curatedCount++;
                } else {
                    $missing++;
                }
                $expected++;
                $items[] = [
                    'target_provision_id' => $target,
                    'article' => $entry['article'],
                    'paragraphs' => $entry['paragraphs'],
                    'curated_r74_relation_present' => $inSeed,
                    'coverage_state' => $inSeed
                        ? 'CURATED_LOCATOR_NOT_HASH_PINNED'
                        : 'CANDIDATE_NOT_YET_IN_R74',
                    'official_pdf_url' => $source['official_pdf_url'],
                    'source_pdf_page' => $source['pdf_page'],
                    'source_locator' => $source['locator'],
                    'legal_effect' => 'NOT_ASSESSED',
                    'review_status' => 'REVIEW_REQUIRED',
                ];
            }
            $rows[] = [
                'source_instrument_id' => $from,
                'target_instrument_id' => $to,
                'source_pdf_sha256' => null,
                'source_evidence_state' => $source['evidence_state'],
                'candidate_article_count' => count($items),
                'curated_article_count' => count(array_filter($items, static fn (array $r): bool =>
                    $r['curated_r74_relation_present'])),
                'candidates' => $items,
            ];
        }

        return [
            'schema' => 'PI_R80_DELEGATION_COVERAGE_QA_V1',
            'tenant_id' => config('pi.tenant_id'),
            'catalogue_as_of' => $data['catalogue_as_of'] ?? null,
            'result_status' => 'DOCUMENT_LOCATOR_CANDIDATES_NOT_VERIFIED_PRIMARY_EXCERPTS',
            'candidate_article_count' => $expected,
            'curated_locator_article_count' => $curatedCount,
            'missing_curated_article_count' => $missing,
            'source_count' => count($rows),
            'sources' => $rows,
            'source_coverage' => 'PARTIAL_NONEXHAUSTIVE_CANDIDATES',
            'legal_harmonization' => 'NOT_ASSESSED',
            'temporal_applicability' => 'NOT_ASSESSED',
            'numeric_comparison' => 'BLOCKED',
            'review_status' => 'REVIEW_REQUIRED',
            'production_admission' => 'DENIED',
        ];
    }
}
