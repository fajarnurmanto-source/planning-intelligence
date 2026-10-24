"""End-to-end candidate continuity built only from existing VS1/VS2/VS3.

Source PostgreSQL engine is read-only; reviewer storage is explicitly separate.
Every capability is for SYNTHETIC_FIXTURE only. Identity is taken from Gradio
request after Gradio's own auth has validated it.
"""
from __future__ import annotations
from pathlib import Path
from urllib.parse import urlparse
from sqlalchemy import create_engine

from app.vs2.reviews import ALLOWED
from app.vs2.workflow import (analyze_fixture, initialize_candidate_store,
                              decide, review_rows, export_review)
from app.vs3.service import analyze_runs, list_candidate_runs
from .archive import initialize_archive, archive_analysis, load_analysis, list_archived


def build_candidate_store(review_store_url: str, *, canonical_database_url: str = ''):
    if not isinstance(review_store_url,str) or not review_store_url:
        raise ValueError('REVIEW_STORE_URL_REQUIRED')
    if review_store_url.startswith('postgresql+psycopg://'):
        # Separate candidate DB is mandatory; reject same credentials/database as canonical.
        if not canonical_database_url or not canonical_database_url.startswith('postgresql+psycopg://'):
            raise ValueError('DISTINCT_CANDIDATE_REVIEW_DATABASE_REQUIRED')
        review_target = urlparse(review_store_url)
        source_target = urlparse(canonical_database_url)
        # Database location is independent of login: differing users don't create isolation.
        if (review_target.hostname, review_target.port or 5432, review_target.path) == (
                source_target.hostname, source_target.port or 5432, source_target.path):
            raise ValueError('DISTINCT_CANDIDATE_REVIEW_DATABASE_REQUIRED')
        name = urlparse(review_store_url).path.strip('/')
        if not name.endswith('_vs4_candidate'):
            raise ValueError('CANDIDATE_REVIEW_DATABASE_SUFFIX_REQUIRED')
    elif not review_store_url.startswith('sqlite:///'):
        raise ValueError('REVIEW_STORE_DRIVER_NOT_ALLOWED')
    engine = create_engine(review_store_url, pool_pre_ping=True)
    initialize_candidate_store(engine)
    initialize_archive(engine)
    return engine


def require_role(actor: str | None, roles: dict[str, str], *, write: bool = False) -> str:
    if not isinstance(actor, str) or roles.get(actor) not in ('viewer','reviewer'):
        raise PermissionError('AUTHORIZED_LOCAL_USER_REQUIRED')
    if write and roles[actor] != 'reviewer':
        raise PermissionError('REVIEWER_ROLE_REQUIRED')
    return actor


def from_demo(store, fixture: Path, actor: str, roles: dict[str,str]) -> dict:
    require_role(actor,roles)
    analysis = analyze_fixture(fixture)
    archive_analysis(store,analysis,actor=actor)
    return analysis


def from_database(source, store, left: str, right: str, *, group: str, scope: str,
                  actor: str, roles: dict[str,str]) -> dict:
    require_role(actor,roles)
    analysis=analyze_runs(source,left,right,group=group,scope=scope)
    archive_analysis(store,analysis,actor=actor)
    return analysis


def resume(store, digest: str, *, actor: str, roles: dict[str,str]) -> dict:
    require_role(actor,roles)
    return load_analysis(store,digest)


def history(store, *, actor: str, roles: dict[str,str]) -> list[dict]:
    require_role(actor,roles)
    return list_archived(store)


def decision(store, digest: str, finding_id: str, reviewer: str, roles: dict[str,str],
             selected: str, note: str = '') -> dict:
    require_role(reviewer,roles,write=True)
    if selected not in ALLOWED:
        raise ValueError('DECISION_NOT_ALLOWED')
    analysis=load_analysis(store,digest)  # Always reload immutable archived result; never trust browser state.
    return decide(store,analysis,finding_id,reviewer,selected,note)


def export(store, digest: str, output: Path, actor: str, roles: dict[str,str]):
    require_role(actor,roles)
    analysis=load_analysis(store,digest)
    actions=review_rows(store,analysis)
    # Export uses VS2 Word/JSON and a SHA-scoped directory, outside the source repo.
    return export_review(analysis,actions,Path(output)/digest)


def table(store, digest: str, *, actor: str, roles: dict[str,str]) -> list[list[str]]:
    require_role(actor,roles)
    analysis=load_analysis(store,digest)
    latest={x['finding_id']:x for x in review_rows(store,analysis)}
    return [[f['indicator'],str(f['year']),f['status'],
             latest.get(f['id'],{}).get('decision','PENDING'),str(len(f.get('evidence',[])))]
            for f in analysis['findings']]


def proof(store, digest: str, fid: str, *, actor: str, roles: dict[str,str]) -> str:
    require_role(actor,roles)
    analysis=load_analysis(store,digest)
    finding=next((f for f in analysis['findings'] if f['id']==fid),None)
    if not finding:
        raise ValueError('FINDING_NOT_IN_ANALYSIS')
    parts=[str(finding['indicator']),str(finding['status']),str(finding['rationale'])]
    for ev in finding.get('evidence',[]):
        parts.extend(['',str(ev['document_title']),str(ev['locator']),
                    'SourceUnit: '+str(ev['source_unit_id']),
                    'Fingerprint: '+str(ev['unit_fingerprint']),str(ev['excerpt'])])
    return '\n'.join(parts)
