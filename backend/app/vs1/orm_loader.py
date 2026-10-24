"""Read canonical PostgreSQL PI document versions. Never writes data.

Requires a read-only DB credential with SELECT permission and test-only synthetic
records. This module is NOT exposed through the original anonymous /api proxy.
"""
from __future__ import annotations
from uuid import UUID
from .canonical_bridge import Refused, compare_existing_sources


def _side(db, run_id, group, scope):
    from sqlalchemy import select
    from app.models import Document, DocumentVersion, ParsingRun, SourceUnit
    rid=UUID(str(run_id))
    run=db.get(ParsingRun,rid)
    if run is None:raise Refused('PARSING_RUN_NOT_FOUND')
    version=db.get(DocumentVersion,run.document_version_id)
    if version is None:raise Refused('DOCUMENT_VERSION_NOT_FOUND')
    document=db.get(Document,version.document_id)
    if document is None:raise Refused('DOCUMENT_NOT_FOUND')
    # Cap before materializing a potentially massive set of units.
    if not isinstance(run.source_unit_count,int) or run.source_unit_count>10000:
        raise Refused('PARSING_UNIT_COUNT_EXCEEDS_CANDIDATE_LIMIT')
    units=db.execute(select(SourceUnit).where(SourceUnit.parsing_run_id==rid).order_by(SourceUnit.sequence_index).limit(10001)).scalars().all()
    if len(units)>10000:raise Refused('PARSING_UNIT_LIMIT_EXCEEDED')
    doc={'id':str(document.id),'title':document.title,'jurisdiction_id':str(document.jurisdiction_id),'planning_period_id':str(document.planning_period_id),'vs1_group':group,'vs1_scope':scope}
    return (doc,version,run,units)


def compare_synthetic_parsing_runs(db,left_run_id,right_run_id,*,group,scope):
    # Database enforces read-only at PostgreSQL transaction level, not only convention.
    from sqlalchemy import text
    if not group or not scope or len(group)>150 or len(scope)>150:
        raise Refused('COMPARISON_DECLARATION_REQUIRED')
    db.execute(text('SET TRANSACTION READ ONLY'))
    return compare_existing_sources(_side(db,left_run_id,group,scope),_side(db,right_run_id,group,scope))
