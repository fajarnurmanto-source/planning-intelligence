<?php
namespace Tests\Unit;
use App\Services\Pi\Development\DocumentLayoutReconstructor;
use PHPUnit\Framework\TestCase;
final class DocumentLayoutReconstructorTest extends TestCase
{
    public function test_indented_children_and_explanation_boundary(): void
    {
        $r=(new DocumentLayoutReconstructor)->reconstruct("MEMUTUSKAN:\nPasal 5A\n  (1) Isi\n    a. rincian\n      1. angka\n\f  (2) lanjut\nPENJELASAN\nPasal 5A\n\f",2);
        self::assertCount(5,$r['hierarchy_nodes']);
        self::assertSame('p1l3',$r['hierarchy_nodes'][2]['parent_candidate']);
        self::assertSame('p1l4',$r['hierarchy_nodes'][3]['parent_candidate']);
        self::assertSame('p1l2',$r['hierarchy_nodes'][4]['parent_candidate']);
    }
    public function test_split_account_and_unresolved_scope(): void
    {
        $r=(new DocumentLayoutReconstructor)->reconstruct("Pasal 1\nSub Kegiatan: 2.11.11.2.01.0004\n5.1.02.01.01.001  barang  100,00\n\f5.1.02.01.01.00001  barang  200,00\n\f",2);
        self::assertCount(0,$r['hierarchy_nodes']);
        self::assertSame(2,$r['account_fragments']);
        self::assertSame('SPLIT_UNRESOLVED',$r['account_fragment_pages'][0]['rows'][0]['code_state']);
        self::assertNull($r['account_fragment_pages'][1]['rows'][0]['subactivity_same_page']);
        self::assertSame(0,$r['numeric_admission']);
    }
    public function test_duplicate_invalidates_descendants(): void
    {
        $r=(new DocumentLayoutReconstructor)->reconstruct("MEMUTUSKAN:\nPasal 1\n  (1) isi\nPasal 1\n\f",1);
        self::assertSame(3,$r['ambiguous_node_count']);
    }
}
