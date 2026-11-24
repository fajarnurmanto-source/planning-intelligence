<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/laravel/app/Services/Pi/Development/FiscalRecordReconstruction.php';
use App\Services\Pi\Development\FiscalRecordReconstruction;
if(count($argv)!==3||!is_file($argv[1])||is_link($argv[1])||filesize($argv[1])>250*1024*1024
    ||!preg_match('/\A[A-F0-9]{64}\z/D',$argv[2])){fwrite(STDERR,"PI_RDI_FISCAL_RECEIPT_HOLD\n");exit(2);}
try{
    $raw=file_get_contents($argv[1]);
    if(strtoupper(hash('sha256',$raw))!==$argv[2])throw new RuntimeException('PI_RDI_FISCAL_SHA_HOLD');
    $r=json_decode($raw,true,64,JSON_THROW_ON_ERROR);
    if(($r['schema']??null)!=='PI_RDI_01A_CORPUS_RECEIPT_V1'||($r['processed_documents']??null)!==4||!empty($r['holds']))throw new RuntimeException('PI_RDI_FISCAL_CORPUS_HOLD');
    $pins=['YOG-APBD-DETAIL-2026'=>'E4AAA45F33B7254FDB7BBE3A0BC456999FD2CE3E19D8C07B874C640A5E1DEE24','YOG-APBD-DETAIL-CHANGE3-2026'=>'4E5194E6987468E30406911B277BACCA73506B3BF93BE413C0C19784AE3825EA'];
    $out=[];
    foreach($pins as $id=>$sha){
        $doc=$r['documents'][$id]??[];
        if(($doc['source_sha256']??null)!==$sha)throw new RuntimeException('PI_RDI_FISCAL_SOURCE_PIN_HOLD');
        $out[$id]=(new FiscalRecordReconstruction)->reconstruct($doc,str_contains($id,'CHANGE3'));
    }
    echo json_encode(['schema'=>'PI_RDI_FISCAL_CORPUS_V1','input_receipt_sha256'=>$argv[2],'documents'=>$out,'numeric_admission'=>0,'production_admission'=>'DENIED'],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)."\n";
}catch(Throwable $e){fwrite(STDERR,$e->getMessage()."\n");exit(1);}
