<?php
declare(strict_types=1);
namespace App\Services\Pi\Regulatory;

use DateTimeImmutable;
use RuntimeException;

/** Deterministic bounded source recovery; time is provided by the trusted worker. */
final class RegulatoryAcquisitionRetryPolicy
{
    public function decide(int $status,int $failures,int $now,?string $retryAfter=null): array
    {
        if($failures<1||$failures>100||$now<1||$status<0||$status>599)throw new RuntimeException('PI_RAI_RETRY_INPUT_HOLD');
        $action=match(true){
            $status===200=>'RESET',
            $status===403=>'ACCESS_DENIED_ALTERNATE_REQUIRED',
            $status===404=>'REDISCOVERY_REQUIRED',
            $status===429=>'RATE_LIMIT',
            $status===0||in_array($status,[408,500,502,503,504],true)=>'TRANSIENT',
            default=>'MANUAL_REVIEW'
        };
        $delay=null;
        if(in_array($action,['RATE_LIMIT','TRANSIENT'],true)){
            $delay=min(3600,60*(2**min(6,$failures-1)));
            if($failures>=3)$delay=max($delay,900);
            if($retryAfter!==null&&strlen($retryAfter)<=128){
                $v=trim($retryAfter);
                if(preg_match('/^\d{1,10}$/D',$v))$delay=max($delay,(int)$v);
                else{
                    $d=DateTimeImmutable::createFromFormat('D, d M Y H:i:s \G\M\T',$v,new \DateTimeZone('UTC'));
                    if($d&&$d->format('D, d M Y H:i:s \G\M\T')===$v)$delay=max($delay,$d->getTimestamp()-$now);
                }
            }
        }
        return ['action'=>$action,'retry_at'=>$delay===null?null:$now+$delay,'circuit_open'=>$delay!==null&&($failures>=3||$status===429),'automatic_retry'=>$delay!==null,'production_admission'=>'DENIED'];
    }
}
