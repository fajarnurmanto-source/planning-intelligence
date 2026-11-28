<?php
namespace Tests\Unit;
use App\Services\Pi\Regulatory\HriaDelegationBenchmark;
use PHPUnit\Framework\TestCase;
final class HriaDelegationBenchmarkTest extends TestCase
{
    public function test_mandate_classification_distinguishes_issuer_scope(): void
    {
        $b=new HriaDelegationBenchmark;
        self::assertSame('PP',$b->classify('Contoh diatur dengan peraturan pemerintah.'));
        self::assertSame('PP_AND_PERDA',$b->classify('Contoh diatur dengan peraturan pemerintah dan peraturan daerah.'));
        self::assertSame('PP_AND_OR_PERDA',$b->classify('Contoh diatur dengan peraturan pemerintah dan/atau peraturan daerah.'));
        self::assertSame('PERDA_ONLY',$b->classify('Contoh diatur dengan peraturan daerah.'));
        self::assertSame('INSUFFICIENT',$b->classify('Merujuk pada peraturan pemerintah.'));
    }
}
