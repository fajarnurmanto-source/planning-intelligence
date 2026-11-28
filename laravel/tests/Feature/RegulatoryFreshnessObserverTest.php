<?php
declare(strict_types=1);
namespace Tests\Feature;

use App\Services\Pi\Regulatory\RegulatoryFreshnessObserver;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class RegulatoryFreshnessObserverTest extends TestCase
{
    public function test_observes_without_replacing_local_evidence_and_honours_cooldown(): void
    {
        $root=sys_get_temp_dir().'/rai-observe-'.bin2hex(random_bytes(8));mkdir($root);
        $body="%PDF-1.7\n".str_repeat('fixture ',30)."\n%%EOF\n";
        $changed=$body."changed\n";
        $pin=strtoupper(hash('sha256',$body));
        $file=$root.'/'.$pin.'.pdf';file_put_contents($file,$body);
        config(['cache.stores.file.path'=>$root.'/cache','cache.stores.file.lock_path'=>$root.'/cache']);
        Cache::purge('file');
        Http::preventStrayRequests();
        // Use a fake callback which also simulates the HTTP sink contract.
        $responses=[[200,$body,[]],[200,$changed,[]],[429,'',['Retry-After'=>'120']]];
        $idx=0;
        $transport=function($url,$tmp)use(&$idx,$responses){
            [$status,$bytes,$headers]=$responses[$idx++];
            file_put_contents($tmp,$bytes);
            return new \Illuminate\Http\Client\Response(new \GuzzleHttp\Psr7\Response($status,$headers,$bytes));
        };
        $d=['id'=>'UU-23-2014','expected_sha256'=>$pin,'sources'=>[['adapter'=>'BPK','url'=>'https://peraturan.bpk.go.id/Download/1/test.pdf']]];
        try{
            $s=new RegulatoryFreshnessObserver;
            self::assertSame('RECENT_MATCHING_BYTES',$s->observe($d,$transport)['assessment']['state']);
            $r=$s->observe($d,$transport);
            self::assertSame('DIFFERENT_BYTES_REVIEW_REQUIRED',$r['assessment']['state']);
            self::assertSame('NOT_ASSESSED',$r['assessment']['legal_effect']);
            self::assertSame($body,file_get_contents($file));
            self::assertSame('SOURCE_UNAVAILABLE_OR_UNVERIFIED',$s->observe($d,$transport)['assessment']['state']);
            self::assertSame('SOURCE_COOLDOWN',$s->observe($d,$transport)['execution']);
            self::assertSame(3,$idx);
            self::assertSame($body,file_get_contents($file));
        }finally{Cache::purge('file');File::deleteDirectory($root);}
    }
}
