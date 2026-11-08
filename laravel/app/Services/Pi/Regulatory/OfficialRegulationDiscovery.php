<?php

namespace App\Services\Pi\Regulatory;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Live discovery only. Search hits are NOT validated laws, versions, or legal advice.
 * External URLs are fixed to an approved national JDIH host; callers cannot supply URLs.
 */
final class OfficialRegulationDiscovery
{
    public function search(string $query, string $source): array
    {
        if ($source !== 'jdih_bpk' || !config('pi.regulatory_discovery.jdih_bpk_enabled', true)) {
            throw new RuntimeException('Official discovery source is not approved or disabled.');
        }

        $endpoint = 'https://peraturan.bpk.go.id/Search';
        $parameters = ['search' => $query, 'filter' => 0, 'page' => 1];
        $response = Http::withHeaders([
            'Accept' => 'text/html',
            'User-Agent' => 'PlanningIntelligence-RegulatoryDiscovery/0.1',
        ])->connectTimeout(3)->timeout(8)->withoutRedirecting()->get($endpoint, $parameters);

        if (!$response->successful() || !str_contains(strtolower((string) $response->header('Content-Type')), 'text/html')) {
            throw new RuntimeException('Official search provider did not return a valid HTML search page.');
        }
        $html = $response->body();
        if ($html === '' || strlen($html) > 5 * 1024 * 1024 || !class_exists(DOMDocument::class)) {
            throw new RuntimeException('Official search page cannot be parsed within limits.');
        }

        return [
            'schema' => 'PI_R70O_OFFICIAL_DISCOVERY_V1',
            'source' => $source,
            'source_name' => 'JDIH BPK RI · Database Peraturan',
            'query' => $query,
            'retrieved_at' => now()->toIso8601String(),
            'official_search_url' => $endpoint.'?'.http_build_query($parameters, '', '&', PHP_QUERY_RFC3986),
            'items' => $this->extract($html, $query),
            'result_status' => 'DISCOVERY_ONLY',
            'amendment_census' => 'NOT_EXHAUSTIVELY_VERIFIED',
            'legal_status' => 'NOT_VERIFIED',
            'normative_alignment' => 'ABSTAIN',
            'numeric_comparison' => 'BLOCKED',
            'production_admission' => 'DENIED',
        ];
    }

    private function extract(string $html, string $query): array
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
            throw new RuntimeException('Official search HTML is malformed.');
        }

        $xpath = new DOMXPath($document);
        $nodes = $xpath->query('//a[@href]');
        if ($nodes === false) {
            throw new RuntimeException('Official search document has no traversable links.');
        }
        $candidates = [];
        foreach ($nodes as $node) {
            if (!$node instanceof DOMElement) {
                continue;
            }
            $url = $this->officialDetailsUrl(trim($node->getAttribute('href')));
            if ($url === null) {
                continue;
            }
            $title = trim((string) preg_replace('/\s+/u', ' ', $node->textContent));
            if (strlen($title) < 10 || strlen($title) > 500) {
                continue;
            }
            if (!isset($candidates[$url]) || strlen($title) > strlen($candidates[$url]['title'])) {
                $candidates[$url] = [
                    'title' => mb_substr($title, 0, 220),
                    'official_metadata_url' => $url,
                    'source' => 'jdih_bpk',
                    'classification' => 'SEARCH_RESULT_CANDIDATE',
                    'amendment_or_repeal_candidate' => (bool) preg_match('/\b(perubahan atas|pencabutan|mencabut)\b/ui', $title),
                    'legal_status' => 'NOT_VERIFIED',
                    'source_pdf_sha256' => null,
                ];
            }
        }

        $items = [];
        foreach ($candidates as $item) {
            if (!$this->matchesQuery($item['title'], $query)) {
                continue;
            }
            $items[] = $item;
            if (count($items) === 15) {
                break;
            }
        }
        return $items;
    }

    private function officialDetailsUrl(string $href): ?string
    {
        if (str_starts_with($href, '/')) {
            $href = 'https://peraturan.bpk.go.id'.$href;
        }
        $parts = parse_url($href);
        if (!is_array($parts) || ($parts['scheme'] ?? null) !== 'https'
            || strtolower((string) ($parts['host'] ?? '')) !== 'peraturan.bpk.go.id'
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['port'])
            || !preg_match('~^/Details/[0-9]+(?:/[a-zA-Z0-9-]+)?/?$~', (string) ($parts['path'] ?? ''))) {
            return null;
        }
        return 'https://peraturan.bpk.go.id'.$parts['path'];
    }

    private function matchesQuery(string $title, string $query): bool
    {
        $tokens = preg_split('/[^\pL\pN]+/u', mb_strtolower($query), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $tokens = array_values(array_unique(array_filter($tokens, static fn (string $token): bool =>
            mb_strlen($token) > 2 || ctype_digit($token)
        )));
        if ($tokens === []) {
            return false;
        }
        $candidate = mb_strtolower($title);
        $hits = count(array_filter($tokens, static fn (string $token): bool => str_contains($candidate, $token)));

        return $hits >= min(2, count($tokens));
    }
}
