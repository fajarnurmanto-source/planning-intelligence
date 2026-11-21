<?php

namespace App\Services\Pi\Regulatory;

use RuntimeException;

/**
 * R74 bounded, read-only multi-parent genealogy graph.
 * Stored edge direction: dependent/newer instrument -> its parent/target.
 * Reversing an edge supports descendant discovery, not a legal-force finding.
 */
final class RegulatoryGenealogyGraph
{
    private const LEGAL_RELATIONS = [
        'CITES_AS_BASIS', 'ISSUED_UNDER_AUTHORITY', 'IMPLEMENTS_PROVISION_OF',
        'DELEGATED_BY', 'PARTIALLY_REPEALS', 'REPEALS', 'REPLACES',
    ];

    private const VERSION_RELATIONS = ['AMENDS', 'AMENDS_PROVISION_OF'];
    private const THEMATIC_RELATIONS = ['REGULATES_TOPIC', 'BROADER_TOPIC'];
    private const STRUCTURAL_RELATIONS = ['PROVISION_OF'];

    public function __construct(private readonly RegulatoryTaxonomyRegistry $registry)
    {
    }

    public function build(array $seed, ?array $automatedAncestry = null): array
    {
        $kinds = array_column($seed['types'], null, 'code');
        $nodes = [];
        $edges = [];

        foreach ($seed['instruments'] as $item) {
            $nodes[$item['id']] = [
                'id' => $item['id'], 'node_type' => 'INSTRUMENT',
                'label' => $item['title'], 'kind' => $item['kind'],
                'article_group' => $kinds[$item['kind']]['group'],
                'formal_rank' => $kinds[$item['kind']]['rank'],
                'jurisdiction' => $item['jurisdiction'], 'official_url' => $item['source_url'],
                'source_status' => 'OFFICIAL_SOURCE_LINKED_NOT_HASH_PINNED',
            ];
        }
        foreach ($seed['provisions'] as $item) {
            $nodes[$item['id']] = [
                'id' => $item['id'], 'node_type' => 'PROVISION',
                'label' => $item['locator'], 'owner_id' => $item['instrument_id'],
                'source_status' => 'PROVISION_LOCATOR_NOT_HASH_PINNED',
            ];
            $this->edge($edges, $item['id'], $item['instrument_id'], 'PROVISION_OF',
                'STRUCTURAL_ONLY', null, null);
        }
        foreach ($seed['topics'] as $item) {
            $nodes[$item['id']] = [
                'id' => $item['id'], 'node_type' => 'TOPIC',
                'label' => $item['label'], 'source_status' => 'PI_CONTROLLED_VOCABULARY',
            ];
            foreach ($item['broader'] as $parent) {
                $this->edge($edges, $item['id'], $parent, 'BROADER_TOPIC',
                    'TAXONOMY_NOT_LEGAL_ANCESTRY', null, null);
            }
        }
        foreach ($seed['instruments'] as $item) {
            foreach ($item['topics'] as $topic) {
                $this->edge($edges, $item['id'], $topic, 'REGULATES_TOPIC',
                    'CURATED_TOPIC_CANDIDATE', $item['source_url'], null);
            }
        }
        foreach ($seed['provisions'] as $item) {
            foreach ($item['topics'] as $topic) {
                $this->edge($edges, $item['id'], $topic, 'REGULATES_TOPIC',
                    'CURATED_TOPIC_CANDIDATE', null, null);
            }
        }
        foreach ($seed['relations'] as $relation) {
            $this->edge($edges, $relation['from'], $relation['to'], $relation['type'],
                $relation['status'], $relation['source_url'], $relation['locator']);
        }
        $legacyCount = 0;
        if (is_array($automatedAncestry)
            && ($automatedAncestry['schema'] ?? null) === 'PI_R70R_AUTOMATED_ANCESTRY_V1'
            && ($automatedAncestry['result_status'] ?? null) === 'AUTOMATED_ANCESTRY_CANDIDATES_ONLY'
            && ($automatedAncestry['tenant_id'] ?? null) === config('pi.tenant_id')
            && is_array($automatedAncestry['nodes'] ?? null)
            && is_array($automatedAncestry['edges'] ?? null)
            && count($automatedAncestry['nodes']) <= 40
            && count($automatedAncestry['edges']) <= 100) {
            foreach ($automatedAncestry['nodes'] as $item) {
                $id = $item['id'] ?? null;
                $kind = $item['kind'] ?? null;
                if (!is_string($id) || !preg_match('/^(?:[A-Z_]+-[1-9][0-9]*-[0-9]{4}|UUD-1945)$/D', $id)
                    || !is_string($kind) || !isset($kinds[$kind]) || isset($nodes[$id])) {
                    continue;
                }
                $candidateUrl = $item['metadata_url'] ?? $item['search_url'] ?? null;
                $nodes[$id] = [
                    'id' => $id, 'node_type' => 'INSTRUMENT',
                    'label' => $id, 'kind' => $kind,
                    'article_group' => $kinds[$kind]['group'],
                    'formal_rank' => $kinds[$kind]['rank'],
                    'jurisdiction' => 'ID',
                    'official_url' => $this->registry->officialUrl($candidateUrl) ? $candidateUrl : null,
                    'source_status' => 'R70R_CITED_IDENTITY_CANDIDATE',
                ];
            }
            foreach ($automatedAncestry['edges'] as $item) {
                $from = $item['from'] ?? null;
                $to = $item['to'] ?? null;
                $url = $item['evidence_url'] ?? null;
                if (!is_string($from) || !is_string($to) || $from === $to
                    || !isset($nodes[$from]) || !isset($nodes[$to])
                    || !$this->registry->officialUrl($url)
                    || ($item['relation_status'] ?? null) !== 'CANDIDATE_NOT_LEGAL_FINDING') {
                    continue;
                }
                $this->edge($edges, $from, $to, 'CITES_AS_BASIS',
                    'R70R_CANDIDATE_NOT_LEGAL_FINDING', $url,
                    is_string($item['citation_text'] ?? null) ? $item['citation_text'] : null);
                $legacyCount++;
            }
        }

        if (count($nodes) > 1600 || count($edges) > 4000) {
            throw new RuntimeException('R74 graph safety limit exceeded.');
        }
        return [
            'schema' => 'PI_R74_EVIDENCE_GENEALOGY_GRAPH_V1',
            'nodes' => $nodes, 'edges' => array_values($edges),
            'legacy_candidate_edge_count' => $legacyCount,
        ];
    }

    public function traverse(array $graph, string $root, string $direction, string $lens, int $depth): array
    {
        if (!isset($graph['nodes'][$root]) || !in_array($direction, ['ancestors', 'descendants', 'both'], true)
            || !in_array($lens, ['legal', 'version', 'thematic', 'all'], true) || $depth < 1 || $depth > 5) {
            throw new RuntimeException('R74 traversal request invalid.');
        }
        if ($lens === 'thematic') {
            $direction = 'both';
        }
        $allowed = match ($lens) {
            'legal' => [...self::LEGAL_RELATIONS, ...self::STRUCTURAL_RELATIONS],
            'version' => [...self::VERSION_RELATIONS, ...self::STRUCTURAL_RELATIONS],
            'thematic' => [...self::THEMATIC_RELATIONS, ...self::STRUCTURAL_RELATIONS],
            default => [
                ...self::LEGAL_RELATIONS, ...self::VERSION_RELATIONS,
                ...self::THEMATIC_RELATIONS, ...self::STRUCTURAL_RELATIONS,
            ],
        };
        $adjacency = [];
        foreach ($graph['edges'] as $edge) {
            if (!in_array($edge['type'], $allowed, true)) {
                continue;
            }
            if ($direction === 'ancestors' || $direction === 'both') {
                $adjacency[$edge['from']][] = ['next' => $edge['to'], 'edge' => $edge];
            }
            if ($direction === 'descendants' || $direction === 'both') {
                $adjacency[$edge['to']][] = ['next' => $edge['from'], 'edge' => $edge];
            }
        }
        $visited = [$root => 0];
        $frontier = [$root];
        $traversed = [];
        for ($level = 1; $level <= $depth && $frontier !== []; $level++) {
            $next = [];
            foreach ($frontier as $current) {
                foreach ($adjacency[$current] ?? [] as $adjacent) {
                    $edge = $adjacent['edge'];
                    $other = $adjacent['next'];
                    if ($other === $root) {
                        continue;
                    }
                    $traversed[$edge['id']] = $edge;
                    if (!isset($visited[$other])) {
                        $visited[$other] = $level;
                        $next[] = $other;
                    }
                }
            }
            $frontier = array_values(array_unique($next));
        }
        $resultNodes = [];
        foreach ($visited as $id => $level) {
            $resultNodes[] = $graph['nodes'][$id] + ['distance' => $level];
        }
        usort($resultNodes, static fn (array $a, array $b): int =>
            ($a['distance'] <=> $b['distance']) ?: strcmp($a['id'], $b['id']));

        return [
            'schema' => 'PI_R74_BIDIRECTIONAL_GENEALOGY_TRACE_V1',
            'tenant_id' => config('pi.tenant_id'),
            'root' => $root, 'direction' => $direction, 'lens' => $lens, 'max_depth' => $depth,
            'node_count' => count($resultNodes), 'edge_count' => count($traversed),
            'nodes' => $resultNodes, 'relations' => array_values($traversed),
            'source_coverage' => 'CURATED_SEED_PLUS_OPTIONAL_R70R_CANDIDATES_NOT_EXHAUSTIVE',
            'legacy_candidate_edge_count' => $graph['legacy_candidate_edge_count'],
            'legal_harmonization' => 'NOT_ASSESSED',
            'temporal_applicability' => 'NOT_ASSESSED',
            'numeric_comparison' => 'BLOCKED',
            'review_status' => 'REVIEW_REQUIRED',
            'result_status' => 'PARTIAL_EVIDENCE_GRAPH_NOT_LEGAL_FINDING',
            'production_admission' => 'DENIED',
        ];
    }

    private function edge(
        array &$edges, string $from, string $to, string $type, string $status,
        ?string $sourceUrl, ?string $locator
    ): void {
        $id = strtoupper(hash('sha256', implode('|', [$from, $to, $type, $sourceUrl ?? ''])));
        $edges[$id] = [
            'id' => $id, 'from' => $from, 'to' => $to, 'type' => $type,
            'status' => $status, 'source_url' => $sourceUrl, 'source_locator' => $locator,
            'legal_effect' => 'NOT_ASSESSED',
        ];
    }
}
