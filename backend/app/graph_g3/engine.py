"""G3 deterministic verification of G2 ledger excerpts against original PI SourceUnits.

Exact text/locator evidence verification != semantic entailment or causal truth.
Strictly synthetic, refuses unknown file admission; no AI, DB mutation or network.
"""
from __future__ import annotations
from collections import Counter
from hashlib import sha256
import json
from pathlib import Path
from typing import Mapping

from app.graph_g2.engine import parse_ledger
from app.graph_core.engine import ROLES
from app.vs1.canonical_bridge import Refused
from app.vs2.reviews import validate_result
from app.vs5.intake import _parse_side

MAX_FINDINGS = 500


def _digest(x):
    return sha256(json.dumps(x, ensure_ascii=False, sort_keys=True,
                             separators=(',', ':')).encode('utf-8')).hexdigest()


def source_locator(unit: dict) -> str | None:
    """Canonical, format-specific exact locator of a parser-produced SourceUnit."""
    loc = unit.get('locator') or {}
    kind = unit.get('unit_type')
    fmt = loc.get('format')
    if fmt == 'xlsx' and kind == 'TABLE_CELL':
        return f"xlsx:{loc.get('sheet_name')}!{loc.get('coordinate')}"
    if fmt == 'pdf' and kind == 'TEXT_BLOCK':
        return f"pdf:page={loc.get('page')}:block={loc.get('block_ordinal')}"
    if fmt == 'docx' and kind == 'PARAGRAPH':
        return f"docx:paragraph={loc.get('paragraph_index')}"
    if fmt == 'docx' and kind == 'TABLE_CELL':
        return (f"docx:table={loc.get('table_index')}:row={loc.get('row_index')}:"
                f"col={loc.get('column_index')}")
    return None


def load_original_source_units(g2:dict, source_paths:Mapping[str,str|Path]) -> dict:
    """Reparse same 4 original synthetic G1 document bytes, verify SHA before use.

    G1 intentionally archives SHA/evidence but not original source bytes. The user
    must supply the exact 4 original files again; no arbitrary replacement accepted.
    """
    validate_result(g2)
    if g2.get('schema') != 'pi.graph_g2.substantive.synthetic.v1' or \
       g2.get('production_admission') != 'DENIED':
        raise Refused('G3_ARCHIVED_G2_SYNTHETIC_REQUIRED')
    if not isinstance(source_paths,Mapping) or set(source_paths)!=set(ROLES):
        raise Refused('G3_FOUR_ORIGINAL_SOURCE_ROLES_REQUIRED')
    result={}
    for role in ROLES:
        declared=g2['documents'][role]
        path=Path(source_paths[role])
        if not path.is_file() or path.stat().st_size>2_000_000:
            raise Refused('G3_BOUNDED_ORIGINAL_FILE_REQUIRED')
        actual=sha256(path.read_bytes()).hexdigest()
        if actual != declared['sha256']:
            raise Refused('G3_SOURCE_SHA256_MISMATCH_'+role)
        doc,version,run,units=_parse_side(path,role,group=declared['group_name'],scope=declared['scope'])
        if (doc['sha256']!=actual or version['sha256']!=actual or
            run['status']!='SUCCEEDED' or not units):
            raise Refused('G3_CANONICAL_PARSING_FAILED_'+role)
        index={}
        for unit in units:
            locator=source_locator(unit)
            if locator is None:continue
            if locator in index:raise Refused('G3_AMBIGUOUS_SOURCE_LOCATOR')
            index[locator]=unit
        result[role]={'sha256':actual,'title':doc['title'],'version_id':version['id'],
                      'run_id':run['id'],'index':index}
    return result


def analyze_document_grounding(g2:dict, ledger:dict, sources:dict) -> dict:
    """Evaluate each G2 node/edge quote as an independently source-located claim.

    An edge is 'located' only when both endpoint excerpts and its own phrase are
    literally found at their operator-supplied original document locators.
    This DOES NOT verify the graph relationship or causal effect.
    """
    validate_result(g2)
    if g2.get('schema')!='pi.graph_g2.substantive.synthetic.v1':
        raise Refused('G3_G2_REQUIRED')
    if ledger.get('ledger_sha256')!=g2.get('ledger_sha256'):
        raise Refused('G3_LEDGER_DIGEST_MISMATCH')
    if set(sources)!=set(ROLES):raise Refused('G3_SOURCE_ROLE_SET_INVALID')
    nodes={n['id']:n for n in ledger['nodes']}
    out_nodes={};out_edges={};findings=[]

    def locate(role:str,quote:str,locator:str):
        roledata=sources.get(role,{})
        unit=roledata.get('index',{}).get(locator)
        if not quote or not locator:
            return 'MISSING_ASSERTION_OR_LOCATOR',None
        if not unit:
            return 'SOURCE_LOCATOR_NOT_FOUND',None
        # Exact text after canonical whitespace normalization; no fuzzy matching.
        expected=' '.join(quote.split())
        actual=' '.join(str(unit['raw_text'] or '').split())
        if expected!=actual:
            return 'SOURCE_QUOTE_MISMATCH',None
        return 'SOURCE_QUOTE_LOCATED', {
            'document_role':role,'document_title':roledata['title'],
            'document_sha256':roledata['sha256'],
            'document_version_id':roledata['version_id'],'parsing_run_id':roledata['run_id'],
            'source_unit_id':unit['id'],'unit_fingerprint':unit['unit_fingerprint'],
            'locator':locator,'excerpt':actual,
            'verification_scope':'EXACT_TEXT_AT_SOURCE_UNIT_ONLY',
        }

    for n in ledger['nodes']:
        status,evidence=locate(n['role'],n['evidence_excerpt'],n['source_locator'])
        out_nodes[n['id']]={'status':status,'evidence':evidence}
        if status!='SOURCE_QUOTE_LOCATED':
            findings.append({'status':'G3_NODE_SOURCE_NOT_LOCATED','claim':n['title'],
                             'reason':status,'role':n['role'],'ledger_locator':n['ledger_locator'],
                             'source_locator':n['source_locator'],'linked_source_unit':None})
    for e in ledger['edges']:
        src,dst=nodes[e['from_id']],nodes[e['to_id']]
        status,evidence=locate(src['role'],e['evidence_quote'],e['source_locator'])
        endpoints_ok=(out_nodes[src['id']]['status']=='SOURCE_QUOTE_LOCATED' and
                      out_nodes[dst['id']]['status']=='SOURCE_QUOTE_LOCATED')
        all_located=status=='SOURCE_QUOTE_LOCATED' and endpoints_ok
        out_edges[e['id']]={'status':('DECLARED_RELATION_EXCERPTS_LOCATED' if all_located
                                   else 'DECLARED_RELATION_UNGROUNDED'),
                            'quote_status':status,'source_evidence':evidence,
                            'from_node_evidence':out_nodes[src['id']]['evidence'],
                            'to_node_evidence':out_nodes[dst['id']]['evidence'],
                            'semantic_entailment':'NOT_TESTED','causal_validity':'NOT_TESTED'}
        if not all_located:
            findings.append({'status':'G3_RELATION_SOURCE_GAP','claim':f"{src['title']} -> {dst['title']}",
                             'reason':status if status!='SOURCE_QUOTE_LOCATED' else 'ENDPOINT_QUOTE_NOT_LOCATED',
                             'role':src['role'],'ledger_locator':e['ledger_locator'],
                             'source_locator':e['source_locator'],
                             'linked_source_unit':evidence})
    if len(findings)>MAX_FINDINGS:raise Refused('G3_FINDING_LIMIT')
    evidence_nodes=sum(x['status']=='SOURCE_QUOTE_LOCATED' for x in out_nodes.values())
    evidence_edges=sum(x['status']=='DECLARED_RELATION_EXCERPTS_LOCATED' for x in out_edges.values())
    doc_meta={role:{'sha256':sources[role]['sha256'], 'title':sources[role]['title']} for role in ROLES}
    finding_rows=[]
    for f in findings:
        # At least one anchored source unit if available; else ledger is merely assertion.
        anchor=f.get('linked_source_unit')
        ev = [dict(document_title=(anchor or {}).get('document_title', ledger['ledger_filename']),
                   file_sha256=(anchor or {}).get('document_sha256',ledger['ledger_sha256']),
                   locator=(anchor or {}).get('locator',f['ledger_locator']),
                   excerpt=(anchor or {}).get('excerpt',f['reason']),
                   source_unit_id=(anchor or {}).get('source_unit_id','UNVERIFIED'),
                   unit_fingerprint=(anchor or {}).get('unit_fingerprint',_digest([f['ledger_locator'],f['reason']])))]
        finding_rows.append({'id':_digest([f['status'],f['claim'],f['ledger_locator']])[:24],
                             'indicator':f['claim'][:500],'year':2026,
                             'status':f['status'],'rationale':f['reason']+'. No substantive entailment determined.',
                             'evidence':ev,'review_status':'PENDING','review_note':''})
    report={'schema':'pi.graph_g3.document_grounded.synthetic.v1',
            'rights_admission':'SYNTHETIC_ONLY','production_admission':'DENIED',
            'review_authority':'NOT_GRANTED','analysis_kind':'EXACT_SOURCE_UNIT_QUOTE_VERIFICATION',
            'source_g2_sha256':g2['report_sha256'],'source_g1_sha256':g2['source_g1_sha256'],
            'ledger_sha256':ledger['ledger_sha256'], 'documents':doc_meta,
            'node_verification':out_nodes,'edge_verification':out_edges,
            'verification_metrics':{'node_quotes_verified':evidence_nodes,'node_quotes_total':len(out_nodes),
                'edge_quotes_verified':evidence_edges,'edge_quotes_total':len(out_edges),
                'source_gaps':len(findings)},
            'findings':finding_rows,
            'boundary_notice':('Exact verified source text is NOT proof of policy support, regulatory '
                'compliance, coherence, impact or causality. Synthetic four-source verification only; '
                'human review required. No production authority or external documents admitted.')}
    report['report_sha256']=_digest(report)
    validate_result(report)
    return report


def analyze_g2_and_originals(g2:dict, ledger_path, source_paths:Mapping[str,str|Path])->dict:
    ledger=parse_ledger(ledger_path)
    sources=load_original_source_units(g2,source_paths)
    return analyze_document_grounding(g2,ledger,sources)
