<?php

namespace App\Services\Pi\Regulatory;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * R70S: bounded, autonomous OFFICIAL CATALOG identity resolution.
 * Exact type/number/year slugs are required. Search hits NEVER prove
 * current legal force, parent applicability, or amendment completion.
 */
final class OfficialParentResolver
{
    public const DEFAULT_BATCH_LIMIT = 3;

    public function resolveBatch(array $graph, int $limit = self::DEFAULT_BATCH_LIMIT): array
    {
        if (($graph['schema'] ?? null) !== 'PI_R70R_AUTOMATED_ANCESTRY_V1'
            || ($graph['result_status'] ?? null) !== 'AUTOMATED_ANCESTRY_CANDIDATES_ONLY'
            || !is_array($graph['nodes'] ?? null) || count($graph['nodes']) > 40
            || $limit < 1 || $limit > self::DEFAULT_BATCH_LIMIT) {
            throw new RuntimeException('Ancestry graph or lookup batch limit is invalid.');
        }

        $previous = $graph['parent_resolution_batch']['attempts'] ?? [];
        if (!is_array($previous)) {
            throw new RuntimeException('Prior parent resolution state is invalid.');
        }

        $candidates = [];
        foreach ($graph['nodes'] as $index => $node) {
            if (($node['official_resolution'] ?? null) !== 'CITED_IDENTITY_NOT_YET_RESOLVED'
                || !in_array($node['kind'] ?? '', ['UU', 'PERPRES', 'PERMENDAGRI'], true)
                || !is_int($node['number'] ?? null) || !is_int($node['year'] ?? null)
                || !is_string($node['id'] ?? null)
                || $node['id'] !== $node['kind'].'-'.$node['number'].'-'.$node['year']) {
                continue;
            }
            $priorStatus = $previous[$node['id']]['result'] ?? null;
            if ($priorStatus !== null && $priorStatus !== 'OFFICIAL_SEARCH_UNAVAILABLE_RETRY') {
                continue;
            }
            $candidates[] = ['index' => $index, 'node' => $node];
        }

        // Go toward higher-level law identities first. No legal hierarchy is inferred
        // from this queue order; it only prioritizes locating official metadata.
        $priority = ['UU' => 0, 'PERPRES' => 1, 'PERMENDAGRI' => 2];
        usort($candidates, static fn (array $a, array $b): int =>
            ($priority[$a['node']['kind']] <=> $priority[$b['node']['kind']])
            ?: strcmp($a['node']['id'], $b['node']['id'])
        );

        $attempted = 0;
        $matched = 0;
        foreach (array_slice($candidates, 0, $limit) as $candidate) {
            $attempted++;
            $node = $candidate['node'];
            $record = [
                'id' => $node['id'],
                'attempted_at' => now()->toIso8601String(),
                'result' => 'NO_EXACT_CATALOG_IDENTITY_MATCH',
                'metadata_url' => null,
                'search_html_sha256' => null,
            ];
            try {
                $search = $this->searchExactIdentity($node);
                $record['result'] = $search['url'] === null
                    ? 'NO_EXACT_CATALOG_IDENTITY_MATCH'
                    : 'OFFICIAL_CATALOG_IDENTITY_CANDIDATE';
                $record['metadata_url'] = $search['url'];
                $record['search_html_sha256'] = $search['sha256'];
                if ($search['url'] !== null) {
                    $graph['nodes'][$candidate['index']]['metadata_url'] = $search['url'];
                    $graph['nodes'][$candidate['index']]['official_resolution'] =
                        'OFFICIAL_CATALOG_IDENTITY_CANDIDATE';
                    $matched++;
                }
            } catch (Throwable $exception) {
                // A blocked/missing official source never turns into a legal claim.
                $record['result'] = 'OFFICIAL_SEARCH_UNAVAILABLE_RETRY';
                $record['error_type'] = 'OFFICIAL_PROVIDER_UNAVAILABLE';
            }
            $previous[$node['id']] = $record;
        }

        $remaining = count(array_filter($graph['nodes'], static fn (array $node): bool =>
            ($node['official_resolution'] ?? null) === 'CITED_IDENTITY_NOT_YET_RESOLVED'
        ));
        $graph['unresolved_parent_count'] = $remaining;
        $graph['parent_resolution_batch'] = [
            'schema' => 'PI_R70S_OFFICIAL_PARENT_RESOLVER_V1',
            'last_run_at' => now()->toIso8601String(),
            'attempted_this_run' => $attempted,
            'matched_this_run' => $matched,
            'attempts' => $previous,
            'catalog_matched_count' => count(array_filter($graph['nodes'], static fn (array $node): bool =>
                ($node['official_resolution'] ?? null) === 'OFFICIAL_CATALOG_IDENTITY_CANDIDATE'
            )),
            'remaining_unresolved_count' => $remaining,
            'traversal_status' => 'PARTIAL_METADATA_IDENTITY_ONLY',
            'legal_status' => 'ABSTAIN',
            'amendment_census' => 'NOT_EXHAUSTIVELY_VERIFIED',
        ];

        return $graph;
    }

    /** @return array{url: ?string, sha256: string} */
    private function searchExactIdentity(array $node): array
    {
        $number = (int) $node['number'];
        $year = (int) $node['year'];
        $kind = (string) $node['kind'];
        if ($number < 1 || $year < 1945 || $year > (int) date('Y') + 1) {
            throw new RuntimeException('Invalid parent instrument identity.');
        }

        $params = ['keywords' => '', 'tentang' => '', 'nomor' => (string) $number,
            'tahun' => (string) $year];
        if ($kind === 'PERMENDAGRI') {
            $params['jenis'] = '40'; // Provider-verified type code for Permendagri.
        }

        $response = Http::withHeaders([
            'Accept' => 'text/html',
            'User-Agent' => 'PlanningIntelligence-ParentResolver/0.1',
        ])->connectTimeout(3)->timeout(8)->withoutRedirecting()
            ->get('https://peraturan.bpk.go.id/Search', $params);

        $html = $response->body();
        if (!$response->successful()
            || !str_contains(strtolower((string) $response->header('Content-Type')), 'text/html')
            || strlen($html) > 5 * 1024 * 1024
            || !str_contains(strtoupper($html), 'PENCARIAN PERATURAN')
            || str_contains(strtoupper($html), 'PERATURAN TERBARU')) {
            throw new RuntimeException('Official provider did not confirm search mode.');
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
            throw new RuntimeException('Official search document cannot be parsed.');
        }

        $xpath = new DOMXPath($dom);
        $results = [];
        foreach ($xpath->query('//a[@href]') ?: [] as $anchor) {
            if (!$anchor instanceof DOMElement) {
                continue;
            }
            $path = $this->verifiedDetailsPath(trim($anchor->getAttribute('href')));
            if ($path === null) {
                continue;
            }
            $slug = strtolower(basename($path));
            $expected = match ($kind) {
                'UU' => ['uu-no-'.$number.'-tahun-'.$year, 'uuno-'.$number.'-tahun-'.$year],
                'PERPRES' => ['perpres-no-'.$number.'-tahun-'.$year],
                'PERMENDAGRI' => ['permendagri-no-'.$number.'-tahun-'.$year],
                default => [],
            };
            if (in_array($slug, $expected, true)) {
                $results['https://peraturan.bpk.go.id'.$path] = true;
            }
        }

        return [
            'url' => count($results) === 1 ? array_key_first($results) : null,
            'sha256' => strtoupper(hash('sha256', $html)),
        ];
    }

    private function verifiedDetailsPath(string $href): ?string
    {
        if (str_starts_with($href, '/Details/')) {
            $href = 'https://peraturan.bpk.go.id'.$href;
        }
        $parts = parse_url($href);
        if (!is_array($parts) || ($parts['scheme'] ?? null) !== 'https'
            || strtolower((string) ($parts['host'] ?? '')) !== 'peraturan.bpk.go.id'
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['port'])
            || isset($parts['query']) || isset($parts['fragment'])) {
            return null;
        }
        $path = (string) ($parts['path'] ?? '');
        return preg_match('~^/Details/[0-9]+/[a-zA-Z0-9-]+/?$~', $path) ? $path : null;
    }
}
