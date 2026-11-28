<?php
declare(strict_types=1);
// Owner-only CLI. Never prints keys, never modifies vault, DB or original config.
use App\Services\Pi\Regulatory\{R82NationalEvidencePipeline,R82NationalSourceVerifier,R82ProvisionTextScanner,RegulatoryDelegationCoverage,RegulatoryTaxonomyRegistry,RegulatoryGenealogyGraph,HybridPrimaryLegalReasoner,HriaNegativeControls};
use App\Services\Pi\Development\CloudSemanticCandidateProvider;
use App\Services\PlanningIntelligence\AiProviderCredentialVault;

$stage='ARGUMENTS';
try {
    if(PHP_SAPI!=='cli'||!in_array(count($argv),[3,4,5],true))throw new RuntimeException('HRIA_ARGUMENT_HOLD');
    $selected=$argv[3]??'deepseek';
    $previewOnly=($argv[4]??null)==='--preview-only';
    if(count($argv)===5&&!$previewOnly)throw new RuntimeException('HRIA_ARGUMENT_HOLD');
    if(!in_array($selected,['deepseek','gemini','groq','openrouter','openai'],true))throw new RuntimeException('HRIA_PROVIDER_SELECTION_HOLD');
    $runtime=realpath($argv[1]);$root=realpath($argv[2]);
    if(!$runtime||!$root||!is_file($runtime.'/vendor/autoload.php'))throw new RuntimeException('HRIA_PATH_HOLD');
    $stage='RUNTIME_AUTOLOAD';
    if(!chdir($runtime))throw new RuntimeException('HRIA_CWD_HOLD');
    require $runtime.'/vendor/autoload.php';
    $qa=dirname(__DIR__).'/laravel';
    $loader=new \Composer\Autoload\ClassLoader;
    $loader->addPsr4('App\\',$qa.'/app');$loader->register(true);
    $stage='QA_CLASS_LOADING';
    // Explicitly load changed/new classes from the pinned checkout before the original app boots.
    foreach(['R82NationalEvidencePipeline','HriaNegativeControls','HriaDelegationBenchmark','HybridPrimaryLegalReasoner','RegulatoryGenealogyGraph','RegulatoryTaxonomyRegistry','R82NationalSourceVerifier','R82ProvisionTextScanner','RegulatoryDelegationCoverage'] as $class){
        $fqcn='App\\Services\\Pi\\Regulatory\\'.$class;
        if(!class_exists($fqcn,false))require_once $qa.'/app/Services/Pi/Regulatory/'.$class.'.php';
        if(realpath((new ReflectionClass($fqcn))->getFileName())!==realpath($qa.'/app/Services/Pi/Regulatory/'.$class.'.php'))throw new RuntimeException('HRIA_CLASS_ORIGIN_HOLD');
    }
    $stage='QA_PROVIDER_CLASS';
    $fqcn=CloudSemanticCandidateProvider::class;
    if(!class_exists($fqcn,false))require_once $qa.'/app/Services/Pi/Development/CloudSemanticCandidateProvider.php';
    if(realpath((new ReflectionClass($fqcn))->getFileName())!==realpath($qa.'/app/Services/Pi/Development/CloudSemanticCandidateProvider.php'))throw new RuntimeException('HRIA_PROVIDER_CLASS_ORIGIN_HOLD');
    $stage='LARAVEL_MINIMAL_CONTEXT';
    require_once __DIR__.'/pi-hria-runtime-context.php';
    $app=piHriaRuntimeContext($runtime,$qa);
    $stage='TENANT_CONFIG';
    if(config('pi.tenant_id')!=='ID-DIY-YOG')throw new RuntimeException('HRIA_TENANT_HOLD');
    // Existing Laravel configuration and storage are read; no database operation.
    $verifier=new R82NationalSourceVerifier;
    $registry=new RegulatoryTaxonomyRegistry;
    $pipeline=new R82NationalEvidencePipeline($verifier,new R82ProvisionTextScanner,new RegulatoryDelegationCoverage($registry));
    $stage='PDF_VERIFICATION';
    $verified=$verifier->verify($root);
    $stage='PDF_EXTRACTION';
    $pp=$pipeline->extract($verified['documents']['PP-81-2012']['path']);
    $uu=$pipeline->extract($verified['documents']['UU-18-2008']['path']);
    if(!is_string($pp)||!is_string($uu))throw new RuntimeException('HRIA_EXTRACTOR_HOLD');
    $stage='NEGATIVE_CONTROLS';
    $negative=(new HriaNegativeControls)->evaluate($pp,$uu);
    echo json_encode(['negative_controls'=>$negative],JSON_THROW_ON_ERROR).PHP_EOL;
    if($negative['state']!=='PASS_TWO_NEGATIVE_CONTROLS')exit(1);
    // Analysis registry comes exclusively from the pinned QA checkout.
    $stage='R82_PIPELINE';
    $report=$pipeline->build($root,['PP-81-2012'=>$pp,'UU-18-2008'=>$uu],$qa.'/resources/pi');
    $reasoner=new HybridPrimaryLegalReasoner(new RegulatoryGenealogyGraph($registry));
    $stage='PRIMARY_PREVIEW';
    $preview=$reasoner->preview($report,16,'2026-11-26','2026-11-26');
    if($previewOnly){
        echo json_encode(['state'=>'HRIA_OFFLINE_PREVIEW_ONLY','preview'=>$preview,'provider_execution'=>'NOT_RUN','production_admission'=>'DENIED'],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).PHP_EOL;
        exit(0);
    }
    $stage='CREDENTIAL_VAULT';
    $credential=$app->make(AiProviderCredentialVault::class)->resolve('ID-DIY-YOG',$selected);
    if($credential['source']!=='encrypted_vault'||trim($credential['api_key'])===''||trim($credential['model'])==='')throw new RuntimeException('HRIA_VAULT_CONFIG_HOLD');
    $stage='APPROVAL_PREVIEW';
    echo json_encode(['provider'=>$selected,'model'=>$credential['model'],'context_sha256'=>$preview['sha256'],'context'=>$preview['context'],'maximum_requests'=>1,'maximum_output_tokens'=>400,'legal_effect'=>'NOT_ASSESSED'],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).PHP_EOL;
    // Explicit local benchmark approval, not a persistent activation of the web cloud endpoint.
    $approval='APPROVE ONE '.strtoupper($selected).' REQUEST';
    fwrite(STDOUT,"Type $approval to authorize sending the displayed public excerpts and potential provider charges (free tier not verified by PI): ");
    if(trim((string)fgets(STDIN))!==$approval){echo "\nHRIA_LIVE_NOT_AUTHORIZED\n";exit(1);}
    $start=hrtime(true);
    $provider=new CloudSemanticCandidateProvider;
    $liveStage='PROVIDER_PROPOSE';
    try{
        $suggestion=$provider->propose($preview['context'],$selected,$credential['model'],$credential['api_key']);
        $liveStage='REASONER_ASSESS';
        $result=$reasoner->assess($preview,$suggestion);
        $result['provider_execution']='CLOUD_PROVIDER_CALLED';
        $result['latency_ms']=round((hrtime(true)-$start)/1e6);
        $result['provider']=$selected;$result['model']=$credential['model'];
        $result['benchmark_scope']='ARTICLE_16_SINGLE_LIVE_SMOKE_NOT_FULL_ACCURACY';
        $result['accuracy']='REQUIRES_REFERENCE_REVIEW';
        $accepted=($result['model_output_validation']??null)==='PASS'&&($result['state']==='SEMANTIC_CANDIDATE_REVIEW_REQUIRED'||($result['state']==='ABSTAIN'&&$result['reason']==='INSUFFICIENT_SEMANTICS'));
        $result['technical_acceptance']=$accepted?'PASS_VALID_MODEL_RESPONSE':'HOLD_INVALID_MODEL_RESPONSE';
        $result['provider_diagnostic']=$provider->diagnostic();
        $result['token_usage']=$provider->diagnostic()['usage']?:'NOT_REPORTED';
        echo "\n".json_encode($result,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).PHP_EOL;
        exit($accepted?0:1);
    }catch(Throwable $e){
        $known=['R89_PROVIDER_NOT_CONFIGURED','R89_CLOUD_CONTEXT_TOO_LARGE','R89_GEMINI_MODEL_INVALID','R89_PROVIDER_NOT_ALLOWED','R89_CLOUD_PROVIDER_UNAVAILABLE','R89_CLOUD_OUTPUT_INVALID','R89_CLOUD_OUTPUT_CONTRACT_REJECTED'];
        $reason=in_array($e->getMessage(),$known,true)?$e->getMessage():($e instanceof JsonException?'JSON_DECODE_FAILED':($e instanceof \Illuminate\Http\Client\ConnectionException?'TRANSPORT_CONNECTION_FAILED':'UNCLASSIFIED_EXCEPTION'));
        $diagnostic=$provider->diagnostic();
        echo "\n".json_encode(['state'=>'HRIA_PROVIDER_OR_VALIDATION_HOLD','stage'=>$liveStage,'reason'=>$reason,'provider_diagnostic'=>$diagnostic,'exception_class'=>get_class($e),'exception_file'=>basename($e->getFile()),'line'=>$e->getLine(),'latency_ms'=>round((hrtime(true)-$start)/1e6),'provider_execution'=>($diagnostic['request_attempted']??false)?'ATTEMPTED_DELIVERY_UNKNOWN_OR_RESPONSE_RECEIVED':'NOT_ATTEMPTED','legal_effect'=>'NOT_ASSESSED','production_admission'=>'DENIED'],JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES).PHP_EOL;
        exit(1);
    }
 }catch(Throwable $e){
    // Never print arbitrary exception messages, stack traces or configuration.
    $message=$e->getMessage();
    $safe=preg_match('/\A(?:HRIA|R82|R80|PI_AI)_[A-Z0-9_]+\z/D',$message)?$message:'UNCLASSIFIED_EXCEPTION';
    fwrite(STDERR,json_encode(['state'=>'HRIA_LOCAL_PREFLIGHT_HOLD','stage'=>$stage,'reason'=>$safe,'exception_class'=>get_class($e),'exception_file'=>basename($e->getFile()),'line'=>$e->getLine(),'provider_execution'=>'NOT_RUN'],JSON_THROW_ON_ERROR).PHP_EOL);
    exit(2);
}
