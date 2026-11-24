<?php
namespace Tests\Unit;

use App\Services\Pi\Development\DocumentStructureProfile;
use App\Services\Pi\Development\FullDocumentInventory;
use PHPUnit\Framework\TestCase;

final class DocumentStructureProfileTest extends TestCase
{
    public function test_candidates_do_not_merge_explanation_or_annex_into_body(): void
    {
        $d=(new FullDocumentInventory)->inventory("BAB I\nPasal 5A\n(1) Isi\n a. butir\n\f(2) lanjutan\n\fPENJELASAN\nPasal 5A\n\fLAMPIRAN II\n5.1.02.01.01.001\n\f",4);
        $r=(new DocumentStructureProfile)->profile(['source_sha256'=>str_repeat('A',64)]+$d);
        self::assertCount(5,$r['hierarchy_candidates']);
        self::assertSame('p1l2',$r['hierarchy_candidates'][4]['parent_candidate']);
        self::assertNull($r['hierarchy_candidates'][3]['parent_candidate']);
        self::assertCount(1,$r['annex_page_signals']);
        self::assertSame('NOT_PERFORMED',$r['table_reconstruction']);
        self::assertSame(0,$r['numeric_admission']);
    }
    public function test_duplicate_article_does_not_promote_unique_identity(): void
    {
        $d=(new FullDocumentInventory)->inventory("Pasal 1\nPasal 1\n\f",1);
        $r=(new DocumentStructureProfile)->profile(['source_sha256'=>str_repeat('B',64)]+$d);
        self::assertSame('AMBIGUOUS_REVIEW_REQUIRED',$r['hierarchy_candidates'][0]['state']);
        self::assertSame('AMBIGUOUS_REVIEW_REQUIRED',$r['hierarchy_candidates'][1]['state']);
    }
}
