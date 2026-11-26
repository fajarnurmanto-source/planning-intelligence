<?php
declare(strict_types=1);
namespace App\Services\Pi\Regulatory;

/** Compare observed mandate language with separately maintained reference cases.
 * No legal conclusion, no semantic inference and no extrapolation to other instruments.
 */
final class HriaDelegationBenchmark
{
    public function classify(string $text): string
    {
        if(!preg_match('/\bdiatur\s+dengan\s+peraturan\s+pemerintah(?:\s+(dan\/atau|dan)\s+peraturan\s+daerah)?\b/iu',$text,$m)){
            return preg_match('/\bdiatur\s+dengan\s+peraturan\s+daerah\b/iu',$text)?'PERDA_ONLY':'INSUFFICIENT';
        }
        return match($m[1]??''){'dan'=>'PP_AND_PERDA','dan/atau'=>'PP_AND_OR_PERDA',default=>'PP'};
    }
    public function evaluate(array $pipeline,array $reference): array
    {
        if(($pipeline['schema']??null)!=='PI_R82_NATIONAL_PASSAGE_CANDIDATES_QA_V1'||($pipeline['source_snapshot_integrity']??null)!=='R81_TWO_INDEPENDENT_SHA256_PINS_MATCH'||($reference['schema']??null)!=='PI_HRIA_DELEGATION_REFERENCE_V1')throw new \RuntimeException('HRIA_BENCH_INPUT_HOLD');
        $records=array_column($pipeline['candidates'],null,'article');
        $rows=[];
        foreach($reference['cases'] as $case){
            $a=$case['article'];$r=$records[$a]??null;$actual='INSUFFICIENT';
            if(($r['evidence_status']??null)==='TEXT_LAYER_CANDIDATE_REVIEW_REQUIRED'&&($r['paragraphs_requested']??null)===$case['paragraphs']){
                $parts=[];
                foreach($r['uu_passages'] as $p){
                    if(($p['truncated']??true)||strtoupper(hash('sha256',$p['excerpt']))!==$p['excerpt_sha256']){$parts=[];break;}
                    $parts[]=$this->classify($p['excerpt']);
                }
                if(count($parts)===1)$actual=$parts[0];
                elseif($parts===['PP','PP_AND_OR_PERDA'])$actual='MIXED_PP_AND_PP_AND_OR_PERDA';
            }
            $rows[]=['article'=>$a,'expected'=>$case['expected'],'observed'=>$actual,'match'=>$actual===$case['expected']];
        }
        $matched=count(array_filter($rows,static fn($r)=>$r['match']));
        return ['schema'=>'PI_HRIA_DELEGATION_BENCHMARK_V1','rows'=>$rows,'matched'=>$matched,'expected'=>count($rows),'state'=>$matched===7&&count($rows)===7?'PASS_POSITIVE_MANDATE_CANDIDATES_ONLY':'PARTIAL_HOLD','negative_controls'=>'NOT_RUN_ON_LOCAL_PDF','live_provider'=>'NOT_RUN','legal_effect'=>'NOT_ASSESSED','production_admission'=>'DENIED'];
    }
}
