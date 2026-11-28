<?php
namespace Tests\Unit;
use App\Services\Pi\Development\AdaptivePlanningTableEvidence;
use PHPUnit\Framework\TestCase;
final class AdaptivePlanningTableEvidenceTest extends TestCase
{
    public function test_integer_and_mixed_locale_whole_tokens(): void
    {
        $r=(new AdaptivePlanningTableEvidence)->tokens('1.01.04.2.01  7.335.300  7.674.456,61  2,880,000.00');
        self::assertSame(['7.335.300','7.674.456,61','2,880,000.00'],array_column($r,'raw'));
    }
    public function test_long_label_and_next_code_boundary(): void
    {
        $a='1.01.04.2.01 - Pemerataan';
        $p="Pagu indikatif 2025 2026 2027 2028 2029\n".$a."\nKuantitas\nPendidik\n\nTenaga Kependidikan\n  7.335.300  7.484.500  7.674.456,61  7.878.903,42  8.126.531,15\n1.01.04.2.02 - Lain\n  99.000.000,00\n";
        $r=(new AdaptivePlanningTableEvidence)->scan($p."\f",170,170,[170=>strtoupper(hash('sha256',$p))],[170=>[2=>['code'=>'1.01.04.2.01','window'=>[['sha256'=>strtoupper(hash('sha256',$a))]]]]]);
        self::assertSame('FIVE_TOKEN_GROUP_CANDIDATE',$r['records'][0]['shape']);
        self::assertSame('STRUCTURAL_BOUNDARY',$r['records'][0]['boundary']);
        self::assertCount(1,$r['records'][0]['token_rows']);
        self::assertSame('NOT_CERTIFIED',$r['records'][0]['column_semantics']);
        self::assertSame(0,$r['numeric_admission']);
    }
}
