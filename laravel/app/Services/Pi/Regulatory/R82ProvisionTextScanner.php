<?php

namespace App\Services\Pi\Regulatory;

use RuntimeException;

/**
 * Text-layer extraction creates review candidates, NOT certified quotations.
 * A matching regex in a text layer cannot establish legal effect or identity.
 */
final class R82ProvisionTextScanner
{
    /**
     * @param list<array{article:int,paragraphs:list<int>}> $candidates
     * @return array{records:list<array<string,mixed>>,extracted:int,unresolved:int}
     */
    public function scan(string $ppText, string $uuText, array $candidates): array
    {
        if (strlen($ppText) > 3_000_000 || strlen($uuText) > 3_000_000
            || ! mb_check_encoding($ppText, 'UTF-8')
            || ! mb_check_encoding($uuText, 'UTF-8')
            || count($candidates) < 1 || count($candidates) > 100) {
            throw new RuntimeException('R82_TEXT_INPUT_CONTRACT_INVALID');
        }

        // Never attribute mentions from explanatory sections to operative law.
        // Preserve form-feed page numbers while turning PDF page transitions
        // into line boundaries for anchored Pasal headings.
        $body = str_replace("\f", "\n\f\n", $uuText);
        if (preg_match('/(?m)^\h*P\s*E\s*N\s*J\s*E\s*L\s*A\s*S\s*A\s*N(?:\h*$|\h+ATAS\b)/iu', $body, $cut, PREG_OFFSET_CAPTURE)) {
            $body = substr($body, 0, $cut[0][1]);
        }
        $heads = [];
        preg_match_all('/(?m)^\h*Pasal\h+([0-9]{1,3})\h*$/iu', $body, $matches, PREG_OFFSET_CAPTURE);
        foreach ($matches[1] as $i => $number) {
            $article = (int) $number[0];
            $heads[$article][] = $matches[0][$i][1];
        }
        $allHeads = [];
        foreach ($heads as $positions) {
            foreach ($positions as $pos) {
                $allHeads[] = $pos;
            }
        }
        sort($allHeads, SORT_NUMERIC);

        $preamble = '';
        $preambleOffset = null;
        if (preg_match('/\bMenimbang\s*:\s*(.*?)\bMengingat\s*:/isu', $ppText, $found, PREG_OFFSET_CAPTURE)) {
            $preamble = $found[1][0];
            $preambleOffset = $found[1][1];
        }

        $result = [];
        $extracted = 0;
        $seen = [];
        foreach ($candidates as $candidate) {
            $article = $candidate['article'] ?? null;
            $paragraphs = $candidate['paragraphs'] ?? null;
            if (! is_int($article) || $article < 1 || $article > 999
                || ! is_array($paragraphs) || ! array_is_list($paragraphs)
                || count($paragraphs) > 20 || isset($seen[$article])) {
                throw new RuntimeException('R82_ARTICLE_CANDIDATE_INVALID');
            }
            $seen[$article] = true;

            $ref = null;
            if ($preamble !== '' &&
                preg_match('/\bPasal\s+'.preg_quote((string)$article, '/').'\b(?:(?!\bPasal\b)[\s\S]){0,160}/iu', $preamble, $m, PREG_OFFSET_CAPTURE)) {
                $candidateText = trim(preg_replace('/\s+/u', ' ', $m[0][0]) ?? '');
                $complete = true;
                foreach ($paragraphs as $para) {
                    if (! is_int($para) || $para < 1 || $para > 99
                        || ! preg_match('/(?:ayat\s*)?\(\s*'.preg_quote((string)$para, '/').'\s*\)/iu', $candidateText)) {
                        $complete = false;
                    }
                }
                if ($complete) {
                    $ref = [
                        'pdf_page' => $this->page($ppText, ($preambleOffset ?? 0) + $m[0][1]),
                        'excerpt' => $candidateText,
                        'excerpt_sha256' => strtoupper(hash('sha256', $candidateText)),
                        'evidence_state' => 'EXTRACTED_PP_PREAMBLE_REFERENCE_FOR_REVIEW',
                    ];
                }
            }

            $passages = [];
            $positionList = $heads[$article] ?? [];
            if (count($positionList) === 1) {
                $start = $positionList[0];
                $end = strlen($body);
                foreach ($allHeads as $position) {
                    if ($position > $start) {
                        $end = $position;
                        break;
                    }
                }
                $segment = substr($body, $start, $end - $start);
                if (is_string($segment) && $segment !== '') {
                    if ($paragraphs === []) {
                        $passages[] = $this->snippet($body, $segment, $start, 'PASAL', null);
                    } else {
                        $paragraphMatches = [];
                        preg_match_all('/(?m)^\h*\(\s*([0-9]{1,2})\s*\)\h*/u', $segment, $pars, PREG_OFFSET_CAPTURE);
                        foreach ($pars[1] as $i => $match) {
                            $paragraphMatches[(int)$match[0]][] = $pars[0][$i][1];
                        }
                        $allPositions = [];
                        foreach ($paragraphMatches as $positions) {
                            foreach ($positions as $pos) {
                                $allPositions[] = $pos;
                            }
                        }
                        sort($allPositions, SORT_NUMERIC);
                        foreach ($paragraphs as $para) {
                            if (! is_int($para) || $para < 1 || $para > 99) {
                                throw new RuntimeException('R82_PARAGRAPH_CANDIDATE_INVALID');
                            }
                            // Duplicate paragraph markers are ambiguous, not success.
                            if (count($paragraphMatches[$para] ?? []) !== 1) {
                                continue;
                            }
                            $rel = $paragraphMatches[$para][0];
                            $next = strlen($segment);
                            foreach ($allPositions as $pos) {
                                if ($pos > $rel) {
                                    $next = $pos;
                                    break;
                                }
                            }
                            $passages[] = $this->snippet($body, substr($segment, $rel, $next - $rel),
                                $start + $rel, 'AYAT', $para);
                        }
                    }
                }
            }
            $complete = $ref !== null && count($passages) === max(1, count($paragraphs));
            if ($complete) {
                $extracted++;
            }
            $result[] = [
                'target_provision_id' => 'UU-18-2008:PASAL-'.$article,
                'article' => $article,
                'paragraphs_requested' => $paragraphs,
                'pp_reference' => $ref,
                'uu_passages' => $passages,
                'article_heading_count' => count($positionList),
                'evidence_status' => $complete
                    ? 'TEXT_LAYER_CANDIDATE_REVIEW_REQUIRED'
                    : 'ABSTAIN_INCOMPLETE_OR_AMBIGUOUS_TEXT',
                'relation_admission' => 'NOT_PERFORMED',
                'legal_effect' => 'NOT_ASSESSED',
                'review_status' => 'REVIEW_REQUIRED',
            ];
        }

        return ['records' => $result, 'extracted' => $extracted,
            'unresolved' => count($result) - $extracted];
    }

    /** @return array<string,mixed> */
    private function snippet(string $wholeText, string $span, int $offset, string $kind, ?int $paragraph): array
    {
        $normalized = trim(preg_replace('/\s+/u', ' ', $span) ?? '');
        $excerpt = mb_substr($normalized, 0, 700, 'UTF-8');
        return [
            'kind' => $kind,
            'paragraph' => $paragraph,
            'pdf_page' => $this->page($wholeText, $offset),
            'excerpt' => $excerpt,
            'excerpt_sha256' => strtoupper(hash('sha256', $excerpt)),
            'truncated' => mb_strlen($normalized, 'UTF-8') > 700,
            'evidence_state' => 'EXTRACTED_UU_OPERATIVE_TEXT_FOR_REVIEW',
        ];
    }

    private function page(string $wholeText, int $offset): int
    {
        return substr_count(substr($wholeText, 0, $offset), "\f") + 1;
    }
}
