"""Append-only synthetic candidate reviews built on SQLAlchemy (not custom persistence).

Records *review actions*, never institutional admission or authority. A future
approved identity component must replace the local Gradio login boundary before
any institutional use. No document payloads are stored in this table.
"""
from __future__ import annotations
from datetime import datetime, timezone
from hashlib import sha256
import json
import re
import uuid

from sqlalchemy import Column, DateTime, Integer, String, Text, UniqueConstraint, select
from sqlalchemy.orm import DeclarativeBase, Session


class ReviewBase(DeclarativeBase):
    pass


class ReviewAction(ReviewBase):
    __tablename__ = 'pi_vs2_candidate_review_actions'
    __table_args__ = (UniqueConstraint('event_id', name='uq_pi_vs2_candidate_review_event'),)

    event_id = Column(String(36), primary_key=True)
    analysis_sha256 = Column(String(64), nullable=False, index=True)
    finding_id = Column(String(24), nullable=False, index=True)
    reviewer = Column(String(128), nullable=False)
    decision = Column(String(36), nullable=False)
    note = Column(Text, nullable=False)
    timestamp_utc = Column(DateTime(timezone=True), nullable=False)


ALLOWED = frozenset({'CONFIRMED_TEST_ONLY', 'REJECTED_TEST_ONLY', 'NEEDS_EVIDENCE'})
_SHA = re.compile(r'^[0-9a-f]{64}$')
_ID = re.compile(r'^[0-9a-f]{24}$')
_ACTOR = re.compile(r'^[A-Za-z0-9_.@-]{2,128}$')


def validate_result(analysis: dict) -> tuple[str, dict]:
    """Synthetic-only analysis; pin actions to exact immutable analysis digest."""
    if analysis.get('production_admission') != 'DENIED' or analysis.get('rights_admission') != 'SYNTHETIC_ONLY':
        raise PermissionError('REVIEW_REQUIRES_SYNTHETIC_ADMISSION')
    if analysis.get('review_authority') != 'NOT_GRANTED':
        raise PermissionError('REVIEW_AUTHORITY_MUST_REMAIN_NOT_GRANTED')
    digest = analysis.get('report_sha256')
    if not isinstance(digest, str) or not _SHA.fullmatch(digest):
        raise ValueError('ANALYSIS_DIGEST_INVALID')
    copy = {k: v for k, v in analysis.items() if k != 'report_sha256'}
    content = json.dumps(copy, ensure_ascii=False, sort_keys=True, separators=(',', ':')).encode('utf-8')
    if sha256(content).hexdigest() != digest:
        raise ValueError('ANALYSIS_DIGEST_MISMATCH')
    findings = analysis.get('findings')
    if not isinstance(findings, list) or len(findings) > 5000:
        raise ValueError('FINDING_SET_INVALID')
    by_id = {}
    for finding in findings:
        if not isinstance(finding, dict) or not isinstance(finding.get('id'), str) or not _ID.fullmatch(finding['id']):
            raise ValueError('FINDING_ID_INVALID')
        if finding['id'] in by_id:
            raise ValueError('DUPLICATE_FINDING_ID')
        by_id[finding['id']] = finding
    return digest, by_id


def add_review(session: Session, analysis: dict, finding_id: str, reviewer: str, decision: str, note: str = '') -> dict:
    digest, by_id = validate_result(analysis)
    if finding_id not in by_id:
        raise ValueError('FINDING_NOT_IN_ANALYSIS')
    if decision not in ALLOWED:
        raise ValueError('DECISION_NOT_ALLOWED')
    if not isinstance(reviewer, str) or not _ACTOR.fullmatch(reviewer):
        raise PermissionError('VERIFIED_LOCAL_REVIEWER_REQUIRED')
    if not isinstance(note, str) or len(note) > 2000:
        raise ValueError('REVIEW_NOTE_TOO_LONG')
    now = datetime.now(timezone.utc)
    action = ReviewAction(event_id=str(uuid.uuid4()), analysis_sha256=digest,
                          finding_id=finding_id, reviewer=reviewer, decision=decision,
                          note=note, timestamp_utc=now)
    session.add(action)
    session.commit()
    return as_public(action)


def as_public(action: ReviewAction) -> dict:
    timestamp = action.timestamp_utc
    if timestamp.tzinfo is None:
        timestamp = timestamp.replace(tzinfo=timezone.utc)
    return {'event_id': action.event_id, 'analysis_sha256': action.analysis_sha256,
            'finding_id': action.finding_id, 'reviewer': action.reviewer,
            'decision': action.decision, 'note': action.note,
            'timestamp_utc': timestamp.astimezone(timezone.utc).isoformat()}


def list_reviews(session: Session, analysis: dict) -> list[dict]:
    digest, _ = validate_result(analysis)
    records = session.execute(select(ReviewAction).where(ReviewAction.analysis_sha256 == digest)
                    .order_by(ReviewAction.timestamp_utc, ReviewAction.event_id)).scalars().all()
    return [as_public(r) for r in records]


def latest_review_status(session: Session, analysis: dict) -> dict[str, dict]:
    # Append-only log: the latest action is a derived view, not an overwrite.
    return {row['finding_id']: row for row in list_reviews(session, analysis)}
