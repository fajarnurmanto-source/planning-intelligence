<?php
declare(strict_types=1);
namespace App\Jobs;

use App\Services\Pi\Regulatory\RegulatoryAcquisitionRetryPolicy;
use App\Services\Pi\Regulatory\ResilientRegulatoryAcquisition;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/** Opt-in public-candidate acquisition; identifiers only in queue payload. */
final class AcquirePinnedRegulation implements ShouldQueue
{
    use Dispatchable,InteractsWithQueue,Queueable;
    public int $tries=6;
    public int $timeout=60;
    public function __construct(public readonly string $documentId){$this->onQueue('pi-regulatory-acquisition');}
    public function middleware(): array
    {
        return [(new WithoutOverlapping('pi-rai:'.$this->documentId))->releaseAfter(60)->expireAfter(120)];
    }
    public function handle(ResilientRegulatoryAcquisition $resolver,RegulatoryAcquisitionRetryPolicy $policy): void
    {
        if(!config('pi_rai.enabled',false))return;
        $root=config('pi_rai.repository_root');
        if(!is_string($root)||!is_dir($root)||is_link($root))throw new RuntimeException('PI_RAI_CONFIGURED_ROOT_HOLD');
        $registry=json_decode(file_get_contents(resource_path('pi/rai-source-registry.json')),true,32,JSON_THROW_ON_ERROR);
        $document=null;
        foreach($registry['documents'] as $d)if($d['id']===$this->documentId){$document=$d;break;}
        if(!$document)throw new RuntimeException('PI_RAI_UNKNOWN_DOCUMENT');
        $cache=Cache::store('file');
        $now=time();$next=[];$observations=[];
        $result=$resolver->resolve($document,$root,function(string $url)use($cache,$now,$policy,&$next,&$observations):array{
            $host=parse_url($url,PHP_URL_HOST);
            $key='pi-rai-source:'.hash('sha256',$host);
            $urlKey='pi-rai-url:'.hash('sha256',$url);
            $state=$cache->get($key,[]);
            if($held=$cache->get($urlKey)){ $observations[]=['url'=>$url,'state'=>'URL_COOLDOWN','previous_status'=>$held];return ['status'=>$held]; }
            if(($state['retry_at']??0)>$now){$next[]=$state['retry_at'];$observations[]=['url'=>$url,'state'=>'SOURCE_COOLDOWN','retry_at'=>$state['retry_at']];return ['status'=>429];}
            $lock=$cache->lock($key.':lock',45);
            if(!$lock->get()){$next[]=$now+60;$observations[]=['url'=>$url,'state'=>'SOURCE_LOCK_BUSY'];return ['status'=>429];}
            try{
                $state=$cache->get($key,[]);
                if(($state['retry_at']??0)>$now){$next[]=$state['retry_at'];$observations[]=['url'=>$url,'state'=>'SOURCE_COOLDOWN','retry_at'=>$state['retry_at']];return ['status'=>429];}
                $tmp=tempnam(sys_get_temp_dir(),'pi-rai-');
                if($tmp===false)throw new RuntimeException('PI_RAI_TEMP_HOLD');
                $status=0;$retryAfter=null;$body=null;
                try{
                    $r=Http::connectTimeout(5)->timeout(20)->withoutRedirecting()->withOptions(['sink'=>$tmp,'progress'=>static function($total,$received){if($total>83886080||$received>83886080)throw new RuntimeException('PI_RAI_BODY_LIMIT');}])->get($url);
                    $status=$r->status();$retryAfter=$r->header('Retry-After');
                    if($status===200&&filesize($tmp)<=83886080)$body=file_get_contents($tmp);
                }catch(\Throwable){$status=0;}finally{@unlink($tmp);}
                $decision=$policy->decide($status,min(100,1+(int)($state['failures']??0)),$now,$retryAfter);
                if($status===200)$cache->forget($key);
                elseif($status===404||$status===403)$cache->put($urlKey,$status,now()->addHours(24));
                elseif($decision['retry_at']!==null){
                    $cache->put($key,['failures'=>min(100,1+(int)($state['failures']??0)),'retry_at'=>$decision['retry_at']],max(3600,$decision['retry_at']-$now+3600));
                    $next[]=$decision['retry_at'];
                }
                $observations[]=['url'=>$url,'state'=>'HTTP_ATTEMPT','status'=>$status];
                return ['status'=>$status,'body'=>$body];
            }finally{$lock->release();}
        });
        $result['transport_observations']=$observations;
        $result['next_retry_at']=$next?min($next):null;
        $result['retry_budget_remaining']=max(0,$this->tries-$this->attempts());
        $result['freshness']='UNVERIFIED';
        $result['freshness_assessment']=(new \App\Services\Pi\Regulatory\RegulatoryFreshnessAssessment)->assess($document['expected_sha256'],null,time());
        $result['recovery_state']=match(true){
            $result['state']!=='ACQUISITION_PENDING'=>'NO_RETRY_REQUIRED',
            $next&&$this->attempts()<$this->tries=>'RETRY_SCHEDULED',
            (bool)$next=>'RETRY_BUDGET_EXHAUSTED',
            default=>'ALTERNATE_OR_DISCOVERY_REQUIRED',
        };
        $cache->put('pi-rai-result:'.$this->documentId,$result,now()->addDays(7));
        if($result['state']==='ACQUISITION_PENDING'&&$next&&$this->attempts()<$this->tries)$this->release(max(1,min($next)-time()));
    }
}
