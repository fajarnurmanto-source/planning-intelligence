"""VS5 vertical slice: real PI parser -> VS1 evidence -> VS4 review/Word export."""
from pathlib import Path
import sys
import pytest
from docx import Document as Docx
from openpyxl import load_workbook
sys.path.insert(0,str(Path(__file__).resolve().parents[1]))
from app.vs5.samples import create_sample_pair
from app.vs5.intake import analyze_pair,analyze_and_archive,SYNTHETIC_MARKER
from app.vs4 import service
from app.vs1.canonical_bridge import Refused
from app.vs2.reviews import validate_result

ROLES={'reviewer':'reviewer','viewer':'viewer'}


def test_complete_upload_to_report_with_original_parser(tmp_path):
    left,right=create_sample_pair(tmp_path/'inputs')
    dbfile=tmp_path/'state.sqlite'
    engine=service.build_candidate_store('sqlite:///'+str(dbfile))
    analysis=analyze_and_archive(engine,left,right,group='Sasaran',scope='Target tahunan',actor='reviewer',roles=ROLES)
    digest,ids=validate_result(analysis)
    assert len(ids)==2
    assert {f['status'] for f in analysis['findings']} == {'POTENTIAL_TARGET_DIFFERENCE','NUMERIC_MATCH_PENDING_REVIEW'}
    fid=analysis['findings'][0]['id']
    evidence=analysis['findings'][0]['evidence']
    assert evidence and all(x['source_unit_id'] and x['unit_fingerprint'] and 'locator' in x for x in evidence)
    service.decision(engine,digest,fid,'reviewer',ROLES,'NEEDS_EVIDENCE','Periksa angka uji')
    engine.dispose()
    reopened=service.build_candidate_store('sqlite:///'+str(dbfile))
    assert service.resume(reopened,digest,actor='viewer',roles=ROLES)==analysis
    assert service.table(reopened,digest,actor='viewer',roles=ROLES)[0][3]=='NEEDS_EVIDENCE'
    exported=service.export(reopened,digest,tmp_path/'out','viewer',ROLES)
    assert all(p.is_file() for p in exported)
    assert 'SIMULASI' in '\n'.join(p.text for p in Docx(exported[1]).paragraphs)
    reopened.dispose()


def test_no_synthetic_marker_rejected(tmp_path):
    left,right=create_sample_pair(tmp_path)
    wb=load_workbook(left); wb.active['A1']='Dokumen asli'; wb.save(left);wb.close()
    with pytest.raises(Refused,match='SYNTHETIC_MARKER_NOT_FOUND'):
        analyze_pair(left,right,group='Sasaran',scope='Target tahunan')


def test_same_document_rejected(tmp_path):
    left,_=create_sample_pair(tmp_path)
    with pytest.raises(Refused,match='DISTINCT_SYNTHETIC_DOCUMENTS_REQUIRED'):
        analyze_pair(left,left,group='Sasaran',scope='Target tahunan')


def test_invalid_extension_rejected(tmp_path):
    left,_=create_sample_pair(tmp_path)
    unknown=tmp_path/'private.txt'; unknown.write_text(SYNTHETIC_MARKER)
    with pytest.raises(Refused,match='SUPPORTED_SYNTHETIC_PDF_DOCX_XLSX_REQUIRED'):
        analyze_pair(left,unknown,group='Sasaran',scope='Target tahunan')


def test_viewer_may_analyze_but_not_review(tmp_path):
    left,right=create_sample_pair(tmp_path/'inputs')
    store=service.build_candidate_store('sqlite:///'+str(tmp_path/'s.sqlite'))
    a=analyze_and_archive(store,left,right,group='Sasaran',scope='Target tahunan',actor='viewer',roles=ROLES)
    with pytest.raises(PermissionError,match='REVIEWER_ROLE_REQUIRED'):
        service.decision(store,a['report_sha256'],a['findings'][0]['id'],'viewer',ROLES,'NEEDS_EVIDENCE')
    store.dispose()


def test_bad_scope_and_empty_files_rejected(tmp_path):
    left,right=create_sample_pair(tmp_path)
    with pytest.raises(Refused,match='COMPARISON_SCOPE_REQUIRED'):
        analyze_pair(left,right,group='Sasaran',scope=' ')
    left.write_bytes(b'')
    with pytest.raises(Refused,match='SYNTHETIC_FILE_SIZE_OUT_OF_BOUNDS'):
        analyze_pair(left,right,group='Sasaran',scope='Target tahunan')


def test_mismatched_unit_not_claimed_coherent(tmp_path):
    left,right=create_sample_pair(tmp_path)
    wb=load_workbook(right);wb.active['D3']='hari';wb.save(right);wb.close()
    result=analyze_pair(left,right,group='Sasaran',scope='Target tahunan')
    assert 'INCOMPARABLE_UNIT' in {x['status'] for x in result['findings']}


def test_gradio_ui_reuses_vs4(tmp_path):
    from app.vs4.reviewer_ui import build_app
    from app.vs5.samples import create_sample_pair
    left,right=create_sample_pair(tmp_path/'example')
    engine=service.build_candidate_store('sqlite:///'+str(tmp_path/'s.sqlite'))
    ui=build_app(engine,tmp_path/'out',ROLES,fixture=Path(__file__).parent/'fixtures'/'pi_vs1_canonical_pair.json')
    assert 'VS5' in ui.title
    engine.dispose()


def test_pdf_and_docx_cross_format_reuses_pi_parser(tmp_path):
    import fitz
    left=tmp_path/'renstra_synthetic.pdf'
    d=fitz.open(); page=d.new_page()
    page.insert_text((35,55),SYNTHETIC_MARKER,fontsize=10)
    page.insert_text((35,85),
        'indikator: Rasio layanan digital | tahun: 2026 | target: 70 | satuan: % | lingkup: Layanan publik | jenis target: Outcome',
        fontsize=8)
    d.save(str(left));d.close()
    right=tmp_path/'renja_synthetic.docx'
    w=Docx();w.add_paragraph(SYNTHETIC_MARKER)
    w.add_paragraph('indikator: Rasio layanan digital | tahun: 2026 | target: 60 | satuan: % | lingkup: Layanan publik | jenis target: Outcome')
    w.save(right)
    result=analyze_pair(left,right,group='Sasaran',scope='Target tahunan')
    assert len(result['findings']) == 1
    finding=result['findings'][0]
    assert finding['status']=='POTENTIAL_TARGET_DIFFERENCE'
    assert all('source_unit_id' in ev for ev in finding['evidence'])
    assert {ev['document_title'] for ev in finding['evidence']} == {left.name,right.name}


def test_report_download_uses_gradio_allowed_temp_without_exposing_archive(tmp_path):
    from app.vs4.reviewer_ui import _stage_reports_for_gradio
    import tempfile
    archive = tmp_path/'outside_gradio'
    archive.mkdir()
    j = archive/'analysis.json'
    w = archive/'analysis.docx'
    j.write_text('{"test":true}', encoding='utf-8')
    w.write_bytes(b'synthetic docx fixture for path boundary')
    staged = _stage_reports_for_gradio((j,w), archive)
    assert [Path(p).read_bytes() for p in staged] == [j.read_bytes(),w.read_bytes()]
    assert all(Path(p).resolve().is_relative_to(Path(tempfile.gettempdir()).resolve()) for p in staged)
    assert all(not Path(p).resolve().is_relative_to(archive.resolve()) for p in staged)
    assert j.is_file() and w.is_file()
    other = tmp_path/'other.json'
    other.write_text('{"secret":"not exported"}', encoding='utf-8')
    with pytest.raises(ValueError,match='INVALID_CANDIDATE_REPORT_PATHS'):
        _stage_reports_for_gradio((other,w),archive)
