<?php
declare(strict_types=1);
namespace App\Services\Pi\Development;
use RuntimeException;

/** Format signals only; no ground truth or inferred cell semantics. */
final class RegulatoryAnnexBenchmarkProfile
{
    public function profile(array $inventory): array
    {
        if(($inventory['schema']??null)!=='PI_RDI_01A_PAGE_INVENTORY_V2'||($inventory['production_admission']??null)!=='DENIED'||($inventory['numeric_admission']??null)!==0||count($inventory['pages'])!==$inventory['expected_pages'])throw new RuntimeException('PI_RF04_INVENTORY_HOLD');
        $pages=[];$counts=[];$annex=false;
        foreach($inventory['pages'] as $i=>$p){
            if($p['pdf_page']!==$i+1||!preg_match('/^[A-F0-9]{64}$/D',$p['text_sha256']))throw new RuntimeException('PI_RF04_LOCATOR_HOLD');
            $m=$p['marker_counts'];if(isset($m['ANNEX_HEADING']))$annex=true;
            if(isset($m['EXPLANATION_HEADING']))$annex=false;
            $signals=[];
            if($p['state']==='NO_TEXT_OCR_OR_VISUAL_REQUIRED')$signals[]='VISUAL_INSPECTION_REQUIRED';
            if(isset($m['ARTICLE']))$signals[]='ARTICLE_MARKERS';
            if(isset($m['TABLE_LAYOUT_SIGNAL']))$signals[]='TABLE_LAYOUT_MARKERS';
            if($annex)$signals[]='ANNEX_ZONE_CANDIDATE';
            if(!$signals)$signals[]='UNCLASSIFIED';
            foreach($signals as $s)$counts[$s]=($counts[$s]??0)+1;
            $route=match(true){
                in_array('VISUAL_INSPECTION_REQUIRED',$signals,true)=>'VISUAL_OR_OCR_REQUIRED',
                in_array('TABLE_LAYOUT_MARKERS',$signals,true)&&in_array('ARTICLE_MARKERS',$signals,true)=>'MIXED_LAYOUT_REVIEW',
                in_array('TABLE_LAYOUT_MARKERS',$signals,true)=>'TABLE_RECONSTRUCTION_CANDIDATE',
                in_array('ARTICLE_MARKERS',$signals,true)=>'PROVISION_STRUCTURE_CANDIDATE',
                default=>'FORMAT_REVIEW_REQUIRED',
            };
            $pages[]=['page'=>$i+1,'text_sha256'=>$p['text_sha256'],'signals'=>$signals,'marker_counts'=>$m,'processing_route'=>$route,'route_verification'=>'NOT_PERFORMED'];
        }
        // Independent second pass: preserve first-pass counts and expose
        // extracted fragments/explicit heading signals, not inferred cells.
        $layout=$inventory['layout_candidates']??null;
        $secondary=null;
        if($layout!==null){
            if(($layout['schema']??null)!=='PI_RDI_LAYOUT_CANDIDATES_V2'||($layout['pages']??null)!==count($pages)
                ||($layout['numeric_admission']??null)!==0||($layout['production_admission']??null)!=='DENIED')throw new RuntimeException('PI_RF05_LAYOUT_HOLD');
            $nodes=[];$fragments=[];
            foreach($layout['hierarchy_nodes'] as $node){
                $p=$node['page']??null;
                if(!is_int($p)||!isset($pages[$p-1])||!is_int($node['line']??null)||$node['line']<1||!preg_match('/^[A-F0-9]{64}$/D',$node['line_sha256']??''))throw new RuntimeException('PI_RF05_NODE_LOCATOR_HOLD');
                $nodes[$p][]=['kind'=>$node['kind'],'line'=>$node['line'],'line_sha256'=>$node['line_sha256'],'state'=>$node['state']];
            }
            foreach($layout['account_fragment_pages'] as $block){
                $p=$block['page']??null;
                if(!is_int($p)||!isset($pages[$p-1])||isset($fragments[$p])||!is_array($block['rows']??null))throw new RuntimeException('PI_RF05_ROW_LOCATOR_HOLD');
                $fragments[$p]=[];
                foreach($block['rows'] as $row){
                    if(($row['page']??null)!==$p||!is_int($row['line']??null)||$row['line']<1||!preg_match('/^[A-F0-9]{64}$/D',$row['line_sha256']??'')||($row['state']??null)!=='UNVERIFIED_ROW_FRAGMENT')throw new RuntimeException('PI_RF05_ROW_LOCATOR_HOLD');
                    $fragments[$p][]=['line'=>$row['line'],'line_sha256'=>$row['line_sha256'],'code_state'=>$row['code_state']];
                }
            }
            $secondary=[];
            foreach($pages as $p){
                $number=$p['page'];$m=$p['marker_counts'];
                $n=$nodes[$number]??[];$rows=$fragments[$number]??[];
                $route=match(true){
                    $p['processing_route']==='VISUAL_OR_OCR_REQUIRED'=>'VISUAL_REQUIRED_HOLD',
                    $rows!==[]&&$n!==[]=>'MIXED_FRAGMENT_REVIEW',
                    $rows!==[]=>'FISCAL_ROW_FRAGMENTS_REVIEW',
                    $n!==[]=>'LEGAL_LAYOUT_NODES_REVIEW',
                    isset($m['EXPLANATION_HEADING'])||isset($m['CONTENTS_HEADING'])||isset($m['ANNEX_HEADING'])||isset($m['PREAMBLE_MARKER'])=>'SECTION_HEADING_REVIEW',
                    isset($m['CHAPTER'])||isset($m['PART'])||isset($m['PARAGRAPH_HEADING'])=>'HEADING_STRUCTURE_REVIEW',
                    $p['processing_route']==='TABLE_RECONSTRUCTION_CANDIDATE'=>'TABLE_LAYOUT_WITHOUT_ACCOUNT_FRAGMENTS_HOLD',
                    default=>'UNRESOLVED_FORMAT_HOLD',
                };
                $secondary[]=['page'=>$number,'text_sha256'=>$p['text_sha256'],'first_pass_route'=>$p['processing_route'],'second_pass_route'=>$route,'hierarchy_locators'=>$n,'row_locators'=>$rows,'verification'=>'NOT_PERFORMED'];
            }
        }
        $routes=array_count_values(array_column($pages,'processing_route'));
        return ['schema'=>'PI_RF04_FORMAT_PROFILE_V1','source_sha256'=>$inventory['source_sha256'],'pages'=>$pages,'counts'=>$counts,'processing_route_counts'=>$routes,'adaptive_layout'=>$secondary===null?null:['pages'=>$secondary,'route_counts'=>array_count_values(array_column($secondary,'second_pass_route')),'state'=>'LAYOUT_CANDIDATES_ONLY'],'gold_records'=>0,'format_verification'=>'NOT_PERFORMED','precision'=>null,'recall'=>null,'production_admission'=>'DENIED','numeric_admission'=>0];
    }
}
