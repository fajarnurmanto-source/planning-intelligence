<?php
declare(strict_types=1);
use App\Services\Pi\Development\PlanningSemanticCandidates;
require_once dirname(__DIR__).'/laravel/app/Services/Pi/Development/PlanningSemanticCandidates.php';
try{
    if(count($argv)!==2||!is_dir($argv[1])||is_link($argv[1]))throw new RuntimeException('PI_RDI02D_INPUT_HOLD');
    $pins=[
        'YOG-RPJPD-2025-2045'=>'BE46B89A43DBD4AB3EE7C97C153AD033E946380CA90840DB75BC8BA76FA048AD',
        'YOG-RPJMD-2025-2029'=>'8318B2DA8195DB9E93FE2AA92E4D451C9052473FF866EE88AFF315F718E27845',
        'YOG-RKPD-2026'=>'65F27828D769BD4263A94F1433AF88E9D44DA887AB50D46BFB938B4ABA52A962',
        'YOG-RENSTRA-PD-2025-2029'=>'5FE3760DB227E796E3B3B49EA1388BCF81876592A8F658B3A55176B1D8F99BD1',
        'YOG-RENJA-PD-2026'=>'A9E8FF6E1D834A542AF1D4E08ECD229CE3CAF7FBFC6F2301470D61401144D660'];
    $docs=[];
    foreach($pins as $id=>$sha){
        $file=$argv[1].DIRECTORY_SEPARATOR.$id.'.evidence.json';
        if(!is_file($file)||is_link($file)||filesize($file)>100*1024*1024)throw new RuntimeException('PI_RDI02D_FILE_HOLD:'.$id);
        $raw=file_get_contents($file);
        if(strtoupper(hash('sha256',$raw))!==$sha)throw new RuntimeException('PI_RDI02D_SHA_HOLD:'.$id);
        $docs[$id]=json_decode($raw,true,64,JSON_THROW_ON_ERROR);unset($raw);
    }
    $r=(new PlanningSemanticCandidates)->build($docs);
    $r['input_receipt_sha256']=$pins;
    echo json_encode($r,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)."\n";
}catch(Throwable $e){fwrite(STDERR,$e->getMessage()."\n");exit(1);}
