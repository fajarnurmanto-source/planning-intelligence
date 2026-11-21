<?php

namespace App\Services\Pi\Development;

use App\Services\Pi\Taxonomy\AffairFirstNomenclature;
use RuntimeException;

/** Read-only composition of accepted engines; never writes a graph or review decision. */
final class RegulatoryPlanningCrosswalk
{
    public function __construct(
        private readonly DevelopmentCaseGraph $graph,
        private readonly RegulatoryBackboneLifecycle $backbone,
    ) {}

    public function read(array $filters): array
    {
        $year = $filters['year'] ?? 2026;
        $asOf = $filters['as_of'] ?? '2026-11-21';
        $code = $filters['code'] ?? '2.11.11.2.01.0004';
        $graph = $this->graph->read(['year' => $year]);
        $lifecycle = $this->backbone->read(['year' => $year, 'as_of' => $asOf]);
        $classifier = new AffairFirstNomenclature(resource_path('pi/affairs-nomenclature-seed-r87.json'));
        $classification = $classifier->classify($code, $year);
        // The source graph binds only this observed subactivity. A prefix is not an authority decision.
        $bound = $code === '2.11.11.2.01.0004';
        $case = $graph['case'];
        $edges = $case['edges'] ?? [];
        $visible = $bound ? array_values(array_filter($edges,
            static fn (array $edge): bool => $edge['provenance']['as_of_date'] <= $asOf)) : [];
        $relations = array_map(static fn (array $edge): array => $edge + [
            'legal_applicability' => 'NOT_ASSESSED',
            'relation_confidence' => $edge['provenance']['uncertainty'],
            'temporal_status' => 'OBSERVED_BY_AS_OF_NOT_PROOF_OF_LEGAL_VALIDITY',
        ], $visible);
        $ready = $case !== null && count($visible) > 0;
        $nomenclature = $classifier->catalogue()['nomenclature'];
        $hierarchy = $ready && $asOf >= '2026-11-19' ? array_values(array_filter($nomenclature, static fn (array $row): bool => $row['year'] === $year && ($code === $row['code'] || str_starts_with($code, $row['code'].'.')))) : [];

        $definitionRaw = file_get_contents(resource_path('pi/operational-definitions-r88.json'));
        $registry = json_decode($definitionRaw, true, 64, JSON_THROW_ON_ERROR);
        if (($registry['schema'] ?? null) !== 'PI_R88_OPERATIONAL_DEFINITIONS_V1'
            || ! is_array($registry['definitions'] ?? null)) {
            throw new RuntimeException('Operational definition registry invalid.');
        }
        $definitions = [];
        foreach ($registry['definitions'] as $definition) {
            if (($definition['relation'] ?? null) !== 'RELATED_CANDIDATE_NOT_IDENTICAL'
                || ($definition['status'] ?? null) !== 'CANDIDATE_REVIEW_REQUIRED'
                || ! is_array($definition['supporting_edge_ids'] ?? null)) {
                throw new RuntimeException('Operational definition cannot promote equivalence.');
            }
            if (! $ready || $asOf < $registry['observed_at'] || $definition['code'] !== $code || $definition['planning_year'] !== $year) {
                continue;
            }
            $proof = array_values(array_filter($visible, static fn (array $edge): bool => in_array($edge['id'], $definition['supporting_edge_ids'], true)));
            if (count($proof) !== count($definition['supporting_edge_ids'])) {
                continue;
            }
            $definitions[] = $definition + ['provenance' => array_column($proof, 'provenance')];
        }
        if ($asOf < '2026-11-19') {
            $classification['status'] = 'ABSTAIN_EVIDENCE_NOT_OBSERVED';
            $classification['match'] = null;
            $classification['sectoral_discovery_candidates'] = [];
        }

        return [
            'schema' => 'PI_R88_REGULATORY_PLANNING_CROSSWALK_V1',
            'tenant_id' => 'ID-DIY-YOG',
            'filters' => ['year' => $year, 'as_of' => $asOf, 'code' => $code],
            'result_count' => $ready ? 1 : 0,
            'source_registry_sha256' => $graph['source_registry_sha256'],
            'backbone_registry_sha256' => $lifecycle['source_registry_sha256'],
            'classification' => $classification,
            'binding' => ['status' => $bound ? 'OBSERVED_LOCAL_CODE_REVIEW_REQUIRED' : 'ABSTAIN_UNBOUND_CODE',
                'reason' => 'Only the existing R85 .0004 documentary observation is bound; lexical similarity and parent prefixes cannot create a case relation.'],
            'hierarchy' => $hierarchy,
            'definitions_registry_sha256' => strtoupper(hash('sha256', $definitionRaw)),
            'operational_definitions' => $definitions,
            'continuity' => ['status' => 'ABSTAIN_MISSING_COMPARISON_VERSION',
                'comparison_year' => 2027, 'equivalence_asserted' => false,
                'reason' => 'No verified 2027 code/version and indicator definition available.'],
            'nodes' => $ready ? array_values(array_filter($case['nodes'], static fn (array $node): bool => in_array($node['id'], array_column($visible, 'from'), true)
                || in_array($node['id'], array_column($visible, 'to'), true))) : [],
            'relations' => $relations,
            'lifecycle' => $lifecycle['case']['lifecycle'] ?? null,
            'temporal_rules' => $lifecycle['case']['temporal_rules'] ?? [],
            'specialized_views' => $lifecycle['case']['specialized_views'] ?? [],
            'gaps' => [
                ['id' => 'AUTHORITY', 'status' => 'MISSING_EVIDENCE', 'detail' => 'Provision-level municipal competence and amendments require legal review; a planning document cannot confer authority.'],
                ['id' => 'INDICATOR', 'status' => 'MISSING_EVIDENCE', 'detail' => 'Indicator formula, unit and comparable target versions are not verified.'],
                ['id' => 'DPA_REALISATION_AUDIT', 'status' => 'ABSTAIN', 'detail' => 'No verified DPA, realisation or BPK finding is admitted for this case. APIP oversight is separate from external BPK audit.'],
            ],
            'interpretation' => $graph['interpretation'],
            'reviewer_url' => '/regulatory-trace-r70a.html#r84-review-workspace',
        ];
    }
}
