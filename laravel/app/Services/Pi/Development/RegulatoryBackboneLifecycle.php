<?php

namespace App\Services\Pi\Development;

use DateTimeImmutable;
use RuntimeException;

final class RegulatoryBackboneLifecycle
{
    public const STAGE_IDS = [
        'authority',
        'planning',
        'budgeting',
        'execution_treasury',
        'monitoring',
        'evaluation',
        'internal_oversight',
        'external_audit',
        'follow_up',
        'feedback_next_planning',
    ];

    private const CASE_ID = 'YOG-2026-PERSAMPAHAN';

    private const JURISDICTION_ID = 'ID-DIY-YOG';

    private const CATALOGUE_SHA256 = 'BB90F07F6387589EBAC3960B899709C4758D76B6707CB72E542480FD1BA17C5B';

    private const BPK_2026_PDF_SHA256 = '8D585DA7E9664B64CED5358ECA8D1FBE935F4A6D6680CF5F7C83AA11366C3F8D';

    private const ALLOWED_HOSTS = [
        'peraturan.bpk.go.id',
        'peraturan.go.id',
        'www.peraturan.go.id',
        'jdih.bpk.go.id',
        'jdih.jogjakota.go.id',
        'jdih.kemendagri.go.id',
    ];

    public function __construct(private readonly DevelopmentCaseGraph $developmentCaseGraph) {}

    /**
     * @param  array{case?:string,jurisdiction?:string,year?:int,as_of?:string,stage?:string}  $filters
     * @return array<string, mixed>
     */
    public function read(array $filters): array
    {
        $registryPath = (string) config('pi.regulatory_backbone.registry_path');
        $cataloguePath = (string) config('pi.regulatory_backbone.catalogue_path');
        $raw = is_file($registryPath) ? file_get_contents($registryPath) : false;
        if (! is_string($raw) || $raw === '' || strlen($raw) > 512 * 1024) {
            throw new RuntimeException('Regulatory backbone registry unavailable.');
        }

        $registry = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($registry) || array_is_list($registry)) {
            throw new RuntimeException('Regulatory backbone registry malformed.');
        }
        $this->assertRegistry($registry);
        $catalogue = $this->readCatalogue($cataloguePath);

        $caseId = $filters['case'] ?? self::CASE_ID;
        $jurisdiction = $filters['jurisdiction'] ?? self::JURISDICTION_ID;
        $year = $filters['year'] ?? 2026;
        $asOf = new DateTimeImmutable($filters['as_of'] ?? '2026-11-18');
        $matches = $caseId === self::CASE_ID && $jurisdiction === self::JURISDICTION_ID && $year === 2026;

        $r85 = $this->developmentCaseGraph->read([
            'case' => self::CASE_ID,
            'jurisdiction' => self::JURISDICTION_ID,
            'year' => 2026,
        ]);
        if (($r85['result_count'] ?? null) !== 1 || ! is_array($r85['case'] ?? null)) {
            throw new RuntimeException('R85 host case unavailable.');
        }

        $stages = $registry['lifecycle']['stages'];
        if (isset($filters['stage'])) {
            $stages = array_values(array_filter(
                $stages,
                static fn (array $stage): bool => $stage['id'] === $filters['stage']
            ));
        }

        $temporal = array_map(
            fn (array $version): array => $this->projectTemporalStatus($version, $asOf, $year),
            $registry['regulation_versions']
        );

        return [
            'schema' => 'PI_R86_REGULATORY_DEVELOPMENT_LIFECYCLE_V1',
            'tenant_id' => self::JURISDICTION_ID,
            'source_registry_sha256' => strtoupper(hash('sha256', $raw)),
            'catalogue' => [
                'schema' => 'PI_R86_REGULATORY_BACKBONE_CANDIDATE_CATALOGUE_V1',
                'sha256' => $this->catalogueExpectedSha256(),
                'entry_count' => count($catalogue),
                'admission' => 'CANDIDATE_CATALOGUE_NOT_LEGAL_VALIDATION',
                'entries' => $catalogue,
            ],
            'filters' => [
                'selected' => [
                    'case' => $caseId,
                    'jurisdiction' => $jurisdiction,
                    'year' => $year,
                    'as_of' => $asOf->format('Y-m-d'),
                    'stage' => $filters['stage'] ?? 'all',
                ],
                'available' => [
                    'cases' => [['id' => self::CASE_ID, 'label' => 'Kota Yogyakarta 2026 - Persampahan']],
                    'jurisdictions' => [self::JURISDICTION_ID],
                    'years' => [2026],
                    'stages' => self::STAGE_IDS,
                ],
            ],
            'result_count' => $matches ? 1 : 0,
            'case' => $matches ? [
                'id' => self::CASE_ID,
                'jurisdiction_id' => self::JURISDICTION_ID,
                'planning_year' => 2026,
                'topic_id' => 'PERSAMPAHAN',
                'r85_contract' => [
                    'schema' => $r85['schema'],
                    'source_registry_sha256' => $r85['source_registry_sha256'],
                    'question_ids' => array_column($r85['case']['questions'], 'id'),
                    'graph_ids' => array_column($r85['case']['graphs'], 'id'),
                ],
                'lifecycle' => [
                    'model' => 'DEPENDENCY_GRAPH_NOT_SINGLE_LINEAR_TIMELINE',
                    'stage_count' => count($registry['lifecycle']['stages']),
                    'returned_stage_count' => count($stages),
                    'stages' => $stages,
                    'relations' => $registry['lifecycle']['relations'],
                ],
                'temporal_rules' => $temporal,
                'specialized_views' => $registry['specialized_views'],
                'evidence' => $registry['evidence'],
                'executive_review_brief' => $registry['executive_review_brief'],
            ] : null,
            'interpretation' => $registry['interpretation'],
        ];
    }

    /** @param array<string, mixed> $registry */
    private function assertRegistry(array $registry): void
    {
        if (($registry['schema'] ?? null) !== 'PI_R86_REGULATORY_BACKBONE_SOURCE_V1'
            || ($registry['case_id'] ?? null) !== self::CASE_ID
            || ($registry['jurisdiction_id'] ?? null) !== self::JURISDICTION_ID
            || ($registry['planning_year'] ?? null) !== 2026
            || ! is_array($registry['regulation_versions'] ?? null)
            || count($registry['regulation_versions']) < 12
            || ! is_array($registry['provisions'] ?? null)
            || ! is_array($registry['lifecycle']['stages'] ?? null)
            || count($registry['lifecycle']['stages']) !== 10
            || ! is_array($registry['lifecycle']['relations'] ?? null)
            || ! is_array($registry['specialized_views'] ?? null)
            || count($registry['specialized_views']) !== 5
            || ! is_array($registry['evidence'] ?? null)
            || ! is_array($registry['executive_review_brief'] ?? null)
            || ! is_array($registry['interpretation'] ?? null)) {
            throw new RuntimeException('Regulatory backbone contract mismatch.');
        }

        $versionIds = [];
        foreach ($registry['regulation_versions'] as $version) {
            if (! is_array($version)
                || ! is_string($version['id'] ?? null)
                || isset($versionIds[$version['id']])
                || ! is_string($version['title'] ?? null)
                || ! in_array($version['source_state'] ?? null, [
                    'VERIFIED_SOURCE_BYTES',
                    'SOURCE_LOCATED_NOT_HASH_PINNED',
                    'METADATA_CANDIDATE_REVIEW_REQUIRED',
                ], true)) {
                throw new RuntimeException('Regulation version invalid.');
            }
            $effectiveFrom = $version['effective_from'] ?? null;
            if ($effectiveFrom !== null
                && (! is_string($effectiveFrom) || ! $this->validDate($effectiveFrom))) {
                throw new RuntimeException('Regulation effective date invalid.');
            }
            if ($effectiveFrom === null && $version['source_state'] !== 'METADATA_CANDIDATE_REVIEW_REQUIRED') {
                throw new RuntimeException('Verified regulation must have an effective date.');
            }
            $this->assertSafeSource($version['source_url'] ?? null);
            $hash = $version['source_sha256'] ?? null;
            if ($hash !== null && (! is_string($hash) || preg_match('/\A[A-F0-9]{64}\z/', $hash) !== 1)) {
                throw new RuntimeException('Regulation source hash invalid.');
            }
            $versionIds[$version['id']] = $version;
        }
        if (($versionIds['REG-PBPK1-2026']['source_sha256'] ?? null) !== self::BPK_2026_PDF_SHA256
            || ($versionIds['REG-PBPK1-2026']['effective_from'] ?? null) !== '2028-04-10'
            || ($versionIds['REG-PBPK1-2017']['effective_from'] ?? null) !== '2017-01-06'
            || ($versionIds['REG-PMD10-2025']['planning_years'] ?? null) !== [2026]
            || ($versionIds['REG-PMD14-2026']['planning_years'] ?? null) !== [2027]
            || ($versionIds['REG-PMD14-2025']['budget_years'] ?? null) !== [2026]
            || ($versionIds['REG-PMD17-2026']['budget_years'] ?? null) !== [2027]) {
            throw new RuntimeException('Temporal regulation pins invalid.');
        }

        $provisionIds = [];
        foreach ($registry['provisions'] as $provision) {
            if (! is_array($provision)
                || ! is_string($provision['id'] ?? null)
                || isset($provisionIds[$provision['id']])
                || ! isset($versionIds[$provision['regulation_version_id'] ?? ''])
                || ! is_string($provision['locator'] ?? null)
                || trim($provision['locator']) === ''
                || ! in_array($provision['verification_status'] ?? null, [
                    'VERIFIED_SOURCE_BYTES',
                    'SOURCE_LOCATED_NOT_HASH_PINNED',
                    'PROVISION_REVIEW_REQUIRED',
                ], true)) {
                throw new RuntimeException('Provision invalid.');
            }
            $provisionIds[$provision['id']] = true;
        }
        foreach (['PROV-PBPK1-2026-P8', 'PROV-PBPK1-2026-P9', 'PROV-PMD10-2025-P12-2'] as $required) {
            if (! isset($provisionIds[$required])) {
                throw new RuntimeException('Required temporal provision absent.');
            }
        }

        $stageIds = [];
        foreach ($registry['lifecycle']['stages'] as $stage) {
            if (! is_array($stage)
                || ! in_array($stage['id'] ?? null, self::STAGE_IDS, true)
                || isset($stageIds[$stage['id']])
                || ! is_array($stage['regulation_version_ids'] ?? null)
                || array_diff($stage['regulation_version_ids'], array_keys($versionIds)) !== []
                || ! is_array($stage['evidence_gaps'] ?? null)
                || ! in_array($stage['evidence_status'] ?? null, [
                    'OBSERVED_SOURCE_LOCATED',
                    'MAPPING_CANDIDATE',
                    'EVIDENCE_NOT_AVAILABLE',
                    'NOT_ASSESSED',
                ], true)) {
                throw new RuntimeException('Lifecycle stage invalid.');
            }
            $stageIds[$stage['id']] = true;
        }
        if (array_diff(self::STAGE_IDS, array_keys($stageIds)) !== []) {
            throw new RuntimeException('Lifecycle stage set incomplete.');
        }
        if (($stageIds['internal_oversight'] ?? null) !== true || ($stageIds['external_audit'] ?? null) !== true) {
            throw new RuntimeException('Assurance roles not separated.');
        }

        $relationIds = [];
        foreach ($registry['lifecycle']['relations'] as $relation) {
            $basis = $relation['basis'] ?? '';
            if (! is_array($relation)
                || ! is_string($relation['id'] ?? null)
                || isset($relationIds[$relation['id']])
                || ! isset($stageIds[$relation['from'] ?? ''], $stageIds[$relation['to'] ?? ''])
                || ($relation['from'] ?? null) === ($relation['to'] ?? null)
                || ! is_string($relation['type'] ?? null)
                || (! isset($versionIds[$basis]) && ! isset($provisionIds[$basis]))
                || ! in_array($relation['status'] ?? null, [
                    'MAPPING_CANDIDATE',
                    'SOURCE_LOCATED_NOT_COMPLIANCE',
                    'EVIDENCE_GAP',
                    'NOT_ASSESSED',
                ], true)) {
                throw new RuntimeException('Lifecycle relation invalid.');
            }
            $relationIds[$relation['id']] = true;
        }

        $expectedApps = [
            'Evidence Intelligence',
            'Regulatory Intelligence',
            'Performance Intelligence',
            'Fiscal Intelligence',
            'Evaluation Intelligence',
        ];
        if (array_column($registry['specialized_views'], 'app') !== $expectedApps) {
            throw new RuntimeException('Specialized shared views incomplete.');
        }
        foreach ($registry['specialized_views'] as $view) {
            if (! is_array($view['stage_ids'] ?? null)
                || $view['stage_ids'] === []
                || array_diff($view['stage_ids'], self::STAGE_IDS) !== []) {
                throw new RuntimeException('Specialized view stage map invalid.');
            }
        }

        $evidenceIds = [];
        foreach ($registry['evidence'] as $evidence) {
            if (! is_array($evidence)
                || ! is_string($evidence['id'] ?? null)
                || isset($evidenceIds[$evidence['id']])
                || ! is_string($evidence['source_id'] ?? null)
                || ! is_string($evidence['locator'] ?? null)) {
                throw new RuntimeException('Lifecycle evidence invalid.');
            }
            $this->assertSafeSource($evidence['source_url'] ?? null);
            $evidenceHash = $evidence['source_sha256'] ?? null;
            if ($evidenceHash !== null
                && (! is_string($evidenceHash) || preg_match('/\A[A-F0-9]{64}\z/', $evidenceHash) !== 1)) {
                throw new RuntimeException('Lifecycle evidence hash invalid.');
            }
            $evidenceIds[$evidence['id']] = true;
        }
        if (($registry['evidence'][2]['source_sha256'] ?? null) !== self::BPK_2026_PDF_SHA256) {
            throw new RuntimeException('SPKN transition evidence pin invalid.');
        }

        foreach (['known', 'dependencies', 'gaps', 'next_checks'] as $section) {
            if (! is_array($registry['executive_review_brief'][$section] ?? null)
                || $registry['executive_review_brief'][$section] === []) {
                throw new RuntimeException('Executive review brief incomplete.');
            }
        }
        if (($registry['executive_review_brief']['human_decision'] ?? null) !== 'NO_ACTIVE_DECISION') {
            throw new RuntimeException('Executive brief invented a review decision.');
        }

        if (($registry['interpretation']['legal_harmonization'] ?? null) !== 'NOT_ASSESSED'
            || ($registry['interpretation']['numeric_comparison'] ?? null) !== 'BLOCKED'
            || ($registry['interpretation']['review_decision'] ?? null) !== 'NO_ACTIVE_DECISION'
            || ($registry['interpretation']['production_admission'] ?? null) !== 'DENIED') {
            throw new RuntimeException('R86 safety gates invalid.');
        }
    }

    /** @return list<array<string, mixed>> */
    private function readCatalogue(string $path): array
    {
        if (! is_file($path)
            || strtoupper((string) hash_file('sha256', $path)) !== $this->catalogueExpectedSha256()) {
            throw new RuntimeException('Regulatory catalogue hash mismatch.');
        }
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException('Regulatory catalogue unreadable.');
        }

        try {
            $header = fgetcsv($handle, 0, ',');
            if (! is_array($header)) {
                throw new RuntimeException('Regulatory catalogue header missing.');
            }
            $header[0] = ltrim((string) $header[0], "\xEF\xBB\xBF");
            $expected = ['cluster', 'regulation', 'relevant_provisions', 'function', 'processes', 'effective_status_as_of_2026_11_18', 'critical_caveat', 'official_or_jdih_url'];
            if ($header !== $expected) {
                throw new RuntimeException('Regulatory catalogue columns invalid.');
            }

            $entries = [];
            $seen = [];
            while (($row = fgetcsv($handle, 0, ',')) !== false) {
                if (count($row) !== count($header)) {
                    throw new RuntimeException('Regulatory catalogue row malformed.');
                }
                $item = array_combine($header, $row);
                if (! is_array($item) || isset($seen[$item['regulation']])) {
                    throw new RuntimeException('Regulatory catalogue duplicate regulation.');
                }
                $seen[$item['regulation']] = true;
                $safeUrl = $this->safeSourceOrNull($item['official_or_jdih_url']);
                $entries[] = [
                    'id' => sprintf('R86-CAT-%03d', count($entries) + 1),
                    'cluster' => $item['cluster'],
                    'regulation' => $item['regulation'],
                    'relevant_provisions' => $item['relevant_provisions'],
                    'processes' => array_values(array_filter(explode(';', $item['processes']))),
                    'research_status' => $item['effective_status_as_of_2026_11_18'],
                    'critical_caveat' => $item['critical_caveat'],
                    'source_url' => $safeUrl,
                    'source_admission' => $safeUrl === null
                        ? 'SOURCE_REQUIRED_OR_HOST_NOT_ADMITTED'
                        : 'OFFICIAL_SOURCE_CANDIDATE_NOT_PROVISION_VERIFIED',
                    'legal_validation' => 'NOT_ASSESSED',
                ];
            }
        } finally {
            fclose($handle);
        }

        if (count($entries) !== 38) {
            throw new RuntimeException('Regulatory catalogue must contain 38 entries.');
        }

        return $entries;
    }

    /** @param array<string, mixed> $version
     * @return array<string, mixed>
     */
    private function projectTemporalStatus(array $version, DateTimeImmutable $asOf, int $caseYear): array
    {
        $effectiveFrom = isset($version['effective_from'])
            ? new DateTimeImmutable($version['effective_from'])
            : null;
        $status = 'CURRENTLY_APPLICABLE';
        $reason = 'EFFECTIVE_DATE_REACHED_FOR_SCOPED_VERSION';

        $scopedYears = $version['planning_years'] ?? $version['budget_years'] ?? null;
        if (is_array($scopedYears) && ! in_array($caseYear, $scopedYears, true)) {
            $status = 'OUT_OF_SCOPE_CASE_YEAR';
            $reason = 'ANNUAL_GUIDANCE_TARGETS_DIFFERENT_YEAR';
        } elseif ($effectiveFrom === null) {
            $status = 'REVIEW_REQUIRED';
            $reason = 'EFFECTIVE_DATE_NOT_VERIFIED';
        } elseif ($asOf < $effectiveFrom) {
            $status = 'NOT_YET_EFFECTIVE';
            $reason = 'EFFECTIVE_DATE_IS_IN_FUTURE';
        } elseif (isset($version['superseded_by_effective_from'])
            && $asOf >= new DateTimeImmutable($version['superseded_by_effective_from'])) {
            $status = 'REPEALED_OR_SUPERSEDED';
            $reason = 'SUCCESSOR_EFFECTIVE_DATE_REACHED';
        } elseif (($version['temporal_confidence'] ?? null) !== 'VERIFIED') {
            $status = 'REVIEW_REQUIRED';
            $reason = 'TEMPORAL_METADATA_NOT_PROVISION_VERIFIED';
        }

        return [
            'regulation_version_id' => $version['id'],
            'title' => $version['title'],
            'as_of' => $asOf->format('Y-m-d'),
            'case_year' => $caseYear,
            'effective_from' => $version['effective_from'],
            'status' => $status,
            'reason' => $reason,
            'source_url' => $version['source_url'],
            'source_sha256' => $version['source_sha256'],
        ];
    }

    private function validDate(string $value): bool
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value;
    }

    private function catalogueExpectedSha256(): string
    {
        $hash = strtoupper((string) config('pi.regulatory_backbone.catalogue_sha256', self::CATALOGUE_SHA256));
        if (preg_match('/\A[A-F0-9]{64}\z/', $hash) !== 1) {
            throw new RuntimeException('Regulatory catalogue configured hash invalid.');
        }

        return $hash;
    }

    private function assertSafeSource(mixed $url): void
    {
        if (! is_string($url) || $this->safeSourceOrNull($url) === null) {
            throw new RuntimeException('Regulatory source origin rejected.');
        }
    }

    private function safeSourceOrNull(mixed $url): ?string
    {
        if (! is_string($url) || $url === '' || ! filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }
        $parts = parse_url($url);
        if (! is_array($parts)
            || ($parts['scheme'] ?? null) !== 'https'
            || ! in_array(strtolower((string) ($parts['host'] ?? '')), self::ALLOWED_HOSTS, true)
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['port'])) {
            return null;
        }

        return $url;
    }
}
