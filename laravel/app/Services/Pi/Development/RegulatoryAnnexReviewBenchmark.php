<?php
declare(strict_types=1);
namespace App\Services\Pi\Development;

use RuntimeException;

/**
 * RF05 page-format routing benchmark, NOT legal hierarchy/table-cell accuracy.
 * Reference must be separately authored from physical pinned PDFs. No auto-gold.
 */
final class RegulatoryAnnexReviewBenchmark
{
    private const CLASSES=['PROVISION','ACCOUNT_FRAGMENT','MIXED','HEADING','VISUAL','OTHER'];
    private const ROUTES=[
        'LEGAL_LAYOUT_NODES_REVIEW'=>'PROVISION',
        'EXPLANATION_PROVISION_REVIEW'=>'PROVISION',
        'FISCAL_ROW_FRAGMENTS_REVIEW'=>'ACCOUNT_FRAGMENT',
        'MIXED_FRAGMENT_REVIEW'=>'MIXED',
        'SECTION_HEADING_REVIEW'=>'HEADING',
        'HEADING_STRUCTURE_REVIEW'=>'HEADING',
        'VISUAL_REQUIRED_HOLD'=>'VISUAL',
        'TABLE_LAYOUT_WITHOUT_ACCOUNT_FRAGMENTS_HOLD'=>null,
        'UNRESOLVED_FORMAT_HOLD'=>null,
        'LOCATOR_RECONCILIATION_HOLD'=>null,
    ];

    /** Select physical pages independent of predicted route; no predicted labels. */
    public function reviewPlan(array $corpus,int $perDocument=12):array
    {
        $docs=$this->documents($corpus);
        if($perDocument<1||$perDocument>100)throw new RuntimeException('PI_RF05_REVIEW_SAMPLE_LIMIT');
        $pages=[];
        foreach($docs as $id=>$doc){
            $ranks=[];
            foreach($doc['pages'] as $page){
                $n=$page['page'];
                $ranks[$n]=hash('sha256','PI_RF05_REVIEW_PLAN_V1|'.$doc['sha'].'|'.$n);
            }
            asort($ranks,SORT_STRING);
            foreach(array_slice(array_keys($ranks),0,$perDocument) as $n){
                $pages[]=['document_id'=>$id,'source_sha256'=>$doc['sha'],'pdf_page'=>$n,'expected_class'=>null,'review_state'=>'UNREVIEWED'];
            }
        }
        return ['schema'=>'PI_RF05_PAGE_REVIEW_PLAN_V1','sampling'=>'DETERMINISTIC_HASH_RANK_WITHIN_DOCUMENT_NOT_MODEL_PREDICTION','classes'=>self::CLASSES,'pages'=>$pages,'gold_records'=>0,'production_admission'=>'DENIED'];
    }

    public function evaluate(array $corpus,array $reference):array
    {
        $docs=$this->documents($corpus);
        if(($reference['schema']??null)!=='PI_RF05_INDEPENDENT_PAGE_REFERENCE_V1'||($reference['reference_method']??null)!=='INDEPENDENT_PHYSICAL_PDF_REVIEW'
            ||!is_array($reference['pages']??null)||!array_is_list($reference['pages'])||count($reference['pages'])>10000)throw new RuntimeException('PI_RF05_REFERENCE_CONTRACT_HOLD');
        $metrics=[];$rows=[];$seen=[];$correct=0;$abstain=0;$visualRoutes=0;$criticalErrors=0;
        foreach(self::CLASSES as $class)$metrics[$class]=['tp'=>0,'fp'=>0,'fn'=>0,'support'=>0];
        foreach($reference['pages'] as $r){
            $id=$r['document_id']??null;$n=$r['pdf_page']??null;$expected=$r['expected_class']??null;
            if(!is_string($id)||!isset($docs[$id])||!is_int($n)||$n<1||!isset($docs[$id]['pages'][$n-1])
                ||($r['source_sha256']??null)!==$docs[$id]['sha']||!in_array($expected,self::CLASSES,true)
                ||($r['review_state']??null)!=='REVIEWED'||!is_string($r['review_reference']??null)||trim($r['review_reference'])===''||strlen($r['review_reference'])>500
                ||isset($seen[$id][$n]))throw new RuntimeException('PI_RF05_REFERENCE_LOCATOR_OR_REVIEW_HOLD');
            $seen[$id][$n]=true;
            $route=$docs[$id]['pages'][$n-1]['second_pass_route'];
            $pred=self::ROUTES[$route];
            if($pred==='VISUAL')$visualRoutes++;
            $critical=in_array($pred,['ACCOUNT_FRAGMENT','MIXED'],true)&&!in_array($expected,['ACCOUNT_FRAGMENT','MIXED'],true);
            if($critical)$criticalErrors++;
            $metrics[$expected]['support']++;
            if($pred===$expected){$correct++;$metrics[$expected]['tp']++;}
            else{$metrics[$expected]['fn']++;if($pred===null)$abstain++;else $metrics[$pred]['fp']++;}
            $rows[]=['document_id'=>$id,'pdf_page'=>$n,'expected'=>$expected,'observed'=>$pred,'route'=>$route,'critical_error'=>$critical,'extraction_state'=>$pred===null||$pred==='VISUAL'?'ABSTAIN_NO_DETERMINISTIC_EXTRACTION':'UNVERIFIED_CANDIDATE_ONLY','state'=>$pred===null?'ABSTAIN':($pred===$expected?'MATCH':'MISMATCH')];
        }
        foreach($metrics as &$m){
            $m['precision']=$m['tp']+$m['fp']>0?$m['tp']/($m['tp']+$m['fp']):null;
            $m['recall']=$m['support']>0?$m['tp']/$m['support']:null;
        }
        unset($m);
        $total=count($rows);
        return ['schema'=>'PI_RF05_PAGE_ROUTING_BENCHMARK_V1','scope'=>'PAGE_ROUTING_ONLY_NOT_EXTRACTION_ACCURACY',
            'reference_verification'=>'DECLARED_INDEPENDENT_REVIEW_NOT_AUTHENTICATED_BY_ENGINE',
            'reference_identity'=>$reference['reference_identity']??'UNSPECIFIED_DECLARED_REFERENCE',
            'human_certified_gold'=>'NOT_VERIFIED','reviewed_pages'=>$total,
            'reviewed_documents'=>count($seen),'expected_documents'=>$corpus['expected_documents']??count($docs),
            'processed_documents'=>count($docs),'document_processing_holds'=>count($corpus['holds']??[]),
            'processed_physical_pages'=>array_sum(array_map(static fn($d)=>count($d['pages']),$docs)),
            'corpus_complete'=>count($docs)===($corpus['expected_documents']??count($docs))&&count($corpus['holds']??[])===0,
            'correct'=>$correct,'mismatched'=>$total-$correct-$abstain,'abstained'=>$abstain,
            'accuracy_including_abstention'=>$total>0?$correct/$total:null,'coverage'=>$total>0?($total-$abstain)/$total:null,
            'routing_coverage'=>$total>0?($total-$abstain)/$total:null,
            'visual_routes'=>$visualRoutes,'extraction_route_abstentions'=>$abstain+$visualRoutes,
            'deterministic_candidate_route_coverage'=>$total>0?($total-$abstain-$visualRoutes)/$total:null,
            'extraction_accuracy'=>null,'extraction_accuracy_reason'=>'ROUTING_REFERENCE_IS_NOT_CELL_OR_PROVISION_GOLD',
            'critical_errors'=>$criticalErrors,'critical_error_policy'=>'FISCAL_ROUTE_FOR_REFERENCE_WITHOUT_FISCAL_ACCOUNTS',
            'unrepresented_reference_classes'=>array_keys(array_filter($metrics,static fn($m)=>$m['support']===0)),
            'per_class'=>$metrics,'rows'=>$rows,'state'=>$total===0?'HOLD_NO_GOLD':'MEASURED_REVIEW_SAMPLE_ONLY',
            'gold_gate'=>'NOT_PASSED','numeric_admission'=>0,'legal_effect'=>'NOT_ASSESSED','production_admission'=>'DENIED'];
    }

    /** Coverage measurement with the original physical-page denominator retained. */
    public function comparisonSummary(array $corpus):array
    {
        $docs=$this->documents($corpus);$rows=[];$first=[];$second=[];$total=0;$priorReview=0;$refined=0;$bound=0;$unbound=0;
        $specific=['EXPLANATION_PROVISION_REVIEW','LEGAL_LAYOUT_NODES_REVIEW','FISCAL_ROW_FRAGMENTS_REVIEW','MIXED_FRAGMENT_REVIEW','SECTION_HEADING_REVIEW','HEADING_STRUCTURE_REVIEW'];
        $kinds=['PASAL'=>'ARTICLE','AYAT'=>'PARAGRAPH_MARKER','HURUF'=>'LETTER_MARKER','ANGKA'=>'NUMBER_MARKER'];
        foreach($docs as $id=>$doc){
            $source=$corpus['documents'][$id];$inventory=$source['inventory']['pages']??[];
            if(count($inventory)!==count($doc['pages']))throw new RuntimeException('PI_RF05_SUMMARY_PAGE_DENOMINATOR_HOLD');
            $a=[];$b=[];$old=0;$better=0;$docBound=0;$docUnbound=0;
            foreach($doc['pages'] as $i=>$page){
                $inv=$inventory[$i];
                if(($inv['pdf_page']??null)!==$page['page']||($inv['text_sha256']??null)!==($page['text_sha256']??null))throw new RuntimeException('PI_RF05_SUMMARY_PAGE_HASH_HOLD');
                $markers=[];foreach($inv['markers'] as $m)$markers[$m['line'].'|'.$m['kind']]=$m['line_sha256'];
                foreach(array_merge($page['hierarchy_locators'],$page['row_locators']) as $loc){
                    $kind=isset($loc['kind'])?($kinds[$loc['kind']]??null):match($loc['code_state']??null){'FULL_MARKER'=>'ACCOUNT_FULL_MARKER','SPLIT_UNRESOLVED'=>'ACCOUNT_SPLIT_MARKER',default=>null};
                    if($kind===null||($markers[$loc['line'].'|'.$kind]??null)!==$loc['line_sha256'])throw new RuntimeException('PI_RF05_SUMMARY_LINE_HASH_HOLD');
                    $docBound++;
                }
                $docUnbound+=count($page['unbound_locators']);$f=$page['first_pass_route'];$r=$page['second_pass_route'];
                $a[$f]=($a[$f]??0)+1;$b[$r]=($b[$r]??0)+1;
                if($f==='FORMAT_REVIEW_REQUIRED'){$old++;if(in_array($r,$specific,true))$better++;}
            }
            foreach($a as $k=>$n)$first[$k]=($first[$k]??0)+$n;foreach($b as $k=>$n)$second[$k]=($second[$k]??0)+$n;
            $total+=count($doc['pages']);$priorReview+=$old;$refined+=$better;$bound+=$docBound;$unbound+=$docUnbound;
            $rows[]=['document_id'=>$id,'source_sha256'=>$doc['sha'],'physical_pages'=>count($doc['pages']),'first_pass'=>$a,'second_pass'=>$b,'prior_format_review_pages'=>$old,'more_specific_candidate_routes'=>$better,'still_requires_alternative_or_visual'=>$old-$better,'bound_line_locators'=>$docBound,'unbound_locators'=>$docUnbound];
        }
        $complete=count($docs)===($corpus['expected_documents']??count($docs))&&count($corpus['holds']??[])===0;
        return ['schema'=>'PI_RF05_ADAPTIVE_COMPARISON_V1','expected_documents'=>$corpus['expected_documents']??count($docs),'processed_documents'=>count($docs),'document_holds'=>$corpus['holds']??[],
            'corpus_complete'=>$complete,'physical_pages'=>$total,'second_pass_pages'=>array_sum($second),'first_pass'=>$first,'second_pass'=>$second,
            'prior_format_review_pages'=>$priorReview,'more_specific_candidate_routes'=>$refined,'still_requires_alternative_or_visual'=>$priorReview-$refined,
            'bound_line_locators'=>$bound,'unbound_locators'=>$unbound,'page_and_line_hash_reconciliation'=>'PASS_FOR_RETURNED_LOCATORS',
            'documents'=>$rows,'network_requests'=>0,'accuracy'=>null,'accuracy_reason'=>'COVERAGE_IS_NOT_ACCURACY','gold_gate'=>'NOT_PASSED','numeric_admission'=>0,'production_admission'=>'DENIED',
            'state'=>$complete?'MEASURED_COMPLETE_CORPUS':'HOLD_INCOMPLETE_CORPUS'];
    }

    private function documents(array $corpus):array
    {
        if(($corpus['schema']??null)!=='PI_RF04_CORPUS_INVENTORY_V1'||($corpus['source_mode']??null)!=='LOCAL_PINNED_REPOSITORY'
            ||($corpus['adaptive_layout_requested']??null)!==true||($corpus['network_requests']??null)!==0
            ||($corpus['production_admission']??null)!=='DENIED'||!is_array($corpus['documents']??null))throw new RuntimeException('PI_RF05_BENCH_CORPUS_HOLD');
        $docs=[];
        foreach($corpus['documents'] as $id=>$d){
            $sha=$d['source_sha256']??null;$profile=$d['format_profile']??[];
            $pages=$profile['adaptive_layout']['pages']??null;
            if(!is_string($id)||!is_string($sha)||!preg_match('/^[A-F0-9]{64}$/D',$sha)
                ||($d['byte_pin_status']??null)!=='OWNER_OBSERVED_PIN_MATCH'||($profile['source_sha256']??null)!==$sha
                ||($profile['numeric_admission']??null)!==0||($profile['production_admission']??null)!=='DENIED'
                ||($profile['adaptive_layout']['binding']??null)!=='SAME_TEXT_PAGE_HASH_AND_INVENTORY_MARKER_LINE_HASH'
                ||!is_array($pages)||!array_is_list($pages)||count($pages)!==($d['inventory']['expected_pages']??null))throw new RuntimeException('PI_RF05_BENCH_DOCUMENT_HOLD');
            foreach($pages as $i=>$p){
                if(($p['page']??null)!==$i+1||!array_key_exists($p['second_pass_route']??'',self::ROUTES))throw new RuntimeException('PI_RF05_BENCH_PAGE_HOLD');
            }
            $docs[$id]=['sha'=>$sha,'pages'=>$pages];
        }
        ksort($docs,SORT_STRING);
        return $docs;
    }
}
