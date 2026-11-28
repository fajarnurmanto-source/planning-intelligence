<?php
namespace Tests\Unit;
use App\Services\Pi\Development\PlanningPageProfile;
use PHPUnit\Framework\TestCase;
final class PlanningPageProfileTest extends TestCase
{
    public function test_physical_offsets_and_blank_page_are_preserved(): void
    {
        $r=(new PlanningPageProfile)->profile("BAB IV\nTabel 4.1\n2.11.11.2.01.0004  Indikator kinerja  target kinerja\n\f\f",101,102);
        self::assertSame(101,$r['samples']['SUBACTIVITY_CODE']['page']);
        self::assertSame('NO_TEXT',$r['pages'][1]['state']);
        self::assertSame(0,$r['numeric_admission']);
        self::assertSame('DENIED',$r['production_admission']);
    }
    public function test_wrong_batch_count_holds(): void
    {
        $this->expectException(\RuntimeException::class);
        (new PlanningPageProfile)->profile("BAB I\f",1,2);
    }
}
