<?php
declare(strict_types=1);
namespace App\Services\Pi\Regulatory;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/** Bounded observation of registered primary bytes. No evidence repository mutation. */
final class RegulatoryFreshnessObserver
{
    public function observe(array $document, ?callable $transport=null): array
    {
        $id=$document['id']??'';
        $pin=$document['expected_sha256']??'';
        if(!preg_match('/^(UU|PERMENDAGRI)-[1-9][0-9]{0,3}-(?:19|20)[0-9]{2}$/D',$id)||!preg_match('/^[A-F0-9]{64}$/D',$pin))throw new RuntimeException('PI_RAI_OBSERVATION_INPUT_HOLD');
        $url=$document['sources'][0]['url']??'';
        $p=parse_url($url);
        if(!is_array($p)||($p['scheme']??null)!=='https'||!in_array($p['host']??null,['peraturan.bpk.go.id','peraturan.go.id'],true)||isset($p['user'])||isset($p['pass'])||isset($p['port'])||isset($p['query'])||isset($p['fragment'])||!preg_match('~^/(?:Download/[0-9]+/[^/]+|files/[^/]+)\.pdf$~i',$p['path']??''))throw new RuntimeException('PI_RAI_OBSERVATION_URL_HOLD');
        $cache=Cache::store('file');
        $key='pi-rai-source:'.hash('sha256',$p['host']);
        $urlKey='pi-rai-url:'.hash('sha256',$url);
        $observationKey='pi-rai-freshness:'.$id;
        $assessment=new RegulatoryFreshnessAssessment;
        $previous=$cache->get($observationKey);
        $now=time();
        $state=$cache->get($key,[]);
        if(($state['retry_at']??0)>$now||$cache->get($urlKey))return ['observation'=>$previous,'assessment'=>$assessment->assess($pin,$previous,$now),'execution'=>'SOURCE_COOLDOWN'];
        $lock=$cache->lock($key.':lock',45);
        if(!$lock->get())return ['observation'=>$previous,'assessment'=>$assessment->assess($pin,$previous,$now),'execution'=>'SOURCE_LOCK_BUSY'];
        try{
            $state=$cache->get($key,[]);
            if(($state['retry_at']??0)>time()||$cache->get($urlKey))return ['observation'=>$previous,'assessment'=>$assessment->assess($pin,$previous,time()),'execution'=>'SOURCE_COOLDOWN'];
            $tmp=tempnam(sys_get_temp_dir(),'pi-rai-fresh-');
            if($tmp===false)throw new RuntimeException('PI_RAI_TEMP_HOLD');
            $status=0;$retryAfter=null;$sha=null;
            try{
                $r=$transport!==null?$transport($url,$tmp):Http::connectTimeout(5)->timeout(20)->withoutRedirecting()->withOptions(['sink'=>$tmp,'progress'=>static function($total,$received){if($total>83886080||$received>83886080)throw new RuntimeException('PI_RAI_BODY_LIMIT');}])->get($url);
                $status=$r->status();$retryAfter=$r->header('Retry-After');
                $size=filesize($tmp);
                if($status===200&&is_int($size)&&$size>=128&&$size<=83886080){
                    $f=fopen($tmp,'rb');
                    try{$magic=fread($f,5);fseek($f,-min(4096,$size),SEEK_END);$tail=stream_get_contents($f);}finally{fclose($f);}
                    if($magic==='%PDF-'&&str_contains($tail,'%%EOF'))$sha=strtoupper(hash_file('sha256',$tmp));
                }
            }catch(\Throwable){$status=0;$sha=null;}finally{@unlink($tmp);}
            $now=time();
            $failures=min(100,1+(int)($state['failures']??0));
            $decision=(new RegulatoryAcquisitionRetryPolicy)->decide($status,$failures,$now,$retryAfter);
            if($status===200)$cache->forget($key);
            elseif(in_array($status,[403,404],true))$cache->put($urlKey,$status,now()->addHours(24));
            elseif($decision['retry_at']!==null)$cache->put($key,['failures'=>$failures,'retry_at'=>$decision['retry_at']],max(3600,$decision['retry_at']-$now+3600));
            $o=['status'=>$sha===null?'UNVERIFIED':'OFFICIAL_BYTES_OBSERVED','sha256'=>$sha,'observed_at'=>$now,'source_url'=>$url,'http_status'=>$status];
            $cache->put($observationKey,$o,now()->addDays(30));
            return ['observation'=>$o,'assessment'=>$assessment->assess($pin,$o,$now),'execution'=>'OBSERVATION_ATTEMPTED'];
        }finally{$lock->release();}
    }
}
