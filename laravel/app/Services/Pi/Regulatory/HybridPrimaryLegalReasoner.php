<?php
declare(strict_types=1);
namespace App\Services\Pi\Regulatory;

use RuntimeException;

/** HRIA-01A: bridge actual R82 passage candidates to RF02, without legal promotion. */
final class HybridPrimaryLegalReasoner
{
    public function __construct(private readonly RegulatoryGenealogyGraph $graph){}

    public function preview(array $pipeline,int $article,string $caseDate,string $knowledgeDate): array
    {
        if(($pipeline['schema']??null)!=='PI_R82_NATIONAL_PASSAGE_CANDIDATES_QA_V1'
            ||($pipeline['source_snapshot_integrity']??null)!=='R81_TWO_INDEPENDENT_SHA256_PINS_MATCH'
            ||($pipeline['production_admission']??null)!=='DENIED')throw new RuntimeException('HRIA_PIPELINE_HOLD');
        foreach([$caseDate,$knowledgeDate] as $date){
            $d=\DateTimeImmutable::createFromFormat('!Y-m-d',$date);
            if(!$d||$d->format('Y-m-d')!==$date)throw new RuntimeException('HRIA_DATE_HOLD');
        }
        $sources=array_column($pipeline['sources'],null,'instrument_id');
        $records=array_values(array_filter($pipeline['candidates'],static fn($c)=>$c['article']===$article));
        if(count($records)!==1||($records[0]['evidence_status']??null)!=='TEXT_LAYER_CANDIDATE_REVIEW_REQUIRED')throw new RuntimeException('HRIA_PASSAGE_HOLD');
        $record=$records[0];
        $evidence=[];
        foreach(array_merge([['instrument'=>'PP-81-2012','passage'=>$record['pp_reference']]],array_map(static fn($p)=>['instrument'=>'UU-18-2008','passage'=>$p],$record['uu_passages'])) as $item){
            $id=$item['instrument'];$p=$item['passage'];$src=$sources[$id]??[];
            if(!is_array($p)||($p['truncated']??false)||!is_string($p['excerpt']??null)||trim($p['excerpt'])===''||strtoupper(hash('sha256',$p['excerpt']))!==($p['excerpt_sha256']??null)
                ||!is_int($p['pdf_page']??null)||$p['pdf_page']<1||!preg_match('/^[A-F0-9]{64}$/D',$src['pdf_sha256']??'')
                ||($src['text_layer_status']??null)!=='LOCAL_TEXT_LAYER_EXTRACTED')throw new RuntimeException('HRIA_EXCERPT_HOLD');
            $evidence[]=['id'=>'P'.(count($evidence)+1),'instrument_id'=>$id,'source_url'=>$src['acquisition_url'],'source_sha256'=>$src['pdf_sha256'],'pdf_page'=>$p['pdf_page'],'excerpt'=>$p['excerpt'],'excerpt_sha256'=>$p['excerpt_sha256'],'role'=>$id==='PP-81-2012'?'PREAMBLE_REFERENCE_CANDIDATE':'OPERATIVE_PASSAGE_CANDIDATE'];
        }
        // P1/P2 identities stay stable; supplementary context is appended.
        $extra=$record['pp_preamble_context']??null;
        if($extra!==null){
            $src=$sources['PP-81-2012'];$ref=$record['pp_reference'];
            if(!is_array($extra)||($extra['truncated']??true)||($extra['evidence_state']??null)!=='EXTRACTED_PP_PREAMBLE_CONTEXT_FOR_REVIEW'
                ||!is_string($extra['excerpt']??null)||mb_strlen($extra['excerpt'])>1600||!str_contains($extra['excerpt'],$ref['excerpt'])
                ||strtoupper(hash('sha256',$extra['excerpt']))!==($extra['excerpt_sha256']??null)
                ||($extra['pdf_page']??null)!==$ref['pdf_page'])throw new RuntimeException('HRIA_PREAMBLE_CONTEXT_HOLD');
            $evidence[]=['id'=>'P'.(count($evidence)+1),'instrument_id'=>'PP-81-2012','source_url'=>$src['acquisition_url'],'source_sha256'=>$src['pdf_sha256'],'pdf_page'=>$extra['pdf_page'],'excerpt'=>$extra['excerpt'],'excerpt_sha256'=>$extra['excerpt_sha256'],'role'=>'PREAMBLE_CONTEXT_CANDIDATE'];
        }
        $ctx=['scope'=>'PRIMARY_TEXT_LAYER_CANDIDATES_NOT_CERTIFIED_LEGAL_TEXT','case_date'=>$caseDate,'knowledge_date'=>$knowledgeDate,'article'=>$article,'evidence'=>$evidence,'permitted_task'=>'Assess whether the excerpts support a reference or potential implementation/delegation relation; distinguish missing evidence. No legal effect or policy compliance conclusion.'];
        $raw=json_encode($ctx,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        if(strlen($raw)>6000)throw new RuntimeException('HRIA_CONTEXT_LIMIT');
        return ['schema'=>'PI_HRIA_PRIMARY_PREVIEW_V1','context'=>$ctx,'sha256'=>strtoupper(hash('sha256',$raw)),'legal_effect'=>'NOT_ASSESSED','production_admission'=>'DENIED'];
    }

    /** Model output is a semantic opinion, never independently established legal evidence. */
    public function assess(array $preview,array $suggestion): array
    {
        $result=['schema'=>'PI_HRIA_PRIMARY_ANALYSIS_V1','state'=>'ABSTAIN','reason'=>null,'semantic_candidate'=>null,'semantic_abstention'=>null,'model_output_validation'=>'NOT_PASSED','structural_relation'=>null,'legal_effect'=>'NOT_ASSESSED','substantive_development_alignment'=>'NOT_ASSESSED','production_admission'=>'DENIED'];
        $ctx=$preview['context'];
        $raw=json_encode($ctx,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        if(!hash_equals($preview['sha256'],strtoupper(hash('sha256',$raw))))return array_replace($result,['reason'=>'PREVIEW_DRIFT']);
        if(count($suggestion)!==3||array_diff(array_keys($suggestion),['semantic_relation','semantic_confidence','explanation'])||!in_array($suggestion['semantic_relation']??null,['RELATED','BROADER','NARROWER','CONFLICT','EQUIVALENT','INSUFFICIENT'],true)||!in_array($suggestion['semantic_confidence']??null,['LOW','MEDIUM','HIGH','UNDETERMINED'],true)||!is_string($suggestion['explanation']??null)||trim($suggestion['explanation'])===''||mb_strlen($suggestion['explanation'])>1000)return array_replace($result,['reason'=>'INVALID_MODEL_OUTPUT']);
        $p=$ctx['evidence'][0];$target='UU-18-2008:PASAL-'.$ctx['article'];
        $nodes=['PP-81-2012'=>['node_type'=>'INSTRUMENT'],'UU-18-2008'=>['node_type'=>'INSTRUMENT'],$target=>['node_type'=>'PROVISION','owner_id'=>'UU-18-2008']];
        // Only the observed citation is typed by the server. No invented effective date.
        $e=['from'=>'PP-81-2012','to'=>$target,'relation_type'=>'CITES_AS_BASIS','source_instrument_id'=>'PP-81-2012','source_url'=>$p['source_url'],'source_locator'=>'PDF page '.$p['pdf_page'],'source_sha256'=>$p['source_sha256'],'excerpt'=>$p['excerpt'],'excerpt_sha256'=>$p['excerpt_sha256'],'status'=>'SOURCE_TEXT_CANDIDATE','observed_on'=>null,'effective_on'=>null];
        $guard=$this->graph->assessProposal($nodes,['P1'=>$e],['from'=>'PP-81-2012','to'=>$target,'type'=>'CITES_AS_BASIS','evidence_id'=>'P1'],$ctx['case_date'],$ctx['knowledge_date']);
        $validated=['model_output_validation'=>'PASS','structural_relation'=>$guard,'supporting_context_sha256'=>$preview['sha256']];
        if($suggestion['semantic_relation']==='INSUFFICIENT'){
            return array_replace($result,$validated,['reason'=>'INSUFFICIENT_SEMANTICS','semantic_abstention'=>$suggestion]);
        }
        return array_replace($result,$validated,['state'=>'SEMANTIC_CANDIDATE_REVIEW_REQUIRED','semantic_candidate'=>$suggestion]);
    }
}
