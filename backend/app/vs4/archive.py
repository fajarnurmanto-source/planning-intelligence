"""SQLAlchemy-backed analysis archive. No new persistence framework.

VS4 explicitly stores *synthetic derived analysis*, not original documents. This
uses the existing VS2 local review database and its append-only action table.
"""
from __future__ import annotations
from datetime import datetime, timezone
import json
import re
from sqlalchemy import Column, DateTime, String, Text, select
from sqlalchemy.orm import DeclarativeBase, Session
from app.vs2.reviews import validate_result

MAX_SNAPSHOT_BYTES = 2_000_000
_SHA = re.compile(r'^[a-f0-9]{64}$')


class ArchiveBase(DeclarativeBase):
    pass


class AnalysisSnapshot(ArchiveBase):
    __tablename__ = 'pi_vs4_candidate_analysis_snapshots'
    digest = Column(String(64), primary_key=True)
    analysis_json = Column(Text, nullable=False)
    created_at_utc = Column(DateTime(timezone=True), nullable=False)
    created_by = Column(String(128), nullable=False)


def initialize_archive(engine) -> None:
    # Candidate-only DDL in its own review database, never source PI DB.
    ArchiveBase.metadata.create_all(engine, tables=[AnalysisSnapshot.__table__])


def archive_analysis(engine, analysis: dict, *, actor: str) -> str:
    digest, _ = validate_result(analysis)
    if not isinstance(actor, str) or not re.fullmatch(r'[A-Za-z0-9_.@-]{2,128}', actor):
        raise PermissionError('VERIFIED_CANDIDATE_ACTOR_REQUIRED')
    serial = json.dumps(analysis, ensure_ascii=False, sort_keys=True, separators=(',', ':'))
    if len(serial.encode('utf-8')) > MAX_SNAPSHOT_BYTES:
        raise ValueError('ARCHIVE_SNAPSHOT_TOO_LARGE')
    with Session(engine) as s:
        existing = s.get(AnalysisSnapshot, digest)
        if existing is not None:
            if existing.analysis_json != serial:
                raise ValueError('ARCHIVE_DIGEST_COLLISION_OR_CORRUPTION')
            return digest
        s.add(AnalysisSnapshot(digest=digest, analysis_json=serial,
              created_at_utc=datetime.now(timezone.utc), created_by=actor))
        s.commit()
    return digest


def load_analysis(engine, digest: str) -> dict:
    if not isinstance(digest, str) or not _SHA.fullmatch(digest):
        raise ValueError('ANALYSIS_DIGEST_INVALID')
    with Session(engine) as s:
        snapshot = s.get(AnalysisSnapshot, digest)
        if snapshot is None:
            raise LookupError('ARCHIVED_ANALYSIS_NOT_FOUND')
        raw = snapshot.analysis_json
    if len(raw.encode('utf-8')) > MAX_SNAPSHOT_BYTES:
        raise ValueError('ARCHIVE_SNAPSHOT_TOO_LARGE')
    analysis = json.loads(raw)
    verified, _ = validate_result(analysis)
    if verified != digest:
        raise ValueError('ARCHIVED_ANALYSIS_DIGEST_MISMATCH')
    return analysis


def list_archived(engine, *, limit: int = 100) -> list[dict]:
    if not isinstance(limit, int) or not 1 <= limit <= 200:
        raise ValueError('ARCHIVE_LIST_LIMIT_INVALID')
    with Session(engine) as s:
        records = s.execute(select(AnalysisSnapshot.digest, AnalysisSnapshot.created_at_utc,
                                  AnalysisSnapshot.created_by)
                     .order_by(AnalysisSnapshot.created_at_utc.desc(), AnalysisSnapshot.digest)
                     .limit(limit)).all()
    return [{'digest': r.digest, 'created_at_utc': r.created_at_utc.isoformat(),
             'created_by': r.created_by} for r in records]
