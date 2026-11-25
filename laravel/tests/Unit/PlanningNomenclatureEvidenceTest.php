<?php
namespace Tests\Unit;
use App\Services\Pi\Development\PlanningNomenclatureEvidence;
use PHPUnit\Framework\TestCase;
final class PlanningNomenclatureEvidenceTest extends TestCase
{
    public function test_row_candidates_and_contents_hold(): void
    {
        $a="DAFTAR ISI\n1.01.01  Program .... 3\n";$b="1.   1.01.01  PROGRAM PENUNJANG  Nilai SPIP\n1.01.01.2.01.0001  Penyusunan Dokumen\n";
        $r=(new PlanningNomenclatureEvidence)->scan($a."\f".$b."\f",109,110,[109=>strtoupper(hash('sha256',$a)),110=>strtoupper(hash('sha256',$b))]);
        self::assertSame(1,$r['counts']['PROGRAM|CONTENTS_HOLD']);
        self::assertSame(1,$r['counts']['PROGRAM|CODE_ROW_CANDIDATE']);
        self::assertSame('1.01.01.2.01.0001',$r['records'][2]['code']);
        self::assertSame('NOT_CERTIFIED',$r['records'][2]['column_semantics']);
    }
    public function test_page_hash_drift_holds(): void
    {
        $this->expectException(\RuntimeException::class);
        (new PlanningNomenclatureEvidence)->scan("BAB I\f",1,1,[1=>str_repeat('0',64)]);
    }
}
