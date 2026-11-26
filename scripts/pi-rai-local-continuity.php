<?php
declare(strict_types=1);
use App\Services\Pi\Regulatory\ResilientRegulatoryAcquisition;
require dirname(__DIR__).'/laravel/app/Services/Pi/Regulatory/ResilientRegulatoryAcquisition.php';
try{
    if(count($argv)!==3||!is_dir($argv[1])||is_link($argv[1])||!is_dir($argv[2])||is_link($argv[2]))throw new RuntimeException('PI_RAI_ROOT_HOLD');
    $src=realpath($argv[1]);$root=realpath($argv[2]);
    if($src===$root)throw new RuntimeException('PI_RAI_ROOT_COLLISION');
    $q=json_decode(file_get_contents(dirname(__DIR__).'/laravel/resources/pi/rai-source-registry.json'),true,32,JSON_THROW_ON_ERROR);
    $receipt=$src.'/inventory.json';
    if(!is_file($receipt)||is_link($receipt)||strtoupper(hash_file('sha256',$receipt))!==$q['receipt_sha256'])throw new RuntimeException('PI_RAI_RECEIPT_PIN_HOLD');
    $i=json_decode(file_get_contents($receipt),true,64,JSON_THROW_ON_ERROR);
    if(($i['schema']??null)!=='PI_RF04_CORPUS_INVENTORY_V1'||($i['processed_documents']??null)!==8||($i['expected_documents']??null)!==8||($i['holds']??null)!==[]||($i['production_admission']??null)!=='DENIED')throw new RuntimeException('PI_RAI_RECEIPT_CONTRACT_HOLD');
    $s=new ResilientRegulatoryAcquisition();$results=[];$holds=[];$calls=0;
    // Validate the entire source corpus before writing any candidate.
    foreach($q['documents'] as $d){
        $id=$d['id'];$file=$src.'/'.$id.'.pdf';
        if(($i['documents'][$id]['source_sha256']??null)!==$d['expected_sha256']||($i['documents'][$id]['source_url']??null)!==$d['sources'][0]['url']||!is_file($file)||is_link($file)||strtoupper(hash_file('sha256',$file))!==$d['expected_sha256'])throw new RuntimeException('PI_RAI_CORPUS_DRIFT:'.$id);
    }
    foreach($q['documents'] as $d){
        $id=$d['id'];
        try{
            $import=$s->importPinned($d,$src.'/'.$id.'.pdf',$root);
            $offline=$s->resolve($d,$root,static function()use(&$calls){$calls++;return ['status'=>0];});
            if(!in_array($import['state'],['IMPORTED_PINNED_CANDIDATE_STORED','LOCAL_PINNED_CANDIDATE_AVAILABLE'],true)||$offline['state']!=='LOCAL_PINNED_CANDIDATE_AVAILABLE'||$offline['attempts']!==[])throw new RuntimeException('PI_RAI_OFFLINE_HOLD');
            $results[$id]=['import_state'=>$import['state'],'offline_state'=>$offline['state'],'sha256'=>$d['expected_sha256'],'original_source_url'=>$d['sources'][0]['url'],'origin_receipt_sha256'=>$q['receipt_sha256'],'freshness'=>'UNVERIFIED','legal_effect'=>'NOT_ASSESSED'];
        }catch(Throwable $e){$holds[$id]=$e->getMessage();}
    }
    echo json_encode(['schema'=>'PI_RAI_LOCAL_CONTINUITY_V1','records'=>$results,'holds'=>(object)$holds,'processed'=>count($results),'expected'=>8,'offline_transport_calls'=>$calls,'production_admission'=>'DENIED'],JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES)."\n";
    exit(count($results)===8&&!$holds&&$calls===0?0:1);
}catch(Throwable $e){fwrite(STDERR,$e->getMessage()."\n");exit(2);}
