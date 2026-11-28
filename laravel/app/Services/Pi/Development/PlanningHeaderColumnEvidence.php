<?php
declare(strict_types=1);
namespace App\Services\Pi\Development;
use RuntimeException;

/** Conservative spatial candidates from immutable adaptive receipts, never numeric admission. */
final class PlanningHeaderColumnEvidence
{
    private function matches(string $pattern,string $text): array
    {
        preg_match_all($pattern,$text,$m,PREG_OFFSET_CAPTURE);
        return array_map(static fn($v)=>['text'=>$v[0],'start'=>mb_strlen(substr($text,0,$v[1]),'UTF-8'),'end'=>mb_strlen(substr($text,0,$v[1]),'UTF-8')+mb_strlen($v[0],'UTF-8')],$m[0]);
    }
    private function checked(array $line): string
    {
        if(($line['truncated']??true)!==false||strtoupper(hash('sha256',$line['text']))!==$line['sha256'])throw new RuntimeException('PI_RDI03C_LINE_HOLD');
        return $line['text'];
    }
    public function map(array $row): array
    {
        $years=[];$roles=[];$yearLine=null;$roleLine=null;$ambiguous=false;
        foreach($row['header_signals'] as $h){
            if($h['line']>=$row['line'])throw new RuntimeException('PI_RDI03C_HEADER_LOCATOR_HOLD');
            if($h['truncated'])continue;
            $text=$this->checked($h);
            $y=$this->matches('/(?<!\d)20(?:2[4-9]|30)(?!\d)/u',$text);
            $r=$this->matches('/\b(?:TARGET|PAGU)(?:\h+INDIKATIF(?:\h*\(Rp\))?)?\b/iu',$text);
            if(count($y)>=2){
                if($years&&array_column($years,'text')!==array_column($y,'text'))$ambiguous=true;
                $years=$y;$yearLine=$h['line'];
            }
            if(count($r)>=2){
                // Repeated, spatially separated labels only, not narrative captions.
                $spread=$r[count($r)-1]['start']-$r[0]['start'];
                if($spread>=40){$roles=$r;$roleLine=$h['line'];}
            }
        }
        $columns=[];$status='HEADER_HOLD';
        if(!$ambiguous&&$years&&$roles&&$yearLine<$roleLine&&count($roles)===2*count($years)){
            $ok=true;
            foreach($years as $i=>$y){
                $a=$roles[2*$i];$b=$roles[2*$i+1];
                if(strtoupper($a['text'])!=='TARGET'||!str_starts_with(strtoupper($b['text']),'PAGU')||$y['start']<$a['start']-15||$y['start']>$b['end']+15){$ok=false;break;}
                foreach([$a,$b] as $j=>$v)$columns[]=['year'=>(int)$y['text'],'role'=>$j?'PAGU':'TARGET','start'=>$v['start'],'end'=>$v['end']];
            }
            if($ok&&count(array_unique(array_column($years,'text')))===count($years))$status='PAIRED_YEAR_ROLE_CANDIDATE';
            else $columns=[];
        }
        $window=[];foreach($row['window'] as $w)$window[$w['line']]=$w;
        $mapped=[];$groups=[];$counts=[];
        foreach($row['token_rows'] as $tr){
            $w=$window[$tr['line']]??null;if(!$w)throw new RuntimeException('PI_RDI03C_TOKEN_LOCATOR_HOLD');
            if($w['truncated']){ $counts['TRUNCATED_LINE_HOLD']=($counts['TRUNCATED_LINE_HOLD']??0)+count($tr['tokens']);continue; }
            $raw=$this->checked($w);$shift=0;
            if($tr['offset_basis']==='AFTER_CODE'){
                if(!preg_match('/^\h*(?:\d{1,3}\.\h+)?'.preg_quote($row['code'],'/').'(?=\h|$)/u',$raw,$m))throw new RuntimeException('PI_RDI03C_CODE_HOLD');
                $shift=mb_strlen($m[0],'UTF-8');
            }elseif($tr['offset_basis']!=='FULL_LINE')throw new RuntimeException('PI_RDI03C_OFFSET_HOLD');
            $group=[];
            foreach($tr['tokens'] as $t){
                $x=$shift+$t['offset'];$len=mb_strlen($t['raw'],'UTF-8');
                if(mb_substr($raw,$x,$len,'UTF-8')!==$t['raw'])throw new RuntimeException('PI_RDI03C_TOKEN_DRIFT:page='.$row['page'].':anchor='.$row['line'].':line='.$tr['line'].':basis='.$tr['offset_basis'].':offset='.$x);
                $end=$x+$len;$hits=[];
                foreach($columns as $c){
                    // Right-aligned table cells: only near header right edge, no nearest-column guess.
                    if(abs($end-$c['end'])<=4)$hits[]=$c;
                }
                $hit=count($hits)===1?$hits[0]:null;
                $state=$hit?'COLUMN_POSITION_CANDIDATE':'COLUMN_HOLD';
                $counts[$state]=($counts[$state]??0)+1;
                $mapped[]=['line'=>$tr['line'],'raw'=>$t['raw'],'start'=>$x,'end'=>$end,'column'=>$hit,'state'=>$state,'numeric_value'=>null];
                $group[]=['raw'=>$t['raw'],'start'=>$x,'end'=>$end];
            }
            if(count($group)>=2){$key=hash('sha256',json_encode($group,JSON_THROW_ON_ERROR));$groups[$key][]=$tr['line'];}
        }
        $repeat=array_values(array_filter($groups,static fn($v)=>count($v)>1));
        return ['page'=>$row['page'],'line'=>$row['line'],'code'=>$row['code'],'page_sha256'=>$row['page_sha256'],'header_status'=>$status,'year_header_line'=>$yearLine,'role_header_line'=>$roleLine,'columns'=>$columns,'tokens'=>$mapped,'token_counts'=>$counts,'identical_positional_groups'=>$repeat,'boundary'=>$row['boundary'],'continuation_state'=>$row['boundary']==='PAGE_END'?'CROSS_PAGE_HOLD':($row['boundary']==='LIMIT_HOLD'?'LIMIT_HOLD':'NOT_ASSESSED'),'agency_identity'=>'UNRESOLVED','column_semantics'=>'NOT_CERTIFIED','numeric_comparison'=>'BLOCKED'];
    }
    public function build(array $doc): array
    {
        if(($doc['schema']??null)!=='PI_RDI03B_ADAPTIVE_TABLE_EVIDENCE_V1'||($doc['numeric_admission']??null)!==0||($doc['production_admission']??null)!=='DENIED'||count($doc['records'])!==$doc['occurrences'])throw new RuntimeException('PI_RDI03C_RECEIPT_HOLD');
        $records=[];$counts=[];$tokens=[];$samples=[];$seen=[];
        foreach($doc['records'] as $row){
            $key=$row['page'].':'.$row['line'];if(isset($seen[$key]))throw new RuntimeException('PI_RDI03C_DUPLICATE_HOLD');$seen[$key]=true;
            $r=$this->map($row);$records[]=$r;
            foreach([$r['header_status'],$r['continuation_state']] as $k)$counts[$k]=($counts[$k]??0)+1;
            if($r['identical_positional_groups'])$counts['IDENTICAL_GROUP_CANDIDATE']=($counts['IDENTICAL_GROUP_CANDIDATE']??0)+1;
            foreach($r['token_counts'] as $k=>$v)$tokens[$k]=($tokens[$k]??0)+$v;
            if(!isset($samples[$r['header_status']]))$samples[$r['header_status']]=$r;
        }
        return ['schema'=>'PI_RDI03C_HEADER_COLUMN_EVIDENCE_V1','source'=>$doc['source'],'source_sha256'=>$doc['source_sha256'],'occurrences'=>count($records),'counts'=>$counts,'token_counts'=>$tokens,'samples'=>$samples,'records'=>$records,'numeric_admission'=>0,'numeric_comparison'=>'BLOCKED','production_admission'=>'DENIED'];
    }
}
