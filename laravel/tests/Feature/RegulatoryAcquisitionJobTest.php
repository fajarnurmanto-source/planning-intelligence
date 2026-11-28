<?php
namespace Tests\Feature;
use App\Jobs\AcquirePinnedRegulation;
use App\Services\Pi\Regulatory\RegulatoryAcquisitionRetryPolicy;
use App\Services\Pi\Regulatory\ResilientRegulatoryAcquisition;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;
final class RegulatoryAcquisitionJobTest extends TestCase
{
    public function test_opt_in_and_known_id_enqueue_only(): void
    {
        Queue::fake();
        config(['pi_rai.enabled'=>false,'queue.default'=>'database']);
        $this->artisan('pi:rai:enqueue',['document'=>'UU-23-2014'])->assertExitCode(2);
        Queue::assertNothingPushed();
        config(['pi_rai.enabled'=>true]);
        $this->artisan('pi:rai:enqueue',['document'=>'NOT-A-REGULATION'])->assertExitCode(2);
        $this->artisan('pi:rai:enqueue',['document'=>'UU-23-2014'])->assertExitCode(0);
        Queue::assertPushedOn('pi-regulatory-acquisition',AcquirePinnedRegulation::class);
    }
    public function test_job_denial_cooldown_does_not_invent_404_or_request_again(): void
    {
        $root=sys_get_temp_dir().'/rai-job-'.bin2hex(random_bytes(8));mkdir($root);
        $cachePath=$root.'/cache';mkdir($cachePath);
        config(['pi_rai.enabled'=>true,'pi_rai.repository_root'=>$root,'cache.stores.file.path'=>$cachePath,'cache.stores.file.lock_path'=>$cachePath]);
        Cache::purge('file');
        Http::preventStrayRequests();
        Http::fake(['https://peraturan.bpk.go.id/*'=>Http::response('',403)]);
        try{
            $job=new AcquirePinnedRegulation('UU-23-2014');
            $job->handle(new ResilientRegulatoryAcquisition,new RegulatoryAcquisitionRetryPolicy);
            $r=Cache::store('file')->get('pi-rai-result:UU-23-2014');
            self::assertSame('ACQUISITION_PENDING',$r['state']);
            self::assertSame(403,$r['attempts'][0]['http_status']);
            $job->handle(new ResilientRegulatoryAcquisition,new RegulatoryAcquisitionRetryPolicy);
            $r=Cache::store('file')->get('pi-rai-result:UU-23-2014');
            self::assertSame(403,$r['attempts'][0]['http_status']);
            self::assertSame('URL_COOLDOWN',$r['transport_observations'][0]['state']);
            self::assertSame('UNVERIFIED',$r['freshness']);
            Http::assertSentCount(1);
        }finally{\Illuminate\Support\Facades\File::deleteDirectory($root);Cache::purge('file');}
    }
}
