<?php
declare(strict_types=1);
use App\Services\Pi\Regulatory\RegulatoryAlternativeSourceDiscovery;
use App\Services\Pi\Regulatory\RegulatoryManifestationIntake;
use App\Services\Pi\Regulatory\ResilientRegulatoryAcquisition;
use Symfony\Component\Process\Process;
try{
    if(count($argv)!==3||!is_dir($argv[1])||!is_dir($argv[2])||is_link($argv[1])||is_link($argv[2])||realpath($argv[1])===realpath($argv[2]))throw new RuntimeException('PI_RAI_BENCH_ROOT_HOLD');
    $repo=dirname(__DIR__);
    require getenv('PI_RDI_COMPOSER_AUTOLOAD')?:$repo.'/laravel/vendor/autoload.php';
    foreach(['RegulatoryAlternativeSourceDiscovery','RegulatoryManifestationIntake','ResilientRegulatoryAcquisition'] as $c)require_once $repo.'/laravel/app/Services/Pi/Regulatory/'.$c.'.php';
    $q=json_decode(file_get_contents($repo.'/laravel/resources/pi/rai-source-registry.json'),true,32,JSON_THROW_ON_ERROR);
    $d=array_values(array_filter($q['documents'],static fn($x)=>$x['id']==='UU-23-2014'))[0];
    $calls=0;$local=(new ResilientRegulatoryAcquisition)->resolve($d,$argv[1],static function()use(&$calls){$calls++;return ['status'=>0];});
    if($local['state']!=='LOCAL_PINNED_CANDIDATE_AVAILABLE'||$calls!==0)throw new RuntimeException('PI_RAI_BENCH_LOCAL_HOLD');
    $fetch=static function(string $url,int $max)use($argv):array{
        $tmp=tempnam($argv[2],'fetch-');
        if($tmp===false)throw new RuntimeException('PI_RAI_TEMP_HOLD');
        try{
            $p=new Process(['curl','--silent','--show-error','--proto','=https','--connect-timeout','10','--max-time','90','--max-filesize',(string)$max,'--output',$tmp,'--write-out','%{http_code}',$url]);
            $p->setTimeout(100);$p->run();
            $status=(int)trim($p->getOutput());
            if(!$p->isSuccessful()||$status!==200||filesize($tmp)>$max)return ['status'=>$status,'body'=>null];
            return ['status'=>$status,'body'=>file_get_contents($tmp)];
        }finally{@unlink($tmp);}
    };
    $discovery=(new RegulatoryAlternativeSourceDiscovery)->discover($d['id'],static fn($url)=>$fetch($url,5242880));
    $records=[];$holds=[];
    foreach($discovery['sources'] as $s){
        try{
            $r=$fetch($s['url'],83886080);
            if($r['status']!==200||!is_string($r['body']))throw new RuntimeException('HTTP_'.$r['status']);
            $records[]=(new RegulatoryManifestationIntake)->stage($d['id'],$discovery,$s,$r['body'],$argv[2],$d['expected_sha256']);
        }catch(Throwable $e){$holds[$s['url']]=$e->getMessage();}
    }
    $after=(new ResilientRegulatoryAcquisition)->resolve($d,$argv[1],static function()use(&$calls){$calls++;return ['status'=>0];});
    $roles=array_column($records,'component_role');
    $pass=$discovery['state']==='LINK_CANDIDATES_FOUND'&&in_array('BODY_CANDIDATE',$roles,true)&&in_array('EXPLANATION_CANDIDATE',$roles,true)&&!$holds&&$after['state']==='LOCAL_PINNED_CANDIDATE_AVAILABLE'&&$calls===0;
    echo json_encode(['schema'=>'PI_RAI_LIVE_MANIFESTATION_BENCHMARK_V1','local_state'=>$after['state'],'local_transport_calls'=>$calls,'discovery'=>$discovery,'manifestations'=>$records,'holds'=>(object)$holds,'state'=>$pass?'PASS_LIVE_COMPONENT_INTAKE_CANDIDATE_ONLY':'PARTIAL_HOLD','coverage'=>'NOT_VERIFIED','content_equivalence'=>'NOT_ASSESSED','production_admission'=>'DENIED'],JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES)."\n";
    exit($pass?0:1);
}catch(Throwable $e){fwrite(STDERR,$e->getMessage()."\n");exit(2);}
