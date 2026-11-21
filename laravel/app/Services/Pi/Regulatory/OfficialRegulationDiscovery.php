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
        $identity = $this->structuredPermendagriIdentity($query);
        $parameters = $identity === null
            ? ['keywords' => $query, 'tentang' => '', 'nomor' => '']
            : ['keywords' => '', 'tentang' => '', 'nomor' => $identity['number'], 'tahun' => $identity['year'], 'jenis' => '40'];
        $response = Http::withHeaders([
            'Accept' => 'text/html',
            'User-Agent' => 'PlanningIntelligence-RegulatoryDiscovery/0.1',
        ])->connectTimeout(3)->timeout(8)->withoutRedirecting()->get($endpoint, $parameters);

        if (!$response->successful() || !str_contains(strtolower((string) $response->header('Content-Type')), 'text/html')) {
            throw new RuntimeException('Official search provider did not return a valid HTML search page.');
        }
        $html = $response->body();
        // The provider returns a generic recent-documents page when a search parameter is ignored.
        // Never treat that page as a completed keyword search.
        if (!str_contains(strtoupper($html), 'PENCARIAN PERATURAN')
            || str_contains(strtoupper($html), 'PERATURAN TERBARU')) {
            throw new RuntimeException('Official provider did not acknowledge the search mode.');
        }
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
            'search_mode' => $identity === null ? 'KEYWORD_BROAD' : 'STRUCTURED_IDENTITY',
            'items' => $this->extract($html, $query, $identity),
            'result_status' => 'DISCOVERY_ONLY',
            'amendment_census' => 'NOT_EXHAUSTIVELY_VERIFIED',
            'legal_status' => 'NOT_VERIFIED',
            'normative_alignment' => 'ABSTAIN',
            'numeric_comparison' => 'BLOCKED',
            'production_admission' => 'DENIED',
        ];
    }

    /**
     * Verified BPK advanced-search contract (8 Nov 2026): type 40 is
     * "Peraturan Menteri Dalam Negeri"; number and year are separate fields.
     * Do not infer legal validity from an exact catalogue identity.
     */
    private function structuredPermendagriIdentity(string $query): ?array
    {
        if (!preg_match('/^\\s*(?:Permendagri|Peraturan\\s+Menteri\\s+Dalam\\s+Negeri)\\s+(?:(?:Nomor|No\\.?)\\s+)?([0-9]{1,4})\\s+(?:Tahun\\s+)?([0-9]{4})\\s*$/ui', $query, $matches)) {
            return null;
        }
        $year = (int) $matches[2];
        if ($year < 1945 || $year > (int) date('Y') + 1) {
            return null;
        }
        return ['number' => (string) (int) $matches[1], 'year' => (string) $year];
    }

    private function extract(string $html, string $query, ?array $identity): array
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
            if ($identity !== null) {
                // The official catalogue slug carries type, number and year even when
                // the linked title contains only the regulation's long subject.
                $slug = basename((string) parse_url($item['official_metadata_url'], PHP_URL_PATH));
                $expectedSlug = 'permendagri-no-'.$identity['number'].'-tahun-'.$identity['year'];
                if (strtolower($slug) !== $expectedSlug) {
                    continue;
                }
            } elseif (!$this->matchesQuery($item['title'], $query)) {
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
        // Broad provider searches may return APBDes documents for RKPD queries.
        // Require every meaningful query token to match an exact title word or
        // an explicit regulatory acronym expansion. Prefer zero candidates
        // over off-topic legal sources.
        $normalize = static fn (string $value): string => trim((string) preg_replace(
            '/[^\pL\pN]+/u', ' ', mb_strtolower($value)
        ));
        $tokens = preg_split('/\s+/u', $normalize($query), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $tokens = array_values(array_unique(array_filter($tokens, static fn (string $token): bool =>
            mb_strlen($token) > 2 || ctype_digit($token)
        )));
        if ($tokens === []) {
            return false;
        }

        $candidate = $normalize($title);
        $words = array_fill_keys(preg_split('/\s+/u', $candidate, -1, PREG_SPLIT_NO_EMPTY) ?: [], true);
        $expansions = [
            'rkpd' => 'rencana kerja pemerintah daerah',
            'rpjmd' => 'rencana pembangunan jangka menengah daerah',
            'apbd' => 'anggaran pendapatan dan belanja daerah',
            'apbdes' => 'anggaran pendapatan dan belanja desa',
        ];
        foreach ($tokens as $token) {
            if (isset($words[$token])) {
                continue;
            }
            if (isset($expansions[$token]) && str_contains($candidate, $expansions[$token])) {
                continue;
            }
            return false;
        }

        return true;
    }
}
