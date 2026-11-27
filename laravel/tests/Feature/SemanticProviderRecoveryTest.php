<?php
declare(strict_types=1);
namespace Tests\Feature;

use App\Services\Pi\Development\CloudSemanticCandidateProvider;
use App\Services\Pi\Development\SemanticProviderRecovery;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Actual Laravel file cache/locks, fake provider HTTP only; no owner data. */
final class SemanticProviderRecoveryTest extends TestCase
{
    private string $root;
    private const TENANT='APR-FIXTURE';
    private const KEY='synthetic-key-never-output';
    private function scope(string $provider='deepseek',string $tenant=self::TENANT):string
    {
        return 'pi:apr:semantic:'.hash('sha256',$tenant.'|'.$provider);
    }
    protected function setUp():void
    {
        parent::setUp();
        $this->root=sys_get_temp_dir().'/pi-apr-'.bin2hex(random_bytes(8));
        mkdir($this->root);
        config(['cache.default'=>'file','cache.stores.file.path'=>$this->root,'cache.stores.file.lock_path'=>$this->root]);
        Cache::purge('file');Http::preventStrayRequests();
    }
    protected function tearDown():void
    {
        $this->travelBack();Cache::purge('file');File::deleteDirectory($this->root);
        parent::tearDown();
    }
    private function invokeRecovery(string $provider='deepseek',string $tenant=self::TENANT):array
    {
        return (new SemanticProviderRecovery)->invoke(new CloudSemanticCandidateProvider,$tenant,$provider,'test-model',self::KEY,['fixture'=>true]);
    }
    private function reply():array
    {
        return ['choices'=>[['message'=>['content'=>'{"semantic_relation":"RELATED","semantic_confidence":"LOW","explanation":"Synthetic test."}'],'finish_reason'=>'stop']]];
    }
    public function test_persisted_balance_hold_survives_new_cache_instance_then_recovers():void
    {
        Http::fake(['https://api.deepseek.com/*'=>Http::sequence()->push(['error'=>['message'=>'PRIVATE_RESPONSE']],402)->push($this->reply(),200)]);
        $first=$this->invokeRecovery();
        self::assertSame('PROVIDER_BALANCE',$first['recovery']['failure_class']);
        self::assertSame('CLOUD_PROVIDER_ATTEMPTED',$first['provider_execution']);
        $receipt=Cache::store('file')->get($this->scope());
        self::assertSame($first['recovery'],$receipt);
        self::assertStringNotContainsString(self::KEY,json_encode($receipt));
        self::assertStringNotContainsString('PRIVATE_RESPONSE',json_encode($receipt));
        Cache::purge('file');
        self::assertSame('NOT_RUN',$this->invokeRecovery()['provider_execution']);
        Http::assertSentCount(1);
        $this->travel(16)->minutes();
        self::assertSame('RESPONSE',$this->invokeRecovery()['state']);
        Http::assertSentCount(2);
        self::assertNull(Cache::store('file')->get($this->scope()));
    }
    public function test_lock_reservation_and_unknown_store_fail_closed():void
    {
        Http::fake(['https://api.deepseek.com/*'=>Http::response($this->reply(),200)]);
        $lock=Cache::store('file')->lock($this->scope().':lock',60);
        self::assertTrue($lock->get());
        try{self::assertSame('PROVIDER_BUSY',$this->invokeRecovery()['recovery']['failure_class']);}
        finally{$lock->release();}
        Cache::store('file')->put($this->scope(),['failure_class'=>'PROVIDER_REQUEST_UNCERTAIN','retry_not_before'=>now()->timestamp+60],60);
        self::assertSame('NOT_RUN',$this->invokeRecovery()['provider_execution']);
        Http::assertNothingSent();
        config(['cache.default'=>'null']);
        self::assertSame('RECOVERY_GUARD_UNAVAILABLE',$this->invokeRecovery()['recovery']['failure_class']);
        Http::assertNothingSent();
    }
    public function test_rate_limit_is_scoped_and_honors_retry_after():void
    {
        Http::fake(['https://api.deepseek.com/*'=>Http::sequence()->push([],429,['Retry-After'=>'300'])->push($this->reply(),200)->push($this->reply(),200)]);
        $first=$this->invokeRecovery();
        self::assertSame('PROVIDER_RATE_LIMIT',$first['recovery']['failure_class']);
        self::assertGreaterThanOrEqual(now()->timestamp+299,$first['recovery']['retry_not_before']);
        self::assertSame('RESPONSE',$this->invokeRecovery('deepseek','APR-OTHER-TENANT')['state']);
        self::assertSame('NOT_RUN',$this->invokeRecovery()['provider_execution']);
        Http::assertSentCount(2);
        $this->travel(6)->minutes();
        self::assertSame('RESPONSE',$this->invokeRecovery()['state']);
        Http::assertSentCount(3);
    }
}
