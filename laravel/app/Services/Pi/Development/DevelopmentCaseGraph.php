<?php

namespace App\Services\Pi\Development;

use RuntimeException;

final class DevelopmentCaseGraph
{
    private const NODE_TYPES = [
        'RegulationVersion',
        'Provision',
        'Authority',
        'GovernmentAffair',
        'PlanningArtifactVersion',
        'ProcessStage',
        'ProcessEvent',
        'PlanningProgram',
        'Indicator',
        'BudgetArtifact',
        'EvidenceLocator',
        'ReviewDecision',
    ];

    private const GRAPH_IDS = [
        'regulatory_authority',
        'planning_development',
        'government_affairs_program',
        'development_workflow',
    ];

    /**
     * @param  array{jurisdiction?:string,year?:int,topic?:string,case?:string}  $filters
     * @return array<string, mixed>
     */
    public function read(array $filters): array
    {
        $path = (string) config('pi.development_case.registry_path');
        $raw = is_file($path) ? file_get_contents($path) : false;
        if (! is_string($raw) || $raw === '' || strlen($raw) > 512 * 1024) {
            throw new RuntimeException('Development case registry unavailable.');
        }

        $registry = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($registry) || array_is_list($registry)) {
            throw new RuntimeException('Development case registry malformed.');
        }

        $this->assertRegistry($registry);
        $case = $registry['case'];
        $matches = (! isset($filters['jurisdiction']) || $filters['jurisdiction'] === $case['jurisdiction_id'])
            && (! isset($filters['year']) || $filters['year'] === $case['planning_year'])
            && (! isset($filters['topic']) || mb_strtolower($filters['topic']) === mb_strtolower($case['topic_id']))
            && (! isset($filters['case']) || $filters['case'] === $case['id']);

        return [
            'schema' => 'PI_R85_GOVERNMENT_DEVELOPMENT_CASE_READ_MODEL_V1',
            'tenant_id' => $case['jurisdiction_id'],
            'source_registry_sha256' => strtoupper(hash('sha256', $raw)),
            'filters' => [
                'selected' => [
                    'jurisdiction' => $filters['jurisdiction'] ?? $case['jurisdiction_id'],
                    'year' => $filters['year'] ?? $case['planning_year'],
                    'topic' => $filters['topic'] ?? $case['topic_id'],
                    'case' => $filters['case'] ?? $case['id'],
                ],
                'available' => [
                    'jurisdictions' => [$case['jurisdiction_id']],
                    'years' => [$case['planning_year']],
                    'topics' => [$case['topic_id']],
                    'cases' => [['id' => $case['id'], 'label' => $case['label']]],
                ],
            ],
            'result_count' => $matches ? 1 : 0,
            'case' => $matches ? $case : null,
            'interpretation' => $registry['interpretation'],
        ];
    }

    /** @param array<string, mixed> $registry */
    private function assertRegistry(array $registry): void
    {
        if (($registry['schema'] ?? null) !== 'PI_R85_GOVERNMENT_DEVELOPMENT_CASE_SOURCE_V1'
            || ! is_array($registry['case'] ?? null)
            || ! is_array($registry['interpretation'] ?? null)) {
            throw new RuntimeException('Development case registry contract mismatch.');
        }

        $case = $registry['case'];
        if (($case['jurisdiction_id'] ?? null) !== config('pi.tenant_id')
            || ($case['planning_year'] ?? null) !== 2026
            || ($case['topic_id'] ?? null) !== 'PERSAMPAHAN'
            || ! is_array($case['questions'] ?? null)
            || count($case['questions']) !== 5
            || ! is_array($case['nodes'] ?? null)
            || ! is_array($case['edges'] ?? null)
            || ! is_array($case['graphs'] ?? null)
            || count($case['graphs']) !== 4) {
            throw new RuntimeException('Development case identity mismatch.');
        }

        $questionIds = array_column($case['questions'], 'id');
        if ($questionIds !== ['why', 'authority', 'process', 'outputs', 'two_way_trace']) {
            throw new RuntimeException('Development case questions incomplete.');
        }

        $nodeIds = [];
        $observedTypes = [];
        foreach ($case['nodes'] as $node) {
            if (! is_array($node)
                || ! is_string($node['id'] ?? null)
                || isset($nodeIds[$node['id']])
                || ! in_array($node['type'] ?? null, self::NODE_TYPES, true)
                || ! is_string($node['label'] ?? null)
                || trim($node['label']) === '') {
                throw new RuntimeException('Development case node invalid.');
            }
            $nodeIds[$node['id']] = true;
            $observedTypes[$node['type']] = true;
        }
        if (array_diff(self::NODE_TYPES, array_keys($observedTypes)) !== []) {
            throw new RuntimeException('Development case node vocabulary incomplete.');
        }

        $edgeIds = [];
        foreach ($case['edges'] as $edge) {
            $provenance = $edge['provenance'] ?? null;
            if (! is_array($edge)
                || ! is_string($edge['id'] ?? null)
                || isset($edgeIds[$edge['id']])
                || ! isset($nodeIds[$edge['from'] ?? ''], $nodeIds[$edge['to'] ?? ''])
                || ! is_string($edge['relation'] ?? null)
                || ! is_array($provenance)
                || ! $this->validProvenance($provenance)) {
                throw new RuntimeException('Development case edge invalid.');
            }
            $edgeIds[$edge['id']] = true;
        }

        foreach ($case['questions'] as $question) {
            if (! is_array($question['edge_ids'] ?? null)
                || $question['edge_ids'] === []
                || array_diff($question['edge_ids'], array_keys($edgeIds)) !== []) {
                throw new RuntimeException('Development case question evidence invalid.');
            }
        }

        $graphIds = [];
        foreach ($case['graphs'] as $graph) {
            if (! is_array($graph)
                || ! in_array($graph['id'] ?? null, self::GRAPH_IDS, true)
                || isset($graphIds[$graph['id']])
                || ! is_array($graph['edge_ids'] ?? null)
                || $graph['edge_ids'] === []
                || array_diff($graph['edge_ids'], array_keys($edgeIds)) !== []) {
                throw new RuntimeException('Development case graph invalid.');
            }
            $graphIds[$graph['id']] = true;
        }
        if (array_diff(self::GRAPH_IDS, array_keys($graphIds)) !== []) {
            throw new RuntimeException('Development case graph set incomplete.');
        }

        if (($registry['interpretation']['legal_harmonization'] ?? null) !== 'NOT_ASSESSED'
            || ($registry['interpretation']['numeric_comparison'] ?? null) !== 'BLOCKED'
            || ($registry['interpretation']['review_decision'] ?? null) !== 'NO_ACTIVE_DECISION'
            || ($registry['interpretation']['production_admission'] ?? null) !== 'DENIED') {
            throw new RuntimeException('Development case safety gates invalid.');
        }
    }

    /** @param array<string, mixed> $provenance */
    private function validProvenance(array $provenance): bool
    {
        $required = ['source_id', 'locator', 'source_url', 'source_sha256', 'version', 'as_of_date', 'jurisdiction', 'verification_status', 'uncertainty'];
        foreach ($required as $field) {
            if (! array_key_exists($field, $provenance)) {
                return false;
            }
        }

        $url = $provenance['source_url'];
        $parts = is_string($url) ? parse_url($url) : false;
        $allowedHosts = ['peraturan.go.id', 'www.peraturan.go.id', 'peraturan.bpk.go.id', 'jdih.jogjakota.go.id'];
        $validRemote = is_array($parts)
            && ($parts['scheme'] ?? null) === 'https'
            && in_array($parts['host'] ?? null, $allowedHosts, true)
            && ! isset($parts['user'])
            && ! isset($parts['pass'])
            && ! isset($parts['port']);
        $validLocal = $url === './overview-r69c.json';
        $hash = $provenance['source_sha256'];

        return is_string($provenance['source_id'])
            && trim($provenance['source_id']) !== ''
            && is_string($provenance['locator'])
            && trim($provenance['locator']) !== ''
            && ($validRemote || $validLocal)
            && ($hash === null || (is_string($hash) && preg_match('/\A[A-F0-9]{64}\z/', $hash) === 1))
            && is_string($provenance['version'])
            && preg_match('/\A\d{4}-\d{2}-\d{2}\z/', (string) $provenance['as_of_date']) === 1
            && $provenance['jurisdiction'] === 'ID-DIY-YOG'
            && in_array($provenance['verification_status'], ['VERIFIED_SOURCE_BYTES', 'SOURCE_LOCATED_NOT_HASH_PINNED', 'LOCAL_QA_OBSERVATION_NOT_SOURCE_PINNED'], true)
            && in_array($provenance['uncertainty'], ['LOW', 'MEDIUM', 'HIGH'], true);
    }
}
