<?php
declare(strict_types=1);
use App\Services\Pi\Development\PlanningHeaderColumnEvidence;
require_once dirname(__DIR__).'/laravel/app/Services/Pi/Development/PlanningHeaderColumnEvidence.php';
try{
    if(count($argv)!==3||!is_file($argv[1])||is_link($argv[1])||filesize($argv[1])>250*1024*1024)throw new RuntimeException('PI_RDI03C_INPUT_HOLD');
    $pins=['YOG-RENSTRA-PD-2025-2029'=>'FAFF63C7B3D7BB1D8679ED6C53382CC9FAE416FFB3139A04C5200386852DA02D','YOG-RENJA-PD-2026'=>'552E110314EFF5849366C9076D2F379C8E6BD62B85C60111C0605FF28044BC77'];
    $raw=file_get_contents($argv[1]);$sha=strtoupper(hash('sha256',$raw));
    if(($pins[$argv[2]]??null)!==$sha)throw new RuntimeException('PI_RDI03C_SHA_HOLD');
    $doc=json_decode($raw,true,64,JSON_THROW_ON_ERROR);unset($raw);
    if(($doc['source']['id']??null)!==$argv[2])throw new RuntimeException('PI_RDI03C_SOURCE_HOLD');
    $r=(new PlanningHeaderColumnEvidence)->build($doc);$r['input_receipt_sha256']=$sha;
    echo json_encode($r,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)."\n";
}catch(Throwable $e){fwrite(STDERR,$e->getMessage()."\n");exit(1);}
