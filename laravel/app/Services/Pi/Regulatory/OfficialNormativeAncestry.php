<?php

namespace App\Services\Pi\Regulatory;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * R70R: autonomously reads official JDIH BPK legal-basis citations.
 * A citation is an ancestry candidate, NOT verified parent-law applicability.
 * Only two pinned official seed URLs are fetched; no user-controlled remote URL.
 */
final class OfficialNormativeAncestry
{
    public const CACHE_KEY = 'pi.r70r.official_ancestry.v1';

    private const SEEDS = [
        'PERMENDAGRI-10-2025' => [
            'number' => 10,
            'year' => 2025,
            'url' => 'https://peraturan.bpk.go.id/Details/321470/permendagri-no-10-tahun-2025',
        ],
        'PERMENDAGRI-86-2017' => [
            'number' => 86,
            'year' => 2017,
            'url' => 'https://peraturan.bpk.go.id/Details/311927/permendagri-no-86-tahun-2017',
        ],
    ];

    public function trace(): array
    {
        $seedDocuments = [];
        foreach (self::SEEDS as $id => $seed) {
            $response = Http::withHeaders([
                'Accept' => 'text/html',
                'User-Agent' => 'PlanningIntelligence-RegulatoryAncestry/0.1',
            ])->connectTimeout(3)->timeout(8)->withoutRedirecting()->get($seed['url']);

            if (!$response->successful()
                || !str_contains(strtolower((string) $response->header('Content-Type')), 'text/html')
                || strlen($response->body()) > 3 * 1024 * 1024) {
                throw new RuntimeException('Pinned official legal metadata is unavailable.');
            }

            $html = $response->body();
            $basis = $this->extractBasis($html, $seed['number'], $seed['year']);
            $seedDocuments[$id] = [
                'id' => $id,
                'official_url' => $seed['url'],
                'source_html_sha256' => strtoupper(hash('sha256', $html)),
                'basis_excerpt' => mb_substr($basis, 0, 1200),
                'citations' => $this->extractCitations($basis),
            ];
            if ($seedDocuments[$id]['citations'] === []) {
                throw new RuntimeException('Official abstract has no extractable legal citations.');
            }
        }

        $nodes = [];
        foreach (self::SEEDS as $id => $seed) {
            $nodes[$id] = [
                'id' => $id,
                'kind' => 'PERMENDAGRI',
                'number' => $seed['number'],
                'year' => $seed['year'],
                'metadata_url' => $seed['url'],
                'official_resolution' => 'PINNED_METADATA_FETCHED',
                'search_url' => null,
            ];
        }
        $edges = [];
        foreach ($seedDocuments as $document) {
            foreach ($document['citations'] as $citation) {
                if (!isset($nodes[$citation['id']])) {
                    $nodes[$citation['id']] = [
                        'id' => $citation['id'],
                        'kind' => $citation['kind'],
                        'number' => $citation['number'],
                        'year' => $citation['year'],
                        'metadata_url' => null,
                        'official_resolution' => 'CITED_IDENTITY_NOT_YET_RESOLVED',
                        'search_url' => $citation['search_url'],
                    ];
                }
                if ($document['id'] === $citation['id']) {
                    continue;
                }
                $edges[] = [
                    'from' => $document['id'],
                    'to' => $citation['id'],
                    'citation_text' => $citation['text'],
                    'evidence_url' => $document['official_url'],
                    'evidence_kind' => 'OFFICIAL_ABSTRACT_LEGAL_BASIS_CITATION',
                    'relation_status' => 'CANDIDATE_NOT_LEGAL_FINDING',
                ];
            }
        }

        return [
            'schema' => 'PI_R70R_AUTOMATED_ANCESTRY_V1',
            'tenant_id' => config('pi.tenant_id'),
            'source' => 'JDIH_BPK_OFFICIAL_METADATA',
            'retrieved_at' => now()->toIso8601String(),
            'seed_documents' => array_values($seedDocuments),
            'nodes' => array_values($nodes),
            'edges' => $edges,
            'resolved_seed_count' => count($seedDocuments),
            'unresolved_parent_count' => count(array_filter($nodes, static fn (array $node): bool =>
                $node['official_resolution'] === 'CITED_IDENTITY_NOT_YET_RESOLVED'
            )),
            'observed_depth' => 2,
            'traversal_scope' => 'AUTOMATED_TWO_SEED_PARENT_AND_GRANDPARENT_CITATIONS',
            'exhaustiveness' => 'NOT_EXHAUSTIVELY_VERIFIED',
            'result_status' => 'AUTOMATED_ANCESTRY_CANDIDATES_ONLY',
            'legal_harmonization' => 'NOT_ASSESSED',
            'numeric_comparison' => 'BLOCKED',
            'production_admission' => 'DENIED',
        ];
    }

    private function extractBasis(string $html, int $number, int $year): string
    {
        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        try {
            $loaded = $document->loadHTML('<?xml encoding="utf-8" ?>'.$html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        if (!$loaded) {
            throw new RuntimeException('Official legal metadata HTML is malformed.');
        }

        $xpath = new DOMXPath($document);
        $bodyText = $this->clean((string) $xpath->query('//body')?->item(0)?->textContent);
        $identity = 'peraturan menteri dalam negeri nomor '.$number.' tahun '.$year;
        if (!str_contains(mb_strtolower($bodyText), $identity)
            || !str_contains(mb_strtolower($bodyText), 'abstrak peraturan')) {
            throw new RuntimeException('Official metadata identity or abstract does not match pinned seed.');
        }

        $elements = $xpath->query('//li | //p');
        if ($elements !== false) {
            foreach ($elements as $element) {
                if (!$element instanceof DOMElement) {
                    continue;
                }
                $line = $this->clean($element->textContent);
                if (preg_match('/\bdasar\s+hukum\s+permendagri\s+ini\s+adalah\b/ui', $line)
                    && mb_strlen($line) <= 1500) {
                    return $line;
                }
            }
        }

        // Provider markup can group abstract bullets in a single div.
        if (preg_match('/\bdasar\s+hukum\s+permendagri\s+ini\s+adalah\s+(.*?)(?=permendagri\s+ini\s+mengatur|catatan\s*:)/uis', $bodyText, $matches)) {
            $line = $this->clean($matches[0]);
            if (mb_strlen($line) >= 30 && mb_strlen($line) <= 1500) {
                return $line;
            }
        }

        throw new RuntimeException('Official legal-basis section is missing or ambiguous.');
    }

    private function extractCitations(string $basis): array
    {
        $result = [];
        $pattern = '/\b(UU|Undang[\s-]+Undang|Perpres|Peraturan\s+Presiden|Permendagri|Peraturan\s+Menteri\s+Dalam\s+Negeri)\s*(?:(?:No\.?|Nomor)\s*)?([0-9]{1,4})\s*(?:Tahun\s*)?([0-9]{4})\b/ui';
        preg_match_all($pattern, $basis, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $kind = preg_match('/^(?:UU|Undang)/ui', $match[1]) ? 'UU'
                : (preg_match('/^(?:Perpres|Peraturan\s+Presiden)/ui', $match[1]) ? 'PERPRES' : 'PERMENDAGRI');
            $number = (int) $match[2];
            $year = (int) $match[3];
            if ($number < 1 || $year < 1945 || $year > (int) date('Y') + 1) {
                continue;
            }
            $id = $kind.'-'.$number.'-'.$year;
            $result[$id] = [
                'id' => $id,
                'kind' => $kind,
                'number' => $number,
                'year' => $year,
                'text' => trim($match[0]),
                'search_url' => $this->officialSearchUrl($kind, $number, $year),
            ];
        }
        if (preg_match('/Undang[\s-]+Undang\s+Dasar\s+Negara\s+Republik\s+Indonesia\s+Tahun\s+1945/ui', $basis, $match)) {
            $result['UUD-1945'] = [
                'id' => 'UUD-1945',
                'kind' => 'UUD',
                'number' => null,
                'year' => 1945,
                'text' => trim($match[0]),
                'search_url' => $this->officialSearchUrl('UUD', null, 1945),
            ];
        }

        return array_values($result);
    }

    private function officialSearchUrl(string $kind, ?int $number, int $year): string
    {
        $label = match ($kind) {
            'UU' => 'Undang-Undang',
            'PERPRES' => 'Peraturan Presiden',
            'PERMENDAGRI' => 'Peraturan Menteri Dalam Negeri',
            default => 'Undang-Undang Dasar',
        };
        $query = $number === null ? $label.' Tahun '.$year : $label.' Nomor '.$number.' Tahun '.$year;
        return 'https://peraturan.bpk.go.id/Search?'.http_build_query(
            ['keywords' => $query, 'tentang' => '', 'nomor' => ''], '', '&', PHP_QUERY_RFC3986
        );
    }

    private function clean(string $text): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }
}
