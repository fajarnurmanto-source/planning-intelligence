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

        // Native Windows Poppler emits CRLF; normalize line endings before
        // anchored headings. Form-feed boundaries and physical pages remain intact.
        $ppText = str_replace(["\r\n", "\r"], "\n", $ppText);
        $uuText = str_replace(["\r\n", "\r"], "\n", $uuText);
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

        // Preserve the narrow original reference and optionally the complete
        // bounded single-page Menimbang text. Never join pages silently.
        $preambleContext = null;
        if ($preambleOffset !== null && $preamble !== '') {
            $normalized = trim(preg_replace('/\s+/u', ' ', $preamble) ?? '');
            $first = $this->page($ppText, $preambleOffset);
            $last = $this->page($ppText, $preambleOffset + strlen(rtrim($preamble)) - 1);
            if ($first === $last && $normalized !== '' && mb_strlen($normalized) <= 1600) {
                $preambleContext = [
                    'pdf_page' => $first,
                    'excerpt' => $normalized,
                    'excerpt_sha256' => strtoupper(hash('sha256', $normalized)),
                    'truncated' => false,
                    'evidence_state' => 'EXTRACTED_PP_PREAMBLE_CONTEXT_FOR_REVIEW',
                ];
            }
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
            $segment = '';
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
            $retrievalContext = $segment ?? '';
            // Resolve only bounded, uniquely headed local cross-references named
            // in this article, without recursive expansion or reference mappings.
            preg_match_all('/\bPasal\h+([0-9]{1,3})\b/iu', $retrievalContext, $localRefs);
            foreach (array_slice(array_unique($localRefs[1]), 0, 4) as $localArticle) {
                if ((int)$localArticle === $article || count($heads[(int)$localArticle] ?? []) !== 1) continue;
                $localStart = $heads[(int)$localArticle][0]; $localEnd = strlen($body);
                foreach ($allHeads as $localPosition) { if ($localPosition > $localStart) { $localEnd = $localPosition; break; } }
                if ($localEnd - $localStart <= 4000 && strlen($retrievalContext) + $localEnd - $localStart <= 12000) {
                    $retrievalContext .= "\n".substr($body, $localStart, $localEnd - $localStart);
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
                'pp_preamble_context' => $ref !== null && ($preambleContext['pdf_page'] ?? null) === $ref['pdf_page'] ? $preambleContext : null,
                'uu_passages' => $passages,
                'pp_body_candidates' => $complete ? $this->retrieveBodyCandidates($ppText,$passages,$retrievalContext) : [],
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

    /**
     * Bounded lexical retrieval, not a delegation proof. Only complete,
     * uniquely headed single-page PP article spans before explanation/annex.
     * Exact shared non-boilerplate word pairs rank candidates for review.
     */
    private function retrieveBodyCandidates(string $text,array $passages,string $articleContext = ''): array
    {
        $body=str_replace("\f","\n\f\n",$text);
        if(!preg_match('/(?m)^\h*MEMUTUSKAN\h*:/iu',$body,$start,PREG_OFFSET_CAPTURE))return [];
        $offset=$start[0][1];
        if(preg_match('/(?m)^\h*(?:P\h*E\h*N\h*J\h*E\h*L\h*A\h*S\h*A\h*N(?:\h+ATAS)?|LAMPIRAN(?:\h+[IVXLCDM\d]+)?|DAFTAR\h+ISI)\h*$/iu',$body,$cut,PREG_OFFSET_CAPTURE,$offset)){
            $body=substr($body,0,$cut[0][1]);
        }
        preg_match_all('/(?m)^\h*Pasal\h+([0-9]{1,3})\h*$/iu',$body,$head,PREG_OFFSET_CAPTURE,$offset);
        if(count($head[0])>500)return [];
        $frequency=array_count_values(array_column($head[1],0));
        $query=[];
        foreach($passages as $p){if(!($p['truncated']??true))$query+=$this->wordPairs($p['excerpt']);}
        // Delegating paragraphs often only say "ayat (1)". Use the same uniquely
        // bounded operative UU article as retrieval context, never as a new proof.
        if(strlen($articleContext)<=12000)$query+=$this->wordPairs($articleContext);
        unset($query['pengelolaan sampah'],$query['penanganan sampah'],$query['tata cara'],$query['rumah tangga'],$query['sampah sejenis'],$query['sejenis sampah']);
        if($query===[])return [];
        $ranked=[];
        for($i=0;$i+1<count($head[0]);$i++){
            [$heading,$from]=$head[0][$i];$number=$head[1][$i][0];
            if($frequency[$number]!==1)continue;
            $span=rtrim(substr($body,$from,$head[0][$i+1][1]-$from),"\r\n\t \f");
            $units=[['text'=>$span,'offset'=>$from,'paragraph'=>null]];
            // Large/cross-page articles may still contain complete single-page
            // paragraphs. Keep their real locator rather than silently clipping.
            if(str_contains($span,"\f")||mb_strlen(preg_replace('/\s+/u',' ',$span)??'')>700){
                $units=[];
                preg_match_all('/(?m)^\h*\(\h*([0-9]{1,2})\h*\)\h*/u',$span,$pars,PREG_OFFSET_CAPTURE);
                $pf=array_count_values(array_column($pars[1],0));
                foreach($pars[0] as $k=>$marker){
                    if($pf[$pars[1][$k][0]]!==1)continue;
                    $end=$pars[0][$k+1][1]??strlen($span);
                    $units[]=['text'=>rtrim(substr($span,$marker[1],$end-$marker[1]),"\r\n\t \f"),'offset'=>$from+$marker[1],'paragraph'=>(int)$pars[1][$k][0]];
                }
            }
            foreach($units as $unit){
                if($unit['text']===''||str_contains($unit['text'],"\f"))continue;
                $excerpt=trim(preg_replace('/\s+/u',' ',$unit['text'])??'');
                if(mb_strlen($excerpt)<30||mb_strlen($excerpt)>1200)continue;
                $shared=array_keys(array_intersect_key($this->wordPairs($excerpt),$query));
                if(count($shared)<2)continue;
                sort($shared,SORT_STRING);
                $ranked[]=['article'=>(int)$number,'paragraph'=>$unit['paragraph'],'pdf_page'=>$this->page($body,$unit['offset']),'excerpt'=>$excerpt,
                    'excerpt_sha256'=>strtoupper(hash('sha256',$excerpt)),'matched_pairs'=>array_slice($shared,0,8),
                    'match_count'=>count($shared),'truncated'=>false,'evidence_state'=>'PP_BODY_LEXICAL_CANDIDATE_FOR_REVIEW'];
            }
        }
        usort($ranked,static fn($a,$b)=>($b['match_count']<=>$a['match_count'])?:($a['article']<=>$b['article'])?:(($a['paragraph']??0)<=>($b['paragraph']??0)));
        $selected=[];$seen=[];
        foreach($ranked as $candidate){
            if(isset($seen[$candidate['article']]))continue;
            $seen[$candidate['article']]=true;$selected[]=$candidate;
            if(count($selected)===2)break;
        }
        return $selected;
    }
    private function wordPairs(string $text):array
    {
        preg_match_all('/\p{L}{4,}/u',mb_strtolower($text),$m);
        $stop=array_flip(['pasal','ayat','yang','dengan','dalam','untuk','dari','atau','pada','sebagaimana','dimaksud','ketentuan','lebih','lanjut','diatur','peraturan','pemerintah','undang','tahun','nomor','tersebut','tentang','dapat','harus','serta','oleh']);
        $pairs=[];$previous=null;
        foreach($m[0] as $word){
            if(isset($stop[$word])){$previous=null;continue;}
            if($previous!==null)$pairs[$previous.' '.$word]=true;
            $previous=$word;
        }
        return $pairs;
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
