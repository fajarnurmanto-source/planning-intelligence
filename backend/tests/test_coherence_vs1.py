"""Regression focused on product result, original PI unit contracts and fail-closed evidence."""
import json
import hashlib
import sys
from pathlib import Path
from types import SimpleNamespace
import pytest
sys.path.insert(0,str(Path(__file__).resolve().parents[1]))
from app.vs1.canonical_bridge import prepare_side, compare_existing_sources, Refused
from app.vs1.report import render_synthetic_review, export_report
from app.vs1.core import num

FIXTURE=Path(__file__).resolve().parent/'fixtures'/'pi_vs1_canonical_pair.json'


def pair():
    obj=json.loads(FIXTURE.read_text(encoding='utf-8'))
    return tuple(obj['left']),tuple(obj['right'])


def test_e2e_canonical_source_units_difference_and_match():
    l,r=pair(); data=compare_existing_sources(l,r)
    assert len(data['findings'])==2
    statuses={f['indicator']:f['status'] for f in data['findings']}
    assert statuses['Persentase layanan selesai']=='POTENTIAL_TARGET_DIFFERENCE'
    assert statuses['Rasio layanan digital']=='NUMERIC_MATCH_PENDING_REVIEW'
    assert all(len(f['evidence'])==2 for f in data['findings'])
    assert all(e['source_unit_id'] and len(e['unit_fingerprint'])==64 and e['document_version_id'] for f in data['findings'] for e in f['evidence'])
    assert data['production_admission']=='DENIED'
    assert data['review_authority']=='NOT_GRANTED'
    assert len(data['report_sha256'])==64
    assert compare_existing_sources(l,r)==data


def test_read_only_pipeline_does_not_modify_inputs():
    l,r=pair()
    before=json.dumps([l,r],sort_keys=True)
    _=compare_existing_sources(l,r)
    assert json.dumps([l,r],sort_keys=True)==before


def test_unauthorized_real_upload_not_consumed():
    l,r=pair();l[1]['source_kind']='UPLOAD'
    with pytest.raises(Refused,match='SOURCE_ADMISSION_NOT_VERIFIED'):compare_existing_sources(l,r)


def test_refuse_failed_or_inconsistent_parse():
    for changed,reason in [('status','PARSING_RUN_NOT_SUCCESSFUL'),('input_sha256','PARSING_INPUT_DIGEST_MISMATCH')]:
        l,r=pair();l[2][changed]='FAILED' if changed=='status' else '0'*64
        with pytest.raises(Refused,match=reason):compare_existing_sources(l,r)


def test_refuse_forged_parser_result_digest():
    l,r=pair();l[2]['result_sha256']='f'*64
    with pytest.raises(Refused,match='PARSING_RESULT_DIGEST_MISMATCH'):compare_existing_sources(l,r)


def test_refuse_missing_or_misbound_evidence():
    l,r=pair();l[3].pop()
    with pytest.raises(Refused,match='PARSING_UNIT_COUNT_MISMATCH'):compare_existing_sources(l,r)
    l,r=pair();l[3][0]['document_version_id']='foreign-version'
    with pytest.raises(Refused,match='SOURCE_UNIT_BOUNDARY_MISMATCH'):compare_existing_sources(l,r)


def test_refuse_duplicate_sequence_and_forged_fingerprint():
    l,r=pair();l[3][1]['sequence_index']=l[3][0]['sequence_index']
    with pytest.raises(Refused,match='SOURCE_UNIT_IDENTITY_INVALID'):compare_existing_sources(l,r)
    l,r=pair();l[3][0]['unit_fingerprint']='not-a-sha'
    with pytest.raises(Refused,match='SOURCE_UNIT_FINGERPRINT_INVALID'):compare_existing_sources(l,r)


def test_refuse_cross_jurisdiction_and_self_comparison():
    l,r=pair();r[0]['jurisdiction_id']='other'
    with pytest.raises(Refused,match='JURISDICTION_BOUNDARY_MISMATCH'):compare_existing_sources(l,r)
    l,r=pair()
    with pytest.raises(Refused,match='SELF_COMPARISON_NOT_ALLOWED'):compare_existing_sources(l,l)


def test_html_review_synthetic_only_and_escapes_user_text(tmp_path):
    l,r=pair();l[0]['title']='<script>alert(1)</script>'
    data=compare_existing_sources(l,r)
    page=render_synthetic_review(data)
    assert '<script>alert(1)</script>' not in page
    assert '&lt;script&gt;' in page
    assert 'CONFIRMED_SYNTHETIC' in page
    json_file,html_file=export_report(data,tmp_path)
    assert json.loads(json_file.read_text(encoding='utf-8'))['report_sha256']==data['report_sha256']
    assert html_file.exists()
    data['rights_admission']='NOT_VERIFIED'
    with pytest.raises(ValueError):render_synthetic_review(data)


def test_metadata_mismatch_does_not_create_false_coherence():
    l,r=pair()
    r[3][13]['raw_text']='Output' # type cell second row, will become incomparable
    # ensure doesn't silently claim target mismatch when type differs
    statuses={f['status'] for f in compare_existing_sources(l,r)['findings']}
    assert 'INCOMPARABLE_TARGET_TYPE' in statuses


def test_numeric_ambiguity_refused():
    for text in ('NaN','Inf','1,234.56','12oo'):
        with pytest.raises(ValueError):num(text)


def test_existing_pi_parser_xlsx_interoperability(tmp_path):
    """Actual repo parser -> its canonical SourceUnit shape -> VS1 bridge.

    Neither the preview server nor duplicate VS1 parser is used.
    """
    from app.parser import parse_document, DEFAULT_CONFIG
    from openpyxl import Workbook
    left,right=pair()
    w=Workbook();ws=w.active;ws.title='Indicators'
    ws.append(['Indikator','Tahun','Target','Satuan','Lingkup','Jenis Target'])
    ws.append(['Persentase layanan selesai',2026,85,'%','Kota Jogja','Outcome'])
    ws.append(['Rasio layanan digital',2026,70,'%','Kota Jogja','Outcome'])
    path=tmp_path/'canonical.xlsx';w.save(path)
    digest=hashlib.sha256(path.read_bytes()).hexdigest()
    parsed=parse_document(path,input_sha256=digest,extension='xlsx',config=DEFAULT_CONFIG)
    assert parsed['source_unit_count']>0
    source=[]
    for i,u in enumerate(parsed['units']):
        source.append({'id':f'unit-{i}', 'document_version_id':'v-left','parsing_run_id':'run-left',
                       'unit_type':u.unit_type,'sequence_index':u.sequence_index,
                       'locator':u.locator,'raw_text':u.raw_text,'unit_fingerprint':u.unit_fingerprint})
    left[1]['sha256']=digest;left[2]['input_sha256']=digest
    left[2]['source_unit_count']=len(source)
    left[2]['result_sha256']=parsed['result_sha256']
    left=list(left);left[3]=source
    doc,units=prepare_side(*left)
    assert len(units)==2
    assert units[0]['pi_source_unit_id']
    assert units[0]['pi_unit_fingerprint']


@pytest.mark.parametrize('fmt',['docx','pdf'])
def test_existing_pi_parser_additional_formats_interoperability(fmt,tmp_path):
    from app.parser import parse_document, DEFAULT_CONFIG
    left,right=pair()
    source_line='Indikator: Persentase layanan selesai | Tahun: 2026 | Target: 85 | Satuan: % | Lingkup: Kota Jogja | Jenis Target: Outcome'
    path=tmp_path / ('fixture.'+fmt)
    if fmt=='docx':
        from docx import Document
        doc=Document();doc.add_paragraph(source_line);doc.save(path)
    else:
        import fitz
        doc=fitz.open();page=doc.new_page(width=1200,height=800)
        page.insert_text((45,75),source_line,fontsize=8);doc.save(path);doc.close()
    digest=hashlib.sha256(path.read_bytes()).hexdigest()
    parsed=parse_document(path,input_sha256=digest,extension=fmt,config=DEFAULT_CONFIG)
    units=[]
    for i,u in enumerate(parsed['units']):
        units.append({'id':f'{fmt}-unit-{i}', 'document_version_id':'v-left','parsing_run_id':'run-left',
                      'unit_type':u.unit_type,'sequence_index':u.sequence_index,'locator':u.locator,
                      'raw_text':u.raw_text,'unit_fingerprint':u.unit_fingerprint})
    left[1]['sha256']=digest;left[2]['input_sha256']=digest
    left[2]['source_unit_count']=len(units);left[2]['result_sha256']=parsed['result_sha256']
    left=list(left);left[3]=units
    _,matched=prepare_side(*left)
    assert matched and matched[0]['indicator']=='Persentase layanan selesai'
    assert matched[0]['pi_source_unit_id']
