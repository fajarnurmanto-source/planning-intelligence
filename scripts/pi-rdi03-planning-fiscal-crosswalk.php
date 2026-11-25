<?php
declare(strict_types=1);
use App\Services\Pi\Development\PlanningFiscalEvidenceCrosswalk;
require_once dirname(__DIR__).'/laravel/app/Services/Pi/Development/PlanningFiscalEvidenceCrosswalk.php';
try{
    if(count($argv)!==3)throw new RuntimeException('PI_RDI03_ARGUMENT_HOLD');
    $read=static function(string $file,string $sha,int $max):array{
        if(!is_file($file)||is_link($file)||filesize($file)>$max)throw new RuntimeException('PI_RDI03_FILE_HOLD');
        $raw=file_get_contents($file);
        if(strtoupper(hash('sha256',$raw))!==$sha)throw new RuntimeException('PI_RDI03_SHA_HOLD');
        return json_decode($raw,true,64,JSON_THROW_ON_ERROR);
    };
    $ps='2F48169648A3B8D3F59CBDCB872E93796561B49D7C4BDB655A6D0D9C6418C5F6';
    $fs='3715A90E85181E8F83621F937025F1CA516F7B29AA8D55E84DFB53AC58812FEC';
    $p=$read($argv[1],$ps,100*1024*1024);$f=$read($argv[2],$fs,250*1024*1024);
    $r=(new PlanningFiscalEvidenceCrosswalk)->build($p,$f);
    $r['input_receipt_sha256']=['planning'=>$ps,'fiscal'=>$fs];
    echo json_encode($r,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)."\n";
}catch(Throwable $e){fwrite(STDERR,$e->getMessage()."\n");exit(1);}
