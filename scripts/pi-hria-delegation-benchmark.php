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
    $pins=(new R82NationalSourceVerifier)->verify($argv[1]);
    $texts=[];
    foreach($pins['documents'] as $id=>$source){
        $body=$pipeline->extract($source['path']);
        if(!is_string($body)||$body===''||!preg_match('/\p{L}/u',$body))throw new RuntimeException('HRIA_LOCAL_TEXT_HOLD');
        $texts[$id]=$body;
    }
    $verified=$pipeline->build($argv[1],$texts,$laravel.'/resources/pi');
    if($verified['r81_manifest_sha256']!==$pins['manifest_sha256'])throw new RuntimeException('HRIA_MANIFEST_DRIFT');
    $ref=json_decode(file_get_contents($laravel.'/resources/pi/hria-delegation-reference.json'),true,32,JSON_THROW_ON_ERROR);
    $result=(new HriaDelegationBenchmark)->evaluate($verified,$ref);
    $negative=(new \App\Services\Pi\Regulatory\HriaNegativeControls)->evaluate($texts['PP-81-2012'],$texts['UU-18-2008']);
    $reasoner=new \App\Services\Pi\Regulatory\HybridPrimaryLegalReasoner(new \App\Services\Pi\Regulatory\RegulatoryGenealogyGraph(new RegulatoryTaxonomyRegistry));
    $result['negative_controls']=$negative;
    $result['operative_previews']=[];
    foreach($ref['cases'] as $case){
        $article=$case['article'];
        try{
            $preview=$reasoner->preview($verified,$article,'2026-11-26','2026-11-26');
            $result['operative_previews'][]=['article'=>$article,'state'=>'REVIEW_CANDIDATE_ONLY','context_sha256'=>$preview['sha256'],
                'retrieval'=>$preview['operative_retrieval'],'candidates'=>array_values(array_filter($preview['context']['evidence'],static fn($p)=>$p['role']==='PP_BODY_LEXICAL_CANDIDATE'))];
        }catch(RuntimeException $e){
            $result['operative_previews'][]=['article'=>$article,'state'=>'ABSTAIN','reason'=>preg_match('/\AHRIA_[A-Z0-9_]+\z/D',$e->getMessage())?$e->getMessage():'HRIA_PREVIEW_HOLD'];
        }
    }
    $result['operative_preview_count']=count(array_filter($result['operative_previews'],static fn($r)=>$r['state']==='REVIEW_CANDIDATE_ONLY'));
    $result['operative_reference_accuracy']='NOT_ASSESSED_NO_INDEPENDENT_PP_BODY_GOLD';
    $result['network_requests']=0;
    $result['source_manifest_sha256']=$verified['r81_manifest_sha256'];
    $result['source_pins']=array_column($verified['sources'],'pdf_sha256','instrument_id');
    echo json_encode($result,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES).PHP_EOL;
    exit($result['state']==='PASS_POSITIVE_MANDATE_CANDIDATES_ONLY'&&$negative['state']==='PASS_TWO_NEGATIVE_CONTROLS'?0:1);
}catch(Throwable $e){fwrite(STDERR,$e->getMessage().PHP_EOL);exit(2);}
