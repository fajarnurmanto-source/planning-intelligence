<?php
declare(strict_types=1);
namespace App\Services\Pi\Development;
use RuntimeException;

/** Exact code links only. No fiscal sums, identity inference or admission. */
final class PlanningFiscalEvidenceCrosswalk
{
    public function build(array $planning,array $fiscal): array
    {
        if(($planning['schema']??null)!=='PI_RDI02D_SEMANTIC_CANDIDATES_V1'
            ||($fiscal['schema']??null)!=='PI_RDI_01A_CORPUS_RECEIPT_V1'
            ||($fiscal['processed_documents']??null)!==4||!empty($fiscal['holds'])
            ||($planning['numeric_admission']??null)!==0||($fiscal['numeric_admission']??null)!==0
            ||($planning['production_admission']??null)!=='DENIED'||($fiscal['production_admission']??null)!=='DENIED'){
            throw new RuntimeException('PI_RDI03_INPUT_HOLD');
        }
        $registry=$planning['registry'];$planIndex=[];$planSeen=[];$planShapes=[];$shapeSamples=[];
        foreach($planning['records'] as $r){
            $id=$r['source_id'];$code=$r['code'];$key=$id.':'.$r['page'].':'.$r['line'];
            if(isset($planSeen[$key])||!isset($registry[$code][$id]))throw new RuntimeException('PI_RDI03_PLANNING_LOCATOR_HOLD');
            $planSeen[$key]=true;
            $planIndex[$code][$id][]=['page'=>$r['page'],'line'=>$r['line'],'page_sha256'=>$r['page_sha256']];
            if($r['shape']==='MULTIYEAR_SHAPE_HOLD'){
                $shape=implode(',',array_map(static fn($a)=>count($a['tokens']),$r['amount_token_rows']));
                $shape=$shape===''?'NO_TOKENS':$shape;
                $planShapes[$shape]=($planShapes[$shape]??0)+1;
                if(!isset($shapeSamples[$shape])&&count($shapeSamples)<12)$shapeSamples[$shape]=['code'=>$code,'page'=>$r['page'],'line'=>$r['line'],'window'=>$r['window']];
            }
        }
        if($planIndex!==$registry||count($registry)!==$planning['registry_unique_codes'])throw new RuntimeException('PI_RDI03_REGISTRY_HOLD');
        $documents=[];$records=[];$fiscalCodes=[];$matchedCodes=[];$agencyGroups=[];$agencySamples=[];
        foreach(['YOG-APBD-DETAIL-2026','YOG-APBD-DETAIL-CHANGE3-2026'] as $id){
            $doc=$fiscal['documents'][$id];$m=$doc['multiline_fiscal'];$c=$doc['fiscal_context'];
            if(($m['schema']??null)!=='PI_RDI_MULTILINE_FISCAL_V2'||($c['schema']??null)!=='PI_RDI_FISCAL_CONTEXT_V1'
                ||($m['numeric_admission']??null)!==0||($c['numeric_admission']??null)!==0
                ||($m['production_admission']??null)!=='DENIED'||($c['production_admission']??null)!=='DENIED'
                ||count($m['records'])!==$m['fragment_count']||count($c['records'])!==$m['fragment_count'])throw new RuntimeException('PI_RDI03_FISCAL_SCHEMA_HOLD');
            $pages=[];foreach($doc['pages'] as $p)$pages[$p['pdf_page']]=$p['text_sha256'];
            $counts=[];$unique=[];$seen=[];$joint=0;
            foreach($m['records'] as $i=>$row){
                $ctx=$c['records'][$i];$p=$row['page'];$l=$row['line'];$loc=$p.':'.$l;
                if(isset($seen[$loc])||$ctx['page']!==$p||$ctx['line']!==$l
                    ||$ctx['page_text_sha256']!==$row['page_text_sha256']||($pages[$p]??null)!==$row['page_text_sha256']
                    ||$ctx['account_code_candidate']!==$row['account_code_candidate'])throw new RuntimeException('PI_RDI03_FISCAL_LOCATOR_HOLD');
                $seen[$loc]=true;
                $sub=$ctx['context']['subactivity']??null;$org=$ctx['context']['organization']??null;
                $code=null;$hits=[];
                if($sub!==null&&($sub['valid']??false)===true&&preg_match('/^(\d\.\d{2}\.\d{2}\.\d\.\d{2}\.\d{4})(?:\h|$)/u',$sub['value'],$match)){
                    $code=$match[1];$unique[$code]=true;$fiscalCodes[$code]=true;
                    $hits=array_keys($registry[$code]??[]);
                    if($hits)$matchedCodes[$code]=true;
                }
                $state=$code===null?'NO_OBSERVED_SUBACTIVITY':(!$hits?'CODE_NOT_IN_PLANNING_REGISTRY':
                    (count($hits)===2?'EXACT_CODE_BOTH_PLANS':'EXACT_CODE_ONE_PLAN'));
                $counts[$state]=($counts[$state]??0)+1;
                if($hits&&$org!==null)$joint++;
                $agency=$org===null?'NO_ORGANIZATION':($sub===null?'ORGANIZATION_WITHOUT_SUBACTIVITY':'ORGANIZATION_AND_SUBACTIVITY');
                $agencyGroups[$agency]=($agencyGroups[$agency]??0)+1;
                if(!isset($agencySamples[$id][$agency]))$agencySamples[$id][$agency]=['page'=>$p,'line'=>$l,'context'=>$ctx['context'],'missing_levels'=>$ctx['missing_levels']];
                $records[]=['source_id'=>$id,'source_sha256'=>$doc['source_sha256'],'page'=>$p,'line'=>$l,
                    'page_sha256'=>$row['page_text_sha256'],'account_code_candidate'=>$row['account_code_candidate'],
                    'subactivity_code'=>$code,'subactivity_source'=>$sub,'organization_source'=>$org,
                    'planning_sources'=>$hits,'state'=>$state,'agency_equivalence'=>'NOT_ASSESSED',
                    'numeric_comparison'=>'BLOCKED','relation_promotion'=>'NOT_PERFORMED'];
            }
            $documents[$id]=['fragments'=>count($seen),'states'=>$counts,'observed_unique_subactivities'=>count($unique),'matched_with_org_candidate'=>$joint];
        }
        $planningSubs=array_filter(array_keys($registry),static fn($s)=>substr_count($s,'.')===5);
        $planningOnly=array_values(array_diff($planningSubs,array_keys($fiscalCodes)));
        arsort($planShapes);
        return ['schema'=>'PI_RDI03_PLANNING_FISCAL_CROSSWALK_V1','documents'=>$documents,
            'fiscal_unique_observed_codes'=>count($fiscalCodes),'matched_unique_codes'=>count($matchedCodes),
            'planning_subactivity_codes'=>count($planningSubs),'planning_codes_without_observed_fiscal_match'=>count($planningOnly),
            'planning_only_codes'=>$planningOnly,'records'=>$records,'agency_context_groups'=>$agencyGroups,'agency_samples'=>$agencySamples,
            'renstra_hold_token_shapes'=>$planShapes,'renstra_hold_samples'=>$shapeSamples,
            'numeric_admission'=>0,'numeric_comparison'=>'BLOCKED','production_admission'=>'DENIED'];
    }
}
