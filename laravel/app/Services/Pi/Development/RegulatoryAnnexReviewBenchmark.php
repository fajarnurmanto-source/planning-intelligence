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
        $metrics=[];$rows=[];$seen=[];$correct=0;$abstain=0;
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
            $metrics[$expected]['support']++;
            if($pred===$expected){$correct++;$metrics[$expected]['tp']++;}
            else{$metrics[$expected]['fn']++;if($pred===null)$abstain++;else $metrics[$pred]['fp']++;}
            $rows[]=['document_id'=>$id,'pdf_page'=>$n,'expected'=>$expected,'observed'=>$pred,'state'=>$pred===null?'ABSTAIN':($pred===$expected?'MATCH':'MISMATCH')];
        }
        foreach($metrics as &$m){
            $m['precision']=$m['tp']+$m['fp']>0?$m['tp']/($m['tp']+$m['fp']):null;
            $m['recall']=$m['support']>0?$m['tp']/$m['support']:null;
        }
        unset($m);
        $total=count($rows);
        return ['schema'=>'PI_RF05_PAGE_ROUTING_BENCHMARK_V1','scope'=>'PAGE_ROUTING_ONLY_NOT_EXTRACTION_ACCURACY',
            'reference_verification'=>'DECLARED_INDEPENDENT_REVIEW_NOT_AUTHENTICATED_BY_ENGINE','reviewed_pages'=>$total,
            'correct'=>$correct,'mismatched'=>$total-$correct-$abstain,'abstained'=>$abstain,
            'accuracy_including_abstention'=>$total>0?$correct/$total:null,'coverage'=>$total>0?($total-$abstain)/$total:null,
            'per_class'=>$metrics,'rows'=>$rows,'state'=>$total===0?'HOLD_NO_GOLD':'MEASURED_REVIEW_SAMPLE_ONLY',
            'gold_gate'=>'NOT_PASSED','numeric_admission'=>0,'legal_effect'=>'NOT_ASSESSED','production_admission'=>'DENIED'];
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
