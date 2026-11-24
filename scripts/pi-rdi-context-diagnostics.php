<?php
declare(strict_types=1);
ini_set('memory_limit','1024M');
ini_set('display_errors','stderr');
require_once dirname(__DIR__).'/laravel/app/Services/Pi/Development/FiscalContextDiagnostics.php';
use App\Services\Pi\Development\FiscalContextDiagnostics;
try{
    if(count($argv)!==3||!is_file($argv[1])||is_link($argv[1])||filesize($argv[1])>250*1024*1024
        ||!preg_match('/\A[A-F0-9]{64}\z/D',$argv[2]))throw new RuntimeException('PI_RDI_DIAGNOSTIC_INPUT_HOLD');
    $raw=file_get_contents($argv[1]);
    if(strtoupper(hash('sha256',$raw))!==$argv[2])throw new RuntimeException('PI_RDI_DIAGNOSTIC_SHA_HOLD');
    $r=json_decode($raw,true,64,JSON_THROW_ON_ERROR);unset($raw);
    if(($r['schema']??null)!=='PI_RDI_01A_CORPUS_RECEIPT_V1'||($r['processed_documents']??null)!==4||!empty($r['holds'])
        ||($r['numeric_admission']??null)!==0||($r['production_admission']??null)!=='DENIED')throw new RuntimeException('PI_RDI_DIAGNOSTIC_CORPUS_HOLD');
    $out=[];
    foreach(['YOG-APBD-DETAIL-2026','YOG-APBD-DETAIL-CHANGE3-2026'] as $id){
        $out[$id]=(new FiscalContextDiagnostics)->inspect($r['documents'][$id]);
    }
    echo json_encode(['schema'=>'PI_RDI_FISCAL_DIAGNOSTICS_V1','input_receipt_sha256'=>$argv[2],'documents'=>$out,'numeric_admission'=>0,'production_admission'=>'DENIED'],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";
}catch(Throwable $e){fwrite(STDERR,$e->getMessage()."\n");exit(1);}
