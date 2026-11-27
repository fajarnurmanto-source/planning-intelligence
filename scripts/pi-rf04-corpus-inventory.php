<?php
declare(strict_types=1);
use App\Services\Pi\Development\FullDocumentInventory;
use App\Services\Pi\Development\NativePdfAnnexIntelligence;
use App\Services\Pi\Development\RegulatoryAnnexBenchmarkProfile;
use Symfony\Component\Process\Process;
try{
    $localPins=count($argv)===3&&$argv[2]==='--local-pins';
    if((count($argv)!==2&&!$localPins)||!is_dir($argv[1])||is_link($argv[1]))throw new RuntimeException('PI_RF04_INPUT_HOLD');
    $root=dirname(__DIR__);require getenv('PI_RDI_COMPOSER_AUTOLOAD')?:$root.'/laravel/vendor/autoload.php';
    foreach(['FullDocumentInventory','NativePdfAnnexIntelligence','RegulatoryAnnexBenchmarkProfile'] as $c)require_once $root.'/laravel/app/Services/Pi/Development/'.$c.'.php';
    $queue=json_decode(file_get_contents($root.'/laravel/resources/pi/rf04-primary-acquisition.json'),true,32,JSON_THROW_ON_ERROR);
    $pins=[];
    if($localPins){
        $registry=json_decode(file_get_contents($root.'/laravel/resources/pi/rai-source-registry.json'),true,32,JSON_THROW_ON_ERROR);
        if(($registry['schema']??null)!=='PI_RAI_SOURCE_REGISTRY_V1'||count($registry['documents']??[])!==count($queue['documents']))throw new RuntimeException('PI_RF04_PIN_REGISTRY_HOLD');
        $pins=array_column($registry['documents'],null,'id');
    }
    $docs=[];$holds=[];
    foreach($queue['documents'] as $d){
        $id=$d['id'];$file=$argv[1].DIRECTORY_SEPARATOR.$id.'.pdf';
        try{
            if($localPins){
                $pin=$pins[$id]['expected_sha256']??null;
                if(!is_string($pin)||!preg_match('/^[A-F0-9]{64}$/D',$pin)||($pins[$id]['sources'][0]['url']??null)!==$d['pdf_url'])throw new RuntimeException('PI_RF04_PIN_IDENTITY_HOLD');
                $file=$argv[1].DIRECTORY_SEPARATOR.$pin.'.pdf';
            }
            if(!is_file($file)||is_link($file)||filesize($file)<128||filesize($file)>80*1024*1024)throw new RuntimeException('PI_RF04_PDF_MISSING_OR_SIZE_HOLD');
            $f=fopen($file,'rb');$magic=fread($f,8);fseek($f,-min(4096,filesize($file)),SEEK_END);$tail=stream_get_contents($f);fclose($f);
            if(!preg_match('/^%PDF-[12]\./',$magic)||!str_contains($tail,'%%EOF'))throw new RuntimeException('PI_RF04_NON_PDF_HOLD');
            $sha=strtoupper(hash_file('sha256',$file));
            if($localPins&&$sha!==$pin)throw new RuntimeException('PI_RF04_LOCAL_PIN_DRIFT');
            $pages=null;
            foreach((new NativePdfAnnexIntelligence)->extractorCommands($file) as $cmd){
                $a=$cmd['argv'];$i=count($a)-6;$info=preg_replace('/pdftotext(\.exe)?$/i','pdfinfo$1',$a[$i]);
                try{$p=new Process(array_merge(array_slice($a,0,$i),[$info,$a[count($a)-2]]));$p->setTimeout(30);$p->run();if($p->isSuccessful()&&preg_match('/^Pages:\h+(\d+)\h*$/m',$p->getOutput(),$m)){$pages=(int)$m[1];break;}}catch(Throwable){}
            }
            if(!$pages)throw new RuntimeException('PI_RF04_PDFINFO_HOLD');
            $inventory=(new FullDocumentInventory)->read($file,$sha,$pages);
            $docs[$id]=['source_url'=>$d['pdf_url'],'source_sha256'=>$sha,'byte_pin_status'=>$localPins?'OWNER_OBSERVED_PIN_MATCH':'OBSERVED_LOCAL_BYTES_NOT_AUTHENTICATED','identity_status'=>'REVIEW_REQUIRED','inventory'=>$inventory,'format_profile'=>(new RegulatoryAnnexBenchmarkProfile)->profile($inventory)];
        }catch(Throwable $e){$holds[$id]=$e->getMessage();}
    }
    echo json_encode(['schema'=>'PI_RF04_CORPUS_INVENTORY_V1','documents'=>$docs,'holds'=>(object)$holds,'expected_documents'=>count($queue['documents']),'processed_documents'=>count($docs),'gold_records'=>0,'source_mode'=>$localPins?'LOCAL_PINNED_REPOSITORY':'NAMED_PDF_CORPUS','network_requests'=>0,'production_admission'=>'DENIED'],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE)."\n";
    exit($holds?1:0);
}catch(Throwable $e){fwrite(STDERR,$e->getMessage()."\n");exit(2);}
