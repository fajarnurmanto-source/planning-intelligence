<?php
declare(strict_types=1);
namespace App\Services\Pi\Regulatory;

use RuntimeException;

/** Byte observation freshness is deliberately separate from legal applicability. */
final class RegulatoryFreshnessAssessment
{
    public function assess(string $pin,?array $observation,int $now,int $maxAge=86400): array
    {
        if(!preg_match('/^[A-F0-9]{64}$/D',$pin)||$now<1||$maxAge<60||$maxAge>2592000)throw new RuntimeException('PI_RAI_FRESHNESS_INPUT_HOLD');
        $state='CHECK_REQUIRED';$age=null;
        if($observation!==null){
            $at=$observation['observed_at']??null;$hash=$observation['sha256']??null;
            if(!is_int($at)||$at<1||$at>$now) $state='OBSERVATION_TIME_HOLD';
            elseif(($observation['status']??null)!=='OFFICIAL_BYTES_OBSERVED')$state='SOURCE_UNAVAILABLE_OR_UNVERIFIED';
            elseif(!is_string($hash)||!preg_match('/^[A-F0-9]{64}$/D',$hash))$state='OBSERVATION_HASH_HOLD';
            else{
                $age=$now-$at;
                $state=$hash!==$pin?'DIFFERENT_BYTES_REVIEW_REQUIRED':($age>=$maxAge?'REFRESH_DUE':'RECENT_MATCHING_BYTES');
            }
        }
        return ['state'=>$state,'age_seconds'=>$age,'refresh_required'=>$state!=='RECENT_MATCHING_BYTES','amendment_census'=>'NOT_EXHAUSTIVELY_VERIFIED','legal_effect'=>'NOT_ASSESSED','production_admission'=>'DENIED'];
    }
}
