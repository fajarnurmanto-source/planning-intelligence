<?php
declare(strict_types=1);
ini_set('display_errors','stderr');
ini_set('log_errors','0');
error_reporting(E_ALL);
// Bounded CLI-only memory budget for decoded receipt plus reconstructed records.
ini_set('memory_limit','1024M');
$reserve=str_repeat(' ',65536);
register_shutdown_function(static function () use (&$reserve): void {
    $reserve=null;
    $e=error_get_last();
    if($e!==null && in_array($e['type'],[E_ERROR,E_PARSE,E_CORE_ERROR,E_COMPILE_ERROR],true)){
        fwrite(STDERR,'PI_RDI_FISCAL_FATAL='.$e['message']."\n");
    }
});
require_once dirname(__DIR__).'/laravel/app/Services/Pi/Development/FiscalRecordReconstruction.php';
use App\Services\Pi\Development\FiscalRecordReconstruction;
if(count($argv)!==3||!is_file($argv[1])||is_link($argv[1])||filesize($argv[1])>250*1024*1024
    ||!preg_match('/\A[A-F0-9]{64}\z/D',$argv[2])){fwrite(STDERR,"PI_RDI_FISCAL_RECEIPT_HOLD\n");exit(2);}
try{
    $raw=file_get_contents($argv[1]);
    if(strtoupper(hash('sha256',$raw))!==$argv[2])throw new RuntimeException('PI_RDI_FISCAL_SHA_HOLD');
    fwrite(STDERR,'PI_RDI_RECEIPT_BYTES='.strlen($raw)."\n");
    $r=json_decode($raw,true,64,JSON_THROW_ON_ERROR);
    unset($raw);
    fwrite(STDERR,'PI_RDI_RECEIPT_DECODED=PASS'."\n");
    if(($r['schema']??null)!=='PI_RDI_01A_CORPUS_RECEIPT_V1'||($r['processed_documents']??null)!==4||!empty($r['holds']))throw new RuntimeException('PI_RDI_FISCAL_CORPUS_HOLD');
    $pins=['YOG-APBD-DETAIL-2026'=>'E4AAA45F33B7254FDB7BBE3A0BC456999FD2CE3E19D8C07B874C640A5E1DEE24','YOG-APBD-DETAIL-CHANGE3-2026'=>'4E5194E6987468E30406911B277BACCA73506B3BF93BE413C0C19784AE3825EA'];
    $out=[];
    foreach($pins as $id=>$sha){
        $doc=$r['documents'][$id]??[];
        if(($doc['source_sha256']??null)!==$sha)throw new RuntimeException('PI_RDI_FISCAL_SOURCE_PIN_HOLD');
        fwrite(STDERR,'PI_RDI_FISCAL_STAGE='.$id."\n");
        $out[$id]=(new FiscalRecordReconstruction)->reconstruct($doc,str_contains($id,'CHANGE3'));
        unset($doc,$r['documents'][$id]);
    }
    echo json_encode(['schema'=>'PI_RDI_FISCAL_CORPUS_V1','input_receipt_sha256'=>$argv[2],'documents'=>$out,'numeric_admission'=>0,'production_admission'=>'DENIED'],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)."\n";
}catch(Throwable $e){fwrite(STDERR,'PI_RDI_FISCAL_EXCEPTION='.get_class($e).'|'.$e->getMessage()."\n");exit(1);}
