<?php
declare(strict_types=1);
namespace App\Services\Pi\Regulatory;

use RuntimeException;

/** Bounded actual-text controls; no assertion about complete legal ancestry. */
final class HriaNegativeControls
{
    public function evaluate(string $pp,string $uu): array
    {
        $scanner=new R82ProvisionTextScanner;
        $cases=[['article'=>12,'paragraphs'=>[2]],['article'=>21,'paragraphs'=>[2]]];
        $scan=$scanner->scan($pp,$uu,$cases);
        $rows=[];
        foreach($scan['records'] as $r){
            $p=$r['uu_passages'][0]??null;
            $valid=count($r['uu_passages'])===1&&is_array($p)&&!($p['truncated']??true)
                &&strtoupper(hash('sha256',$p['excerpt']))===$p['excerpt_sha256'];
            $mandate=$valid?(new HriaDelegationBenchmark)->classify($p['excerpt']):'INSUFFICIENT';
            // Search the entire normalized Menimbang, not only the 160-char scanner window.
            $hasPreamble=preg_match('/\bMenimbang\s*:\s*(.*?)\bMengingat\s*:/isu',$pp,$m)===1;
            $cited=$hasPreamble?preg_match('/\bPasal\s+'.$r['article'].'\b/iu',$m[1])===1:null;
            $expected=$r['article']===12?'PERDA_ONLY':'PP';
            $rows[]=['article'=>$r['article'],'expected_mandate'=>$expected,'observed_mandate'=>$mandate,
                'preamble_cited'=>$cited,'pdf_page'=>$valid?$p['pdf_page']:null,
                'excerpt_sha256'=>$valid?$p['excerpt_sha256']:null,
                'match'=>$valid&&$mandate===$expected&&$cited===false];
        }
        return ['rows'=>$rows,'state'=>count($rows)===2&&count(array_filter($rows,fn($r)=>$r['match']))===2?'PASS_TWO_NEGATIVE_CONTROLS':'HOLD','legal_effect'=>'NOT_ASSESSED'];
    }
}
