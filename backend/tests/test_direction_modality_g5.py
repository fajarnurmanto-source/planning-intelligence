"""G5 regression: accurate abstention, direction, archived evidence, export."""
import copy
import json
from pathlib import Path
import sys
import pytest
from docx import Document
sys.path.insert(0,str(Path(__file__).resolve().parents[1]))
from app.graph_g5.engine import orientation_screen,assess_direction,_digest
from app.graph_g5.intake import analyze_archived_g4,synthetic_g5_example
from app.graph_g4.samples import make_assessment_set
from app.graph_core.intake import analyze_four_files
from app.graph_g2.engine import parse_ledger,analyze_substantive
from app.graph_g3.engine import analyze_g2_and_originals
from app.graph_g4.engine import assess_relationships
from app.vs4 import service
from app.vs4.archive import archive_analysis
from app.vs1.canonical_bridge import Refused

R={'reviewer':'reviewer','viewer':'viewer'}

def make_chain(tmp_path):
    f,l=make_assessment_set(tmp_path/'docs')
    g1=analyze_four_files(f,group='Sasaran',scope='Target tahunan')
    g2=analyze_substantive(g1,parse_ledger(l))
    g3=analyze_g2_and_originals(g2,l,f)
    g4=assess_relationships(g2,g3)
    return g1,g2,g3,g4


def test_g5_source_verified_5_positive_1_negated(tmp_path):
    g1,g2,g3,g4=make_chain(tmp_path)
    result=assess_direction(g2,g3,g4)
    assert result['assessment_metrics']['status_counts']=={'DIRECTIONAL_ASSERTION_CANDIDATE':5,'EXPLICIT_NEGATION':1}
    assert result['assessment_metrics']['high_priority']==1
    assert all(x['semantic_entailment']=='NOT_TESTED' and x['review_required'] for x in result['relationship_assessments'])
    assert all(f['evidence'][0]['source_unit_id']!='UNVERIFIED' for f in result['findings'])
    assert result['production_admission']=='DENIED'


def test_reversed_relation_is_not_positive():
    x=orientation_screen('Ketepatan pelayanan mendukung Digitalisasi layanan',
        'Digitalisasi layanan','Ketepatan pelayanan','SUPPORTS')
    assert x[0]=='REVERSED_DIRECTION_CANDIDATE'


def test_prospective_is_not_achieved():
    x=orientation_screen('Digitalisasi layanan akan mendukung Ketepatan pelayanan',
        'Digitalisasi layanan','Ketepatan pelayanan','SUPPORTS')
    assert x[0]=='PROSPECTIVE_RELATION'


def test_explicit_denial_takes_priority():
    x=orientation_screen('Digitalisasi layanan tidak pernah mendukung Ketepatan pelayanan',
        'Digitalisasi layanan','Ketepatan pelayanan','SUPPORTS')
    assert x[0]=='EXPLICIT_NEGATION'


def test_missing_predicate_fails_closed():
    x=orientation_screen('Digitalisasi layanan terkait Ketepatan pelayanan',
        'Digitalisasi layanan','Ketepatan pelayanan','SUPPORTS')
    assert x[0]=='RELATION_UNRESOLVED'


def test_separate_clauses_cannot_fake_relation():
    x=orientation_screen('Digitalisasi layanan dibahas. Program lain mendukung Ketepatan pelayanan.',
        'Digitalisasi layanan','Ketepatan pelayanan','SUPPORTS')
    assert x[0]=='RELATION_UNRESOLVED'


def test_multiple_polarities_require_review():
    x=orientation_screen('Digitalisasi layanan mendukung Ketepatan pelayanan. Digitalisasi layanan tidak mendukung Ketepatan pelayanan.',
        'Digitalisasi layanan','Ketepatan pelayanan','SUPPORTS')
    assert x[0]=='MIXED_POLARITY_CANDIDATE'


def test_g4_digest_binding_fails_closed(tmp_path):
    _,g2,g3,g4=make_chain(tmp_path)
    wrong=copy.deepcopy(g4)
    wrong['relationship_assessments'][0]['classification']='EXPLICIT_RELATION_NEGATION_CANDIDATE'
    wrong['report_sha256']=_digest({k:v for k,v in wrong.items() if k!='report_sha256'})
    with pytest.raises(Refused,match='G5_RECOMPUTED_G4_MISMATCH'):
        assess_direction(g2,g3,wrong)


def test_archive_review_export_read_only(tmp_path):
    chain=make_chain(tmp_path)
    store=service.build_candidate_store('sqlite:///'+str(tmp_path/'reviews.sqlite'))
    for report in chain:archive_analysis(store,report,actor='reviewer')
    before=json.dumps(chain[-1],sort_keys=True,ensure_ascii=False)
    g5=analyze_archived_g4(store,chain[-1]['report_sha256'],actor='reviewer',roles=R)
    assert json.dumps(service.resume(store,chain[-1]['report_sha256'],actor='viewer',roles=R),sort_keys=True,ensure_ascii=False)==before
    event=service.decision(store,g5['report_sha256'],g5['findings'][0]['id'],
                           'reviewer',R,'NEEDS_EVIDENCE','Only a candidate assertion')
    assert event['decision']=='NEEDS_EVIDENCE'
    store.dispose()
    reopened=service.build_candidate_store('sqlite:///'+str(tmp_path/'reviews.sqlite'))
    assert any(x[3]=='NEEDS_EVIDENCE' for x in service.table(reopened,g5['report_sha256'],actor='viewer',roles=R))
    files=service.export(reopened,g5['report_sha256'],tmp_path/'export','viewer',R)
    assert len(files)==2 and all(p.exists() for p in files)
    assert Document(next(f for f in files if f.suffix=='.docx')).paragraphs
    assert json.loads(next(f for f in files if f.suffix=='.json').read_text('utf8'))
    reopened.dispose()


def test_viewer_cannot_decide(tmp_path):
    chain=make_chain(tmp_path)
    store=service.build_candidate_store('sqlite:///'+str(tmp_path/'r.sqlite'))
    for report in chain:archive_analysis(store,report,actor='reviewer')
    g5=analyze_archived_g4(store,chain[-1]['report_sha256'],actor='reviewer',roles=R)
    with pytest.raises(PermissionError,match='REVIEWER_ROLE_REQUIRED'):
        service.decision(store,g5['report_sha256'],g5['findings'][0]['id'],'viewer',R,'NEEDS_EVIDENCE')
    store.dispose()


def test_fixtures_reused_without_adding_archives(tmp_path):
    store=service.build_candidate_store('sqlite:///'+str(tmp_path/'r.sqlite'))
    a=synthetic_g5_example(store,tmp_path/'inputs',actor='reviewer',roles=R)
    b=synthetic_g5_example(store,tmp_path/'inputs',actor='reviewer',roles=R)
    assert a['report_sha256']==b['report_sha256']
    assert len(service.history(store,actor='reviewer',roles=R))==5
    store.dispose()


def test_ui_includes_g5_and_g4(tmp_path):
    from app.vs4.reviewer_ui import build_app
    store=service.build_candidate_store('sqlite:///'+str(tmp_path/'r.sqlite'))
    app=build_app(store,tmp_path/'export',R)
    assert 'G5' in app.title and 'G4' in app.title
    store.dispose()
