"""G3 tests: exact original document SHA, SourceUnit locator, excerpt & review safety."""
from pathlib import Path
import sys
import json
import pytest
from openpyxl import load_workbook
from docx import Document
sys.path.insert(0,str(Path(__file__).resolve().parents[1]))
from app.graph_core.intake import analyze_four_files
from app.graph_g2.engine import parse_ledger, analyze_substantive
from app.graph_g3.engine import analyze_g2_and_originals,source_locator
from app.graph_g3.samples import make_grounded_set
from app.graph_g3.intake import analyze_g2_archive_and_originals
from app.vs4 import service
from app.vs4.archive import archive_analysis
from app.vs1.canonical_bridge import Refused
from app.vs2.reviews import validate_result

ROLES={'reviewer':'reviewer','viewer':'viewer'}


def chain(tmp_path,**kw):
    files,ledger=make_grounded_set(tmp_path,**kw)
    g1=analyze_four_files(files,group='Sasaran',scope='Target tahunan')
    g2=analyze_substantive(g1,parse_ledger(ledger))
    return files,ledger,g1,g2


def test_exact_original_quotes_and_source_unit_fingerprints(tmp_path):
    files,ledger,g1,g2=chain(tmp_path)
    g3=analyze_g2_and_originals(g2,ledger,files)
    assert g3['schema']=='pi.graph_g3.document_grounded.synthetic.v1'
    assert g3['verification_metrics']=={'node_quotes_verified':6,'node_quotes_total':6,
                                        'edge_quotes_verified':6,'edge_quotes_total':6,'source_gaps':0}
    assert not g3['findings']
    assert g3['source_g2_sha256']==g2['report_sha256']
    assert g3['source_g1_sha256']==g1['report_sha256']
    for ev in (x['evidence'] for x in g3['node_verification'].values()):
        assert ev['source_unit_id'] and len(ev['unit_fingerprint'])==64
        assert ev['verification_scope']=='EXACT_TEXT_AT_SOURCE_UNIT_ONLY'
    for x in g3['edge_verification'].values():
        assert x['semantic_entailment']=='NOT_TESTED'
        assert x['causal_validity']=='NOT_TESTED'
    assert validate_result(g3)[0]==g3['report_sha256']


def test_false_relationship_quote_fails_closed(tmp_path):
    files,ledger,g1,g2=chain(tmp_path,change_quote=True)
    result=analyze_g2_and_originals(g2,ledger,files)
    assert result['verification_metrics']['edge_quotes_verified']==5
    assert 'SOURCE_QUOTE_MISMATCH' in {e['quote_status'] for e in result['edge_verification'].values()}
    assert {f['status'] for f in result['findings']}=={'G3_RELATION_SOURCE_GAP'}


def test_wrong_source_locator_explicit_gap(tmp_path):
    files,ledger,g1,g2=chain(tmp_path,change_locator=True)
    result=analyze_g2_and_originals(g2,ledger,files)
    assert result['verification_metrics']['node_quotes_verified']==5
    assert 'G3_NODE_SOURCE_NOT_LOCATED' in {f['status'] for f in result['findings']}
    assert 'G3_RELATION_SOURCE_GAP' in {f['status'] for f in result['findings']}
    assert result['node_verification']['T1']['status']=='SOURCE_LOCATOR_NOT_FOUND'


def test_swapped_source_documents_rejected(tmp_path):
    files,ledger,g1,g2=chain(tmp_path)
    swapped=dict(files)
    swapped['RPJMD']=files['RKPD']
    with pytest.raises(Refused,match='G3_SOURCE_SHA256_MISMATCH_RPJMD'):
        analyze_g2_and_originals(g2,ledger,swapped)


def test_modified_original_document_rejected(tmp_path):
    files,ledger,g1,g2=chain(tmp_path)
    wb=load_workbook(files['RENJA']);wb['Target']['C3']=1;wb.save(files['RENJA']);wb.close()
    with pytest.raises(Refused,match='G3_SOURCE_SHA256_MISMATCH_RENJA'):
        analyze_g2_and_originals(g2,ledger,files)


def test_modified_ledger_rejected_before_verification(tmp_path):
    files,ledger,g1,g2=chain(tmp_path)
    wb=load_workbook(ledger);wb['Nodes']['D3']='Manipulated';wb.save(ledger);wb.close()
    with pytest.raises(Refused,match='G3_LEDGER_DIGEST_MISMATCH'):
        analyze_g2_and_originals(g2,ledger,files)


def test_missing_role_rejected(tmp_path):
    files,ledger,g1,g2=chain(tmp_path)
    with pytest.raises(Refused,match='G3_FOUR_ORIGINAL_SOURCE_ROLES_REQUIRED'):
        analyze_g2_and_originals(g2,ledger,{'RPJMD':files['RPJMD']})


def test_g2_digest_corruption_rejected(tmp_path):
    files,ledger,g1,g2=chain(tmp_path)
    fake=dict(g2,source_g1_sha256='untrusted')
    with pytest.raises(ValueError,match='ANALYSIS_DIGEST_MISMATCH'):
        analyze_g2_and_originals(fake,ledger,files)


def test_review_persistence_export_original_g2_unchanged(tmp_path):
    files,ledger,g1,g2=chain(tmp_path,change_quote=True)
    store=service.build_candidate_store('sqlite:///'+str(tmp_path/'review.sqlite'))
    archive_analysis(store,g1,actor='reviewer')
    archive_analysis(store,g2,actor='reviewer')
    before=json.dumps(g2,ensure_ascii=False,sort_keys=True)
    g3=analyze_g2_archive_and_originals(store,g2['report_sha256'],ledger,files,
                                         actor='reviewer',roles=ROLES)
    fid=g3['findings'][0]['id']
    event=service.decision(store,g3['report_sha256'],fid,'reviewer',ROLES,
                           'NEEDS_EVIDENCE','Source quote absent')
    assert event['decision']=='NEEDS_EVIDENCE'
    assert json.dumps(service.resume(store,g2['report_sha256'],actor='viewer',roles=ROLES),
                      ensure_ascii=False,sort_keys=True)==before
    store.dispose()
    restarted=service.build_candidate_store('sqlite:///'+str(tmp_path/'review.sqlite'))
    again=service.resume(restarted,g3['report_sha256'],actor='viewer',roles=ROLES)
    assert again==g3
    assert any(row[3]=='NEEDS_EVIDENCE' for row in service.table(
        restarted,g3['report_sha256'],actor='viewer',roles=ROLES))
    paths=service.export(restarted,g3['report_sha256'],tmp_path/'export','viewer',ROLES)
    assert all(p.is_file() for p in paths)
    assert Document(next(p for p in paths if p.suffix=='.docx')).paragraphs
    with pytest.raises(PermissionError,match='REVIEWER_ROLE_REQUIRED'):
        service.decision(restarted,g3['report_sha256'],fid,'viewer',ROLES,'NEEDS_EVIDENCE')
    restarted.dispose()


def test_still_synthetic_even_all_quotes_match(tmp_path):
    files,ledger,g1,g2=chain(tmp_path)
    g3=analyze_g2_and_originals(g2,ledger,files)
    assert g3['rights_admission']=='SYNTHETIC_ONLY'
    assert g3['production_admission']=='DENIED'
    assert g3['review_authority']=='NOT_GRANTED'
    assert 'NOT proof' in g3['boundary_notice']


def test_ui_contains_g3_without_rewriting_g1_g2(tmp_path):
    from app.vs4.reviewer_ui import build_app
    store=service.build_candidate_store('sqlite:///'+str(tmp_path/'review.sqlite'))
    app=build_app(store,tmp_path/'reports',ROLES)
    assert 'G3' in app.title and 'G2' in app.title and 'G1' in app.title
    store.dispose()


def test_parser_locator_examples():
    assert source_locator({'unit_type':'TABLE_CELL',
            'locator':{'format':'xlsx','sheet_name':'Evidence','coordinate':'B2'}})=='xlsx:Evidence!B2'
    assert source_locator({'unit_type':'TEXT_BLOCK',
            'locator':{'format':'pdf','page':2,'block_ordinal':3}})=='pdf:page=2:block=3'
    assert source_locator({'unit_type':'PARAGRAPH',
            'locator':{'format':'docx','paragraph_index':3}})=='docx:paragraph=3'
