<?php
namespace Tests\Unit;
use App\Services\Pi\Regulatory\RegulatoryAlternativeSourceDiscovery;
use PHPUnit\Framework\TestCase;
final class RegulatoryAlternativeSourceDiscoveryTest extends TestCase
{
    public function test_only_exact_identity_and_official_pdf_links(): void
    {
        $d=new RegulatoryAlternativeSourceDiscovery;
        $html='<h1>Undang-Undang Nomor 23 Tahun 2014</h1><a href="/files/uu-no-23-tahun-2014.pdf">PDF</a><a href="/files/uu-no-17-tahun-2019.pdf">other</a><a href="https://example.org/files/uu-no-23-tahun-2014.pdf">outside</a>';
        $r=$d->discover('UU-23-2014',static fn()=>['status'=>200,'body'=>$html]);
        self::assertSame('LINK_CANDIDATES_FOUND',$r['state']);
        self::assertCount(1,$r['sources']);self::assertSame('DENIED',$r['production_admission']);
        self::assertSame('METADATA_IDENTITY_HOLD',$d->discover('UU-17-2019',static fn()=>['status'=>200,'body'=>$html])['state']);
        self::assertSame('METADATA_UNAVAILABLE',$d->discover('UU-23-2014',static fn()=>['status'=>403])['state']);
    }
}
