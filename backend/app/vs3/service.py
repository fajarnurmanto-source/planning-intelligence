"""Canonical PI DB → existing VS1 analysis → existing VS2 reviewer persistence.

Read queries are transaction-read-only, limited, and synthetic-only. Decision
writes go to a separate candidate store, never to PI's existing planning tables.
"""
from __future__ import annotations
from pathlib import Path
from uuid import UUID

from sqlalchemy import create_engine, select, text
from sqlalchemy.orm import Session

from app.vs1.canonical_bridge import Refused
from app.vs1.orm_loader import compare_synthetic_parsing_runs
from app.vs2.workflow import decide, review_rows, export_review, initialize_candidate_store


def canonical_engine(database_url: str):
    if not isinstance(database_url, str) or not database_url.startswith('postgresql+psycopg://'):
        raise ValueError('CANONICAL_POSTGRESQL_URL_REQUIRED')
    return create_engine(database_url, pool_pre_ping=True, connect_args={'connect_timeout': 5})


def review_engine(database_url: str):
    if not isinstance(database_url, str) or not database_url.startswith('sqlite:///'):
        raise ValueError('VS3_SEPARATE_LOCAL_SQLITE_STORE_REQUIRED')
    engine = create_engine(database_url, pool_pre_ping=True)
    # SQLite state is explicitly separate from the original PI PostgreSQL database.
    initialize_candidate_store(engine)
    return engine


def list_candidate_runs(engine, *, limit: int = 100) -> list[dict]:
    """Small listing, no raw document payloads; requires existing canonical ORM."""
    if not isinstance(limit, int) or not 1 <= limit <= 200:
        raise ValueError('RUN_LIST_LIMIT_INVALID')
    from app.models import Document, DocumentVersion, ParsingRun
    with Session(engine) as db:
        db.execute(text('SET TRANSACTION READ ONLY'))
        statement = (select(ParsingRun.id, Document.title, Document.doc_type,
                            Document.jurisdiction_id, Document.planning_period_id,
                            ParsingRun.source_unit_count)
            .join(DocumentVersion, ParsingRun.document_version_id == DocumentVersion.id)
            .join(Document, DocumentVersion.document_id == Document.id)
            .where(ParsingRun.status == 'SUCCEEDED')
            .where(DocumentVersion.source_kind == 'SYNTHETIC_FIXTURE')
            .where(ParsingRun.source_unit_count > 0)
            .where(ParsingRun.source_unit_count <= 10000)
            .order_by(ParsingRun.created_at.desc(), ParsingRun.id)
            .limit(limit))
        rows = db.execute(statement).all()
        db.rollback()
    return [{'run_id': str(r.id), 'title': r.title,
             'document_type': r.doc_type,
             'jurisdiction_id': str(r.jurisdiction_id),
             'planning_period_id': str(r.planning_period_id or ''),
             'source_unit_count': r.source_unit_count} for r in rows]


def analyze_runs(engine, left_run_id: str, right_run_id: str, *, group: str, scope: str) -> dict:
    if not isinstance(group,str) or not group.strip() or len(group)>150:
        raise Refused('GROUP_REQUIRED')
    if not isinstance(scope,str) or not scope.strip() or len(scope)>150:
        raise Refused('SCOPE_REQUIRED')
    try:
        left, right = UUID(str(left_run_id)), UUID(str(right_run_id))
    except (ValueError, TypeError, AttributeError) as exc:
        raise Refused('PARSING_RUN_UUID_INVALID') from exc
    if left == right:
        raise Refused('CANNOT_COMPARE_SAME_RUN')
    with Session(engine) as db:
        analysis = compare_synthetic_parsing_runs(db, left, right,
                                                   group=group.strip(), scope=scope.strip())
        db.rollback()
    # Preserve VS1 synthetic-only qualification; review export checks digest again.
    if analysis.get('production_admission') != 'DENIED' or analysis.get('rights_admission') != 'SYNTHETIC_ONLY':
        raise Refused('ANALYSIS_ADMISSION_NOT_SYNTHETIC')
    return analysis


def submit_decision(store, analysis: dict, finding_id: str,
                    verified_username: str, decision: str, note: str='') -> dict:
    if not verified_username:
        raise PermissionError('AUTHENTICATED_REVIEWER_REQUIRED')
    return decide(store, analysis, finding_id, verified_username, decision, note)


def read_review_history(store, analysis: dict) -> list[dict]:
    return review_rows(store, analysis)


def export_candidate_report(store, analysis: dict, output_folder: Path) -> tuple[Path, Path]:
    return export_review(analysis, read_review_history(store, analysis), Path(output_folder) / analysis['report_sha256'])
