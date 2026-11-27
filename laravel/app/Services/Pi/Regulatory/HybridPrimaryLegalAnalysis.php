<?php
declare(strict_types=1);
namespace App\Services\Pi\Regulatory;

use App\Services\Pi\Development\SemanticCandidateProvider;
use App\Services\Pi\Development\SemanticProviderRecovery;
use App\Services\PlanningIntelligence\AiProviderCredentialVault;

final class HybridPrimaryLegalAnalysis
{
    public function __construct(private readonly R82NationalEvidencePipeline $pipeline,
        private readonly HybridPrimaryLegalReasoner $reasoner,
        private readonly SemanticCandidateProvider $provider,
        private readonly AiProviderCredentialVault $vault,
        private readonly SemanticProviderRecovery $recovery){}

    public function preview(int $article,string $caseDate,string $knowledgeDate): array
    {
        return $this->reasoner->preview($this->pipeline->build(),$article,$caseDate,$knowledgeDate);
    }

    public function analyze(int $article,string $caseDate,string $knowledgeDate,string $tenant,string $provider,string $approvedSha,bool $approvedTransfer,bool $approvedCharges): array
    {
        $hold=static fn(string $reason)=>['schema'=>'PI_HRIA_PRIMARY_ANALYSIS_V1','state'=>'ABSTAIN','reason'=>$reason,'provider_execution'=>'NOT_RUN','legal_effect'=>'NOT_ASSESSED','production_admission'=>'DENIED'];
        if($tenant!=='ID-DIY-YOG'||!$approvedTransfer||!$approvedCharges)return $hold('AUTHORIZATION_HOLD');
        if(!config('pi.semantic_cloud.enabled',false))return $hold('CLOUD_DISABLED');
        if(!isset(AiProviderCredentialVault::PROVIDERS[$provider]))return $hold('PROVIDER_NOT_ALLOWED');
        $preview=$this->preview($article,$caseDate,$knowledgeDate);
        if(!hash_equals($preview['sha256'],strtoupper($approvedSha)))return $hold('PREVIEW_DRIFT');
        try{$settings=$this->vault->resolve($tenant,$provider);}catch(\Throwable){return $hold('CREDENTIAL_UNAVAILABLE');}
        $key=$settings['api_key']??'';$model=$settings['model']??'';
        if(!is_string($key)||trim($key)===''||!is_string($model)||!preg_match('~\A[A-Za-z0-9_.:/-]{1,100}\z~D',$model))return $hold('PROVIDER_NOT_CONFIGURED');
        try{
            $call=$this->recovery->invoke($this->provider,$tenant,$provider,$model,$key,$preview['context']);
            $metadata=['provider_execution'=>$call['provider_execution'],'provider'=>$provider,'model'=>$model,'context_sha256'=>$preview['sha256'],'recovery'=>$call['recovery']];
            if($call['state']!=='RESPONSE')return array_replace($hold($call['recovery']['failure_class']),$metadata);
            return $this->reasoner->assess($preview,$call['proposal'])+$metadata;
        }catch(\Throwable){return array_replace($hold('PROVIDER_OR_VALIDATION_HOLD'),['provider_execution'=>'CLOUD_PROVIDER_ATTEMPTED']);}
    }
}
