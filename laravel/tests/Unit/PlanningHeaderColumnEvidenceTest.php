<?php
namespace Tests\Unit;
use App\Services\Pi\Development\PlanningHeaderColumnEvidence;
use PHPUnit\Framework\TestCase;
final class PlanningHeaderColumnEvidenceTest extends TestCase
{
    private function line(int $n,string $s): array{return ['line'=>$n,'text'=>$s,'sha256'=>strtoupper(hash('sha256',$s)),'truncated'=>false];}
    public function test_repeated_positions_are_not_deduplicated_or_admitted(): void
    {
        $code='1.01.01';$a=$code.'  PROGRAM';$b=str_repeat(' ',20).'1.000,00'.str_repeat(' ',32).'2.000,00';
        $r=(new PlanningHeaderColumnEvidence)->map(['page'=>1,'line'=>5,'code'=>$code,'page_sha256'=>str_repeat('A',64),'header_signals'=>[],'window'=>[$this->line(5,$a),$this->line(6,$b),$this->line(7,$b)],'token_rows'=>array_map(static fn($n)=>['line'=>$n,'offset_basis'=>'FULL_LINE','tokens'=>[['raw'=>'1.000,00','offset'=>20],['raw'=>'2.000,00','offset'=>60]]],[6,7]),'boundary'=>'PAGE_END']);
        self::assertSame([[6,7]],$r['identical_positional_groups']);
        self::assertCount(4,$r['tokens']);
        self::assertSame('CROSS_PAGE_HOLD',$r['continuation_state']);
        self::assertSame('BLOCKED',$r['numeric_comparison']);
    }
    public function test_paired_header_and_absolute_offset(): void
    {
        $code='1.01.01';$head=str_repeat(' ',24).'2026'.str_repeat(' ',36).'2027';
        $roles=str_repeat(' ',16).'TARGET'.str_repeat(' ',6).'PAGU'.str_repeat(' ',24).'TARGET'.str_repeat(' ',6).'PAGU';
        $raw=$code.str_repeat(' ',17).'1.000,00';
        $r=(new PlanningHeaderColumnEvidence)->map(['page'=>1,'line'=>5,'code'=>$code,'page_sha256'=>str_repeat('A',64),'header_signals'=>[$this->line(2,$head),$this->line(3,$roles)],'window'=>[$this->line(5,$raw)],'token_rows'=>[['line'=>5,'offset_basis'=>'AFTER_CODE','tokens'=>[['raw'=>'1.000,00','offset'=>0]]]],'boundary'=>'STRUCTURAL_BOUNDARY']);
        self::assertSame('PAIRED_YEAR_ROLE_CANDIDATE',$r['header_status']);
        self::assertSame(24,$r['tokens'][0]['start']);
        self::assertSame('PAGU',$r['tokens'][0]['column']['role']);
        self::assertNull($r['tokens'][0]['numeric_value']);
    }
}
