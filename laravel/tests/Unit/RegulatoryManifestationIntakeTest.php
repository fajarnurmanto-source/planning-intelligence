<?php
namespace Tests\Unit;
use App\Services\Pi\Regulatory\RegulatoryAlternativeSourceDiscovery;
use App\Services\Pi\Regulatory\RegulatoryManifestationIntake;
use PHPUnit\Framework\TestCase;
final class RegulatoryManifestationIntakeTest extends TestCase
{
    public function test_split_publication_does_not_replace_canonical_or_claim_equivalence(): void
    {
        $root=sys_get_temp_dir().'/rai-manifest-'.bin2hex(random_bytes(8));mkdir($root);
        $html='<h1>Undang-Undang Nomor 23 Tahun 2014</h1><a href="https://www.peraturan.go.id/files/uu23-2014bt.pdf">body</a><a href="/files/uu23-2014pjl.pdf">explanation</a><a href="/files/uu17-2019bt.pdf">wrong</a>';
        $d=(new RegulatoryAlternativeSourceDiscovery)->discover('UU-23-2014',static fn()=>['status'=>200,'body'=>$html]);
        self::assertSame('LINK_CANDIDATES_FOUND',$d['state']);
        self::assertSame(['BODY_CANDIDATE','EXPLANATION_CANDIDATE'],array_column($d['sources'],'component_role'));
        $pin=str_repeat('A',64);$body="%PDF-1.7\n".str_repeat('candidate ',30)."%%EOF\n";
        try{
            $s=new RegulatoryManifestationIntake;
            $r=$s->stage('UU-23-2014',$d,$d['sources'][0],$body,$root,$pin);
            self::assertSame('MANIFESTATION_QUARANTINED',$r['state']);
            self::assertSame('DISTINCT_BYTES',$r['byte_relation']);
            self::assertSame('NOT_ASSESSED',$r['content_equivalence']);
            self::assertSame('NOT_VERIFIED',$r['coverage']);
            self::assertSame('DENIED',$r['production_admission']);
            self::assertFileDoesNotExist($root.'/'.$pin.'.pdf');
            self::assertSame($r,$s->stage('UU-23-2014',$d,$d['sources'][0],$body,$root,$pin));
            self::assertCount(2,glob($root.'/*'));
        }finally{foreach(glob($root.'/*') as $file)unlink($file);rmdir($root);}
    }
}
