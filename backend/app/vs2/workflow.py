"""One bounded candidate workflow using existing VS1 parser/evidence and python-docx.

No remote dependencies or document upload, no P5-G12 promotion, no official
approval. The analysis is produced by VS1 compare_existing_sources.
"""
from __future__ import annotations
from hashlib import sha256
import json
import os
from pathlib import Path
import tempfile

from sqlalchemy import create_engine
from sqlalchemy.orm import Session
from docx import Document as WordDocument

from app.vs1.canonical_bridge import compare_existing_sources
from .reviews import ReviewBase, add_review, list_reviews, validate_result


def open_store(db_url: str):
    # The file/database is outside the tracked repository; no PI operational DB use.
    if not (db_url.startswith('sqlite:///') or db_url.startswith('postgresql+psycopg://')):
        raise ValueError('UNSUPPORTED_REVIEW_STORE_DRIVER')
    engine = create_engine(db_url, pool_pre_ping=True)
    return engine


def initialize_candidate_store(engine):
    # Candidate only. Future migration must be reviewed before production use.
    ReviewBase.metadata.create_all(engine, tables=[ReviewBase.metadata.tables['pi_vs2_candidate_review_actions']])


def analyze_fixture(path: Path) -> dict:
    with Path(path).open(encoding='utf-8') as f:
        raw = json.load(f)
    if set(raw.keys()) != {'left', 'right'}:
        raise ValueError('ONLY_CANONICAL_FIXTURE_PAIR_ACCEPTED')
    return compare_existing_sources(tuple(raw['left']), tuple(raw['right']))


def decide(engine, analysis, finding_id, reviewer, decision, note=''):
    with Session(engine) as s:
        return add_review(s, analysis, finding_id, reviewer, decision, note)


def review_rows(engine, analysis):
    with Session(engine) as s:
        return list_reviews(s, analysis)


def export_review(analysis: dict, actions: list[dict], folder: Path) -> tuple[Path, Path]:
    digest, valid_findings = validate_result(analysis)
    # Always verify that actions are scoped to the same analysis.
    for action in actions:
        if action['analysis_sha256'] != digest or action['finding_id'] not in valid_findings:
            raise ValueError('REVIEW_ACTION_SCOPE_MISMATCH')
    result = {'schema': 'pi.vs2.synthetic_review.v1',
              'production_admission': 'DENIED',
              'review_authority': 'NOT_GRANTED',
              'analysis': analysis, 'review_actions': actions}
    folder = Path(folder)
    folder.mkdir(parents=True, exist_ok=True)
    # Reuse python-docx, not a custom PDF generator. No credential/document originals.
    report = WordDocument()
    report.add_heading('Planning Intelligence — Review Temuan VS2', 0)
    report.add_paragraph('SIMULASI • BUKAN TEMUAN RESMI • BELUM ADMISSION PRODUKSI')
    report.add_paragraph('SHA-256 analisis: ' + digest)
    report.add_heading('Ringkasan', level=1)
    report.add_paragraph(f"{len(valid_findings)} temuan kandidat; {len(actions)} aksi reviewer simulasi")
    last = {action['finding_id']: action for action in actions}
    for position, finding in enumerate(analysis['findings']):
        if position:
            report.add_page_break()
        report.add_heading(f"{finding['indicator']} — {finding['year']}", level=2)
        report.add_paragraph(f"Status: {finding['status']}")
        report.add_paragraph('Alasan: ' + finding['rationale'])
        for ev in finding['evidence']:
            report.add_paragraph('Sumber: ' + ev['document_title'] + ' | ' + ev['locator'])
            report.add_paragraph('SourceUnit: ' + ev['source_unit_id'] + ' | sha256: ' + ev['unit_fingerprint'])
            report.add_paragraph('Kutipan: ' + ev['excerpt'])
        action = last.get(finding['id'])
        if action:
            report.add_paragraph('Keputusan simulasi: ' + action['decision'] + ' oleh ' + action['reviewer'])
            if action['note']: report.add_paragraph('Catatan: ' + action['note'])
        else:
            report.add_paragraph('Keputusan simulasi: PENDING')
    export_json = folder / 'PI_VS2_CANDIDATE_REVIEW.json'
    export_docx = folder / 'PI_VS2_CANDIDATE_REVIEW.docx'
    with tempfile.NamedTemporaryFile(mode='w', encoding='utf-8', dir=folder, delete=False, suffix='.json.tmp') as temp:
        json.dump(result, temp, ensure_ascii=False, indent=2, sort_keys=True)
        temp.write('\n')
        tmp_path = Path(temp.name)
    os.replace(tmp_path, export_json)
    with tempfile.NamedTemporaryFile(dir=folder, delete=False, suffix='.docx.tmp') as temp:
        tmp_word = Path(temp.name)
    try:
        report.save(str(tmp_word))
        os.replace(tmp_word, export_docx)
    finally:
        tmp_word.unlink(missing_ok=True)
    return export_json, export_docx
