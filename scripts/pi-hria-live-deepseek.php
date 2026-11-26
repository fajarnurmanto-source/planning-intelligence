<?php
declare(strict_types=1);
// Owner-only CLI. Never prints keys, never modifies vault, DB or original config.
use App\Services\Pi\Regulatory\{R82NationalEvidencePipeline,R82NationalSourceVerifier,R82ProvisionTextScanner,RegulatoryDelegationCoverage,RegulatoryTaxonomyRegistry,RegulatoryGenealogyGraph,HybridPrimaryLegalReasoner,HriaNegativeControls};
use App\Services\Pi\Development\CloudSemanticCandidateProvider;
use App\Services\PlanningIntelligence\AiProviderCredentialVault;

try {
    if(PHP_SAPI!=='cli'||count($argv)!==3)throw new RuntimeException('HRIA_ARGUMENT_HOLD');
    $runtime=realpath($argv[1]);$root=realpath($argv[2]);
    if(!$runtime||!$root||!is_file($runtime.'/vendor/autoload.php'))throw new RuntimeException('HRIA_PATH_HOLD');
    require $runtime.'/vendor/autoload.php';
    $qa=dirname(__DIR__).'/laravel';
    $loader=new \Composer\Autoload\ClassLoader;
    $loader->addPsr4('App\\',$qa.'/app');$loader->register(true);
    $app=require $runtime.'/bootstrap/app.php';
    $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    if(config('pi.tenant_id')!=='ID-DIY-YOG')throw new RuntimeException('HRIA_TENANT_HOLD');
    // Existing Laravel configuration and storage are read; no database operation.
    $verifier=new R82NationalSourceVerifier;
    $registry=new RegulatoryTaxonomyRegistry;
    $pipeline=new R82NationalEvidencePipeline($verifier,new R82ProvisionTextScanner,new RegulatoryDelegationCoverage($registry));
    $verified=$verifier->verify($root);
    $pp=$pipeline->extract($verified['documents']['PP-81-2012']['path']);
    $uu=$pipeline->extract($verified['documents']['UU-18-2008']['path']);
    if(!is_string($pp)||!is_string($uu))throw new RuntimeException('HRIA_EXTRACTOR_HOLD');
    $negative=(new HriaNegativeControls)->evaluate($pp,$uu);
    echo json_encode(['negative_controls'=>$negative],JSON_THROW_ON_ERROR).PHP_EOL;
    if($negative['state']!=='PASS_TWO_NEGATIVE_CONTROLS')exit(1);
    // The original app resource path contains the already accepted R80/R74 registry.
    $report=$pipeline->build($root);
    $reasoner=new HybridPrimaryLegalReasoner(new RegulatoryGenealogyGraph($registry));
    $preview=$reasoner->preview($report,16,'2026-11-26','2026-11-26');
    $credential=$app->make(AiProviderCredentialVault::class)->resolve('ID-DIY-YOG','deepseek');
    if($credential['source']!=='encrypted_vault'||trim($credential['api_key'])===''||trim($credential['model'])==='')throw new RuntimeException('HRIA_VAULT_CONFIG_HOLD');
    echo json_encode(['provider'=>'deepseek','model'=>$credential['model'],'context_sha256'=>$preview['sha256'],'context'=>$preview['context'],'maximum_requests'=>1,'maximum_output_tokens'=>400,'legal_effect'=>'NOT_ASSESSED'],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).PHP_EOL;
    // Explicit local benchmark approval, not a persistent activation of the web cloud endpoint.
    fwrite(STDOUT,"Type APPROVE ONE DEEPSEEK REQUEST to authorize sending the displayed public excerpts and provider charges: ");
    if(trim((string)fgets(STDIN))!=='APPROVE ONE DEEPSEEK REQUEST'){echo "\nHRIA_LIVE_NOT_AUTHORIZED\n";exit(1);}
    $start=hrtime(true);
    try{
        $suggestion=(new CloudSemanticCandidateProvider)->propose($preview['context'],'deepseek',$credential['model'],$credential['api_key']);
        $result=$reasoner->assess($preview,$suggestion);
        $result['provider_execution']='CLOUD_PROVIDER_CALLED';
        $result['latency_ms']=round((hrtime(true)-$start)/1e6);
        $result['provider']='deepseek';$result['model']=$credential['model'];
        $result['benchmark_scope']='ARTICLE_16_SINGLE_LIVE_SMOKE_NOT_FULL_ACCURACY';
        $result['accuracy']='REQUIRES_REFERENCE_REVIEW';
        $result['token_usage']='NOT_EXPOSED_BY_EXISTING_ADAPTER';
        echo "\n".json_encode($result,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).PHP_EOL;
        exit($result['state']==='SEMANTIC_CANDIDATE_REVIEW_REQUIRED'?0:1);
    }catch(Throwable){echo "\nHRIA_PROVIDER_OR_VALIDATION_HOLD\n";exit(1);}
}catch(Throwable){fwrite(STDERR,"HRIA_LOCAL_PREFLIGHT_HOLD\n");exit(2);}
