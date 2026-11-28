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

    /**
     * Relation-specific guard for proposed direct edges. The evidence registry
     * is supplied by the trusted caller, not the AI. A matching locator/hash
     * establishes an evidence binding, not the truth of the proposed relation.
     */
    public function assessProposal(array $nodes,array $evidence,array $proposal,string $caseDate,string $knowledgeDate): array
    {
        foreach ([$caseDate,$knowledgeDate] as $date) {
            $d=\DateTimeImmutable::createFromFormat('!Y-m-d',$date);
            if (!$d || $d->format('Y-m-d')!==$date) throw new RuntimeException('PI_RF02_DATE_HOLD');
        }
        $rules=[
            'CITES_AS_BASIS'=>[['INSTRUMENT','PROVISION'],['INSTRUMENT','PROVISION'],'REFERENCE_ONLY'],
            'ISSUED_UNDER_AUTHORITY'=>[['INSTRUMENT'],['PROVISION'],'AUTHORITY_CANDIDATE'],
            'DELEGATED_BY'=>[['INSTRUMENT','PROVISION'],['PROVISION'],'DELEGATION_CANDIDATE'],
            'IMPLEMENTS_PROVISION_OF'=>[['INSTRUMENT','PROVISION'],['PROVISION'],'IMPLEMENTATION_CANDIDATE'],
            'AMENDS'=>[['INSTRUMENT'],['INSTRUMENT'],'CHANGE_INSTRUMENT_ONLY'],
            'AMENDS_PROVISION_OF'=>[['INSTRUMENT','PROVISION'],['PROVISION','ANNEX'],'SCOPED_CHANGE_CANDIDATE'],
            'PARTIALLY_REPEALS'=>[['INSTRUMENT','PROVISION'],['PROVISION','ANNEX'],'SCOPED_REPEAL_CANDIDATE'],
            'REPEALS'=>[['INSTRUMENT'],['INSTRUMENT'],'WHOLE_INSTRUMENT_CANDIDATE'],
            'REPLACES'=>[['INSTRUMENT'],['INSTRUMENT'],'REPLACEMENT_CANDIDATE'],
            'TRANSITIONAL_SCOPE'=>[['PROVISION'],['PROVISION','ANNEX'],'TRANSITION_CANDIDATE'],
            'MEMBER_OF_SERIES'=>[['INSTRUMENT'],['SERIES'],'MEMBERSHIP_ONLY'],
        ];
        $type=$proposal['type']??null;$from=$proposal['from']??null;$to=$proposal['to']??null;
        $eid=$proposal['evidence_id']??null;
        $result=['schema'=>'PI_RF02_RELATION_GUARD_V1','type'=>$type,'from'=>$from,'to'=>$to,'evidence_id'=>$eid,
            'state'=>'ABSTAIN','reason'=>null,'semantic_scope'=>null,'inverse_discovery_label'=>null,
            'transitive_inference_allowed'=>false,'relation_promotion'=>'NOT_PERFORMED','legal_effect'=>'NOT_ASSESSED','production_admission'=>'DENIED'];
        $hold=static function(string $reason)use($result):array{return array_replace($result,['reason'=>$reason]);};
        if (!is_string($type)||!isset($rules[$type])||!is_string($from)||!is_string($to)||$from===$to||!isset($nodes[$from],$nodes[$to])) return $hold('UNKNOWN_RELATION_OR_ENDPOINT');
        [$sourceKinds,$targetKinds,$scope]=$rules[$type];
        $a=$nodes[$from];$b=$nodes[$to];
        if (!in_array($a['node_type']??null,$sourceKinds,true)||!in_array($b['node_type']??null,$targetKinds,true)) return $hold('ENDPOINT_TYPE_MISMATCH');
        if (!is_string($eid)||!isset($evidence[$eid])) return $hold('EVIDENCE_NOT_FOUND');
        $e=$evidence[$eid];
        if (($e['from']??null)!==$from||($e['to']??null)!==$to||($e['relation_type']??null)!==$type) return $hold('EVIDENCE_EDGE_MISMATCH');
        if (!$this->registry->officialUrl($e['source_url']??null)||!is_string($e['source_locator']??null)||trim($e['source_locator'])===''
            ||!is_string($e['source_sha256']??null)||!preg_match('/^[A-Fa-f0-9]{64}$/D',$e['source_sha256'])
            ||!is_string($e['excerpt']??null)||trim($e['excerpt'])===''||strlen($e['excerpt'])>16000
            ||strtoupper(hash('sha256',$e['excerpt']))!==($e['excerpt_sha256']??null)
            ||($e['status']??null)!=='SOURCE_TEXT_CANDIDATE') return $hold('EVIDENCE_INCOMPLETE');
        $owner=$a['node_type']==='INSTRUMENT'?$from:($a['owner_id']??null);
        if (!is_string($owner)||!isset($nodes[$owner])||($e['source_instrument_id']??null)!==$owner) return $hold('SOURCE_OWNER_MISMATCH');
        foreach (['observed_on','effective_on'] as $k) {
            $v=$e[$k]??null;
            if (!is_string($v)) return $hold('TEMPORAL_EVIDENCE_UNKNOWN');
            $d=\DateTimeImmutable::createFromFormat('!Y-m-d',$v);
            if (!$d||$d->format('Y-m-d')!==$v) return $hold('TEMPORAL_EVIDENCE_INVALID');
        }
        if ($e['observed_on']>$knowledgeDate) return $hold('NOT_KNOWN_AT_CUTOFF');
        if ($e['effective_on']>$caseDate) return $hold('NOT_YET_EFFECTIVE');
        if (($proposal['derived_from_path']??false)!==false) return $hold('TRANSITIVE_PROPOSAL_NOT_ALLOWED');
        return array_replace($result,['state'=>'DIRECT_EVIDENCE_CANDIDATE_REVIEW_REQUIRED','reason'=>null,'semantic_scope'=>$scope,
            'inverse_discovery_label'=>match($type){'DELEGATED_BY'=>'DELEGATES_TO','AMENDS'=>'AMENDED_BY','AMENDS_PROVISION_OF'=>'PROVISION_AMENDED_BY','IMPLEMENTS_PROVISION_OF'=>'IMPLEMENTED_BY',default=>null}]);
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
