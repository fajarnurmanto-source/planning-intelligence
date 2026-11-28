<?php
namespace Tests\Unit;
use App\Services\Pi\Regulatory\RegulatoryGenealogyGraph;
use App\Services\Pi\Regulatory\RegulatoryTaxonomyRegistry;
use PHPUnit\Framework\TestCase;
final class RegulatoryRelationGuardTest extends TestCase
{
    private function fixture(string $type='DELEGATED_BY'): array
    {
        $nodes=['A'=>['node_type'=>'INSTRUMENT'],'B'=>['node_type'=>'INSTRUMENT'],'B:P1'=>['node_type'=>'PROVISION','owner_id'=>'B']];
        $text='Synthetic direct relation test; not a legal finding.';
        $e=['E'=>['from'=>'A','to'=>'B:P1','relation_type'=>$type,'source_instrument_id'=>'A','source_url'=>'https://peraturan.go.id/id/fixture','source_locator'=>'Pasal 1','source_sha256'=>str_repeat('A',64),'excerpt'=>$text,'excerpt_sha256'=>strtoupper(hash('sha256',$text)),'status'=>'SOURCE_TEXT_CANDIDATE','observed_on'=>'2026-11-25','effective_on'=>'2025-01-01']];
        return [$nodes,$e,['from'=>'A','to'=>'B:P1','type'=>$type,'evidence_id'=>'E']];
    }
    private function runGuard(array $n,array $e,array $p): array
    {return (new RegulatoryGenealogyGraph(new RegulatoryTaxonomyRegistry()))->assessProposal($n,$e,$p,'2026-11-25','2026-11-25');}
    public function test_direct_candidate_inverse_is_discovery_only(): void
    {
        [$n,$e,$p]=$this->fixture();$r=$this->runGuard($n,$e,$p);
        self::assertSame('DIRECT_EVIDENCE_CANDIDATE_REVIEW_REQUIRED',$r['state']);
        self::assertSame('DELEGATES_TO',$r['inverse_discovery_label']);
        self::assertFalse($r['transitive_inference_allowed']);
        self::assertSame('DENIED',$r['production_admission']);
    }
    public function test_citation_cannot_be_promoted_to_delegation(): void
    {
        [$n,$e,$p]=$this->fixture('CITES_AS_BASIS');$p['type']='DELEGATED_BY';
        self::assertSame('EVIDENCE_EDGE_MISMATCH',$this->runGuard($n,$e,$p)['reason']);
    }
    public function test_partial_change_not_whole_repeal_and_future_hold(): void
    {
        [$n,$e,$p]=$this->fixture('PARTIALLY_REPEALS');$p['type']='REPEALS';
        self::assertSame('ENDPOINT_TYPE_MISMATCH',$this->runGuard($n,$e,$p)['reason']);
        $p['type']='PARTIALLY_REPEALS';$e['E']['effective_on']='2027-01-01';
        self::assertSame('NOT_YET_EFFECTIVE',$this->runGuard($n,$e,$p)['reason']);
    }
    public function test_transitive_and_tampered_excerpt_hold(): void
    {
        [$n,$e,$p]=$this->fixture();$p['derived_from_path']=true;
        self::assertSame('TRANSITIVE_PROPOSAL_NOT_ALLOWED',$this->runGuard($n,$e,$p)['reason']);
        $e['E']['excerpt']='tampered';$p['derived_from_path']=false;
        self::assertSame('EVIDENCE_INCOMPLETE',$this->runGuard($n,$e,$p)['reason']);
    }
}
