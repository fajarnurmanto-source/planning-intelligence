<?php
declare(strict_types=1);

namespace App\Services\Pi\Development;

use RuntimeException;

/** Independent context overlay. Never changes V2 row extraction or grants admission. */
final class FiscalOrganizationContext
{
    private const LEVELS=['urusan','bidang','organization','unit','program','activity','subactivity'];

    public function resolve(string $text, array $multiline): array
    {
        $pages=explode("\f",$text);
        if(array_pop($pages)!==''||count($pages)!==($multiline['pages']??null)
            ||($multiline['schema']??null)!=='PI_RDI_MULTILINE_FISCAL_V2'
            ||($multiline['numeric_admission']??null)!==0||($multiline['production_admission']??null)!=='DENIED'){
            throw new RuntimeException('PI_RDI_CONTEXT_INPUT_HOLD');
        }
        $byPage=[];$seen=[];
        foreach($multiline['records'] as $row){
            $p=$row['page'];$l=$row['line'];
            if(isset($byPage[$p][$l]))throw new RuntimeException('PI_RDI_CONTEXT_DUPLICATE_LOCATOR');
            $byPage[$p][$l]=$row;
        }
        $active=[];$previousPrinted=null;$previousHeader=null;$records=[];$events=[];$counts=[];
        foreach($pages as $i=>$page){
            $p=$i+1;$lines=preg_split('/\r\n|\n|\r/u',$page);
            $printed=null;$header=null;$firstAccount=null;
            foreach($lines as $n=>$line){
                if(isset($byPage[$p][$n+1])&&$firstAccount===null)$firstAccount=$n;
                if(preg_match('/^\h*Halaman\h+(\d+)\b/iu',$line,$m))$printed=(int)$m[1];
                if($firstAccount===null&&preg_match('/\bKode\h+Rekening\b/iu',$line)
                    &&preg_match('/\b(?:Uraian|Jumlah|Anggaran|Sebelum|Sesudah)\b/iu',$line)){
                    $header=strtoupper(hash('sha256',preg_replace('/\h+/u',' ',trim($line))));
                }
            }
            $continuity=$printed!==null&&$previousPrinted!==null&&$printed===$previousPrinted+1
                &&$header!==null&&$previousHeader!==null&&$header===$previousHeader;
            if(!$continuity)$active=[];
            $previousPrinted=$printed;$previousHeader=$header;
            $pageHash=strtoupper(hash('sha256',$page));
            foreach($lines as $n=>$raw){
                $line=$n+1;
                if(preg_match('/^\h*(?:LAMPIRAN|PENJELASAN|PERATURAN)\b/iu',$raw)){
                    $active=[];
                }
                $event=$this->heading($raw);
                if($event!==null){
                    [$level,$value,$valid]=$event;
                    $rank=array_search($level,self::LEVELS,true);
                    // A repeated explicit identical heading does not authorize
                    // stale descendants; every heading invalidates its descendants.
                    foreach(self::LEVELS as $k=>$name){if($k>=$rank)unset($active[$name]);}
                    $e=['level'=>$level,'value'=>$value,'page'=>$p,'line'=>$line,
                        'line_sha256'=>strtoupper(hash('sha256',$raw)),'valid'=>$valid];
                    $events[]=$e;
                    if($valid)$active[$level]=$e;
                }
                if(!isset($byPage[$p][$line]))continue;
                $row=$byPage[$p][$line];
                if(($row['page_text_sha256']??null)!==$pageHash
                    ||($row['evidence'][0]['sha256']??null)!==strtoupper(hash('sha256',$raw))){
                    throw new RuntimeException('PI_RDI_CONTEXT_SOURCE_LOCATOR_HOLD');
                }
                $missing=[];$inherited=[];
                foreach(self::LEVELS as $level){
                    if(!isset($active[$level]))$missing[]=$level;
                    elseif($active[$level]['page']!==$p)$inherited[]=$level;
                }
                $org=isset($active['organization']);
                $sub=isset($active['subactivity']);
                $state=$org&&$sub?($inherited?'CROSS_PAGE_CONTEXT_CANDIDATE':'SAME_PAGE_CONTEXT_CANDIDATE'):'CONTEXT_HOLD';
                $counts[$state]=($counts[$state]??0)+1;
                $records[]=['page'=>$p,'line'=>$line,'page_text_sha256'=>$pageHash,
                    'account_code_candidate'=>$row['account_code_candidate'],
                    'context'=>$active,'missing_levels'=>$missing,'inherited_levels'=>$inherited,
                    'printed_page'=>$printed,'header_sha256'=>$header,'state'=>$state,
                    'identity_certification'=>'NOT_PERFORMED','column_semantics'=>'NOT_CERTIFIED'];
                $seen[$p.':'.$line]=true;
            }
        }
        if(count($seen)!==($multiline['fragment_count']??null)||count($seen)!==count($multiline['records'])){
            throw new RuntimeException('PI_RDI_CONTEXT_COUNT_HOLD');
        }
        return ['schema'=>'PI_RDI_FISCAL_CONTEXT_V1','fragment_count'=>count($records),
            'organization_candidates'=>count(array_filter($records,static fn($r)=>isset($r['context']['organization']))),
            'subactivity_candidates'=>count(array_filter($records,static fn($r)=>isset($r['context']['subactivity']))),
            'complete_hierarchy_candidates'=>count(array_filter($records,static fn($r)=>!$r['missing_levels'])),
            'cross_page_candidates'=>$counts['CROSS_PAGE_CONTEXT_CANDIDATE']??0,
            'same_page_candidates'=>$counts['SAME_PAGE_CONTEXT_CANDIDATE']??0,
            'held'=>$counts['CONTEXT_HOLD']??0,'events'=>$events,'records'=>$records,
            'numeric_admission'=>0,'production_admission'=>'DENIED'];
    }

    private function heading(string $line): ?array
    {
        if(!preg_match('/^\h*(?:\d{1,2}[.)]\h+)?(Urusan(?:\h+Pemerintahan)?|Bidang\h+Urusan|Organisasi|Unit\h+Organisasi|Unit\h+Kerja|Perangkat\h+Daerah|SKPD|Program|Kegiatan|Sub\h*Kegiatan)\h*:\h*(.*)$/iu',$line,$m))return null;
        $label=strtolower(preg_replace('/\h+/u',' ',trim($m[1])));
        $level=match($label){
            'urusan','urusan pemerintahan'=>'urusan','bidang urusan'=>'bidang',
            'organisasi','perangkat daerah','skpd'=>'organization',
            'unit organisasi','unit kerja'=>'unit','program'=>'program','kegiatan'=>'activity',
            default=>'subactivity'
        };
        $value=trim($m[2]);
        $valid=$value!=='';
        if($level==='urusan'&&preg_match('/^\h*\d{1,2}[.)]\h+/u',$line)){$valid=preg_match('/^\d(?:\.\d{2})?(?:\h|$)/u',$value)===1;}
        if($level==='subactivity'){
            $valid=preg_match('/^\d+(?:\.\d+){5}(?![\d.])(?:\h|$)/u',$value)===1;
        }
        return [$level,$value,$valid];
    }
    /** Separate derived candidates: never fill observed hierarchy or infer OPD. */
    public function enrich(string $text,array $multiline,array $context,string $seedPath): array
    {
        $pages=explode("\f",$text);
        if(array_pop($pages)!==''||count($pages)!==$multiline['pages']||count($context['records'])!==count($multiline['records']))throw new RuntimeException('PI_RDI_DISCOVERY_INPUT_HOLD');
        $raw=file_get_contents($seedPath);$seed=json_decode($raw,true,32,JSON_THROW_ON_ERROR);
        if(($seed['schema']??null)!=='PI_AFFAIR_FIRST_NOMENCLATURE_SEED_V1')throw new RuntimeException('PI_RDI_SEED_HOLD');
        $index=[];foreach($seed['nomenclature'] as $e){if(($e['year']??null)===2026)$index[$e['code']][]=$e;}
        $derived=[];$matches=0;$conflicts=0;$groups=[];$samples=[];$cachedPage=null;$lines=[];$hash=null;
        foreach($multiline['records'] as $i=>$r){
            $c=$context['records'][$i];$p=$r['page'];$l=$r['line'];
            if($c['page']!==$p||$c['line']!==$l||$c['page_text_sha256']!==$r['page_text_sha256'])throw new RuntimeException('PI_RDI_DISCOVERY_LOCATOR_HOLD');
            $sub=$c['context']['subactivity']??null;
            if($sub!==null&&preg_match('/^(\d\.\d{2}\.\d{2}\.\d\.\d{2}\.\d{4})(?:\h|$)/u',$sub['value'],$m)){
                $parts=explode('.',$m[1]);$prefixes=['bidang'=>implode('.',array_slice($parts,0,2)),'program'=>implode('.',array_slice($parts,0,3)),'activity'=>implode('.',array_slice($parts,0,5)),'subactivity'=>$m[1]];
                $hits=[];$issues=[];
                foreach($prefixes as $level=>$code){
                    $explicit=$c['context'][$level]['value']??null;
                    if($explicit!==null&&preg_match('/^(\d+(?:\.\d+)+)(?:\h|$)/u',$explicit,$em)&&$em[1]!==$code)$issues[]='EXPLICIT_'.$level.'_CONFLICT';
                    if(count($index[$code]??[])===1)$hits[$level]=$index[$code][0];
                    elseif(count($index[$code]??[])>1)$issues[]='REGISTRY_'.$level.'_AMBIGUOUS';
                }
                if($hits)$matches++;if($issues)$conflicts++;
                $derived[]=['page'=>$p,'line'=>$l,'subactivity_source'=>$sub,'prefix_candidates'=>$prefixes,'registry_observations'=>$hits,'issues'=>$issues,'state'=>$issues?'HOLD':'PREFIX_CANDIDATE_ONLY','organization_identity'=>'NOT_INFERRED'];
            }
            if(!in_array('SPLIT_SUFFIX_UNRESOLVED',$r['reasons'],true)&&!in_array('COLUMN_TOKEN_COUNT_UNRESOLVED',$r['reasons'],true))continue;
            if($cachedPage!==$p){$lines=preg_split('/\r\n|\n|\r/u',$pages[$p-1]);$hash=strtoupper(hash('sha256',$pages[$p-1]));$cachedPage=$p;}
            if($hash!==$r['page_text_sha256']||strtoupper(hash('sha256',$lines[$l-1]))!==$r['evidence'][0]['sha256'])throw new RuntimeException('PI_RDI_WINDOW_LOCATOR_HOLD');
            $next=$lines[$l]??null;
            $kind=match(true){
                $next===null=>'PAGE_END',
                trim($next)==='' =>'BLANK',
                preg_match('/^\h*\d(?:\.\d+){4}\.(?:\d{5}|\d{3})(?!\d)/u',$next)===1=>'NEXT_ACCOUNT',
                preg_match('/^\h*\d(?:\.\d+){1,}\b/u',$next)===1=>'OTHER_DOTTED_CODE',
                preg_match('/^\h*(?:Kode|Halaman|LAMPIRAN|PENJELASAN|Jumlah|Total|PERATURAN)\b/iu',$next)===1=>'HEADER_OR_TOTAL',
                default=>'OTHER'
            };
            $groups[$kind]=($groups[$kind]??0)+1;
            if(!isset($samples[$kind])){
                $window=[];for($n=max(0,$l-3);$n<min(count($lines),$l+8);$n++)$window[]=['line'=>$n+1,'sha256'=>strtoupper(hash('sha256',$lines[$n])),'text'=>mb_substr($lines[$n],0,400,'UTF-8')];
                $samples[$kind]=['page'=>$p,'line'=>$l,'page_sha256'=>$hash,'window'=>$window];
            }
        }
        return ['schema'=>'PI_RDI_FISCAL_DISCOVERY_V1','seed_sha256'=>strtoupper(hash('sha256',$raw)),'seed_scope'=>$seed['scope'],'decomposed'=>count($derived),'registry_matches'=>$matches,'conflicts'=>$conflicts,'records'=>$derived,'next_line_groups'=>(object)$groups,'samples'=>(object)$samples,'national_master_verified'=>false,'numeric_admission'=>0,'production_admission'=>'DENIED'];
    }

}
