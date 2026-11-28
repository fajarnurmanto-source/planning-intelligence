<?php
declare(strict_types=1);
use App\Services\Pi\Development\NativePdfAnnexIntelligence;
use App\Services\Pi\Development\PlanningPageProfile;
use Symfony\Component\Process\Process;
$root=dirname(__DIR__);
try{
    if(count($argv)!==8||!is_file($argv[2])||is_link($argv[2])||filesize($argv[2])<128||filesize($argv[2])>128*1024*1024||!preg_match('/\A[A-F0-9]{64}\z/D',$argv[3]))throw new RuntimeException('PI_RDI02_INPUT_HOLD');
    $queue=json_decode(file_get_contents($root.'/laravel/resources/pi/rdi02-planning-sources.json'),true,32,JSON_THROW_ON_ERROR);
    $catalogue=json_decode(file_get_contents($root.'/laravel/resources/pi/official-source-metadata-r100.json'),true,64,JSON_THROW_ON_ERROR);
    $source=null;$identity=null;
    foreach($queue['documents'] as $d)if($d['id']===$argv[1])$source=$d;
    foreach($catalogue['documents'] as $d)if($d['id']===$argv[1]&&$d['lane']==='PLANNING')$identity=$d;
    if(!$source||!$identity||strtoupper(hash_file('sha256',$argv[2]))!==$argv[3])throw new RuntimeException('PI_RDI02_SOURCE_HOLD');
    $f=fopen($argv[2],'rb');$magic=fread($f,8);fseek($f,-min(4096,filesize($argv[2])),SEEK_END);$tail=stream_get_contents($f);fclose($f);
    if(!preg_match('/^%PDF-[12]\./',$magic)||!str_contains($tail,'%%EOF'))throw new RuntimeException('PI_RDI02_NON_PDF_HOLD');
    require getenv('PI_RDI_COMPOSER_AUTOLOAD')?:$root.'/laravel/vendor/autoload.php';
    require_once $root.'/laravel/app/Services/Pi/Development/NativePdfAnnexIntelligence.php';
    require_once $root.'/laravel/app/Services/Pi/Development/PlanningPageProfile.php';
    require_once $root.'/laravel/app/Services/Pi/Development/PlanningNomenclatureEvidence.php';
    if(!is_file($argv[4])||is_link($argv[4])||filesize($argv[4])>30_000_000||strtoupper(hash_file('sha256',$argv[4]))!==$argv[5])throw new RuntimeException('PI_RDI02C_BASELINE_SHA_HOLD');
    $prior=json_decode(file_get_contents($argv[4]),true,64,JSON_THROW_ON_ERROR);
    if(($prior['schema']??null)!=='PI_RDI02_PLANNING_PROFILE_V1'||($prior['source_sha256']??null)!==$argv[3]||($prior['source']['id']??null)!==$argv[1]||($prior['production_admission']??null)!=='DENIED')throw new RuntimeException('PI_RDI02C_BASELINE_HOLD');
    $baseline=[];foreach($prior['pages'] as $page)$baseline[$page['page']]=$page['text_sha256'];
    require_once $root.'/laravel/app/Services/Pi/Development/AdaptivePlanningTableEvidence.php';
    if(!is_file($argv[6])||is_link($argv[6])||filesize($argv[6])>100*1024*1024||strtoupper(hash_file('sha256',$argv[6]))!==$argv[7])throw new RuntimeException('PI_RDI03B_EVIDENCE_SHA_HOLD');
    $old=json_decode(file_get_contents($argv[6]),true,64,JSON_THROW_ON_ERROR);
    if(($old['schema']??null)!=='PI_RDI02C_NOMENCLATURE_EVIDENCE_V1'||($old['source_sha256']??null)!==$argv[3]||($old['baseline_sha256']??null)!==$argv[5]||($old['source']['id']??null)!==$argv[1]||($old['production_admission']??null)!=='DENIED')throw new RuntimeException('PI_RDI03B_EVIDENCE_HOLD');
    $anchors=[];$expected=0;
    foreach($old['code_occurrences'] as $row){if($row['state']!=='CODE_ROW_CANDIDATE')continue;if(isset($anchors[$row['page']][$row['line']]))throw new RuntimeException('PI_RDI03B_DUPLICATE_ANCHOR');$anchors[$row['page']][$row['line']]=$row;$expected++;}
    unset($old);
    $evidence=[];$evidenceCounts=[];$evidenceSamples=[];$noText=[];$headings=[];
    $engine=null;$pages=0;
    foreach((new NativePdfAnnexIntelligence)->extractorCommands($argv[2]) as $candidate){
        $a=$candidate['argv'];$i=count($a)-6;
        $info=preg_replace('/pdftotext(\.exe)?$/i','pdfinfo$1',$a[$i]);
        try{$p=new Process(array_merge(array_slice($a,0,$i),[$info,$a[count($a)-2]]));$p->setTimeout(45);$p->run();
            if($p->isSuccessful()&&preg_match('/^Pages:\h+(\d+)\h*$/m',$p->getOutput(),$m)){$pages=(int)$m[1];$engine=$candidate;break;}
        }catch(Throwable){}
    }
    if(!$engine||$pages<1||$pages>10000)throw new RuntimeException('PI_RDI02_PDFINFO_HOLD');
    if(count($baseline)!==$pages||$prior['physical_pages']!==$pages)throw new RuntimeException('PI_RDI02C_PAGE_COUNT_HOLD');
    $records=[];$counts=[];$samples=[];$identitySignal=false;
    for($first=1;$first<=$pages;$first+=100){
        $last=min($pages,$first+99);$a=$engine['argv'];array_splice($a,count($a)-2,0,['-f',(string)$first,'-l',(string)$last]);
        $p=new Process($a);$p->setTimeout(180);$body='';
        $p->run(static function($type,$chunk)use(&$body){if($type!==Process::OUT)return;if(strlen($body)+strlen($chunk)>20_000_000)throw new RuntimeException('PI_RDI02_BATCH_BYTES_HOLD');$body.=$chunk;});
        if(!$p->isSuccessful())throw new RuntimeException('PI_RDI02_EXTRACT_HOLD:'.$first);
        $e=(new \App\Services\Pi\Development\AdaptivePlanningTableEvidence)->scan($body,$first,$last,$baseline,$anchors);
        foreach($e['records'] as $row)$evidence[]=$row;
        foreach($e['counts'] as $key=>$count)$evidenceCounts[$key]=($evidenceCounts[$key]??0)+$count;
        foreach($e['samples'] as $key=>$sample)if(!isset($evidenceSamples[$key]))$evidenceSamples[$key]=$sample;
        unset($body,$p,$e);
    }
    if(count($evidence)!==$expected||strtoupper(hash_file('sha256',$argv[2]))!==$argv[3])throw new RuntimeException('PI_RDI03B_COUNT_OR_SOURCE_DRIFT');
    echo json_encode(['schema'=>'PI_RDI03B_ADAPTIVE_TABLE_EVIDENCE_V1','source'=>$source,'source_sha256'=>$argv[3],'baseline_sha256'=>$argv[5],'input_evidence_sha256'=>$argv[7],'physical_pages'=>$pages,'occurrences'=>count($evidence),'counts'=>$evidenceCounts,'samples'=>$evidenceSamples,'records'=>$evidence,'numeric_admission'=>0,'numeric_comparison'=>'BLOCKED','production_admission'=>'DENIED'],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)."\n";
}catch(Throwable $e){fwrite(STDERR,$e->getMessage()."\n");exit(1);}
