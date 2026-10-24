"""Substantive four-document graph E2E: canonical parser → graph → review → export."""
from pathlib import Path
import sys
import json
import pytest
from docx import Document as WordDocument
from openpyxl import load_workbook
sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
from app.graph_core.samples import make_four
from app.graph_core.intake import analyze_four_files, analyze_and_archive_four
from app.graph_core.engine import ROLES, analyze_graph
from app.vs4 import service
from app.vs1.canonical_bridge import Refused
from app.vs5.intake import _parse_side
from app.vs2.reviews import validate_result

ROLES_MAP={'reviewer':'reviewer', 'viewer':'viewer'}

def test_four_document_graph_and_evidence(tmp_path):
    paths=make_four(tmp_path)
    result=analyze_four_files(paths,group='Sasaran',scope='Target tahunan')
    assert result['schema']=='pi.graph_core.g1.synthetic.v1'
    assert result['production_admission']=='DENIED'
    assert result['regulatory_graph']=='NOT_IMPLEMENTED'
    assert result['graph_metrics']['documents']==4
    assert result['graph_metrics']['compared_observation_pairs']==6
    assert len(result['findings'])==6
    assert {f['status'] for f in result['findings']}=={
        'POTENTIAL_TARGET_DIFFERENCE','NUMERIC_MATCH_PENDING_REVIEW'}
    assert len([n for n in result['graph']['nodes'] if n['kind']=='indicator_observation'])==8
    assert len(result['graph']['edges'])==17 # 8 HAS + 3 DECLARED + 6 CANDIDATE
    assert result['graph']['multi_hop_role_paths']['RPJMD_TO_RENJA']==['RPJMD','RENSTRA','RENJA']
    assert all(len(f['evidence'])==2 for f in result['findings'])
    assert all(ev.get('source_unit_id') and ev.get('unit_fingerprint')
               for f in result['findings'] for ev in f['evidence'])
    assert validate_result(result)[0]==result['report_sha256']


def test_archive_review_word_report_restart(tmp_path):
    paths=make_four(tmp_path/'files')
    filename=tmp_path/'reviews.sqlite'
    store=service.build_candidate_store('sqlite:///'+str(filename))
    result=analyze_and_archive_four(store,paths,group='Sasaran',scope='Target tahunan',
                                    actor='reviewer',roles=ROLES_MAP)
    digest=result['report_sha256'];fid=result['findings'][0]['id']
    service.decision(store,digest,fid,'reviewer',ROLES_MAP,'NEEDS_EVIDENCE','Periksa kesamaan metode')
    store.dispose()
    reopened=service.build_candidate_store('sqlite:///'+str(filename))
    assert service.resume(reopened,digest,actor='viewer',roles=ROLES_MAP)==result
    assert any(r[3]=='NEEDS_EVIDENCE' for r in service.table(reopened,digest,actor='viewer',roles=ROLES_MAP))
    json_path,docx_path=service.export(reopened,digest,tmp_path/'export','viewer',ROLES_MAP)
    assert json_path.is_file() and docx_path.is_file()
    assert json.loads(json_path.read_text(encoding='utf-8'))['analysis']['graph_metrics']['documents']==4
    doc=WordDocument(docx_path)
    assert any('SIMULASI' in p.text for p in doc.paragraphs)
    reopened.dispose()


def test_viewer_cannot_write(tmp_path):
    paths=make_four(tmp_path/'files')
    store=service.build_candidate_store('sqlite:///'+str(tmp_path/'s.sqlite'))
    analysis=analyze_and_archive_four(store,paths,group='Sasaran',scope='Target tahunan',
                                     actor='viewer',roles=ROLES_MAP)
    with pytest.raises(PermissionError,match='REVIEWER_ROLE_REQUIRED'):
        service.decision(store,analysis['report_sha256'],analysis['findings'][0]['id'],
                         'viewer',ROLES_MAP,'NEEDS_EVIDENCE')
    store.dispose()


def test_wrong_roles_missing_docs_rejected(tmp_path):
    paths=make_four(tmp_path)
    with pytest.raises(Refused,match='FOUR_DOCUMENTS_REQUIRED'):
        analyze_four_files({'RPJMD':paths['RPJMD']},group='G',scope='S')
    paths['RKPD']=paths['RPJMD']
    with pytest.raises(Refused,match='FOUR_DISTINCT_SOURCE_DOCUMENTS_REQUIRED'):
        analyze_four_files(paths,group='G',scope='S')


def test_duplicate_indicator_fail_closed(tmp_path):
    paths=make_four(tmp_path)
    w=load_workbook(paths['RPJMD']);ws=w.active
    ws.append(['Persentase layanan selesai',2026,85,'%','Layanan publik','Outcome'])
    w.save(paths['RPJMD']);w.close()
    with pytest.raises(Refused,match='AMBIGUOUS_DUPLICATE_INDICATOR_YEAR'):
        analyze_four_files(paths,group='G',scope='S')


def test_missing_link_only_candidate(tmp_path):
    paths=make_four(tmp_path)
    w=load_workbook(paths['RENJA']);ws=w.active
    ws['A3']='Indikator yang tidak tercantum';w.save(paths['RENJA']);w.close()
    result=analyze_four_files(paths,group='G',scope='S')
    assert 'NO_VERIFIED_LINEAGE' in {f['status'] for f in result['findings']}
    assert 'NOT_IMPLEMENTED'==result['regulatory_graph']


def test_marker_and_role_auth_boundary(tmp_path):
    paths=make_four(tmp_path)
    w=load_workbook(paths['RENJA']);w.active['A1']='Official document';w.save(paths['RENJA']);w.close()
    with pytest.raises(Refused,match='SYNTHETIC_MARKER_NOT_FOUND'):
        analyze_four_files(paths,group='G',scope='S')


def test_same_scope_unit_type_compared_safely(tmp_path):
    paths=make_four(tmp_path)
    w=load_workbook(paths['RKPD']);w.active['D3']='hari';w.save(paths['RKPD']);w.close()
    result=analyze_four_files(paths,group='G',scope='S')
    assert 'INCOMPARABLE_UNIT' in {f['status'] for f in result['findings']}


def test_gradio_can_build_graph_tab(tmp_path):
    from app.vs4.reviewer_ui import build_app
    store=service.build_candidate_store('sqlite:///'+str(tmp_path/'state.sqlite'))
    ui=build_app(store,tmp_path/'reports',ROLES_MAP)
    assert 'Graph' in ui.title
    store.dispose()
