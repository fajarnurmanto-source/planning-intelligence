<?php
declare(strict_types=1);

namespace App\Services\Pi\Development;

use RuntimeException;

/** RDI candidate hierarchy and annex format profile; no legal or numeric admission. */
final class DocumentStructureProfile
{
    public function profile(array $document): array
    {
        if (($document['schema']??null)!=='PI_RDI_01A_PAGE_INVENTORY_V1'
            || !preg_match('/\A[A-F0-9]{64}\z/D',$document['source_sha256']??'')
            || ($document['numeric_admission']??null)!==0
            || ($document['production_admission']??null)!=='DENIED'
            || !is_array($document['pages']??null)
            || count($document['pages'])!==($document['expected_pages']??null)
            || count($document['pages'])!==($document['inventoried_pages']??null)) {
            throw new RuntimeException('PI_RDI_PROFILE_INPUT_HOLD');
        }
        $nodes=[];$issues=[];$stack=[];$seen=[];$annex=[];$unknown=[];$zone='UNRESOLVED';
        $ranks=['CHAPTER'=>0,'PART'=>1,'PARAGRAPH_HEADING'=>2,'ARTICLE'=>3,'PARAGRAPH_MARKER'=>4,'LETTER_MARKER'=>5,'NUMBER_MARKER'=>6];
        foreach($document['pages'] as $index=>$page){
            $p=$index+1;
            if(($page['pdf_page']??null)!==$p||!is_array($page['markers']??null)
                || !preg_match('/\A[A-F0-9]{64}\z/D',$page['text_sha256']??'')){
                throw new RuntimeException('PI_RDI_PROFILE_PAGE_HOLD');
            }
            if(!$page['markers']){$unknown[]=['page'=>$p,'state'=>$page['state']];$stack=[];}
            $last=0;$counts=[];
            foreach($page['markers'] as $m){
                $kind=$m['kind']??'';
                $line=$m['line']??0;
                if(!is_int($line)||$line<=$last||!is_string($m['excerpt']??null)
                    ||!preg_match('/\A[A-F0-9]{64}\z/D',$m['line_sha256']??'')){
                    throw new RuntimeException('PI_RDI_PROFILE_MARKER_HOLD');
                }
                $last=$line;$counts[$kind]=($counts[$kind]??0)+1;
                $loc=['page'=>$p,'line'=>$line,'line_sha256'=>$m['line_sha256'],'page_text_sha256'=>$page['text_sha256']];
                if(in_array($kind,['EXPLANATION_HEADING','ANNEX_HEADING','CONTENTS_HEADING'],true)){
                    $zone=$kind;$stack=[];$seen=[];
                    continue;
                }
                if(!isset($ranks[$kind]))continue;
                // Never label a candidate operative law. TOC, explanation and annex
                // are separate observations, not members of the legal hierarchy.
                if($zone!=='UNRESOLVED')continue;
                $rank=$ranks[$kind];
                foreach(array_keys($stack) as $r){if($r>=$rank)unset($stack[$r]);}
                $parent=null;
                if($stack){$parent=$stack[max(array_keys($stack))];}
                $label=trim($m['excerpt']);
                $identity=match($kind){
                    'ARTICLE'=>preg_match('/^Pasal\h+(\S+)$/iu',$label,$x)?strtoupper($x[1]):null,
                    'PARAGRAPH_MARKER'=>preg_match('/^\((\d+[a-z]?)\)/iu',$label,$x)?strtolower($x[1]):null,
                    'LETTER_MARKER','NUMBER_MARKER'=>preg_match('/^([a-z]|\d+)[.)]/iu',$label,$x)?strtolower($x[1]):null,
                    default=>$label
                };
                $id='p'.$p.'l'.$line;
                $key=($parent??'ROOT').'|'.$kind.'|'.$identity;
                $state='CANDIDATE_ONLY';
                if($identity===null||isset($seen[$key])||($rank>=4&&!isset($stack[3]))
                    ||($rank===6&&!isset($stack[5]))){
                    $state='AMBIGUOUS_REVIEW_REQUIRED';
                    $issues[]=$loc+['kind'=>$kind,'reason'=>'DUPLICATE_OR_MISSING_CONTEXT'];
                    if(isset($seen[$key])){$nodes[$seen[$key]]['state']=$state;}
                    $parent=null;
                }
                // Numbers following letters can be nested, but indentation and
                // continuation are not present in the RDI-01A receipt.
                if($rank>=5){$state='AMBIGUOUS_REVIEW_REQUIRED';$parent=null;}
                $nodes[$id]=$loc+['id'=>$id,'kind'=>$kind,'label'=>$identity,'parent_candidate'=>$parent,'state'=>$state,'zone'=>'UNRESOLVED_BODY_CANDIDATE'];
                $seen[$key]=$id;
                if($state==='CANDIDATE_ONLY'){$stack[$rank]=$id;}
            }
            $signals=array_intersect_key($counts,array_flip(['ANNEX_HEADING','SUBACTIVITY_MARKER','ACCOUNT_FULL_MARKER','ACCOUNT_SPLIT_MARKER','TABLE_LAYOUT_SIGNAL']));
            if($signals){$annex[]=['page'=>$p,'signals'=>$signals,'page_text_sha256'=>$page['text_sha256'],'state'=>'FORMAT_CANDIDATE_ONLY'];}
        }
        return ['schema'=>'PI_RDI_01BC_STRUCTURE_PROFILE_V1','source_sha256'=>$document['source_sha256'],
            'page_count'=>count($document['pages']),'hierarchy_candidates'=>array_values($nodes),
            'ambiguities'=>$issues,'unclassified_pages'=>$unknown,'annex_page_signals'=>$annex,
            'structure_verification'=>'NOT_PERFORMED','table_reconstruction'=>'NOT_PERFORMED',
            'legal_harmonization'=>'NOT_ASSESSED','numeric_admission'=>0,'production_admission'=>'DENIED'];
    }
}
