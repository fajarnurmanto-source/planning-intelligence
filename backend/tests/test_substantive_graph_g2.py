"""G2 synthetic end-to-end tests atop real G1/VS4 module interfaces."""
from pathlib import Path
import sys
import json
import pytest
from openpyxl import load_workbook
from docx import Document
sys.path.insert(0,str(Path(__file__).resolve().parents[1]))
from app.graph_core.samples import make_four
from app.graph_core.intake import analyze_and_archive_four
from app.graph_g2.samples import make_ledger
from app.graph_g2.engine import parse_ledger,analyze_substantive
from app.graph_g2.intake import analyze_g1_archive_and_ledger
from app.vs4 import service
from app.vs1.canonical_bridge import Refused
from app.vs2.reviews import validate_result
ROLES={'reviewer':'reviewer','viewer':'viewer'}


def _g1(tmp_path):
    store=service.build_candidate_store('sqlite:///'+str(tmp_path/'review.sqlite'))
    g1=analyze_and_archive_four(store,make_four(tmp_path/'files'),group='Sasaran',scope='Target tahunan',
                               actor='reviewer',roles=ROLES)
    return store,g1


def test_chain_claim_with_valid_synthetic_ledger(tmp_path):
    store,g1=_g1(tmp_path)
    g2=analyze_g1_archive_and_ledger(store,g1['report_sha256'],make_ledger(tmp_path/'ledger'),
                                     actor='reviewer',roles=ROLES)
    assert g2['schema']=='pi.graph_g2.substantive.synthetic.v1'
    assert g2['graph_metrics']=={'nodes':6,'edges':6,'evidence_link_declarations':6,
                                 'findings':0,'status_counts':{}}
    assert validate_result(g2)[0]==g2['report_sha256']
    assert g2['source_g1_sha256']==g1['report_sha256']
    assert all(e['has_declared_evidence'] for e in g2['graph']['edges'])
    assert 'not verified canonical SourceUnit' in g2['boundary_notice']
    store.dispose()


def test_gap_evidence_and_incomplete_path(tmp_path):
    store,g1=_g1(tmp_path)
    g2=analyze_g1_archive_and_ledger(store,g1['report_sha256'],make_ledger(tmp_path/'ledger',break_link=True),
                                     actor='reviewer',roles=ROLES)
    assert g2['graph_metrics']['evidence_link_declarations']==5
    assert g2['graph_metrics']['status_counts']['RELATION_EVIDENCE_GAP']==1
    assert g2['graph_metrics']['status_counts']['INCOMPLETE_SUBSTANTIVE_LINEAGE']>=1
    assert all(f['evidence'] for f in g2['findings'])
    assert all(ev['provenance_class']=='OPERATOR_LEDGER_ASSERTION_NOT_SOURCE_PROOF'
               for f in g2['findings'] for ev in f['evidence'])
    store.dispose()


def test_review_archive_export_word_json_after_restart(tmp_path):
    store,g1=_g1(tmp_path)
    g2=analyze_g1_archive_and_ledger(store,g1['report_sha256'],make_ledger(tmp_path/'ledger',break_link=True),
                                     actor='reviewer',roles=ROLES)
    fid=g2['findings'][0]['id'];digest=g2['report_sha256']
    assert service.decision(store,digest,fid,'reviewer',ROLES,'NEEDS_EVIDENCE','Source evidence insufficient')
    store.dispose()
    restarted=service.build_candidate_store('sqlite:///'+str(tmp_path/'review.sqlite'))
    assert service.resume(restarted,digest,actor='viewer',roles=ROLES)==g2
    actions=service.table(restarted,digest,actor='viewer',roles=ROLES)
    assert any(r[3]=='NEEDS_EVIDENCE' for r in actions)
    jpath,wpath=service.export(restarted,digest,tmp_path/'out','viewer',ROLES)
    assert jpath.is_file() and wpath.is_file()
    assert json.loads(jpath.read_text(encoding='utf-8'))['analysis']['schema']==g2['schema']
    assert Document(wpath).paragraphs
    with pytest.raises(PermissionError,match='REVIEWER_ROLE_REQUIRED'):
        service.decision(restarted,digest,fid,'viewer',ROLES,'NEEDS_EVIDENCE')
    restarted.dispose()


def test_wrong_g1_source_rejected(tmp_path):
    store,g1=_g1(tmp_path)
    tampered=dict(g1);tampered['schema']='other'
    # digest deliberately mismatched: mandatory checksum validation
    with pytest.raises(ValueError,match='DIGEST_MISMATCH'):
        analyze_substantive(tampered,parse_ledger(make_ledger(tmp_path/'ledger')))
    store.dispose()


def test_duplicate_ids_and_unknown_type_rejected(tmp_path):
    p=make_ledger(tmp_path)
    wb=load_workbook(p)
    wb['Nodes']['A4']=wb['Nodes']['A3'].value
    wb.save(p);wb.close()
    with pytest.raises(Refused,match='AMBIGUOUS_NODE_ID'):
        parse_ledger(p)
    p=make_ledger(tmp_path)
    wb=load_workbook(p)
    wb['Edges']['D2']='IS_CAUSAL_PROOF'
    wb.save(p);wb.close()
    store,g1=_g1(tmp_path)
    with pytest.raises(Refused,match='INVALID_ONTOLOGY_EDGE_TYPE'):
        analyze_substantive(g1,parse_ledger(p))
    store.dispose()


def test_no_formula_no_official_file_no_overflow(tmp_path):
    p=make_ledger(tmp_path)
    wb=load_workbook(p);wb['Nodes']['D3']='=1+1';wb.save(p);wb.close()
    with pytest.raises(Refused,match='FORMULA_NOT_ALLOWED'):
        parse_ledger(p)
    p=make_ledger(tmp_path)
    wb=load_workbook(p);wb['Meta']['A1']='Official Document';wb.save(p);wb.close()
    with pytest.raises(Refused,match='SYNTHETIC_MARKER'):
        parse_ledger(p)


def test_g2_does_not_write_original_g1(tmp_path):
    store,g1=_g1(tmp_path)
    before=json.dumps(g1,ensure_ascii=False,sort_keys=True)
    analyze_g1_archive_and_ledger(store,g1['report_sha256'],make_ledger(tmp_path/'ledger',break_link=True),
                                  actor='viewer',roles=ROLES)
    assert json.dumps(service.resume(store,g1['report_sha256'],actor='viewer',roles=ROLES),
                      ensure_ascii=False,sort_keys=True)==before
    store.dispose()


def test_scope_mismatch_produces_candidate_not_assertion(tmp_path):
    store,g1=_g1(tmp_path)
    p=make_ledger(tmp_path/'ledger')
    wb=load_workbook(p);wb['Nodes']['F5']='Kesehatan';wb.save(p);wb.close()
    g2=analyze_substantive(g1,parse_ledger(p))
    assert 'SCOPE_MISMATCH_CANDIDATE' in {x['status'] for x in g2['findings']}
    assert g2['review_authority']=='NOT_GRANTED'
    store.dispose()


def test_ui_build_includes_g2(tmp_path):
    from app.vs4.reviewer_ui import build_app
    store=service.build_candidate_store('sqlite:///'+str(tmp_path/'state.sqlite'))
    ui=build_app(store,tmp_path/'reports',ROLES)
    assert 'G2' in ui.title
    store.dispose()
