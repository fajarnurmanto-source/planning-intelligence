"""VS4 product acceptance: new/resume/review/export/restart; no PI operational DB changes."""
from __future__ import annotations
import copy
import json
from pathlib import Path
import sys
import pytest
from sqlalchemy import create_engine, select, func
from sqlalchemy.orm import Session
from docx import Document as WordDocument

sys.path.insert(0,str(Path(__file__).resolve().parents[1]))
from app.vs4 import service
from app.vs4.archive import (AnalysisSnapshot, archive_analysis, load_analysis,
                             list_archived, MAX_SNAPSHOT_BYTES)
from app.vs2.reviews import ReviewAction
from app.vs2.workflow import analyze_fixture

FIXTURE = Path(__file__).resolve().parent/'fixtures'/'pi_vs1_canonical_pair.json'
ROLES = {'alice':'reviewer','bob':'viewer'}

@pytest.fixture
def store(tmp_path):
    path=tmp_path/'reviews.sqlite'
    engine=service.build_candidate_store('sqlite:///'+str(path))
    yield engine,path
    engine.dispose()


def test_complete_workflow_and_reopen_after_process_restart(store,tmp_path):
    engine,path=store
    analysis=service.from_demo(engine,FIXTURE,'alice',ROLES)
    digest=analysis['report_sha256']
    assert len(analysis['findings'])==2
    assert len(service.history(engine,actor='bob',roles=ROLES))==1
    fid=analysis['findings'][0]['id']
    assert 'SourceUnit:' in service.proof(engine,digest,fid,actor='bob',roles=ROLES)
    action=service.decision(engine,digest,fid,'alice',ROLES,'NEEDS_EVIDENCE','Review ulang angka')
    assert action['decision']=='NEEDS_EVIDENCE'
    assert service.table(engine,digest,actor='bob',roles=ROLES)[0][3]=='NEEDS_EVIDENCE'
    first=service.export(engine,digest,tmp_path/'exports','bob',ROLES)
    engine.dispose()
    engine2=service.build_candidate_store('sqlite:///'+str(path))
    after=service.resume(engine2,digest,actor='bob',roles=ROLES)
    assert after==analysis
    assert service.table(engine2,digest,actor='bob',roles=ROLES)[0][3]=='NEEDS_EVIDENCE'
    j,w=service.export(engine2,digest,tmp_path/'exports','bob',ROLES)
    assert all(p.exists() for p in (j,w))
    loaded=json.loads(j.read_text(encoding='utf-8'))
    assert loaded['review_actions'][0]['note']=='Review ulang angka'
    assert 'SIMULASI' in '\n'.join(p.text for p in WordDocument(w).paragraphs)
    engine2.dispose()


def test_archive_idempotency_no_duplicate(store):
    engine,_=store
    analysis=analyze_fixture(FIXTURE)
    digest=archive_analysis(engine,analysis,actor='alice')
    assert archive_analysis(engine,analysis,actor='bob')==digest
    with Session(engine) as db:
        assert db.scalar(select(func.count()).select_from(AnalysisSnapshot))==1


def test_viewer_may_read_but_may_not_write(store):
    engine,_=store
    analysis=service.from_demo(engine,FIXTURE,'alice',ROLES)
    fid=analysis['findings'][0]['id']
    with pytest.raises(PermissionError,match='REVIEWER_ROLE_REQUIRED'):
        service.decision(engine,analysis['report_sha256'],fid,'bob',ROLES,'NEEDS_EVIDENCE')
    assert service.resume(engine,analysis['report_sha256'],actor='bob',roles=ROLES)


def test_unauthorized_actor_refused(store):
    engine,_=store
    for who in ('outsider',None,''):
        with pytest.raises(PermissionError,match='AUTHORIZED_LOCAL_USER_REQUIRED'):
            service.history(engine,actor=who,roles=ROLES)


def test_store_rejects_wrong_backends_and_canonical_credential(store):
    with pytest.raises(ValueError,match='REVIEW_STORE_DRIVER_NOT_ALLOWED'):
        service.build_candidate_store('mysql://localhost/db')
    same='postgresql+psycopg://actor:password@db:5432/planning_intelligence'
    with pytest.raises(ValueError,match='DISTINCT_CANDIDATE_REVIEW_DATABASE_REQUIRED'):
        service.build_candidate_store(same,canonical_database_url=same)
    with pytest.raises(ValueError,match='CANDIDATE_REVIEW_DATABASE_SUFFIX_REQUIRED'):
        service.build_candidate_store('postgresql+psycopg://actor:pass@db/planning_intelligence',canonical_database_url='postgresql+psycopg://user:p@db/a')
    with pytest.raises(ValueError,match='DISTINCT_CANDIDATE_REVIEW_DATABASE_REQUIRED'):
        service.build_candidate_store('postgresql+psycopg://reviewer:secret@db/planning_vs4_candidate',
             canonical_database_url='postgresql+psycopg://reader:other@db/planning_vs4_candidate')


def test_tamper_detected_on_load_and_no_secret_columns(store):
    engine,_=store
    analysis=service.from_demo(engine,FIXTURE,'alice',ROLES)
    digest=analysis['report_sha256']
    with Session(engine) as db:
        stored=db.get(AnalysisSnapshot,digest)
        altered=json.loads(stored.analysis_json)
        altered['findings'][0]['rationale']='tamper'
        stored.analysis_json=json.dumps(altered)
        db.commit()
    with pytest.raises(ValueError,match='ANALYSIS_DIGEST_MISMATCH'):
        service.resume(engine,digest,actor='alice',roles=ROLES)
    cols=AnalysisSnapshot.__table__.columns.keys()
    assert {'password','credential','original_bytes','raw_document'}.isdisjoint(cols)


def test_corrupted_finding_cannot_be_reviewed(store):
    engine,_=store
    analysis=service.from_demo(engine,FIXTURE,'alice',ROLES)
    with pytest.raises(ValueError,match='FINDING_NOT_IN_ANALYSIS'):
        service.decision(engine,analysis['report_sha256'],'0'*24,'alice',ROLES,'NEEDS_EVIDENCE')


def test_archive_only_synthetic_and_bounded(store):
    engine,_=store
    analysis=analyze_fixture(FIXTURE)
    analysis['rights_admission']='UNKNOWN'
    with pytest.raises(PermissionError,match='REVIEW_REQUIRES_SYNTHETIC_ADMISSION'):
        archive_analysis(engine,analysis,actor='alice')
    original=analyze_fixture(FIXTURE)
    assert len(json.dumps(original).encode('utf-8'))<MAX_SNAPSHOT_BYTES


def test_append_only_reviewer_decisions(store):
    engine,_=store
    analysis=service.from_demo(engine,FIXTURE,'alice',ROLES)
    d=analysis['report_sha256'];f=analysis['findings'][0]['id']
    service.decision(engine,d,f,'alice',ROLES,'NEEDS_EVIDENCE')
    service.decision(engine,d,f,'alice',ROLES,'REJECTED_TEST_ONLY')
    with Session(engine) as db:
        assert db.scalar(select(func.count()).select_from(ReviewAction))==2
    assert service.table(engine,d,actor='alice',roles=ROLES)[0][3]=='REJECTED_TEST_ONLY'


def test_ui_reuses_gradio_off_the_shelf(store,tmp_path):
    from app.vs4.reviewer_ui import build_app
    engine,_=store
    ui=build_app(engine,tmp_path,ROLES,fixture=FIXTURE)
    assert 'VS4' in ui.title


def test_invalid_digest_rejected(store):
    engine,_=store
    with pytest.raises(ValueError,match='ANALYSIS_DIGEST_INVALID'):
        load_analysis(engine,'../../bad')
    with pytest.raises(ValueError,match='ARCHIVE_LIST_LIMIT_INVALID'):
        list_archived(engine,limit=1000)


def test_no_canonical_postgres_modifications(store):
    engine,_=store
    service.from_demo(engine,FIXTURE,'alice',ROLES)
    assert set(engine.dialect.get_table_names(engine.connect())) >= {
        'pi_vs2_candidate_review_actions','pi_vs4_candidate_analysis_snapshots'
    }
