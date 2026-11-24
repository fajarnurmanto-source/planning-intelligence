<?php
declare(strict_types=1);
namespace App\Services\Pi\Development;

use RuntimeException;

/** Receipt-only diagnostics: observed source excerpts, never guessed fiscal values. */
final class FiscalContextDiagnostics
{
    public function inspect(array $doc): array
    {
        $m=$doc['multiline_fiscal']??[];$c=$doc['fiscal_context']??[];
        if(($m['schema']??null)!=='PI_RDI_MULTILINE_FISCAL_V2'
            ||($c['schema']??null)!=='PI_RDI_FISCAL_CONTEXT_V1'
            ||($m['numeric_admission']??null)!==0||($c['numeric_admission']??null)!==0
            ||($m['production_admission']??null)!=='DENIED'||($c['production_admission']??null)!=='DENIED'
            ||count($m['records']??[])!==($m['fragment_count']??null)
            ||count($c['records']??[])!==$m['fragment_count']){
            throw new RuntimeException('PI_RDI_DIAGNOSTIC_SCHEMA_HOLD');
        }
        $missing=[];$pairs=[];$samples=[];$headingSamples=[];$headingCounts=[];$rowSamples=[];$pages=[];
        foreach($doc['pages'] as $page)$pages[$page['pdf_page']]=$page;
        foreach($m['records'] as $i=>$row){
            $ctx=$c['records'][$i];$p=$row['page'];$l=$row['line'];
            if(($ctx['page']??null)!==$p||($ctx['line']??null)!==$l
                ||($ctx['page_text_sha256']??null)!==($row['page_text_sha256']??null)
                ||($pages[$p]['text_sha256']??null)!==$row['page_text_sha256']){
                throw new RuntimeException('PI_RDI_DIAGNOSTIC_LOCATOR_HOLD');
            }
            foreach($ctx['missing_levels'] as $level)$missing[$level]=($missing[$level]??0)+1;
            $suffix=in_array('SPLIT_SUFFIX_UNRESOLVED',$row['reasons'],true);
            $amount=in_array('COLUMN_TOKEN_COUNT_UNRESOLVED',$row['reasons'],true);
            $key=($suffix?'SUFFIX_HOLD':'SUFFIX_OK').'|'.($amount?'TOKEN_HOLD':'TOKEN_OK');
            $pairs[$key]=($pairs[$key]??0)+1;
            if($suffix||$amount){
                $shape=count($row['evidence']).'lines|'.count($row['money_tokens']).'tokens|'.count($row['suffix_candidates']).'suffixes';
                $samples[$shape]=($samples[$shape]??0)+1;
                if(!isset($rowSamples[$shape])&&count($rowSamples)<12){
                    $e=[];
                    foreach($row['evidence'] as $line){
                        if(strtoupper(hash('sha256',$line['text']))!==$line['sha256'])throw new RuntimeException('PI_RDI_DIAGNOSTIC_LINE_HASH_HOLD');
                        $e[]=['line'=>$line['line'],'sha256'=>$line['sha256'],'text'=>mb_substr($line['text'],0,300,'UTF-8')];
                    }
                    $rowSamples[$shape]=['page'=>$p,'line'=>$l,'evidence'=>$e];
                }
            }
        }
        // Existing inventory markers preserve source excerpts outside account chunks.
        foreach($pages as $p=>$page){
            foreach($page['markers'] as $marker){
                $excerpt=$marker['excerpt'];
                if(!preg_match('/\b(?:urusan|organisasi|unit|skpd|perangkat\s+daerah|program|kegiatan|kode\s+rekening|sebelum|sesudah|bertambah|berkurang)\b/iu',$excerpt,$hit))continue;
                $word=mb_strtolower($hit[0],'UTF-8');
                $shape=$word.'|'.$marker['kind'];
                $headingCounts[$shape]=($headingCounts[$shape]??0)+1;
                if(!isset($headingSamples[$shape])&&count($headingSamples)<24){
                    $headingSamples[$shape]=['page'=>$p,'line'=>$marker['line'],'sha256'=>$marker['line_sha256'],'excerpt'=>$excerpt];
                }
            }
        }
        ksort($missing);ksort($pairs);arsort($samples);arsort($headingCounts);
        return ['fragments'=>$m['fragment_count'],'missing_levels'=>$missing,'suffix_token_intersection'=>$pairs,
            'unresolved_row_shapes'=>$samples,'row_samples'=>$rowSamples,'heading_signal_counts'=>$headingCounts,
            'heading_samples'=>$headingSamples,'coverage'=>'INVENTORY_MARKERS_AND_ROW_CHUNKS_ONLY',
            'numeric_admission'=>0,'production_admission'=>'DENIED'];
    }
}
