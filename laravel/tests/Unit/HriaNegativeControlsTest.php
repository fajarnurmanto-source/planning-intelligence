<?php
namespace Tests\Unit;
use App\Services\Pi\Regulatory\HriaNegativeControls;
use PHPUnit\Framework\TestCase;
final class HriaNegativeControlsTest extends TestCase
{
    public function test_absence_is_not_absence_of_mandate(): void
    {
        $uu="Pasal 12\n(1) Contoh.\n(2) Diatur dengan peraturan daerah.\nPasal 21\n(1) Contoh.\n(2) Diatur dengan peraturan pemerintah.\nPasal 22\nContoh.";
        $b=new HriaNegativeControls;
        self::assertSame('PASS_TWO_NEGATIVE_CONTROLS',$b->evaluate("Menimbang: Pasal 16.\nMengingat:",$uu)['state']);
        self::assertSame('HOLD',$b->evaluate("Menimbang: Pasal 21.\nMengingat:",$uu)['state']);
        self::assertSame('HOLD',$b->evaluate("missing preamble",$uu)['state']);
    }
}
