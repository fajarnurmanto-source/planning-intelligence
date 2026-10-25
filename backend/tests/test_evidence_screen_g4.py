"""G4 conservative literal evidence screen: source, lineage, contradiction, archive."""
import copy
import json
from pathlib import Path
import sys
import pytest
from docx import Document
sys.path.insert(0,str(Path(__file__).resolve().parents[1]))
from app.graph_core.intake import analyze_four_files
from app.graph_g2.engine import parse_ledger, analyze_substantive
from app.graph_g3.engine import analyze_g2_and_originals
from app.graph_g3.samples import make_grounded_set
from app.graph_g4.engine import assess_relationships, lexical_screen
from app.graph_g4.samples import make_assessment_set
from app.graph_g4.intake import analyze_archived_g2_g3
from app.vs1.canonical_bridge import Refused
from app.vs2.reviews import validate_result
from app.vs4 import service
from app.vs4.archive import archive_analysis

ROLES={'reviewer':'reviewer','viewer':'viewer'}


def chain(tmp_path, enhanced=False, tampered=False):
    if enhanced:
        files,ledger=make_assessment_set(tmp_path)
    else:
        files,ledger=make_grounded_set(tmp_path,change_quote=tampered)
    g1=analyze_four_files(files,group='Sasaran',scope='Target tahunan')
    g2=analyze_substantive(g1,parse_ledger(ledger))
    g3=analyze_g2_and_originals(g2,ledger,files)
    return g1,g2,g3


def test_existing_g3_quote_match_is_not_substantive_approval(tmp_path):
    _,g2,g3=chain(tmp_path)
    g4=assess_relationships(g2,g3)
    assert g3['verification_metrics']['edge_quotes_verified']==6
    assert g4['assessment_metrics']['status_counts']=={'RELATION_ENTITY_REFERENCE_GAP':6}
    assert all(x['semantic_entailment']=='NOT_VERIFIED' and x['causal_validity']=='NOT_TESTED'
               for x in g4['relationship_assessments'])
    assert validate_result(g4)[0]==g4['report_sha256']


def test_explicit_positive_and_negative_linkage_both_require_review(tmp_path):
    _,g2,g3=chain(tmp_path,enhanced=True)
    g4=assess_relationships(g2,g3)
    indexed={e['edge_id']:e for e in g4['relationship_assessments']}
    assert indexed['E2']['classification']=='EXPLICIT_RELATION_ASSERTION_CANDIDATE'
    assert indexed['E5']['classification']=='EXPLICIT_RELATION_NEGATION_CANDIDATE'
    assert g4['assessment_metrics']['status_counts']=={
        'EXPLICIT_RELATION_ASSERTION_CANDIDATE':5,
        'EXPLICIT_RELATION_NEGATION_CANDIDATE':1}
    assert all(x['review_required'] for x in g4['relationship_assessments'])
    assert g4['production_admission']=='DENIED'
    assert g4['review_authority']=='NOT_GRANTED'
    assert all(f['evidence'][0]['source_unit_id']!='UNVERIFIED' for f in g4['findings'])


def test_tampered_edge_cannot_be_upgraded_by_sounding_reasonable(tmp_path):
    _,g2,g3=chain(tmp_path,tampered=True)
    g4=assess_relationships(g2,g3)
    assert 'SOURCE_GROUNDING_GAP' in g4['assessment_metrics']['status_counts']


def test_title_substring_and_missing_predicate_abstain():
    assert lexical_screen('Digitalisasi layananan mendukung Ketepatan pelayanan',
                         'Digitalisasi layanan','Ketepatan pelayanan','SUPPORTS')[0]=='RELATION_ENTITY_REFERENCE_GAP'
    assert lexical_screen('Digitalisasi layanan dekat Ketepatan pelayanan',
                         'Digitalisasi layanan','Ketepatan pelayanan','SUPPORTS')[0]=='RELATION_PREDICATE_GAP'
    assert lexical_screen('Digitalisasi layanan tidak mendukung Ketepatan pelayanan',
                         'Digitalisasi layanan','Ketepatan pelayanan','SUPPORTS')[0]=='EXPLICIT_RELATION_NEGATION_CANDIDATE'
    assert lexical_screen('Digitalisasi layanan mendukung Ketepatan pelayanan',
                         'Digitalisasi layanan','Ketepatan pelayanan','SUPPORTS')[0]=='EXPLICIT_RELATION_ASSERTION_CANDIDATE'


def test_lineage_mismatch_fails_closed(tmp_path):
    _,g2,g3=chain(tmp_path)
    g2_wrong=copy.deepcopy(g2);g2_wrong['ledger_sha256']='0'*64
    from app.graph_g4.engine import _digest
    g2_wrong['report_sha256']=_digest({k:v for k,v in g2_wrong.items() if k!='report_sha256'})
    with pytest.raises(Refused,match='G4_ARCHIVED_LINEAGE_MISMATCH'):
        assess_relationships(g2_wrong,g3)


def test_digest_tampering_rejected(tmp_path):
    _,g2,g3=chain(tmp_path)
    g3_bad=copy.deepcopy(g3);g3_bad['verification_metrics']['source_gaps']=9
    with pytest.raises(ValueError,match='ANALYSIS_DIGEST_MISMATCH'):
        assess_relationships(g2,g3_bad)


def test_archive_reviewer_export_and_source_archive_unchanged(tmp_path):
    g1,g2,g3=chain(tmp_path,enhanced=True)
    store=service.build_candidate_store('sqlite:///'+str(tmp_path/'review.sqlite'))
    for a in [g1,g2,g3]:archive_analysis(store,a,actor='reviewer')
    before=json.dumps(g3,ensure_ascii=False,sort_keys=True)
    g4=analyze_archived_g2_g3(store,g3['report_sha256'],actor='reviewer',roles=ROLES)
    assert g4['schema']=='pi.graph_g4.literal_relation_screen.synthetic.v1'
    assert json.dumps(service.resume(store,g3['report_sha256'],actor='viewer',roles=ROLES),
                      ensure_ascii=False,sort_keys=True)==before
    fid=g4['findings'][0]['id']
    event=service.decision(store,g4['report_sha256'],fid,'reviewer',ROLES,'NEEDS_EVIDENCE','Review explicit statement')
    assert event['decision']=='NEEDS_EVIDENCE'
    store.dispose()
    again=service.build_candidate_store('sqlite:///'+str(tmp_path/'review.sqlite'))
    rows=service.table(again,g4['report_sha256'],actor='viewer',roles=ROLES)
    assert any(r[3]=='NEEDS_EVIDENCE' for r in rows)
    exported=service.export(again,g4['report_sha256'],tmp_path/'export','viewer',ROLES)
    assert len(exported)==2 and all(p.is_file() for p in exported)
    assert Document(next(p for p in exported if p.suffix=='.docx')).paragraphs
    assert json.loads(next(p for p in exported if p.suffix=='.json').read_text(encoding='utf8'))
    with pytest.raises(PermissionError,match='REVIEWER_ROLE_REQUIRED'):
        service.decision(again,g4['report_sha256'],fid,'viewer',ROLES,'NEEDS_EVIDENCE')
    again.dispose()


def test_g4_requires_g3_archive_not_g1(tmp_path):
    g1,g2,g3=chain(tmp_path)
    store=service.build_candidate_store('sqlite:///'+str(tmp_path/'review.sqlite'))
    archive_analysis(store,g1,actor='reviewer')
    with pytest.raises(ValueError,match='G4_SELECT_ARCHIVED_G3'):
        analyze_archived_g2_g3(store,g1['report_sha256'],actor='reviewer',roles=ROLES)
    store.dispose()


def test_g4_ui_reuses_archive_not_uploads(tmp_path):
    from app.vs4.reviewer_ui import build_app
    store=service.build_candidate_store('sqlite:///'+str(tmp_path/'review.sqlite'))
    app=build_app(store,tmp_path/'reports',ROLES)
    assert 'G4' in app.title and 'G3' in app.title
    store.dispose()


def test_single_click_g4_example_idempotent(tmp_path):
    from app.graph_g4.intake import synthetic_g4_example
    store=service.build_candidate_store('sqlite:///'+str(tmp_path/'review.sqlite'))
    first=synthetic_g4_example(store,tmp_path/'example',actor='reviewer',roles=ROLES)
    second=synthetic_g4_example(store,tmp_path/'example',actor='reviewer',roles=ROLES)
    assert first['report_sha256']==second['report_sha256']
    assert first['assessment_metrics']['status_counts']=={
        'EXPLICIT_RELATION_ASSERTION_CANDIDATE':5,
        'EXPLICIT_RELATION_NEGATION_CANDIDATE':1}
    assert len(service.history(store,actor='reviewer',roles=ROLES))==4
    store.dispose()
