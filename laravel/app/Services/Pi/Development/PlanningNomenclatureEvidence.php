<?php
declare(strict_types=1);
namespace App\Services\Pi\Development;
use RuntimeException;

/** Observed code occurrences and column fragments, never infer agency or target meaning. */
final class PlanningNomenclatureEvidence
{
    public function scan(string $text,int $first,int $last,array $baseline): array
    {
        $pages=explode("\f",$text);
        if(array_pop($pages)!==''||count($pages)!==$last-$first+1||$first<1||!preg_match('//u',$text)||strlen($text)>20_000_000)throw new RuntimeException('PI_RDI02C_BATCH_HOLD');
        $records=[];$counts=[];$samples=[];$noText=[];$headings=[];
        $code='/^\h*(?:\d{1,3}\.\h+)?(\d\.\d{2}\.\d{2}(?:\.\d\.\d{2}(?:\.\d{4})?)?)(?=\h|$)/u';
        foreach($pages as $i=>$page){
            $p=$first+$i;$hash=strtoupper(hash('sha256',$page));
            if(($baseline[$p]??null)!==$hash)throw new RuntimeException('PI_RDI02C_PAGE_HASH_HOLD:'.$p);
            $lines=preg_split('/\r\n|\n|\r/u',$page);
            if(trim($page)===''){$noText[]=$p;continue;}
            $toc=false;
            foreach($lines as $line)if(preg_match('/^\h*DAFTAR\h+(?:ISI|TABEL|GAMBAR)\h*$/iu',$line))$toc=true;
            if($p<=3)foreach(array_slice($lines,0,45,true) as $n=>$line)if(trim($line)!=='')$headings[]=['page'=>$p,'line'=>$n+1,'text'=>mb_substr($line,0,240,'UTF-8'),'sha256'=>strtoupper(hash('sha256',$line))];
            foreach($lines as $n=>$line){
                if(!preg_match($code,$line,$m))continue;
                $level=match(substr_count($m[1],'.')){2=>'PROGRAM',4=>'ACTIVITY',5=>'SUBACTIVITY',default=>'UNKNOWN'};
                $state=$toc||preg_match('/\.{4,}\h*[\w-]+\h*$/u',$line)?'CONTENTS_HOLD':'CODE_ROW_CANDIDATE';
                $tail=trim(substr($line,strlen($m[0])));
                $cells=preg_split('/\h{2,}/u',$tail,-1,PREG_SPLIT_NO_EMPTY);
                $window=[];
                for($k=$n;$k<min(count($lines),$n+3);$k++){
                    if($k>$n&&(trim($lines[$k])===''||preg_match($code,$lines[$k])))break;
                    $window[]=['line'=>$k+1,'sha256'=>strtoupper(hash('sha256',$lines[$k])),'text'=>mb_substr($lines[$k],0,600,'UTF-8')];
                }
                $record=['page'=>$p,'line'=>$n+1,'page_sha256'=>$hash,'code'=>$m[1],'level'=>$level,'state'=>$state,'raw_cells'=>array_map(static fn($s)=>mb_substr($s,0,400,'UTF-8'),$cells),'window'=>$window,'agency'=>'UNRESOLVED','column_semantics'=>'NOT_CERTIFIED'];
                $records[]=$record;$key=$level.'|'.$state;$counts[$key]=($counts[$key]??0)+1;
                if(!isset($samples[$key]))$samples[$key]=$record;
            }
        }
        return ['records'=>$records,'counts'=>$counts,'samples'=>$samples,'no_text_pages'=>$noText,'identity_heading_evidence'=>$headings,'numeric_admission'=>0,'production_admission'=>'DENIED'];
    }
}
