<?php

namespace App\Services\Pi\Regulatory;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * R70T: bounded reading of actual official parent metadata after R70S resolution.
 * Adds source-hashed constitutional mentions and amendment CANDIDATES only.
 * No PDF snapshot, legal effect or full amendment census is asserted.
 */
final class OfficialParentEvidenceExpansion
{
    public const DEFAULT_BATCH_LIMIT = 2;

    public function expandBatch(array $graph, int $limit = self::DEFAULT_BATCH_LIMIT): array
    {
        if (($graph['schema'] ?? null) !== 'PI_R70R_AUTOMATED_ANCESTRY_V1'
            || ($graph['result_status'] ?? null) !== 'AUTOMATED_ANCESTRY_CANDIDATES_ONLY'
            || !is_array($graph['nodes'] ?? null) || !is_array($graph['edges'] ?? null)
            || count($graph['nodes']) > 50 || count($graph['edges']) > 100
            || $limit < 1 || $limit > self::DEFAULT_BATCH_LIMIT) {
            throw new RuntimeException('Untrusted parent-evidence graph or expansion limit.');
        }
        $previous = $graph['parent_evidence_expansion']['attempts'] ?? [];
        $priorVersionList = $graph['version_candidates'] ?? [];
        // R70T V1 could mistake legislation mentioned in an amendment's title
        // for an actual listed amending instrument. Never retain that legacy list.
        if (isset($graph['parent_evidence_expansion'])
            && ($graph['parent_evidence_expansion']['schema'] ?? null)
                !== 'PI_R70T_PARENT_OFFICIAL_EVIDENCE_V2') {
            $previous = [];
            $priorVersionList = [];
        }
        if (!is_array($previous) || !is_array($priorVersionList)) {
            throw new RuntimeException('Prior parent-evidence state is invalid.');
        }
        $versionCandidates = [];
        foreach ($priorVersionList as $candidate) {
            if (!is_array($candidate) || !is_string($candidate['id'] ?? null)) {
                throw new RuntimeException('Prior amendment candidate is malformed.');
            }
            $versionCandidates[$candidate['id']] = $candidate;
        }

        $eligible = [];
        foreach ($graph['nodes'] as $node) {
            if (($node['official_resolution'] ?? null) !== 'OFFICIAL_CATALOG_IDENTITY_CANDIDATE'
                || ($node['kind'] ?? null) !== 'UU'
                || !is_int($node['number'] ?? null) || !is_int($node['year'] ?? null)
                || !is_string($node['id'] ?? null)
                || $node['id'] !== 'UU-'.$node['number'].'-'.$node['year']) {
                continue;
            }
            $oldResult = $previous[$node['id']]['result'] ?? null;
            if ($oldResult !== null && $oldResult !== 'OFFICIAL_METADATA_UNAVAILABLE_RETRY') {
                continue;
            }
            $eligible[] = $node;
        }
        usort($eligible, static fn (array $a, array $b): int => strcmp($a['id'], $b['id']));

        $attempted = 0;
        $read = 0;
        $newEdges = 0;
        $newChanges = 0;
        foreach (array_slice($eligible, 0, $limit) as $node) {
            $attempted++;
            $record = ['id' => $node['id'], 'attempted_at' => now()->toIso8601String(),
                'result' => 'OFFICIAL_METADATA_UNAVAILABLE_RETRY', 'source_html_sha256' => null,
                'constitutional_reference_found' => false, 'amendment_mentions' => 0];
            try {
                $details = $this->readOfficialDetails($node);
                $record['result'] = 'OFFICIAL_METADATA_ABSTRACT_READ';
                $record['source_html_sha256'] = $details['sha256'];
                $read++;

                if ($details['constitutional_reference'] !== null) {
                    $record['constitutional_reference_found'] = true;
                    $exists = false;
                    foreach ($graph['edges'] as $edge) {
                        if (($edge['from'] ?? null) === $node['id']
                            && ($edge['to'] ?? null) === 'UUD-1945') {
                            $exists = true;
                            break;
                        }
                    }
                    if (!$exists) {
                        $rootPresent = false;
                        foreach ($graph['nodes'] as $existingNode) {
                            if (($existingNode['id'] ?? null) === 'UUD-1945') {
                                $rootPresent = true;
                                break;
                            }
                        }
                        if (!$rootPresent) {
                            $graph['nodes'][] = [
                                'id' => 'UUD-1945', 'kind' => 'UUD', 'number' => null,
                                'year' => 1945, 'metadata_url' => null,
                                'official_resolution' => 'CITED_IDENTITY_NOT_YET_RESOLVED',
                                'search_url' => 'https://peraturan.bpk.go.id/Search?keywords=Undang-Undang%20Dasar%201945',
                            ];
                            $graph['unresolved_parent_count'] = ($graph['unresolved_parent_count'] ?? 0) + 1;
                            if (isset($graph['parent_resolution_batch']['remaining_unresolved_count'])) {
                                $graph['parent_resolution_batch']['remaining_unresolved_count'] =
                                    $graph['unresolved_parent_count'];
                            }
                        }
                        $graph['edges'][] = [
                            'from' => $node['id'], 'to' => 'UUD-1945',
                            'citation_text' => $details['constitutional_reference'],
                            'evidence_url' => $node['metadata_url'],
                            'evidence_kind' => 'OFFICIAL_ABSTRACT_CONSTITUTIONAL_REFERENCE',
                            'relation_status' => 'CANDIDATE_NOT_LEGAL_FINDING',
                        ];
                        $newEdges++;
                    }
                }

                foreach ($details['amendment_mentions'] as $candidate) {
                    $id = $node['id'].'::'.$candidate['id'];
                    if (isset($versionCandidates[$id])) {
                        continue;
                    }
                    if (count($versionCandidates) >= 25) {
                        break;
                    }
                    $versionCandidates[$id] = [
                        'id' => $id, 'instrument_id' => $node['id'],
                        'amending_candidate_id' => $candidate['id'],
                        'relation' => 'OFFICIAL_STATUS_LISTED_AS_AMENDING_CANDIDATE',
                        'evidence_url' => $node['metadata_url'],
                        'amending_metadata_url' => $candidate['metadata_url'],
                        'source_html_sha256' => $details['sha256'],
                        'legal_effect' => 'NOT_ASSESSED',
                    ];
                    $newChanges++;
                }
                $record['amendment_mentions'] = count($details['amendment_mentions']);
            } catch (Throwable $error) {
                $record['error_type'] = 'OFFICIAL_PROVIDER_OR_IDENTITY_UNAVAILABLE';
            }
            $previous[$node['id']] = $record;
        }

        $graph['version_candidates'] = array_values($versionCandidates);
        $graph['parent_evidence_expansion'] = [
            'schema' => 'PI_R70T_PARENT_OFFICIAL_EVIDENCE_V2',
            'last_run_at' => now()->toIso8601String(),
            'attempted_this_run' => $attempted,
            'read_this_run' => $read,
            'new_constitutional_edges' => $newEdges,
            'new_version_candidates' => $newChanges,
            'total_version_candidates' => count($graph['version_candidates']),
            'attempts' => $previous,
            'status' => 'PARTIAL_OFFICIAL_METADATA_READING',
            'legal_status' => 'ABSTAIN',
            'amendment_census' => 'NOT_EXHAUSTIVELY_VERIFIED',
            'candidate_extraction' => 'EXACT_LINKED_STATUS_ENTRIES_ONLY',
        ];

        return $graph;
    }

    /** @return array{sha256: string, constitutional_reference: ?string, amendment_mentions: array} */
    private function readOfficialDetails(array $node): array
    {
        $url = (string) ($node['metadata_url'] ?? '');
        $parts = parse_url($url);
        $number = (int) $node['number'];
        $year = (int) $node['year'];
        if (!is_array($parts) || ($parts['scheme'] ?? null) !== 'https'
            || ($parts['host'] ?? null) !== 'peraturan.bpk.go.id'
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['port'])
            || isset($parts['query']) || isset($parts['fragment'])
            || !preg_match('~^/Details/[0-9]+/(?:uu-no-|uuno-)'.$number.'-tahun-'.$year.'/?$~',
                (string) ($parts['path'] ?? ''))) {
            throw new RuntimeException('Parent official URL is not an exact resolved UU identity.');
        }

        $response = Http::withHeaders([
            'Accept' => 'text/html',
            'User-Agent' => 'PlanningIntelligence-ParentEvidence/0.1',
        ])->connectTimeout(3)->timeout(8)->withoutRedirecting()->get($url);
        $html = $response->body();
        if (!$response->successful()
            || !str_contains(strtolower((string) $response->header('Content-Type')), 'text/html')
            || $html === '' || strlen($html) > 5 * 1024 * 1024) {
            throw new RuntimeException('Official parent metadata unavailable.');
        }

        $dom = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        try {
            $loaded = $dom->loadHTML('<?xml encoding="utf-8" ?>'.$html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        if (!$loaded) {
            throw new RuntimeException('Official parent HTML cannot be parsed.');
        }
        $xpath = new DOMXPath($dom);
        $title = $this->clean((string) $xpath->query('//h1')?->item(0)?->textContent);
        $identity = '/\\b(?:Undang[\\s-]+undang|UU)\\b.{0,55}\\b(?:No\\.?|Nomor)\\s*'
            .$number.'\\s*Tahun\\s*'.$year.'\\b/ui';
        if (!preg_match($identity, $title)) {
            throw new RuntimeException('Official parent heading identity disagrees with catalog.');
        }
        $body = $this->clean((string) $xpath->query('//body')?->item(0)?->textContent);
        $abstractStart = mb_stripos($body, 'ABSTRAK PERATURAN');
        if ($abstractStart === false) {
            throw new RuntimeException('Official parent abstract section missing.');
        }
        $abstract = mb_substr($body, $abstractStart, 8000);
        $constitutionalReference = null;
        if (preg_match('/Undang[\\s-]+Undang\\s+Dasar(?:\\s+Negara)?\\s+Republik\\s+Indonesia\\s+Tahun\\s+1945/ui',
            $abstract, $reference)) {
            $constitutionalReference = trim($reference[0]);
        }

        // Only the actual linked list entries under "Diubah dengan" count.
        // Parsing all raw status text also captures incidental numbers in titles,
        // notes, repeal summaries, or citations to the law itself.
        $amendments = [];
        $inStatus = false;
        $inAmendingList = false;
        foreach ($xpath->query('//body//text()') ?: [] as $textNode) {
            $fragment = $this->clean((string) $textNode->nodeValue);
            if ($fragment === '') {
                continue;
            }
            if (preg_match('/^STATUS\s+PERATURAN$/ui', $fragment)) {
                $inStatus = true;
                $inAmendingList = false;
                continue;
            }
            if ($inStatus && preg_match('/^ABSTRAK\s+PERATURAN$/ui', $fragment)) {
                break;
            }
            if (!$inStatus) {
                continue;
            }
            if (preg_match('/^Diubah\s+dengan\s*:?$/ui', $fragment)) {
                $inAmendingList = true;
                continue;
            }
            if (preg_match('/^(?:Dicabut(?:\s+sebagian)?\s+(?:dengan|oleh)|Mencabut(?:\s+sebagian)?|Mengubah|Dilaksanakan\s+dengan|Ditetapkan\s+dengan)\s*:?$/ui', $fragment)) {
                $inAmendingList = false;
                continue;
            }
            if (!$inAmendingList) {
                continue;
            }

            $anchor = $textNode->parentNode;
            for ($depth = 0; $depth < 3 && $anchor instanceof DOMElement
                && strtolower($anchor->tagName) !== 'a'; $depth++) {
                $anchor = $anchor->parentNode;
            }
            if (!($anchor instanceof DOMElement) || strtolower($anchor->tagName) !== 'a') {
                continue;
            }
            $label = $this->clean((string) $anchor->textContent);
            if (!preg_match('/^(?:UU|Undang[\s-]+undang)\s*(?:(?:No\.?|Nomor)\s*)?([0-9]{1,4})\s*(?:Tahun\s*)?([0-9]{4})$/ui',
                $label, $candidate)) {
                continue;
            }

            $href = trim($anchor->getAttribute('href'));
            if (str_starts_with($href, '/Details/')) {
                $href = 'https://peraturan.bpk.go.id'.$href;
            }
            $parts = parse_url($href);
            if (!is_array($parts) || ($parts['scheme'] ?? null) !== 'https'
                || ($parts['host'] ?? null) !== 'peraturan.bpk.go.id'
                || isset($parts['user']) || isset($parts['pass']) || isset($parts['port'])
                || isset($parts['query']) || isset($parts['fragment'])
                || !preg_match('~^/Details/[0-9]+/(?:uu-no-|uuno-)'.(int) $candidate[1]
                    .'-tahun-'.(int) $candidate[2].'/?$~', (string) ($parts['path'] ?? ''))) {
                continue;
            }
            $changeId = 'UU-'.(int) $candidate[1].'-'.(int) $candidate[2];
            if ($changeId !== $node['id']) {
                $amendments[$changeId] = ['id' => $changeId, 'metadata_url' => $href];
            }
            if (count($amendments) >= 12) {
                break;
            }
        }

        return [
            'sha256' => strtoupper(hash('sha256', $html)),
            'constitutional_reference' => $constitutionalReference,
            'amendment_mentions' => array_values($amendments),
        ];
    }

    private function clean(string $text): string
    {
        return trim((string) preg_replace('/\\s+/u', ' ', $text));
    }
}
