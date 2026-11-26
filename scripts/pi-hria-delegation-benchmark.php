<?php
declare(strict_types=1);
// Read-only, zero-spend actual local pinned PDF benchmark. Run from isolated QA clone.
// The owner runtime and its DB are not booted or modified.
use App\Services\Pi\Regulatory\HriaDelegationBenchmark;
use App\Services\Pi\Regulatory\R82NationalEvidencePipeline;
use App\Services\Pi\Regulatory\R82NationalSourceVerifier;
use App\Services\Pi\Regulatory\R82ProvisionTextScanner;
use App\Services\Pi\Regulatory\RegulatoryDelegationCoverage;
use App\Services\Pi\Regulatory\RegulatoryTaxonomyRegistry;

try{
    if(count($argv)!==2||!is_dir($argv[1]))throw new RuntimeException('HRIA_PINNED_SNAPSHOT_ROOT_REQUIRED');
    $laravel=dirname(__DIR__).'/laravel';
    $autoload=getenv('PI_RDI_COMPOSER_AUTOLOAD')?:$laravel.'/vendor/autoload.php';
    require $autoload;
    // Register the isolated checkout App namespace first if using an existing vendor.
    $loader=new \Composer\Autoload\ClassLoader;
    $loader->addPsr4('App\\',$laravel.'/app');
    $loader->register(true);
    $app=new \Illuminate\Foundation\Application($laravel);
    $app->instance('config',new \Illuminate\Config\Repository(['pi'=>['tenant_id'=>'ID-DIY-YOG']]));
    $pipeline=new R82NationalEvidencePipeline(new R82NationalSourceVerifier,new R82ProvisionTextScanner,new RegulatoryDelegationCoverage(new RegulatoryTaxonomyRegistry));
    $verified=$pipeline->build($argv[1]);
    $ref=json_decode(file_get_contents($laravel.'/resources/pi/hria-delegation-reference.json'),true,32,JSON_THROW_ON_ERROR);
    $result=(new HriaDelegationBenchmark)->evaluate($verified,$ref);
    $result['source_manifest_sha256']=$verified['r81_manifest_sha256'];
    $result['source_pins']=array_column($verified['sources'],'pdf_sha256','instrument_id');
    echo json_encode($result,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES).PHP_EOL;
    exit($result['state']==='PASS_POSITIVE_MANDATE_CANDIDATES_ONLY'?0:1);
}catch(Throwable $e){fwrite(STDERR,$e->getMessage().PHP_EOL);exit(2);}
