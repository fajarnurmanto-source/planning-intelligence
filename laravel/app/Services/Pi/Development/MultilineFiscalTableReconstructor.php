<?php
declare(strict_types=1);

namespace App\Services\Pi\Development;

use RuntimeException;

/**
 * Extends the R106 prefix/suffix and three-token method to every account
 * occurrence. No legal scope, OPD identity, column certification or admission.
 */
final class MultilineFiscalTableReconstructor
{
    public function reconstruct(string $text, int $expectedPages, bool $amendment): array
    {
        $pages=explode("\f",$text);
        if(array_pop($pages)!==''||count($pages)!==$expectedPages||$expectedPages<1
            ||strlen($text)>60_000_000||!preg_match('//u',$text)){
            throw new RuntimeException('PI_RDI_MULTILINE_BOUNDARY_HOLD');
        }
        $records=[];$counts=[];$scope=null;$scopePage=null;$previousPrinted=null;
        $account='/^\h*(\d(?:\.\d+){4}\.(?:\d{5}|\d{3}))(?!\d)/u';
        $boundary='/^\h*(?:Urusan(?:\h+Pemerintahan)?|Bidang\h+Urusan|Organisasi|Unit\h+Organisasi|Unit\h+Kerja|Perangkat\h+Daerah|SKPD|Program|Kegiatan|Sub\h*Kegiatan)\h*:/iu';
        $sub='/^\h*Sub\h*Kegiatan\h*:\h*(\d+(?:\.\d+){5})(?![\d.])/iu';
        $header='/^\h*(?:Kode\h+Rekening|Kode\h+|Halaman\b|LAMPIRAN\b|PENJELASAN\b|Jumlah\b|Total\b|PERATURAN\b)/iu';
        // A leading rupiah amount is not a hierarchical code. Require a
        // complete code token ending at horizontal whitespace or EOL.
        $otherCode='/^\h*\d(?:\.\d+){1,}(?=\h|$)/u';
        $org=null;$organizationPage=null;$organizationLine=null;
        foreach($pages as $i=>$page){
            $p=$i+1;$lines=preg_split('/\r\n|\n|\r/u',$page);
            $printed=null;
            if(preg_match('/\bHalaman\h+(\d+)\b/iu',$page,$m))$printed=(int)$m[1];
            // Carry is a separate weak candidate only, never a resolved scope.
            $carry=$printed!==null&&$previousPrinted!==null&&$printed===$previousPrinted+1&&$scopePage===$p-1?$scope:null;
            $carryOrigin=$carry!==null?$scopePage:null;
            $scope=null;$scopePage=null;$scopeLine=null;$org=null;$organizationPage=null;$organizationLine=null;
            $previousPrinted=$printed;
            $pageHash=strtoupper(hash('sha256',$page));
            for($n=0,$len=count($lines);$n<$len;$n++){
                $line=$lines[$n];
                if(preg_match($boundary,$line)){
                    $carry=null;$carryOrigin=null;$scope=null;$scopePage=null;$scopeLine=null;
                    if(preg_match('/^\h*(?:Organisasi|Unit\h+Organisasi|Unit\h+Kerja|Perangkat\h+Daerah|SKPD)\h*:\h*(\S.*)$/iu',$line,$o)){
                        $org=trim($o[1]);$organizationPage=$p;$organizationLine=$n+1;
                    }
                    if(preg_match($sub,$line,$s)){$scope=$s[1];$scopePage=$p;$scopeLine=$n+1;}
                    continue;
                }
                if(preg_match('/^\h*(?:LAMPIRAN|PENJELASAN|PERATURAN)\b/iu',$line)){
                    $scope=null;$scopePage=null;$scopeLine=null;$carry=null;$carryOrigin=null;$org=null;$organizationPage=null;$organizationLine=null;
                }
                if(!preg_match($account,$line,$m))continue;
                $prefix=$m[1];$full=strlen(substr(strrchr($prefix,'.'),1))===5;
                $chunk=[$line];$suffixes=[];$reasons=[];$end=$n;
                // Same-page, bounded continuation only. Do not consume the
                // next account, section total, header, or organization boundary.
                for($k=$n+1;$k<$len&&$k<=$n+8;$k++){
                    $next=$lines[$k];
                    if(preg_match($account,$next)||preg_match($boundary,$next)
                        ||preg_match($header,$next)||preg_match($otherCode,$next)||trim($next)==='')break;
                    $chunk[]=$next;$end=$k;
                    if(preg_match('/^\h*(\d{2})(?:\h|$)/u',$next,$s))$suffixes[]=['value'=>$s[1],'line'=>$k+1];
                }
                // Do not claim completion when the bounded scan may truncate.
                if($end===$n+8&&isset($lines[$end+1])&&trim($lines[$end+1])!==''
                    &&!preg_match($account,$lines[$end+1])&&!preg_match($boundary,$lines[$end+1])
                    &&!preg_match($header,$lines[$end+1])&&!preg_match($otherCode,$lines[$end+1])){
                    $reasons[]='CONTINUATION_LIMIT_HOLD';
                }
                $code=$full?$prefix:null;
                if(!$full){
                    if(count($suffixes)===1){$code=$prefix.$suffixes[0]['value'];}
                    // R106 fixture places the unique suffix AFTER the amount line.
                    // The bounded row chunk, not immediate adjacency, is the evidence.
                    else{$reasons[]='SPLIT_SUFFIX_UNRESOLVED';}
                }
                $money=[];$evidence=[];
                foreach($chunk as $offset=>$raw){
                    preg_match_all('/(?<![\d.])-?\d{1,3}(?:\.\d{3})*,\d{2}(?!\d)/u',$raw,$tokens);
                    foreach($tokens[0] as $token)$money[]=$token;
                    $evidence[]=['line'=>$n+$offset+1,'sha256'=>strtoupper(hash('sha256',$raw)),'text'=>$raw];
                }
                $values=[];
                if(count($money)!==($amendment?3:1))$reasons[]='COLUMN_TOKEN_COUNT_UNRESOLVED';
                else{
                    foreach($money as $token){
                        $digits=str_replace(['-','.',','],'',$token);
                        if(strlen($digits)>15){$reasons[]='AMOUNT_RANGE_HOLD';break;}
                        $values[]=(str_starts_with($token,'-')?-1:1)*(int)$digits;
                    }
                    if(count($values)===($amendment?3:1)){
                        if($values[0]<0||($amendment&&$values[1]<0))$reasons[]='NEGATIVE_ALLOCATION_HOLD';
                        if($amendment&&$values[1]-$values[0]!==$values[2])$reasons[]='POSITIONAL_DELTA_MISMATCH';
                    }
                }
                if($scope===null)$reasons[]='SAME_PAGE_SUBACTIVITY_UNRESOLVED';
                if($org===null)$reasons[]='ORGANIZATION_UNRESOLVED';
                $records[]=['page'=>$p,'line'=>$n+1,'page_text_sha256'=>$pageHash,'code_fragment'=>$prefix,
                    'account_code_candidate'=>$code,'suffix_candidates'=>$suffixes,
                    'subactivity_same_page'=>$scope,'subactivity_line'=>$scopeLine,
                    'previous_page_scope_candidate'=>$scope===null?$carry:null,'previous_page_scope_origin'=>$scope===null?$carryOrigin:null,
                    'organization_text_candidate'=>$org,'organization_page'=>$organizationPage,'organization_line'=>$organizationLine,
                    'money_tokens'=>$money,'positional_minor_units'=>$values,'evidence'=>$evidence,
                    'state'=>$reasons?'HOLD':'ROW_SHAPE_CANDIDATE','reasons'=>$reasons,
                    'column_semantics'=>'NOT_CERTIFIED'];
                foreach($reasons as $reason)$counts[$reason]=($counts[$reason]??0)+1;
            }
        }
        $n=count($records);
        return ['schema'=>'PI_RDI_MULTILINE_FISCAL_V2','pages'=>$expectedPages,'fragment_count'=>$n,
            'resolved_code_candidates'=>count(array_filter($records,static fn($r)=>$r['account_code_candidate']!==null)),
            'positional_amount_candidates'=>count(array_filter($records,static fn($r)=>count($r['positional_minor_units'])===($amendment?3:1)&&!in_array('POSITIONAL_DELTA_MISMATCH',$r['reasons'],true)&&!in_array('NEGATIVE_ALLOCATION_HOLD',$r['reasons'],true)&&!in_array('CONTINUATION_LIMIT_HOLD',$r['reasons'],true))),
            'row_shape_candidates'=>count(array_filter($records,static fn($r)=>$r['state']==='ROW_SHAPE_CANDIDATE')),
            'previous_page_scope_candidates'=>count(array_filter($records,static fn($r)=>$r['previous_page_scope_candidate']!==null)),
            'hold_reason_counts'=>(object)$counts,'records'=>$records,
            'full_record_reconstruction'=>'OPEN','cross_version_comparison'=>'BLOCKED',
            'numeric_admission'=>0,'production_admission'=>'DENIED'];
    }
}
