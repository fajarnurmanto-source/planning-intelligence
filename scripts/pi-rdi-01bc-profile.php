<?php
declare(strict_types=1);
// Local receipt only, no network, database, or source writes.
require_once dirname(__DIR__).'/laravel/app/Services/Pi/Development/DocumentStructureProfile.php';
use App\Services\Pi\Development\DocumentStructureProfile;
if(count($argv)!==3||!preg_match('/\A[A-F0-9]{64}\z/D',$argv[2])
    ||!is_file($argv[1])||is_link($argv[1])||filesize($argv[1])>250*1024*1024){
    fwrite(STDERR,"PI_RDI_PROFILE_RECEIPT_HOLD\n");exit(2);
}
try{
    $raw=file_get_contents($argv[1]);
    if(!is_string($raw)||strtoupper(hash('sha256',$raw))!==$argv[2])throw new RuntimeException('PI_RDI_PROFILE_RECEIPT_SHA_HOLD');
    $r=json_decode($raw,true,64,JSON_THROW_ON_ERROR);
    if(($r['schema']??null)!=='PI_RDI_01A_CORPUS_RECEIPT_V1'||($r['processed_documents']??null)!==4
        ||count($r['documents']??[])!==4||!empty($r['holds']))throw new RuntimeException('PI_RDI_PROFILE_CORPUS_HOLD');
    $profiles=[];
    foreach($r['documents'] as $id=>$doc){$profiles[$id]=(new DocumentStructureProfile)->profile($doc);}
    echo json_encode(['schema'=>'PI_RDI_01BC_CORPUS_PROFILE_V1','input_receipt_sha256'=>$argv[2],
        'documents'=>$profiles,'production_admission'=>'DENIED'],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)."\n";
}catch(Throwable $e){fwrite(STDERR,$e->getMessage()."\n");exit(1);}
