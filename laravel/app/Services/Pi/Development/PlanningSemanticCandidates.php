<?php
declare(strict_types=1);
namespace App\Services\Pi\Development;
use RuntimeException;

/** Receipt-only semantic discovery; column years, OPD and equivalence remain unapproved. */
final class PlanningSemanticCandidates
{
    public function identity(array $doc): array
    {
        $id=$doc['catalogue_identity'];$lines=[];
        foreach($doc['identity_heading_evidence'] as $r)if($r['page']===1)$lines[]=trim($r['text']);
        $head=preg_replace('/\s+/u',' ',implode(' ',$lines));
        $prefix=match($id['instrument']){'PERDA'=>'PERATURAN DAERAH KOTA YOGYAKARTA','PERWAL'=>'PERATURAN WALI\s*KOTA YOGYAKARTA',default=>throw new RuntimeException('PI_RDI02D_INSTRUMENT_HOLD')};
        $match=preg_match('/'.$prefix.'\s+NOMOR\s+'.(int)$id['number'].'\s+TAHUN\s+'.(int)$id['year'].'\b/iu',$head)===1;
        return ['heading_match'=>$match,'identity_certification'=>'REVIEW_REQUIRED'];
    }

    public function build(array $docs): array
    {
        $index=[];$summary=[];$records=[];$seen=[];
        foreach($docs as $id=>$doc){
            if(($doc['schema']??null)!=='PI_RDI02C_NOMENCLATURE_EVIDENCE_V1'||($doc['source']['id']??null)!==$id||($doc['numeric_admission']??null)!==0||($doc['production_admission']??null)!=='DENIED')throw new RuntimeException('PI_RDI02D_RECEIPT_HOLD');
            $pages=[];foreach($doc['pages'] as $p)$pages[$p['page']]=$p['text_sha256'];
            $identity=$this->identity($doc);$counts=[];$unique=[];
            foreach($doc['code_occurrences'] as $row){
                $key=$id.':'.$row['page'].':'.$row['line'];
                if(isset($seen[$key])||($pages[$row['page']]??null)!==$row['page_sha256']||($row['window'][0]['line']??null)!==$row['line'])throw new RuntimeException('PI_RDI02D_LOCATOR_HOLD');
                $seen[$key]=true;
                if($row['state']!=='CODE_ROW_CANDIDATE')continue;
                $code=$row['code'];
                if(!preg_match('/^\d\.\d{2}\.\d{2}(?:\.\d\.\d{2}(?:\.\d{4})?)?$/D',$code))throw new RuntimeException('PI_RDI02D_CODE_HOLD');
                $label=[];$amountRows=[];$indicatorCells=[];$agencySignals=[];
                foreach($row['window'] as $i=>$w){
                    $raw=$w['text'];
                    // RDI02C stores excerpts capped at 600 chars; never claim full source hash for truncated lines.
                    if(mb_strlen($raw,'UTF-8')<600&&strtoupper(hash('sha256',$raw))!==$w['sha256'])throw new RuntimeException('PI_RDI02D_LINE_HASH_HOLD');
                    if($i===0)$raw=preg_replace('/^\h*(?:\d{1,3}\.\h+)?'.preg_quote($code,'/').'\h*/u','',$raw);
                    $cells=preg_split('/\h{2,}/u',trim($raw),-1,PREG_SPLIT_NO_EMPTY);
                    preg_match_all('/(?<![\d.,])-?\d{1,3}(?:\.\d{3})+,\d{2}(?!\d)|(?<![\d.,])-?\d{1,3},\d{2}(?!\d)/u',$raw,$m);
                    if($m[0])$amountRows[]=['line'=>$w['line'],'tokens'=>$m[0]];
                    if(isset($cells[0])&&!preg_match('/^[\d.,-]+$/D',$cells[0])&&($i===0||!preg_match('/^\h{40,}/u',$raw)))$label[]=ltrim($cells[0],'- ');
                    if(str_contains($id,'RENJA')){
                        foreach($cells as $cell){
                            if(preg_match('/^(?:Nilai|Persentase|Jumlah|Indeks|Rasio|Cakupan)\h+\p{L}/iu',$cell))$indicatorCells[]=['line'=>$w['line'],'text'=>$cell];
                            if(preg_match('/^(?:DINAS|BADAN|SEKRETARIAT|KEMANTREN|KELURAHAN)\h+\p{L}/u',$cell))$agencySignals[]=['line'=>$w['line'],'text'=>$cell];
                        }
                    }
                }
                $five=count(array_filter($amountRows,static fn($a)=>count($a['tokens'])===5));
                $shape=str_contains($id,'RENSTRA')?($five===1?'FIVE_POSITIONAL_TOKENS':'MULTIYEAR_SHAPE_HOLD'):(str_contains($id,'RENJA')?'ANNUAL_RAW_CELLS':'UNCLASSIFIED');
                $candidate=['source_id'=>$id,'source_sha256'=>$doc['source_sha256'],'page'=>$row['page'],'line'=>$row['line'],'page_sha256'=>$row['page_sha256'],'code'=>$code,'level'=>$row['level'],'label_fragments'=>$label,'amount_token_rows'=>$amountRows,'indicator_signals'=>$indicatorCells,'agency_signals'=>$agencySignals,'shape'=>$shape,'window'=>$row['window'],'column_semantics'=>'NOT_CERTIFIED','agency_identity'=>'UNRESOLVED','numeric_comparison'=>'BLOCKED'];
                $records[]=$candidate;$counts[$shape]=($counts[$shape]??0)+1;$unique[$code]=true;
                $index[$code][$id][]=['page'=>$row['page'],'line'=>$row['line'],'page_sha256'=>$row['page_sha256']];
            }
            $summary[$id]=['identity'=>$identity,'occurrences'=>array_sum($counts),'unique_codes'=>count($unique),'shapes'=>$counts];
        }
        $shared=[];$only=[];$levels=[];
        foreach($index as $code=>$sources){
            $a=$sources['YOG-RENSTRA-PD-2025-2029']??[];$b=$sources['YOG-RENJA-PD-2026']??[];
            if($a&&$b){$level=match(substr_count($code,'.')){2=>'PROGRAM',4=>'ACTIVITY',5=>'SUBACTIVITY'};$levels[$level]=($levels[$level]??0)+1;$shared[$code]=['renstra'=>$a,'renja'=>$b,'state'=>'EXACT_CODE_ONLY','agency_equivalence'=>'NOT_ASSESSED','numeric_comparison'=>'BLOCKED'];}
            else $only[$code]=array_keys($sources);
        }
        return ['schema'=>'PI_RDI02D_SEMANTIC_CANDIDATES_V1','documents'=>$summary,'registry_unique_codes'=>count($index),'shared_codes'=>count($shared),'shared_levels'=>$levels,'single_document_codes'=>count($only),'registry'=>$index,'crosswalk'=>$shared,'records'=>$records,'numeric_admission'=>0,'production_admission'=>'DENIED'];
    }
}
