<?php

namespace App\Services\PlanningIntelligence;

/**
 * Deterministically selects short, previously hash-verified excerpts from
 * the canonical regulatory projection. This service never reads PDFs,
 * resolves external URLs, or calls an AI provider.
 */
final class PublicRegulatoryExcerptSelector
{
    /** @return list<array<string, mixed>> */
    public function select(array $projection, string $focus): array
    {
        $keywords = $this->keywords($focus);
        $selected = [];

        foreach ($projection['documents'] ?? [] as $document) {
            $candidates = [];
            $seen = [];
            foreach ($document['scope_targets'] ?? [] as $target) {
                foreach ($target['evidence'] ?? [] as $evidence) {
                    $this->add($candidates, $seen, $document, $evidence,
                        (string) ($target['target'] ?? 'LAMPIRAN'), $keywords, 2);
                }
            }
            foreach ($document['source_evidence'] ?? [] as $evidence) {
                $this->add($candidates, $seen, $document, $evidence,
                    'SUMBER', $keywords, 0);
            }

            usort($candidates, static function (array $a, array $b): int {
                return ($b['_score'] <=> $a['_score']) ?: ($a['_order'] <=> $b['_order']);
            });
            foreach (array_slice($candidates, 0, 3) as $candidate) {
                unset($candidate['_score'], $candidate['_order']);
                $selected[] = $candidate;
            }
        }

        return array_slice($selected, 0, 21);
    }

    /** @param list<array<string, mixed>> $excerpts */
    public function fingerprint(array $excerpts): string
    {
        return strtoupper(hash('sha256',
            json_encode($excerpts, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)));
    }

    /** @param array<int, array<string, mixed>> $candidates
     *  @param array<string, bool> $seen
     *  @param list<string> $keywords
     */
    private function add(array &$candidates, array &$seen, array $document,
        array $evidence, string $section, array $keywords, int $priority): void
    {
        $text = $evidence['excerpt'] ?? null;
        $hash = $evidence['excerpt_sha256'] ?? null;
        $page = $evidence['pdf_page'] ?? null;
        if (! is_string($text) || $text === '' || ! is_string($hash)
            || ! is_int($page) || ! is_string($document['id'] ?? null)) {
            return;
        }
        $id = $document['id'].':'.$page.':'.$hash;
        if (isset($seen[$id])) {
            return;
        }
        $seen[$id] = true;

        $normalized = preg_replace('/\s+/u', ' ', trim($text));
        if (! is_string($normalized) || $normalized === '') {
            return;
        }
        if (preg_match('/\A(.{1,320})/us', $normalized, $matches) !== 1) {
            return;
        }
        $snippet = $matches[1];
        $haystack = strtolower($normalized.' '.($document['title'] ?? '').' '.$section);
        $score = $priority;
        foreach ($keywords as $keyword) {
            if (str_contains($haystack, $keyword)) {
                $score += 2;
            }
        }
        $candidates[] = [
            'source_id' => $document['id'],
            'pdf_page' => $page,
            'excerpt_sha256' => $hash,
            'source_section' => $section,
            'excerpt_text' => $snippet,
            'excerpt_truncated' => strlen($snippet) < strlen($normalized),
            'review_status' => 'REVIEW_REQUIRED',
            '_score' => $score,
            '_order' => count($candidates),
        ];
    }

    /** @return list<string> */
    private function keywords(string $focus): array
    {
        $terms = preg_split('/[^\p{L}\p{N}]+/u', strtolower($focus)) ?: [];
        $stop = ['yang', 'dan', 'untuk', 'dari', 'pada', 'dalam', 'apa', 'apakah',
            'harus', 'perlu', 'reviewer', 'tentang', 'terkait', 'dengan', 'regulasi',
            'mengenai', 'oleh', 'atau', 'ini'];

        return array_slice(array_values(array_unique(array_filter($terms,
            static fn (string $term): bool => strlen($term) >= 4
                && ! in_array($term, $stop, true)))), 0, 12);
    }
}
