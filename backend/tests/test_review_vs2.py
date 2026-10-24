"""Single VS2 acceptance: actual VS1 canonical SourceUnits → SQLAlchemy actions → DOCX/JSON."""
from __future__ import annotations
import copy
import hashlib
import json
from pathlib import Path
import sys

import pytest
from docx import Document as WordDocument
from sqlalchemy import create_engine, select, func
from sqlalchemy.orm import Session

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
from app.vs2.reviews import ReviewAction, ReviewBase, add_review, list_reviews, validate_result
from app.vs2.workflow import analyze_fixture, decide, review_rows, export_review, open_store, initialize_candidate_store
from app.vs1.canonical_bridge import compare_existing_sources

FIXTURE = Path(__file__).parent / 'fixtures' / 'pi_vs1_canonical_pair.json'


@pytest.fixture
def store(tmp_path):
    engine = create_engine('sqlite:///' + str(tmp_path / 'vs2.sqlite'))
    initialize_candidate_store(engine)
    return engine


def test_one_complete_workflow_canonical_review_export(store, tmp_path):
    result = analyze_fixture(FIXTURE)
    assert len(result['findings']) == 2
    fid = result['findings'][0]['id']
    action = decide(store, result, fid, 'local-reviewer', 'NEEDS_EVIDENCE', 'Periksa asal target')
    assert action['analysis_sha256'] == result['report_sha256']
    assert action['reviewer'] == 'local-reviewer'
    actions = review_rows(store, result)
    assert len(actions) == 1
    json_out, docx_out = export_review(result, actions, tmp_path / 'reports')
    document = WordDocument(docx_out)
    paragraphs = '\n'.join(p.text for p in document.paragraphs)
    assert 'SIMULASI' in paragraphs
    assert result['findings'][0]['indicator'] in paragraphs
    assert 'SourceUnit' in paragraphs
    report = json.loads(json_out.read_text(encoding='utf-8'))
    assert report['analysis']['report_sha256'] == result['report_sha256']
    assert report['review_actions'][0]['decision'] == 'NEEDS_EVIDENCE'
    assert report['production_admission'] == 'DENIED'


def test_event_history_is_append_only(store):
    result = analyze_fixture(FIXTURE)
    fid = result['findings'][0]['id']
    decide(store, result, fid, 'user.1', 'NEEDS_EVIDENCE')
    decide(store, result, fid, 'user.1', 'REJECTED_TEST_ONLY')
    with Session(store) as s:
        assert s.scalar(select(func.count()).select_from(ReviewAction)) == 2
    assert [e['decision'] for e in review_rows(store, result)] == ['NEEDS_EVIDENCE','REJECTED_TEST_ONLY']


@pytest.mark.parametrize('field,value,reason', [
    ('production_admission','GRANTED','REVIEW_REQUIRES_SYNTHETIC_ADMISSION'),
    ('rights_admission','UNKNOWN','REVIEW_REQUIRES_SYNTHETIC_ADMISSION'),
    ('review_authority','GRANTED','REVIEW_AUTHORITY_MUST_REMAIN_NOT_GRANTED'),
    ('report_sha256','0'*64,'ANALYSIS_DIGEST_MISMATCH'),
])
def test_unqualified_analysis_refused(store, field, value, reason):
    result = analyze_fixture(FIXTURE)
    result[field] = value
    with pytest.raises((PermissionError,ValueError), match=reason):
        decide(store, result, result['findings'][0]['id'], 'reviewer', 'NEEDS_EVIDENCE')


@pytest.mark.parametrize('finding,reviewer,decision,reason', [
    ('deadbeef'*3,'reviewer','NEEDS_EVIDENCE','FINDING_NOT_IN_ANALYSIS'),
    (None,'','NEEDS_EVIDENCE','VERIFIED_LOCAL_REVIEWER_REQUIRED'),
    (None,'reviewer','CONFIRMED','DECISION_NOT_ALLOWED'),
])
def test_unsafe_review_refused(store, finding, reviewer, decision, reason):
    result=analyze_fixture(FIXTURE)
    fid=finding or result['findings'][0]['id']
    with pytest.raises((ValueError,PermissionError),match=reason):
        decide(store,result,fid,reviewer,decision)


def test_note_limit_and_no_documents_stored(store):
    result=analyze_fixture(FIXTURE)
    fid=result['findings'][0]['id']
    with pytest.raises(ValueError, match='REVIEW_NOTE_TOO_LONG'):
        decide(store, result, fid, 'reviewer', 'NEEDS_EVIDENCE', 'X'*2001)
    decide(store,result,fid,'reviewer','NEEDS_EVIDENCE','short')
    cols=set(ReviewAction.__table__.columns.keys())
    assert {'raw_document', 'password', 'csrf', 'credential', 'original_bytes'}.isdisjoint(cols)
    assert 'analysis_sha256' in cols


def test_other_analysis_action_cannot_be_exported(store, tmp_path):
    result=analyze_fixture(FIXTURE)
    fid=result['findings'][0]['id']
    event=decide(store,result,fid,'reviewer','NEEDS_EVIDENCE')
    action=dict(event);action['analysis_sha256']='f'*64
    with pytest.raises(ValueError,match='REVIEW_ACTION_SCOPE_MISMATCH'):
        export_review(result,[action],tmp_path)


def test_original_vs1_analysis_not_modified(store):
    result=analyze_fixture(FIXTURE)
    snapshot=json.dumps(result,sort_keys=True)
    decide(store,result,result['findings'][0]['id'],'reviewer','NEEDS_EVIDENCE')
    assert json.dumps(result,sort_keys=True)==snapshot


def test_reject_unsupported_database_driver():
    with pytest.raises(ValueError,match='UNSUPPORTED_REVIEW_STORE_DRIVER'):
        open_store('mysql://localhost/unsafe')


def test_gradio_off_the_shelf_builder_smoke(store, tmp_path):
    gr=pytest.importorskip('gradio')
    # UI is created with Gradio's prebuilt data grid, controls and file outputs.
    from app.vs2.reviewer_ui import build_app
    app = build_app(FIXTURE,store,tmp_path)
    assert isinstance(app, gr.Blocks)


def test_vs1_regression_still_same(store):
    data=analyze_fixture(FIXTURE)
    statuses={f['indicator']:f['status'] for f in data['findings']}
    assert statuses['Persentase layanan selesai']=='POTENTIAL_TARGET_DIFFERENCE'
    assert statuses['Rasio layanan digital']=='NUMERIC_MATCH_PENDING_REVIEW'
