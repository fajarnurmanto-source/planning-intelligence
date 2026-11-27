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
        $routes=array_count_values(array_column($pages,'processing_route'));
        return ['schema'=>'PI_RF04_FORMAT_PROFILE_V1','source_sha256'=>$inventory['source_sha256'],'pages'=>$pages,'counts'=>$counts,'processing_route_counts'=>$routes,'gold_records'=>0,'format_verification'=>'NOT_PERFORMED','precision'=>null,'recall'=>null,'production_admission'=>'DENIED','numeric_admission'=>0];
    }
}
