"""G6 integration and adversity: no model is an abstention, never automatic approval."""
from __future__ import annotations
import copy,json,math,sys
from pathlib import Path
import pytest
from docx import Document
sys.path.insert(0,str(Path(__file__).resolve().parents[1]))
from app.graph_core.intake import analyze_four_files
from app.graph_g2.engine import parse_ledger,analyze_substantive
from app.graph_g3.engine import analyze_g2_and_originals
from app.graph_g4.engine import assess_relationships
from app.graph_g4.samples import make_assessment_set
from app.graph_g5.engine import assess_direction,_digest
from app.graph_g6.engine import assess_semantic, cosine
from app.graph_g6.benchmark import evaluate_benchmark,PAIRS
from app.graph_g6.provider import choose_provider,OllamaLocal,EmbeddingUnavailable
from app.graph_g6.intake import analyze_archived_g5
from app.vs1.canonical_bridge import Refused
from app.vs4.archive import archive_analysis
from app.vs4 import service

R={'reviewer':'reviewer','viewer':'viewer'}

class UnitEmbedder:
    identity='TEST_DOUBLE_NOT_REAL_MODEL'
    def embed(self,texts):
        assert len(texts)==2
        return [[1.0,0.0],[0.85,0.52678269]]

class FailEmbedder:
    identity='TEST_DOUBLE_FAILURE'
    def embed(self,texts):
        raise EmbeddingUnavailable('LOCAL_MODEL_MISSING')


def make_chain(tmp_path):
    files,ledger=make_assessment_set(tmp_path/'docs')
    a=analyze_four_files(files,group='Sasaran',scope='Target tahunan')
    b=analyze_substantive(a,parse_ledger(ledger))
    c=analyze_g2_and_originals(b,ledger,files)
    d=assess_relationships(b,c)
    e=assess_direction(b,c,d)
    return a,b,c,d,e


def test_cosine_dimensions_finite():
    assert cosine([1,0],[1,0])==1
    assert cosine([1,0],[0,1])==0
    with pytest.raises(Refused):cosine([1,0],[1,2,3])
    with pytest.raises(Refused):cosine([float('nan'),0],[0,1])
    with pytest.raises(Refused):cosine([0,0],[0,1])
    with pytest.raises(Refused):cosine([1,True],[0,1])


def test_without_model_abstains_instead_of_lexical_fallback(tmp_path):
    chain=make_chain(tmp_path)
    out=assess_semantic(*chain[1:])
    assert out['assessment_metrics']['edges']==6
    assert out['assessment_metrics']['scored']==0
    assert out['assessment_metrics']['status_counts']=={'NO_MODEL_ABSTAIN':5,'BLOCKED_BY_DIRECTION_OR_NEGATION':1}
    assert all(x['semantic_entailment']=='NOT_ESTABLISHED' and x['review_required']
               and x['similarity'] is None for x in out['relationship_assessments'])
    assert out['rights_admission']=='SYNTHETIC_ONLY'
    assert out['production_admission']=='DENIED'


def test_test_double_scores_only_g5_eligible(tmp_path):
    out=assess_semantic(*make_chain(tmp_path)[1:],embedder=UnitEmbedder())
    assert out['assessment_metrics']['scored']==5
    assert out['assessment_metrics']['status_counts']=={
        'SEMANTIC_RELEVANCE_CANDIDATE':5,'BLOCKED_BY_DIRECTION_OR_NEGATION':1}
    assert all(x['model_id']=='TEST_DOUBLE_NOT_REAL_MODEL' for x in out['relationship_assessments'])
    assert all(f['review_status']=='PENDING' for f in out['findings'])


def test_g5_report_alteration_refused(tmp_path):
    a,b,c,d,e=make_chain(tmp_path)
    fake=copy.deepcopy(e)
    fake['relationship_assessments'][0]['classification']='REVERSED_DIRECTION_CANDIDATE'
    fake['report_sha256']=_digest({k:v for k,v in fake.items() if k!='report_sha256'})
    with pytest.raises(Refused,match='G6_RECOMPUTED_G5_OR_LINEAGE_MISMATCH'):
        assess_semantic(b,c,d,fake)


def test_wrong_source_file_cannot_score(tmp_path):
    a,b,c,d,e=make_chain(tmp_path)
    corrupted=copy.deepcopy(c)
    eid=b['graph']['edges'][0]['declaration_id']
    corrupted['edge_verification'][eid]['status']='DECLARED_RELATION_UNGROUNDED'
    corrupted['report_sha256']=_digest({k:v for k,v in corrupted.items() if k!='report_sha256'})
    with pytest.raises(Refused):assess_semantic(b,corrupted,d,e,embedder=UnitEmbedder())


def test_threshold_out_of_bounds(tmp_path):
    chain=make_chain(tmp_path)
    with pytest.raises(Refused):assess_semantic(*chain,threshold=0.2)
    with pytest.raises(Refused):assess_semantic(*chain,threshold=1.1)


def test_model_failure_cannot_be_hidden(tmp_path):
    with pytest.raises(EmbeddingUnavailable,match='LOCAL_MODEL_MISSING'):
        assess_semantic(*make_chain(tmp_path)[1:],embedder=FailEmbedder())


def test_ollama_never_accepts_external_hosts_or_bad_models():
    assert choose_provider('disabled') is None
    with pytest.raises(ValueError):choose_provider('remote-api')
    with pytest.raises(ValueError):OllamaLocal('x; wget example.com')
    with pytest.raises(ValueError):OllamaLocal('')


def test_ollama_input_boundary():
    with pytest.raises(ValueError):OllamaLocal().embed(['x'*12001])


def test_benchmark_requires_real_configured_embedder():
    result=evaluate_benchmark()
    assert result['status']=='NOT_RUN_NO_LOCAL_MODEL'
    assert result['dataset_size']==40
    assert not any(x in result for x in ('f1','precision','accuracy'))


def test_benchmark_counts_coherent():
    out=evaluate_benchmark(UnitEmbedder())
    assert out['status']=='COMPLETED_SYNTHETIC_TOPICAL_RELEVANCE'
    assert out['development']['n']==20 and out['holdout']['n']==20
    assert all(m['tp']+m['fp']+m['tn']+m['fn']==m['n'] for m in (out['development'],out['holdout']))
    assert len(PAIRS)==40


def test_archive_review_export_persists(tmp_path):
    chain=make_chain(tmp_path)
    store=service.build_candidate_store('sqlite:///'+str(tmp_path/'r.sqlite'))
    for item in chain:archive_analysis(store,item,actor='reviewer')
    before=json.dumps(chain[-1],ensure_ascii=False,sort_keys=True)
    out=analyze_archived_g5(store,chain[-1]['report_sha256'],actor='reviewer',roles=R)
    assert json.dumps(service.resume(store,chain[-1]['report_sha256'],actor='viewer',roles=R),ensure_ascii=False,sort_keys=True)==before
    event=service.decision(store,out['report_sha256'],out['findings'][0]['id'],
                           'reviewer',R,'NEEDS_EVIDENCE','Model belum terpasang')
    assert event['decision']=='NEEDS_EVIDENCE'
    with pytest.raises(PermissionError):
        service.decision(store,out['report_sha256'],out['findings'][0]['id'],
                         'viewer',R,'NEEDS_EVIDENCE')
    store.dispose()
    restart=service.build_candidate_store('sqlite:///'+str(tmp_path/'r.sqlite'))
    assert any(x[3]=='NEEDS_EVIDENCE' for x in service.table(restart,out['report_sha256'],actor='viewer',roles=R))
    exported=service.export(restart,out['report_sha256'],tmp_path/'export','viewer',R)
    assert all(p.is_file() for p in exported)
    assert json.loads(next(p for p in exported if p.suffix=='.json').read_text('utf8'))
    assert Document(next(p for p in exported if p.suffix=='.docx')).paragraphs
    restart.dispose()


def test_ui_still_has_g5_and_g6(tmp_path):
    from app.vs4.reviewer_ui import build_app
    store=service.build_candidate_store('sqlite:///'+str(tmp_path/'r.sqlite'))
    app=build_app(store,tmp_path/'export',R)
    assert 'G6' in app.title and 'G5' in app.title
    store.dispose()


def test_no_automatic_model_download():
    from inspect import getsource
    from app.graph_g6.provider import CachedSentenceTransformer
    assert 'local_files_only=True' in getsource(CachedSentenceTransformer)
    assert "HTTPConnection('127.0.0.1',11434" in getsource(OllamaLocal)
