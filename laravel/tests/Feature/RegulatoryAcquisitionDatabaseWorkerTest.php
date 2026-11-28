<?php
declare(strict_types=1);
namespace Tests\Feature;

use App\Jobs\AcquirePinnedRegulation;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Real database queue + Laravel worker, simulated HTTP only. */
final class RegulatoryAcquisitionDatabaseWorkerTest extends TestCase
{
    public function test_database_worker_retains_retry_and_recovers_without_repeating_denied_requests(): void
    {
        $root=sys_get_temp_dir().'/rai-worker-'.bin2hex(random_bytes(8));
        mkdir($root);mkdir($root.'/cache');
        $db=$root.'/queue.sqlite';touch($db);
        config([
            'database.default'=>'sqlite','database.connections.sqlite.database'=>$db,
            'database.connections.sqlite.url'=>null,
            'queue.default'=>'database','queue.connections.database.connection'=>'sqlite',
            'queue.connections.database.retry_after'=>90,
            'queue.failed.database'=>'sqlite',
            'cache.default'=>'file','cache.stores.file.path'=>$root.'/cache',
            'cache.stores.file.lock_path'=>$root.'/cache',
            'pi_rai.enabled'=>true,'pi_rai.repository_root'=>$root,
        ]);
        DB::purge('sqlite');Cache::purge('file');
        Http::preventStrayRequests();
        Http::fake(['https://peraturan.bpk.go.id/*'=>Http::sequence()
            ->push('',429,['Retry-After'=>'120'])->push('',403)]);
        try{
            $migration=require database_path('migrations/0001_01_01_000002_create_jobs_table.php');
            $migration->up();
            $this->artisan('pi:rai:enqueue',['document'=>'UU-23-2014'])->assertExitCode(0);
            self::assertSame(1,DB::table('jobs')->count());
            $this->artisan('queue:work',['connection'=>'database','--queue'=>'pi-regulatory-acquisition','--once'=>true,'--sleep'=>0,'--timeout'=>60])->assertExitCode(0);
            $r=Cache::store('file')->get('pi-rai-result:UU-23-2014');
            self::assertSame('ACQUISITION_PENDING',$r['state']);
            self::assertSame(429,$r['transport_observations'][0]['status']);
            self::assertGreaterThan(time()+90,$r['next_retry_at']);
            self::assertSame(1,DB::table('jobs')->count());
            self::assertGreaterThan(time()+90,(int)DB::table('jobs')->value('available_at'));
            // Prove no early execution while database release is outstanding.
            $this->artisan('queue:work',['connection'=>'database','--queue'=>'pi-regulatory-acquisition','--once'=>true,'--sleep'=>0,'--timeout'=>60])->assertExitCode(0);
            Http::assertSentCount(1);
            // Advance test state explicitly, not the production clock.
            DB::table('jobs')->update(['available_at'=>time()-1]);
            Cache::store('file')->forget('pi-rai-source:'.hash('sha256','peraturan.bpk.go.id'));
            $this->artisan('queue:work',['connection'=>'database','--queue'=>'pi-regulatory-acquisition','--once'=>true,'--sleep'=>0,'--timeout'=>60])->assertExitCode(0);
            self::assertSame(0,DB::table('jobs')->count());
            self::assertSame(0,DB::table('failed_jobs')->count());
            $r=Cache::store('file')->get('pi-rai-result:UU-23-2014');
            self::assertSame(403,$r['transport_observations'][0]['status']);
            self::assertSame('DENIED',$r['production_admission']);
            $this->artisan('pi:rai:enqueue',['document'=>'UU-23-2014'])->assertExitCode(0);
            $this->artisan('queue:work',['connection'=>'database','--queue'=>'pi-regulatory-acquisition','--once'=>true,'--sleep'=>0,'--timeout'=>60])->assertExitCode(0);
            $r=Cache::store('file')->get('pi-rai-result:UU-23-2014');
            self::assertSame('URL_COOLDOWN',$r['transport_observations'][0]['state']);
            Http::assertSentCount(2);
            self::assertSame(0,DB::table('jobs')->count());
        }finally{
            DB::disconnect('sqlite');Cache::purge('file');File::deleteDirectory($root);
        }
    }
}
