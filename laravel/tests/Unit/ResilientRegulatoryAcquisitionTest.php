<?php
namespace Tests\Unit;
use App\Services\Pi\Regulatory\ResilientRegulatoryAcquisition;
use PHPUnit\Framework\TestCase;
final class ResilientRegulatoryAcquisitionTest extends TestCase
{
    public function test_fallback_then_offline_reuse_and_corruption_hold(): void
    {
        $root=sys_get_temp_dir().'/rai-'.bin2hex(random_bytes(8));mkdir($root);
        $body="%PDF-1.7\n".str_repeat('fixture ',30)."\n%%EOF\n";
        $sha=strtoupper(hash('sha256',$body));$path=$root.'/'.$sha.'.pdf';
        $d=['id'=>'UU-TEST-2026','expected_sha256'=>$sha,'sources'=>[['adapter'=>'BPK','url'=>'https://peraturan.bpk.go.id/Download/1/test.pdf'],['adapter'=>'DITJEN_PP','url'=>'https://peraturan.go.id/files/test.pdf']]];
        $s=new ResilientRegulatoryAcquisition();
        try{
            $r=$s->resolve($d,$root,static fn($u)=>str_contains($u,'bpk.go.id')?['status'=>403]:['status'=>200,'body'=>$body]);
            self::assertSame('REMOTE_PINNED_CANDIDATE_STORED',$r['state']);
            self::assertSame('ALTERNATE_SOURCE',$r['attempts'][0]['action']);
            $r=$s->resolve($d,$root,static function(){self::fail('network must not run');});
            self::assertSame('LOCAL_PINNED_CANDIDATE_AVAILABLE',$r['state']);
            file_put_contents($path,'corrupt');
            self::assertSame('LOCAL_INTEGRITY_HOLD',$s->resolve($d,$root,static fn()=>['status'=>200,'body'=>$body])['state']);
        }finally{@unlink($path);@rmdir($root);}
    }
    public function test_all_sources_unavailable_or_different_bytes_never_admitted(): void
    {
        $root=sys_get_temp_dir().'/rai-'.bin2hex(random_bytes(8));mkdir($root);
        $d=['id'=>'UU-TEST-2026','expected_sha256'=>str_repeat('A',64),'sources'=>[['adapter'=>'BPK','url'=>'https://peraturan.bpk.go.id/Download/1/test.pdf'],['adapter'=>'DITJEN_PP','url'=>'https://peraturan.go.id/files/test.pdf']]];
        try{
            $r=(new ResilientRegulatoryAcquisition)->resolve($d,$root,static fn($u)=>str_contains($u,'bpk.go.id')?['status'=>429]:['status'=>200,'body'=>"%PDF-1.7\n".str_repeat('x',150)."%%EOF"]);
            self::assertSame('ACQUISITION_PENDING',$r['state']);self::assertSame('DENIED',$r['production_admission']);self::assertSame('DIFFERENT_BYTES_HOLD',$r['attempts'][1]['action']);self::assertSame(['.','..'],scandir($root));
        }finally{@rmdir($root);}
    }
    public function test_local_import_does_not_claim_remote_download(): void
    {
        $root=sys_get_temp_dir().'/rai-'.bin2hex(random_bytes(8));mkdir($root);
        $file=$root.'/origin.pdf';$body="%PDF-1.7\n".str_repeat('fixture ',30)."\n%%EOF\n";file_put_contents($file,$body);
        $sha=strtoupper(hash('sha256',$body));$path=$root.'/'.$sha.'.pdf';
        $d=['id'=>'UU-TEST-2026','expected_sha256'=>$sha,'sources'=>[['adapter'=>'BPK','url'=>'https://peraturan.bpk.go.id/Download/1/test.pdf']]];
        try{
            $s=new ResilientRegulatoryAcquisition();$r=$s->importPinned($d,$file,$root);
            self::assertSame('IMPORTED_PINNED_CANDIDATE_STORED',$r['state']);
            self::assertSame('LOCAL_IMPORT',$r['source']);self::assertSame([],$r['attempts']);
            self::assertSame('LOCAL_PINNED_CANDIDATE_AVAILABLE',$s->importPinned($d,$file,$root)['state']);
            self::assertSame($body,file_get_contents($file));
        }finally{@unlink($path);@unlink($file);@rmdir($root);}
    }
}
