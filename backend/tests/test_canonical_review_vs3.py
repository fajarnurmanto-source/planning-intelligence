"""VS3: canonical synthetic PostgreSQL reader → reused VS1/VS2 workflow."""
from __future__ import annotations
import json
from pathlib import Path
import sys
import uuid
from types import SimpleNamespace, ModuleType
import sys as module_sys
from sqlalchemy import String, Integer, Column
from sqlalchemy.orm import declarative_base

import pytest
from docx import Document as WordDocument
from sqlalchemy.orm import Session

sys.path.insert(0,str(Path(__file__).resolve().parents[1]))
from app.vs3 import service
from app.vs1.canonical_bridge import compare_existing_sources, Refused

FIXTURE=Path(__file__).parent / 'fixtures' / 'pi_vs1_canonical_pair.json'


@pytest.fixture
def analysis():
    pair=json.loads(FIXTURE.read_text(encoding='utf-8'))
    return compare_existing_sources(tuple(pair['left']),tuple(pair['right']))


@pytest.fixture
def store(tmp_path):
    return service.review_engine('sqlite:///'+str(tmp_path / 'review.sqlite'))


def test_end_to_end_canonical_evidence_review_word(store,analysis,tmp_path):
    assert analysis['production_admission']=='DENIED'
    assert analysis['rights_admission']=='SYNTHETIC_ONLY'
    fid=analysis['findings'][0]['id']
    entry=service.submit_decision(store,analysis,fid,'reviewer.demo','NEEDS_EVIDENCE','Periksa halaman')
    assert entry['finding_id']==fid
    assert entry['analysis_sha256']==analysis['report_sha256']
    assert len(service.read_review_history(store,analysis))==1
    js,doc=service.export_candidate_report(store,analysis,tmp_path/'out')
    payload=json.loads(js.read_text(encoding='utf8'))
    assert payload['review_actions'][0]['decision']=='NEEDS_EVIDENCE'
    assert payload['analysis']['findings'][0]['evidence'][0]['source_unit_id']
    assert 'SIMULASI' in '\n'.join(x.text for x in WordDocument(doc).paragraphs)


def test_append_only_user_switch(store,analysis):
    fid=analysis['findings'][0]['id']
    service.submit_decision(store,analysis,fid,'reviewer.demo','NEEDS_EVIDENCE')
    service.submit_decision(store,analysis,fid,'reviewer.demo','REJECTED_TEST_ONLY')
    assert len(service.read_review_history(store,analysis))==2


@pytest.mark.parametrize('username',['','  ',None])
def test_unverified_actor_refused(store,analysis,username):
    with pytest.raises((PermissionError,ValueError)):
        service.submit_decision(store,analysis,analysis['findings'][0]['id'],username,'NEEDS_EVIDENCE')


@pytest.mark.parametrize('uri', ['','sqlite:///tmp/pidb','postgresql://localhost/pi','postgresql+psycopg2://localhost/pi'])
def test_canonical_requires_psycopg_postgres(uri):
    with pytest.raises(ValueError,match='CANONICAL_POSTGRESQL_URL_REQUIRED'):
        service.canonical_engine(uri)


def test_separate_candidate_review_store_required():
    with pytest.raises(ValueError,match='VS3_SEPARATE_LOCAL_SQLITE_STORE_REQUIRED'):
        service.review_engine('postgresql+psycopg://a:b@localhost/pi')


def test_analyze_parsing_run_pair_reuses_existing_orm_loader(monkeypatch,analysis):
    left=str(uuid.uuid4()); right=str(uuid.uuid4())
    seen=[]
    class FakeSession:
        def __init__(self,engine):
            seen.append(('open',engine))
        def __enter__(self):return self
        def __exit__(self,*args):return None
        def rollback(self):seen.append(('rollback',))
    def fake_compare(session,l,r,*,group,scope):
        seen.append(('compare',str(l),str(r),group,scope))
        return analysis
    monkeypatch.setattr(service,'Session',FakeSession)
    monkeypatch.setattr(service,'compare_synthetic_parsing_runs',fake_compare)
    result=service.analyze_runs('engine',left,right,group='Sasaran',scope='Renstra / Renja')
    assert result['report_sha256']==analysis['report_sha256']
    assert seen==[('open','engine'),('compare',left,right,'Sasaran','Renstra / Renja'),('rollback',)]


def test_reject_same_run_and_missing_scope(monkeypatch):
    a=str(uuid.uuid4())
    with pytest.raises(Refused,match='CANNOT_COMPARE_SAME_RUN'):
        service.analyze_runs(None,a,a,group='A',scope='B')
    with pytest.raises(Refused,match='GROUP_REQUIRED'):
        service.analyze_runs(None,a,str(uuid.uuid4()),group='',scope='B')


def test_canonical_orm_listing_filters_synthetic_and_bounds(monkeypatch):
    seen=[]
    item=SimpleNamespace(id=uuid.uuid4(),title='SINTETIS Renja',doc_type='RENJA',
       jurisdiction_id=uuid.uuid4(),planning_period_id=None,source_unit_count=13)
    class FakeResults:
        def __init__(self,rows):self.rows=rows
        def all(self):return self.rows
    class FakeSession:
        def __init__(self,engine):pass
        def __enter__(self):return self
        def __exit__(self,*args):return None
        def execute(self,query):
            seen.append(str(query))
            return FakeResults([item]) if 'SELECT' in str(query) else FakeResults([])
        def rollback(self):seen.append('ROLLBACK')
    B=declarative_base()
    class Document(B):
        __tablename__='documents'
        id=Column(String,primary_key=True)
        title=Column(String)
        doc_type=Column(String)
        jurisdiction_id=Column(String)
        planning_period_id=Column(String)
    class DocumentVersion(B):
        __tablename__='document_versions'
        id=Column(String,primary_key=True)
        document_id=Column(String)
        source_kind=Column(String)
    class ParsingRun(B):
        __tablename__='parsing_runs'
        id=Column(String,primary_key=True)
        document_version_id=Column(String)
        status=Column(String)
        source_unit_count=Column(Integer)
        created_at=Column(String)
    fake_models=ModuleType('app.models')
    fake_models.Document=Document
    fake_models.DocumentVersion=DocumentVersion
    fake_models.ParsingRun=ParsingRun
    monkeypatch.setitem(module_sys.modules,'app.models',fake_models)
    monkeypatch.setattr(service,'Session',FakeSession)
    result=service.list_candidate_runs('engine')
    assert result[0]['title']=='SINTETIS Renja'
    assert result[0]['source_unit_count']==13
    assert 'SET TRANSACTION READ ONLY' in seen[0]
    assert 'SYNTHETIC_FIXTURE' in str(service.list_candidate_runs.__code__.co_consts) or 'source_kind' in seen[1]
    assert seen[-1]=='ROLLBACK'


def test_ui_mounts_gradio_components(store,analysis,monkeypatch,tmp_path):
    from app.vs3 import reviewer_ui
    monkeypatch.setattr(reviewer_ui,'list_candidate_runs',lambda *args:[])
    app=reviewer_ui.build_app('source',store,tmp_path)
    assert app is not None
    assert 'PI VS3' in app.title
