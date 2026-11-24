<?php
declare(strict_types=1);

namespace App\Services\Pi\Development;

use RuntimeException;

/** RDI receipt-bound fiscal candidate reconstruction. No inferred suffix,
 * cross-page ownership, OPD identity, column certification or fiscal admission.
 */
final class FiscalRecordReconstruction
{
    public function reconstruct(array $document, bool $amendment): array
    {
        $layout=$document['layout_candidates']??[];
        $sha=$document['source_sha256']??'';
        if (!preg_match('/\A[A-F0-9]{64}\z/D',$sha)
            || ($layout['schema']??null)!=='PI_RDI_LAYOUT_CANDIDATES_V1'
            || ($layout['numeric_admission']??null)!==0
            || ($layout['production_admission']??null)!=='DENIED'
            || ($document['expected_pages']??null)!==($layout['pages']??null)
            || count($document['pages']??[])!==($layout['pages']??null)) {
            throw new RuntimeException('PI_RDI_FISCAL_INPUT_HOLD');
        }
        $records=[];$keys=[];$counts=[];$lastPage=0;
        foreach($layout['account_fragment_pages'] as $group){
            $page=$group['page'];
            if(!is_int($page)||$page<=$lastPage||$page>$layout['pages'])throw new RuntimeException('PI_RDI_FISCAL_PAGE_HOLD');
            $lastPage=$page;$lastLine=0;
            $sourcePage=$document['pages'][$page-1];
            if(($sourcePage['pdf_page']??null)!==$page)throw new RuntimeException('PI_RDI_FISCAL_LOCATOR_HOLD');
            $markers=[];
            foreach($sourcePage['markers'] as $m){$markers[$m['line']]=$m;}
            foreach($group['rows'] as $row){
                $line=$row['line'];$code=$row['code_fragment'];$sub=$row['subactivity_same_page'];
                if(!is_int($line)||$line<=$lastLine||($row['page']??null)!==$page
                    ||!preg_match('/\A\d(?:\.\d+){4}\.(?:\d{3}|\d{5})\z/D',$code)
                    ||!preg_match('/\A[A-F0-9]{64}\z/D',$row['line_sha256']??'')
                    ||($markers[$line]['line_sha256']??null)!==$row['line_sha256']
                    ||!is_array($row['money_tokens']??null))throw new RuntimeException('PI_RDI_FISCAL_FRAGMENT_HOLD');
                $lastLine=$line;
                $reasons=[];$values=[];$full=strlen(substr(strrchr($code,'.'),1))===5;
                if(!$full)$reasons[]='SPLIT_CODE_NEEDS_CONTINUATION';
                if($sub===null||!preg_match('/\A\d+(?:\.\d+){4}\z/D',$sub))$reasons[]='SUBACTIVITY_CONTEXT_UNRESOLVED';
                else {
                    $nearest=null;
                    foreach($markers as $ml=>$m){
                        if($ml>=$line)break;
                        if($m['kind']==='SUBACTIVITY_MARKER'&&preg_match('/Sub\h*Kegiatan\h*:\h*(\d+(?:\.\d+){4})\b/iu',$m['excerpt'],$hit))$nearest=$hit[1];
                    }
                    if($nearest!==$sub)$reasons[]='SUBACTIVITY_MARKER_NOT_CORROBORATED';
                }
                if(count($row['money_tokens'])!==($amendment?3:1))$reasons[]='COLUMN_TOKEN_COUNT_UNRESOLVED';
                else {
                    foreach($row['money_tokens'] as $token){
                        $v=$this->minorUnits($token);
                        if($v===null){$reasons[]='AMOUNT_FORMAT_OR_RANGE_HOLD';break;}
                        $values[]=$v;
                    }
                    if(count($values)===($amendment?3:1)){
                        if($values[0]<0||($amendment&&$values[1]<0))$reasons[]='NEGATIVE_ALLOCATION_HOLD';
                        if($amendment&&$values[1]-$values[0]!==$values[2])$reasons[]='POSITIONAL_DELTA_MISMATCH';
                    }
                }
                $id='p'.$page.'l'.$line;
                $key=$full&&$sub!==null?$sub.'|'.$code:null;
                $records[$id]=['id'=>$id,'source_sha256'=>$sha,'page'=>$page,'line'=>$line,
                    'line_sha256'=>$row['line_sha256'],'subactivity_candidate'=>$sub,
                    'account_code_candidate'=>$full?$code:null,'code_fragment'=>$code,
                    'raw_money_tokens'=>$row['money_tokens'],'positional_minor_units'=>$values,
                    'column_semantics'=>'NOT_CERTIFIED','opd_identity'=>'UNRESOLVED',
                    'state'=>$reasons?'HOLD':'ROW_SHAPE_CANDIDATE','reasons'=>$reasons];
                if($key!==null)$keys[$key][]=$id;
            }
        }
        if(count($records)!==($layout['account_fragments']??null))throw new RuntimeException('PI_RDI_FISCAL_COUNT_HOLD');
        foreach($keys as $ids){
            if(count($ids)<2)continue;
            foreach($ids as $id){$records[$id]['state']='HOLD';$records[$id]['reasons'][]='REPEATED_SUBACTIVITY_ACCOUNT_IDENTITY';}
        }
        $eligible=0;
        foreach($records as $r){
            if($r['state']==='ROW_SHAPE_CANDIDATE')$eligible++;
            foreach($r['reasons'] as $reason)$counts[$reason]=($counts[$reason]??0)+1;
        }
        return ['schema'=>'PI_RDI_FISCAL_RECORD_CANDIDATES_V1','source_sha256'=>$sha,
            'fragment_count'=>count($records),'row_shape_candidates'=>$eligible,'held_fragments'=>count($records)-$eligible,
            'hold_reason_counts'=>$counts,'records'=>array_values($records),
            'full_record_reconstruction'=>'OPEN','cross_version_comparison'=>'BLOCKED',
            'numeric_admission'=>0,'production_admission'=>'DENIED'];
    }

    private function minorUnits(string $token): ?int
    {
        if(!preg_match('/\A-?\d{1,3}(?:\.\d{3})*,\d{2}\z/D',$token))return null;
        $digits=str_replace(['-','.',','],'',$token);
        // Exact integer hundredths of rupiah; bound subtraction against overflow.
        if(strlen($digits)>15)return null;
        return (str_starts_with($token,'-')?-1:1)*(int)$digits;
    }
}
