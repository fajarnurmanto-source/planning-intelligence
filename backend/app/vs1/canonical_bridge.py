"""Bridge EXISTING PI canonical ORM records to deterministic VS1 evidence.

This module performs NO file I/O, no network, no writes and no identity escalation.
It only accepts synthetic fixture data until a governed admission service exists.
"""
from __future__ import annotations
from dataclasses import dataclass
from hashlib import sha256
import json
import re
from typing import Any
from .core import analyze
from .source_adapter import from_pi_source_units

SHA256 = re.compile(r"^[a-f0-9]{64}$")


class Refused(ValueError):
    """Input is not qualified for a VS1 analysis; fail closed."""


def _attr(v: Any, key: str, default=None):
    return v.get(key, default) if isinstance(v, dict) else getattr(v, key, default)


def _identity(v: Any) -> str:
    return str(_attr(v, 'id', ''))


def prepare_side(document, version, run, source_units, *, expected_source_kind='SYNTHETIC_FIXTURE'):
    if _identity(version) == '' or str(_attr(version,'document_id')) != _identity(document):
        raise Refused('DOCUMENT_VERSION_LINK_INVALID')
    if str(_attr(run,'document_version_id')) != _identity(version):
        raise Refused('PARSING_RUN_VERSION_LINK_INVALID')
    if _attr(version, 'source_kind') != expected_source_kind:
        raise Refused('SOURCE_ADMISSION_NOT_VERIFIED')
    if _attr(run,'status') != 'SUCCEEDED':
        raise Refused('PARSING_RUN_NOT_SUCCESSFUL')
    digest = str(_attr(version,'sha256', '')).lower()
    if not SHA256.fullmatch(digest) or str(_attr(run,'input_sha256','')).lower() != digest:
        raise Refused('PARSING_INPUT_DIGEST_MISMATCH')
    expected_count=_attr(run,'source_unit_count')
    if not isinstance(expected_count,int) or expected_count < 1:
        raise Refused('PARSING_UNIT_COUNT_NOT_VALID')
    if not isinstance(source_units,list) or len(source_units) != expected_count:
        raise Refused('PARSING_UNIT_COUNT_MISMATCH')
    rows=[]
    seen_seq=set();seen_id=set()
    for s in source_units:
        uid=_identity(s)
        seq=_attr(s,'sequence_index')
        if not uid or not isinstance(seq,int) or seq<0 or seq in seen_seq or uid in seen_id:
            raise Refused('SOURCE_UNIT_IDENTITY_INVALID')
        seen_id.add(uid);seen_seq.add(seq)
        if str(_attr(s,'parsing_run_id')) != _identity(run) or str(_attr(s,'document_version_id')) != _identity(version):
            raise Refused('SOURCE_UNIT_BOUNDARY_MISMATCH')
        fingerprint = str(_attr(s,'unit_fingerprint','')).lower()
        if not SHA256.fullmatch(fingerprint):
            raise Refused('SOURCE_UNIT_FINGERPRINT_INVALID')
        locator=_attr(s,'locator',{})
        if not isinstance(locator,dict):
            raise Refused('SOURCE_UNIT_LOCATOR_INVALID')
        raw=_attr(s,'raw_text') or ''
        if len(raw)>500000:
            raise Refused('SOURCE_UNIT_TEXT_LIMIT_EXCEEDED')
        rows.append({'id':uid,'parsing_run_id':_identity(run),'document_version_id':_identity(version),
            'unit_type':_attr(s,'unit_type'), 'sequence_index':seq, 'locator':locator,
            'raw_text':raw,'unit_fingerprint':fingerprint,
            'page_start':_attr(s,'page_start')})
    result_hash=str(_attr(run,'result_sha256','')).lower()
    if result_hash:
        fingerprints=[x['unit_fingerprint'] for x in sorted(rows,key=lambda item:item['sequence_index'])]
        actual=sha256(json.dumps(fingerprints,ensure_ascii=False,sort_keys=True,separators=(',',':')).encode('utf-8')).hexdigest()
        if result_hash!=actual:
            raise Refused('PARSING_RESULT_DIGEST_MISMATCH')
    transformed=from_pi_source_units(rows)
    # Allow explicit, structured indicator fields only; no inference from arbitrary text.
    if len(transformed)==0:
        raise Refused('NO_STRUCTURED_INDICATOR_TARGET_RECORDS')
    return ({'id':_identity(document), 'title':str(_attr(document,'title',''))[:500],
             'sha256':digest,
             'group_name':str(_attr(document,'vs1_group','UNSPECIFIED')),
             'scope':str(_attr(document,'vs1_scope','UNSPECIFIED')),
             'jurisdiction_id':str(_attr(document,'jurisdiction_id','')),
             'planning_period_id':str(_attr(document,'planning_period_id','')),
             'version_id':_identity(version),'run_id':_identity(run)},
            transformed)


def compare_existing_sources(left_tuple, right_tuple):
    left,units_l=prepare_side(*left_tuple)
    right,units_r=prepare_side(*right_tuple)
    if left['id'] == right['id'] and left['version_id'] == right['version_id']:
        raise Refused('SELF_COMPARISON_NOT_ALLOWED')
    if not left['jurisdiction_id'] or left['jurisdiction_id'] != right['jurisdiction_id']:
        raise Refused('JURISDICTION_BOUNDARY_MISMATCH')
    findings=analyze(left,right,units_l,units_r)
    # Attach canonical source-unit fingerprints to each matching exact source unit.
    source_index = {
        (side['id'],unit['locator']):unit
        for side, units in ((left,units_l),(right,units_r)) for unit in units
    }
    for f in findings:
        for ev in f['evidence']:
            mapped=source_index.get((ev['document_id'],ev['locator']))
            if not mapped or not mapped.get('pi_source_unit_id') or not mapped.get('pi_unit_fingerprint'):
                raise Refused('EVIDENCE_SOURCE_UNIT_UNRESOLVED')
            ev['source_unit_id']=mapped['pi_source_unit_id']
            ev['unit_fingerprint']=mapped['pi_unit_fingerprint']
            ev['parsing_run_id']=left['run_id'] if ev['document_id']==left['id'] else right['run_id']
            ev['document_version_id']=left['version_id'] if ev['document_id']==left['id'] else right['version_id']
    result={'schema':'pi.vs1.synthetic_candidate.v1','production_admission':'DENIED',
        'rights_admission':'SYNTHETIC_ONLY','left':left,'right':right,
        'findings':findings,'review_authority':'NOT_GRANTED','analysis_kind':'DETERMINISTIC_CANDIDATE'}
    serialized=json.dumps(result,ensure_ascii=False,sort_keys=True,separators=(',',':')).encode('utf-8')
    result['report_sha256']=sha256(serialized).hexdigest()
    return result
