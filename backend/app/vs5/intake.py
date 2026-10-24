"""Synthetic-only file-to-review bridge. Reuses PI parser/VS1/VS4; no original bytes stored.

This is a local product acceptance workflow, NOT a rights-admission mechanism.
The marker is an eligibility test for artificial samples, not a legal attestation.
"""
from __future__ import annotations
from hashlib import sha256
from pathlib import Path
from uuid import UUID, NAMESPACE_URL, uuid5

from app.parser import parse_document
from app.vs1.canonical_bridge import Refused, compare_existing_sources
from app.vs4.archive import archive_analysis
from app.vs4.service import require_role

SYNTHETIC_MARKER = 'PI_SYNTHETIC_FIXTURE_ONLY'
ALLOWED_EXTENSIONS = frozenset({'.pdf', '.docx', '.xlsx'})
MAX_UPLOAD_BYTES = 2_000_000
MAX_UNITS_PER_DOCUMENT = 10_000


def _uuid(*parts: str) -> str:
    return str(uuid5(NAMESPACE_URL, 'pi.vs5.synthetic/' + '/'.join(parts)))


def _parse_side(path_like: str | Path, side: str, *, group: str, scope: str):
    path = Path(path_like)
    if not path.is_file() or path.suffix.lower() not in ALLOWED_EXTENSIONS:
        raise Refused('SUPPORTED_SYNTHETIC_PDF_DOCX_XLSX_REQUIRED')
    if path.stat().st_size == 0 or path.stat().st_size > MAX_UPLOAD_BYTES:
        raise Refused('SYNTHETIC_FILE_SIZE_OUT_OF_BOUNDS')
    raw = path.read_bytes()
    digest = sha256(raw).hexdigest()
    del raw
    parsed = parse_document(path, input_sha256=digest, extension=path.suffix,
                            config={**__import__('app.parser', fromlist=['DEFAULT_CONFIG']).DEFAULT_CONFIG,
                                    'max_source_units': MAX_UNITS_PER_DOCUMENT})
    units = parsed['units']
    if not units or len(units) > MAX_UNITS_PER_DOCUMENT:
        raise Refused('SYNTHETIC_SOURCE_UNITS_OUT_OF_BOUNDS')
    # Reject documents that do not identify themselves as synthetic. This does not
    # prove rights/admission and must never permit production classification.
    marker_seen = any(SYNTHETIC_MARKER in str(u.raw_text or '')
                      for u in units[:min(60, len(units))])
    if not marker_seen:
        raise Refused('SYNTHETIC_MARKER_NOT_FOUND')
    document_id = _uuid(side,'document',digest)
    version_id = _uuid(side,'version',digest)
    run_id = _uuid(side,'run',parsed['run_fingerprint'])
    doc = dict(id=document_id, title=path.name[:160], sha256=digest,
               jurisdiction_id='SYNTHETIC_LOCAL', planning_period_id='SYNTHETIC_PERIOD',
               vs1_group=group, vs1_scope=scope)
    version = dict(id=version_id, document_id=document_id, source_kind='SYNTHETIC_FIXTURE',sha256=digest)
    run = dict(id=run_id,document_version_id=version_id,status='SUCCEEDED',
               input_sha256=digest,source_unit_count=len(units),result_sha256=parsed['result_sha256'])
    source_units = [dict(id=_uuid(side,run_id,str(u.sequence_index)),
                         parsing_run_id=run_id,document_version_id=version_id,
                         sequence_index=u.sequence_index,unit_type=u.unit_type,
                         locator=u.locator,raw_text=u.raw_text,
                         page_start=u.page_start,unit_fingerprint=u.unit_fingerprint)
                    for u in units]
    return doc, version, run, source_units


def analyze_pair(left_path: str | Path, right_path: str | Path, *,
                 group: str, scope: str) -> dict:
    if not isinstance(group,str) or not group.strip() or len(group.strip())>150:
        raise Refused('COMPARISON_GROUP_REQUIRED')
    if not isinstance(scope,str) or not scope.strip() or len(scope.strip())>150:
        raise Refused('COMPARISON_SCOPE_REQUIRED')
    left = _parse_side(left_path,'left',group=group.strip(),scope=scope.strip())
    right = _parse_side(right_path,'right',group=group.strip(),scope=scope.strip())
    if left[1]['sha256'] == right[1]['sha256']:
        raise Refused('DISTINCT_SYNTHETIC_DOCUMENTS_REQUIRED')
    result = compare_existing_sources(left,right)
    if result['rights_admission']!='SYNTHETIC_ONLY' or result['production_admission']!='DENIED':
        raise PermissionError('SYNTHETIC_BOUNDARY_BROKEN')
    return result


def analyze_and_archive(store, left_path, right_path, *, group,scope,actor,roles):
    require_role(actor,roles)
    analysis = analyze_pair(left_path,right_path,group=group,scope=scope)
    archive_analysis(store,analysis,actor=actor)
    return analysis
