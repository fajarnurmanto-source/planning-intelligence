<?php
declare(strict_types=1);
// Read-only local corpus inventory; output JSON to stdout, no admission or DB.
$base=dirname(__DIR__);
require $base.'/laravel/vendor/autoload.php';
use App\Services\Pi\Development\FullDocumentInventory;
use App\Services\Pi\Development\BpkQuarantineReadOnlyReview;
use App\Services\Pi\Regulatory\R82NationalSourceVerifier;
use Symfony\Component\Process\Process;
$args=[];
foreach(array_slice($argv,1) as $arg){
    if(!preg_match('/^--(national-root|bpk-root|pdftotext|pdfinfo)=(.+)$/D',$arg,$m)||isset($args[$m[1]])){
        fwrite(STDERR,"PI_RDI_UNKNOWN_PARAMETER_HOLD\n");exit(2);
    }
    $args[$m[1]]=$m[2];
}
$local=getenv('LOCALAPPDATA');
$national=$args['national-root']??dirname($base).'/08_RUNTIME/R81_NATIONAL_PDF_SNAPSHOTS';
$bpk=$args['bpk-root']??($local?$local.'/PlanningIntelligence/R104_BPK_PDF_QUARANTINE':'');
$docs=[];$errors=[];$results=[];
try{
    $r=(new R82NationalSourceVerifier)->verify($national);
    foreach($r['documents'] as $id=>$d){$docs[$id]=['path'=>$d['path'],'sha'=>$d['sha256']];}
}catch(Throwable $e){$errors['national_pair']=$e->getMessage();}
try{
    $res=$base.'/laravel/resources/pi/';
    $r=(new BpkQuarantineReadOnlyReview($res.'r105-owner-bpk-byte-pins.json',$res.'official-source-metadata-r100.json',$res.'bpk-perwal12-dlh-0004-fiscal-candidate-r103.json'))->inspect($bpk);
    foreach($r['sources'] as $id=>$d){$docs[$id]=['path'=>$bpk.'/'.$id.'/'.strtolower($d['sha256']).'.pdf','sha'=>$d['sha256']];}
}catch(Throwable $e){$errors['bpk_pair']=$e->getMessage();}
foreach($docs as $id=>$d){
    try{
        $infoCommands=[];
        if(isset($args['pdfinfo'])){$infoCommands[]=[$args['pdfinfo'],$d['path']];}
        else {
            foreach((new \App\Services\Pi\Development\NativePdfAnnexIntelligence)->extractorCommands($d['path'],$args['pdftotext']??null) as $cmd){
                $a=$cmd['argv'];$index=count($a)-6;
                if($index<0)continue;
                $exe=$a[$index];
                $info=preg_replace('/pdftotext(\.exe)?$/i','pdfinfo$1',$exe);
                if($info===$exe)continue;
                $infoCommands[]=array_merge(array_slice($a,0,$index),[$info,$a[count($a)-2]]);
            }
        }
        $pages=null;
        foreach($infoCommands as $command){
            try{$p=new Process($command);$p->setTimeout(30);$p->run();if($p->isSuccessful()&&preg_match('/^Pages:\h+(\d+)\h*$/m',$p->getOutput(),$m)){$pages=(int)$m[1];break;}}catch(Throwable){}
        }
        if(!$pages)throw new RuntimeException('PI_RDI_INDEPENDENT_PDFINFO_PAGE_COUNT_HOLD');
        $r=(new FullDocumentInventory)->read($d['path'],$d['sha'],$pages,$args['pdftotext']??null);
        $results[$id]=$r;
    }catch(Throwable $e){$errors[$id]=$e->getMessage();}
}
echo json_encode(['schema'=>'PI_RDI_01A_CORPUS_RECEIPT_V1','expected_documents'=>4,'processed_documents'=>count($results),'documents'=>$results,'holds'=>$errors,'numeric_admission'=>0,'production_admission'=>'DENIED'],JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE|JSON_THROW_ON_ERROR)."\n";
exit(count($results)===4&&!$errors?0:1);
