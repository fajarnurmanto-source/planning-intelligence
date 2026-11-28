<?php
declare(strict_types=1);
namespace App\Services\Pi\Development;

use Illuminate\Contracts\Cache\Repository;
use RuntimeException;

/** Local Gemini semantic-route resource ledger. Not Google project quota or billing. */
final class GeminiSemanticBudget
{
    private const TTL=172800;

    public function reserve(Repository $store,string $tenant,array $context):array
    {
        $day=now('UTC')->format('Y-m-d');
        $key='pi:ai:gemini:daily:'.hash('sha256',$tenant.'|'.$day);
        $limit=config('pi_ai_budget.gemini_daily_requests',100);
        $unitsLimit=config('pi_ai_budget.gemini_daily_reservation_units',300000);
        if(!is_int($limit)||$limit<0||$limit>100000||!is_int($unitsLimit)||$unitsLimit<0||$unitsLimit>1000000000)throw new RuntimeException('PI_AI_BUDGET_CONFIG_HOLD');
        $raw=json_encode($context,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        // Reject impossible contexts before reserving any local request/units.
        // Keep the adapter's encoded UTF-8 byte cap; never truncate approved evidence.
        if(strlen($raw)>6000)throw new RuntimeException('PI_AI_CONTEXT_LIMIT_HOLD');
        // Conservative local scheduling units only, not a tokenizer or official limit.
        $units=strlen($raw)+4096+400;
        $state=$store->get($key);
        if($state===null)$state=['requests'=>0,'reserved_units'=>0,'reported_tokens'=>0,'usage_reports'=>0];
        if(!is_array($state))throw new RuntimeException('PI_AI_BUDGET_LEDGER_HOLD');
        foreach(['requests','reserved_units','reported_tokens','usage_reports'] as $field){
            if(!is_int($state[$field]??null)||$state[$field]<0||$state[$field]>PHP_INT_MAX-1000000000)throw new RuntimeException('PI_AI_BUDGET_LEDGER_HOLD');
        }
        if($state['requests']>=$limit||$state['reserved_units']>$unitsLimit-$units){
            return ['allowed'=>false,'receipt'=>$this->receipt($day,$state,$limit,$unitsLimit)];
        }
        $state['requests']++;$state['reserved_units']+=$units;
        if(!$store->put($key,$state,self::TTL)||$store->get($key)!==$state)throw new RuntimeException('PI_AI_BUDGET_LEDGER_WRITE_HOLD');
        return ['allowed'=>true,'key'=>$key,'day'=>$day,'state'=>$state,'limit'=>$limit,'units_limit'=>$unitsLimit,'receipt'=>$this->receipt($day,$state,$limit,$unitsLimit)];
    }

    public function observe(Repository $store,array $reservation,array $diagnostic):array
    {
        $state=$reservation['state'];
        $value=$diagnostic['usage']['totalTokenCount']??null;
        if(is_int($value)&&$value>=0&&$value<=10000000){
            $state['reported_tokens']+=$value;$state['usage_reports']++;
            if(!$store->put($reservation['key'],$state,self::TTL)||$store->get($reservation['key'])!==$state)throw new RuntimeException('PI_AI_BUDGET_LEDGER_WRITE_HOLD');
        }
        // Never release reserved units on uncertain delivery or missing usage.
        return $this->receipt($reservation['day'],$state,$reservation['limit'],$reservation['units_limit']);
    }

    private function receipt(string $day,array $state,int $limit,int $unitsLimit):array
    {
        return ['scope'=>'PI_LOCAL_TENANT_GEMINI_SEMANTIC_ROUTES_ONLY','utc_day'=>$day,'request_limit'=>$limit,'reservation_unit_limit'=>$unitsLimit,
            'attempt_reservations'=>$state['requests'],'reserved_units'=>$state['reserved_units'],'provider_reported_tokens'=>$state['reported_tokens'],
            'usage_reports'=>$state['usage_reports'],'remaining_requests'=>max(0,$limit-$state['requests']),
            'remaining_reservation_units'=>max(0,$unitsLimit-$state['reserved_units']),'unit_method'=>'CONTEXT_UTF8_BYTES_PLUS_FIXED_4496','provider_quota'=>'NOT_VERIFIED','billing'=>'NOT_VERIFIED'];
    }
}
