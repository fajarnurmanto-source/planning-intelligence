"""PI VS1: deterministic, evidence-linked comparison. No AI or legal ruling."""
from __future__ import annotations
from dataclasses import dataclass, asdict
from decimal import Decimal, InvalidOperation
from hashlib import sha256
import json
import re
import unicodedata

HEADER_ALIASES = {
    'indicator': {'indikator','indicator','indikator kinerja'},
    'year': {'tahun','year'},
    'target': {'target','target nilai','nilai target'},
    'unit': {'satuan','unit'},
    'scope': {'lingkup','scope','cakupan'},
    'type': {'jenis target','target type','tipe target'},
}


def norm(text: object) -> str:
    return ' '.join(unicodedata.normalize('NFKC', str(text or '')).casefold().split())


def num(value: object) -> str:
    v = str(value or '').strip().replace(' ', '').replace('%','')
    if not v or len(v) > 60:
        raise ValueError('INVALID_TARGET')
    if ',' in v and '.' not in v:
        v = v.replace(',', '.')
    # no thousands separators accepted, ambiguous data fail-closed
    try:
        d = Decimal(v)
    except InvalidOperation as exc:
        raise ValueError('INVALID_TARGET') from exc
    if not d.is_finite():
        raise ValueError('INVALID_TARGET')
    return format(d.normalize(), 'f')


def header_map(cells: list[object]) -> dict[str,int]:
    words = [norm(x) for x in cells]
    found = {}
    for canonical, aliases in HEADER_ALIASES.items():
        for idx, val in enumerate(words):
            if val in aliases:
                found[canonical] = idx
                break
    return found


@dataclass(frozen=True)
class Unit:
    indicator: str
    year: int
    target: str
    unit: str
    scope: str
    target_type: str
    locator: str
    excerpt: str

    def public(self):
        return asdict(self)


def from_cells(cells: list[object], heads: dict[str,int], locator: str) -> Unit | None:
    if not all(k in heads for k in ('indicator','year','target')):
        return None
    def val(key, default=''):
        idx = heads.get(key)
        return str(cells[idx] if idx is not None and idx < len(cells) and cells[idx] is not None else default).strip()
    indicator = val('indicator')
    if not indicator:
        return None
    yr = val('year')
    try:
        year = int(yr)
    except ValueError:
        return None
    if not (1900 <= year <= 2200):
        return None
    target_raw = val('target')
    try:
        target = num(target_raw)
    except ValueError:
        return None
    unit = val('unit') or ('%' if '%' in target_raw else 'UNSPECIFIED')
    scope = val('scope','UNSPECIFIED')
    target_type = val('type','UNSPECIFIED')
    excerpt = ' | '.join(f'{k}={val(k)}' for k in ['indicator','year','target','unit','scope','type'] if k in heads)
    return Unit(indicator, year, target, unit, scope, target_type, locator, excerpt)


def from_line(line: str, locator: str) -> Unit | None:
    # Explicit key:value syntax; no guessing from arbitrary prose.
    parts = [part.strip() for part in line.split('|')]
    values = {}
    for part in parts:
        if ':' not in part:
            continue
        key, value = part.split(':', 1)
        canonical = next((k for k, alias in HEADER_ALIASES.items() if norm(key) in alias), None)
        if canonical:
            values[canonical] = value.strip()
    heads = {k:i for i,k in enumerate(values)}
    result = from_cells(list(values.values()),heads,locator)
    if result:
        return Unit(result.indicator,result.year,result.target,result.unit,result.scope,result.target_type,locator,line[:800])
    return None


def _canonical(data: dict) -> bytes:
    return json.dumps(data,ensure_ascii=False,sort_keys=True,separators=(',',':')).encode('utf-8')


def evidence(doc: dict, unit: dict) -> dict:
    return {'document_id':doc['id'],'document_title':doc['title'],
            'file_sha256':doc['sha256'],'locator':unit['locator'],
            'excerpt':unit['excerpt'],'target':unit['target']}


def analyze(left: dict, right: dict, left_units: list[dict], right_units: list[dict]) -> list[dict]:
    """Only compare matching exact normalized indicator/year; all findings require human review.
    No inferred DERIVED_FROM, causal links, legal compliance, or production admission.
    """
    outputs=[]
    right_by_indicator={}
    for u in right_units:
        right_by_indicator.setdefault((norm(u['indicator']), int(u['year'])),[]).append(u)
    seen=set()
    for l in left_units:
        key=(norm(l['indicator']),int(l['year']))
        matches=right_by_indicator.get(key,[])
        if not matches:
            outputs.append(_finding(left,right,l,None,'NO_VERIFIED_LINEAGE','Indicator/year not matched across the two sources; semantic lineage cannot be inferred.'))
            continue
        for r in matches:
            if left['group_name'] != right['group_name'] or not left['group_name']:
                status='SCOPE_DECLARATION_REQUIRED'
                rationale='Comparison groups differ or are missing; no cross-document comparison is authorized.'
            elif any(norm(x) in ('', 'unspecified') for x in (l['scope'],r['scope'],l['unit'],r['unit'],l['target_type'],r['target_type'])):
                status='INCOMPARABLE_METADATA'
                rationale='Row scope, unit or target type not explicitly supplied; comparison is withheld.'
            elif norm(left['scope']) != norm(right['scope']) or norm(l['scope']) != norm(r['scope']):
                status='INCOMPARABLE_SCOPE'
                rationale='Declared document or row scope differs; targets are not directly comparable.'
            elif norm(l['target_type']) != norm(r['target_type']):
                status='INCOMPARABLE_TARGET_TYPE'
                rationale='Target types differ; output and outcome must not be conflated.'
            elif norm(l['unit']) != norm(r['unit']):
                status='INCOMPARABLE_UNIT'
                rationale='Units differ; no conversion or interpretation was assumed.'
            elif l['target'] != r['target']:
                status='POTENTIAL_TARGET_DIFFERENCE'
                rationale='Same explicitly declared group, scope, target type, indicator and year, but numeric targets differ. This is a candidate for human review, not proof of incoherence.'
            else:
                status='NUMERIC_MATCH_PENDING_REVIEW'
                rationale='Numeric values match on explicitly declared fields. Semantic/programmatic linkage remains unverified.'
            outputs.append(_finding(left,right,l,r,status,rationale))
            seen.add((r['locator'],r['indicator'],r['year']))
    for r in right_units:
        if (r['locator'],r['indicator'],r['year']) not in seen and (norm(r['indicator']),int(r['year'])) not in {(norm(l['indicator']),int(l['year'])) for l in left_units}:
            outputs.append(_finding(left,right,None,r,'NO_VERIFIED_LINEAGE','Indicator/year not matched across the two sources; semantic lineage cannot be inferred.'))
    return outputs


def _finding(d1,d2,l,r,status,rationale):
    ev=[]
    if l: ev.append(evidence(d1,l))
    if r: ev.append(evidence(d2,r))
    # stable fingerprint without filesystem absolute paths
    key={'left_sha':d1['sha256'],'right_sha':d2['sha256'],'status':status,
         'left_locator': l['locator'] if l else None,'right_locator':r['locator'] if r else None,
         'indicator':l['indicator'] if l else r['indicator'], 'year':l['year'] if l else r['year']}
    return {'id':sha256(_canonical(key)).hexdigest()[:24], 'indicator':key['indicator'],
            'year':key['year'],'status':status,'rationale':rationale,'evidence':ev,
            'review_status':'PENDING','review_note':''}
