<?php
declare(strict_types=1);
namespace App\Services\Pi\Development;

use Illuminate\Support\Facades\Cache;
use Throwable;

/** APR-001. Shared paid-provider isolation. No automatic retry or substitution. */
final class SemanticProviderRecovery
{
    public function invoke(SemanticCandidateProvider $adapter,string $tenant,string $provider,string $model,string $key,array $context): array
    {
        $scope='pi:apr:semantic:'.hash('sha256',$tenant.'|'.$provider);
        $lock=null;$acquired=false;
        try{
            $driver=config('cache.stores.'.config('cache.default').'.driver');
            if(!in_array($driver,['database','file','redis'],true)&&!($driver==='array'&&app()->environment('testing'))){
                return $this->hold('RECOVERY_GUARD_UNAVAILABLE',null,0,'CHECK_LOCAL_CACHE_SERVICE',false);
            }
            $store=Cache::store();
            $lock=$store->lock($scope.':lock',60);
            $acquired=$lock->get();
            if(!$acquired)return $this->hold('PROVIDER_BUSY',null,0,'WAIT_FOR_ACTIVE_REQUEST',false);
            $prior=$store->get($scope);
            if(is_array($prior)&&is_int($prior['retry_not_before']??null)&&$prior['retry_not_before']>now()->timestamp){
                return ['state'=>'HOLD','provider_execution'=>'NOT_RUN','recovery'=>$prior];
            }
            $budget=null;$budgetReservation=null;
            if($provider==='gemini'){
                $budget=new GeminiSemanticBudget;
                try{$budgetReservation=$budget->reserve($store,$tenant,$context);}
                catch(Throwable){return $this->hold('LOCAL_AI_BUDGET_UNAVAILABLE',null,0,'CHECK_LOCAL_AI_BUDGET',false);}
                if(!$budgetReservation['allowed']){
                    $out=$this->hold('LOCAL_AI_BUDGET_EXHAUSTED',null,0,'WAIT_FOR_LOCAL_DAILY_BUDGET_OR_ADMIN_POLICY',false);
                    $out['resource_budget']=$budgetReservation['receipt'];
                    return $out;
                }
            }
            // Persist a short reservation before egress. If the process dies,
            // subsequent workers suppress another paid request during this interval.
            $reservation=$this->hold('PROVIDER_REQUEST_UNCERTAIN',null,60,'WAIT_AND_REQUEST_NEW_APPROVAL',false)['recovery'];
            if(!$store->put($scope,$reservation,60)||$store->get($scope)!==$reservation){
                return $this->hold('RECOVERY_GUARD_UNAVAILABLE',null,0,'CHECK_LOCAL_CACHE_SERVICE',false);
            }
            try{
                $proposal=$adapter->propose($context,$provider,$model,$key);
                try{$store->forget($scope);}catch(Throwable){}
                $out=['state'=>'RESPONSE','proposal'=>$proposal,'provider_execution'=>'CLOUD_PROVIDER_CALLED','recovery'=>['failure_class'=>null,'automatic_retry'=>false,'fresh_consent_required'=>true,'deterministic_functions_available'=>true]];
                if($budget!==null){
                    try{$out['resource_budget']=$budget->observe($store,$budgetReservation,$adapter instanceof CloudSemanticCandidateProvider?$adapter->diagnostic():[]);}
                    catch(Throwable){$out['resource_budget']=$budgetReservation['receipt']+['usage_persistence'=>'FAILED'];}
                }
                return $out;
            }catch(Throwable){
                $meta=$adapter instanceof CloudSemanticCandidateProvider?$adapter->diagnostic():[];
                $status=$meta['http_status']??null;
                $attempted=($meta['request_attempted']??true)===true;
                [$class,$delay,$action]=match(true){
                    $status===402=>['PROVIDER_BALANCE',900,'RESTORE_BALANCE_OR_SELECT_AUTHORIZED_PROVIDER'],
                    in_array($status,[401,403],true)=>['PROVIDER_ACCESS',300,'CHECK_PROVIDER_CREDENTIAL_OR_PERMISSION'],
                    $status===429=>['PROVIDER_RATE_LIMIT',120,'WAIT_FOR_QUOTA_AND_NEW_APPROVAL'],
                    is_int($status)&&($status>=500||$status===408)=>['PROVIDER_TRANSIENT',60,'WAIT_AND_REQUEST_NEW_APPROVAL'],
                    $status===200=>['PROVIDER_OUTPUT_INVALID',120,'REVIEW_OUTPUT_CONTRACT'],
                    !$attempted=>['PROVIDER_PREFLIGHT',0,'CHECK_LOCAL_CONFIGURATION'],
                    default=>['PROVIDER_TRANSPORT_OR_REQUEST',60,'CHECK_PROVIDER_STATUS_AND_NEW_APPROVAL'],
                };
                $hint=$meta['retry_after_seconds']??null;
                if($delay>0&&is_int($hint)&&$hint>0)$delay=max($delay,min(86400,$hint));
                $out=$this->hold($class,is_int($status)?$status:null,$delay,$action,$attempted);
                if($delay>0){
                    try{
                        if(!$store->put($scope,$out['recovery'],$delay)||$store->get($scope)!==$out['recovery'])$out['recovery']['state_persistence']='FAILED';
                    }catch(Throwable){$out['recovery']['state_persistence']='FAILED';}
                }else{
                    try{$store->forget($scope);}catch(Throwable){}
                }
                if($budget!==null){
                    try{$out['resource_budget']=$budget->observe($store,$budgetReservation,$meta);}
                    catch(Throwable){$out['resource_budget']=$budgetReservation['receipt']+['usage_persistence'=>'FAILED'];}
                }
                return $out;
            }
        }catch(Throwable){
            return $this->hold('RECOVERY_GUARD_UNAVAILABLE',null,0,'CHECK_LOCAL_CACHE_SERVICE',false);
        }finally{
            if($acquired&&$lock!==null){try{$lock->release();}catch(Throwable){}}
        }
    }

    private function hold(string $class,?int $status,int $delay,string $action,bool $attempted): array
    {
        return ['state'=>'HOLD','provider_execution'=>$attempted?'CLOUD_PROVIDER_ATTEMPTED':'NOT_RUN',
            'recovery'=>['failure_class'=>$class,'http_status'=>$status,'retry_not_before'=>$delay>0?now()->timestamp+$delay:null,
                'operator_action'=>$action,'automatic_retry'=>false,'fresh_consent_required'=>true,'deterministic_functions_available'=>true]];
    }
}
