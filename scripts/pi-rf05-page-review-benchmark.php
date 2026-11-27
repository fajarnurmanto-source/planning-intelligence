<?php
declare(strict_types=1);
// Offline review plan or score a separately reviewed page reference.
use App\Services\Pi\Development\RegulatoryAnnexReviewBenchmark;
try{
    if(PHP_SAPI!=='cli'||!in_array($argc,[2,3],true))throw new RuntimeException('PI_RF05_BENCH_ARGUMENT_HOLD');
    $read=static function(string $file,int $limit):array{
        if(!is_file($file)||is_link($file)||filesize($file)>$limit)throw new RuntimeException('PI_RF05_BENCH_FILE_HOLD');
        $data=json_decode(file_get_contents($file),true,64,JSON_THROW_ON_ERROR);
        if(!is_array($data))throw new RuntimeException('PI_RF05_BENCH_FILE_HOLD');
        return $data;
    };
    require_once dirname(__DIR__).'/laravel/app/Services/Pi/Development/RegulatoryAnnexReviewBenchmark.php';
    $corpus=$read($argv[1],200_000_000);$b=new RegulatoryAnnexReviewBenchmark;
    $result=$argc===2?$b->reviewPlan($corpus):$b->evaluate($corpus,$read($argv[2],10_000_000));
    $result['corpus_receipt_sha256']=strtoupper(hash_file('sha256',$argv[1]));
    if($argc===3)$result['reference_receipt_sha256']=strtoupper(hash_file('sha256',$argv[2]));
    echo json_encode($result,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).PHP_EOL;
    exit(($result['state']??null)==='HOLD_NO_GOLD'?1:0);
}catch(Throwable $e){
    fwrite(STDERR,(preg_match('/\API_RF05_[A-Z0-9_]+\z/D',$e->getMessage())?$e->getMessage():'PI_RF05_BENCH_EXECUTION_HOLD').PHP_EOL);exit(2);
}
