"""Explainable, synthetic-only substantive planning alignment on top of G1.

Reuses a verified G1 analysis digest, SQLAlchemy archive, NetworkX and Word
export. An auxiliary structured claim ledger explicitly identifies links and
source quotes. A quote in that ledger is *operator-supplied*, never proof of a
causal relationship or a verbatim quote from the original document.
"""
from __future__ import annotations
from collections import Counter
from hashlib import sha256
import json
from pathlib import Path
from typing import Any

import networkx as nx
from openpyxl import load_workbook
from app.vs1.canonical_bridge import Refused
from app.vs2.reviews import validate_result

MARKER = 'PI_SYNTHETIC_FIXTURE_ONLY'
KINDS = frozenset({'TUJUAN','SASARAN','PROGRAM','KEGIATAN','OUTPUT','OUTCOME'})
ROLES = frozenset({'RPJMD','RKPD','RENSTRA','RENJA'})
# Orientation: intervention/effect -> declared supported target, except goal decomposition
RELATIONS = {
    ('TUJUAN','SASARAN'): 'DECOMPOSES_TO',
    ('PROGRAM','SASARAN'): 'SUPPORTS',
    ('KEGIATAN','PROGRAM'): 'IMPLEMENTS',
    ('OUTPUT','KEGIATAN'): 'DELIVERED_BY',
    ('OUTPUT','OUTCOME'): 'CONTRIBUTES_TO',
    ('OUTCOME','SASARAN'): 'ALIGNS_WITH',
}
NODE_HEADERS = ('id','kind','role','title','year','scope','evidence_excerpt','source_locator')
EDGE_HEADERS = ('id','from_id','to_id','relation','evidence_quote','source_locator')
MAX_SIZE = 2_000_000
MAX_NODES = 250
MAX_EDGES = 500
MAX_FINDINGS = 1500


def _digest(value: Any) -> str:
    return sha256(json.dumps(value, ensure_ascii=False, sort_keys=True,
                             separators=(',', ':')).encode('utf-8')).hexdigest()


def _require(value: Any, field: str, limit: int=600) -> str:
    if not isinstance(value, str):
        raise Refused(f'G2_{field.upper()}_REQUIRED')
    value = value.strip()
    if not value or len(value) > limit or any(ord(ch)<32 for ch in value):
        raise Refused(f'G2_{field.upper()}_REQUIRED')
    return value


def _optional(value: Any, limit: int=1000) -> str:
    if value is None:
        return ''
    if not isinstance(value, str) or len(value)>limit or any(ord(ch)<32 for ch in value):
        raise Refused('G2_INVALID_OPTIONAL_TEXT')
    return value.strip()


def _rows(ws, headers, maximum):
    if ws.max_row < 2 or ws.max_row > maximum+1 or ws.max_column != len(headers):
        raise Refused('G2_LEDGER_ROW_OR_COLUMN_BOUNDARY')
    actual = tuple(str(ws.cell(1,i).value or '').strip().casefold() for i in range(1,len(headers)+1))
    if actual != headers:
        raise Refused('G2_LEDGER_HEADERS_MISMATCH')
    for index in range(2,ws.max_row+1):
        cells=[ws.cell(index,j) for j in range(1,len(headers)+1)]
        if any(c.data_type=='f' or (isinstance(c.value,str) and c.value.lstrip().startswith('=')) for c in cells):
            raise Refused('G2_FORMULA_NOT_ALLOWED')
        vals=[c.value for c in cells]
        if all(v is None for v in vals):
            continue
        yield index, dict(zip(headers, vals))


def parse_ledger(path: str|Path) -> dict:
    path=Path(path)
    if (not path.is_file() or path.suffix.lower()!='.xlsx' or
            path.stat().st_size>MAX_SIZE or path.stat().st_size<500):
        raise Refused('G2_SYNTHETIC_XLSX_REQUIRED')
    doc_hash=sha256(path.read_bytes()).hexdigest()
    wb=load_workbook(path, read_only=False, data_only=False)
    try:
        if set(wb.sheetnames) != {'Meta','Nodes','Edges'} or wb['Meta']['A1'].value != MARKER:
            raise Refused('G2_SYNTHETIC_MARKER_AND_SHEETS_REQUIRED')
        nodes=[]; edges=[]; seen=set()
        for row,item in _rows(wb['Nodes'], NODE_HEADERS, MAX_NODES):
            node_id=_require(item['id'],'node_id',60)
            if not node_id.replace('_','').replace('-','').isalnum() or node_id in seen:
                raise Refused('G2_AMBIGUOUS_NODE_ID')
            seen.add(node_id)
            kind=_require(item['kind'],'kind',40).upper()
            role=_require(item['role'],'role',40).upper()
            if kind not in KINDS or role not in ROLES:
                raise Refused('G2_UNKNOWN_NODE_KIND_OR_ROLE')
            try:year=int(item['year'])
            except (ValueError,TypeError):raise Refused('G2_NODE_YEAR_REQUIRED')
            if year<2000 or year>2100:raise Refused('G2_NODE_YEAR_INVALID')
            nodes.append({'id':node_id,'kind':kind,'role':role,
                          'title':_require(item['title'],'title',180),
                          'year':year,'scope':_require(item['scope'],'scope',100),
                          'evidence_excerpt':_optional(item['evidence_excerpt']),
                          'source_locator':_optional(item['source_locator'],250),
                          'ledger_locator':f'Nodes!{row}'})
        ids={n['id'] for n in nodes}
        if not nodes:raise Refused('G2_NODES_REQUIRED')
        for row,item in _rows(wb['Edges'], EDGE_HEADERS, MAX_EDGES):
            edge_id=_require(item['id'],'edge_id',60)
            if edge_id in seen:raise Refused('G2_DUPLICATE_ID')
            seen.add(edge_id)
            src=_require(item['from_id'],'from_id',60)
            dst=_require(item['to_id'],'to_id',60)
            if src not in ids or dst not in ids or src==dst:
                raise Refused('G2_UNRESOLVED_EDGE_ENDPOINT')
            relation=_require(item['relation'],'relation',60).upper()
            edges.append({'id':edge_id,'from_id':src,'to_id':dst,'relation':relation,
                          'evidence_quote':_optional(item['evidence_quote']),
                          'source_locator':_optional(item['source_locator'],250),
                          'ledger_locator':f'Edges!{row}'})
        if len({(e['from_id'],e['to_id']) for e in edges})!=len(edges):
            raise Refused('G2_DUPLICATE_EDGE_ENDPOINT')
        if not edges:raise Refused('G2_EDGES_REQUIRED')
        return {'ledger_sha256':doc_hash,'ledger_filename':path.name,
                'nodes':nodes,'edges':edges}
    finally:
        wb.close()


def _finding(status, text, rationale, year, evidence, seed):
    fid=_digest([status, seed, [e['locator'] for e in evidence]])[:24]
    return {'id':fid,'indicator':text,'year':year,'status':status,
            'rationale':rationale,'evidence':evidence,
            'review_status':'PENDING','review_note':''}


def analyze_substantive(g1: dict, ledger: dict) -> dict:
    validate_result(g1)
    if g1.get('schema')!='pi.graph_core.g1.synthetic.v1' or not isinstance(g1.get('documents'),dict):
        raise Refused('G2_VERIFIED_G1_GRAPH_REQUIRED')
    if g1.get('production_admission')!='DENIED':raise Refused('G2_PRODUCTION_DISABLED')
    graph=nx.DiGraph()
    nodes={n['id']:n for n in ledger['nodes']}
    if set(n['role'] for n in nodes.values())-set(g1['documents']):
        raise Refused('G2_ROLE_NOT_IN_G1_SOURCE')
    for n in nodes.values():
        graph.add_node(n['id'],kind=n['kind'],title=n['title'],role=n['role'],year=n['year'],scope=n['scope'])
    findings=[]; evidence_links=nx.DiGraph()
    evidence_links.add_nodes_from(nodes)
    # Synthetic worksheet cell is evidence of an *assertion only*, not proof of source text.
    def ev(sheet, locator, excerpt, role=None):
        return {'document_title':ledger['ledger_filename'],
                'file_sha256':ledger['ledger_sha256'], 'locator':locator,
                'excerpt':excerpt or '[no declared excerpt]',
                'source_unit_id':'NOT_CANONICAL_SOURCE_UNIT',
                'unit_fingerprint':_digest([ledger['ledger_sha256'],locator,excerpt]),
                'provenance_class':'OPERATOR_LEDGER_ASSERTION_NOT_SOURCE_PROOF',
                'related_document_role':role or 'N/A'}
    for e in ledger['edges']:
        src,dst=nodes[e['from_id']],nodes[e['to_id']]
        expected=RELATIONS.get((src['kind'],dst['kind']))
        if expected != e['relation']:
            raise Refused('G2_INVALID_ONTOLOGY_EDGE_TYPE')
        has_quote = bool(e['evidence_quote'] and e['source_locator'] and
                         src['evidence_excerpt'] and src['source_locator'] and
                         dst['evidence_excerpt'] and dst['source_locator'])
        graph.add_edge(src['id'],dst['id'],relation=e['relation'],
                       declaration_id=e['id'],has_declared_evidence=has_quote)
        related=[ev('Edges',e['ledger_locator'],e['evidence_quote'],src['role']),
                 ev('Nodes',src['ledger_locator'],src['evidence_excerpt'],src['role']),
                 ev('Nodes',dst['ledger_locator'],dst['evidence_excerpt'],dst['role'])]
        if not has_quote:
            findings.append(_finding('RELATION_EVIDENCE_GAP',src['title']+' → '+dst['title'],
                'One or more source excerpts/locators or relationship declaration quotes are absent. '
                'No substantive linkage can be substantiated from this ledger.',src['year'],related,e['id']))
        else:
            evidence_links.add_edge(src['id'],dst['id'])
        if src['scope'].casefold()!=dst['scope'].casefold():
            findings.append(_finding('SCOPE_MISMATCH_CANDIDATE',src['title']+' → '+dst['title'],
               'Declared scopes differ; review contextual compatibility before accepting this connection.',
               src['year'],related,e['id']))
        if src['year']!=dst['year']:
            findings.append(_finding('PERIOD_MISMATCH_CANDIDATE',src['title']+' → '+dst['title'],
                'Declared years differ; historical or forward causal linkage must be justified by reviewer.',
                src['year'],related,e['id']))
    # Structural paths are necessary but not sufficient to validate causal claims.
    target_ids=[n['id'] for n in nodes.values() if n['kind']=='SASARAN']
    outcome_ids=[n['id'] for n in nodes.values() if n['kind']=='OUTCOME']
    for n in nodes.values():
        if n['kind'] not in ('PROGRAM','KEGIATAN','OUTPUT','OUTCOME'):
            continue
        destination= outcome_ids if n['kind']=='OUTPUT' else target_ids
        # For output check both output->outcome and outcome->sasaran.
        if n['kind']=='OUTPUT':
            valid=any(nx.has_path(evidence_links,n['id'],o) and
                      any(nx.has_path(evidence_links,o,s) for s in target_ids)
                      for o in outcome_ids)
        else:
            valid=any(nx.has_path(evidence_links,n['id'],s) for s in target_ids)
        if not valid:
            findings.append(_finding('INCOMPLETE_SUBSTANTIVE_LINEAGE',n['title'],
                'No complete chain of declared, source-located relation evidence reaches an intended SASARAN. '
                'This flags a gap in the evidence ledger, not a proven failure of the policy.',
                n['year'],[ev('Nodes',n['ledger_locator'],n['evidence_excerpt'],n['role'])],n['id']))
    if len(findings)>MAX_FINDINGS:raise Refused('G2_FINDINGS_LIMIT_EXCEEDED')
    graph_record={
        'nodes':[{'id':k,**graph.nodes[k]} for k in sorted(graph.nodes)],
        'edges':[{'from':a,'to':b,**graph.edges[a,b]} for a,b in sorted(graph.edges)],
    }
    count=Counter(f['status'] for f in findings)
    result={
        'schema':'pi.graph_g2.substantive.synthetic.v1',
        'production_admission':'DENIED','rights_admission':'SYNTHETIC_ONLY',
        'review_authority':'NOT_GRANTED','analysis_kind':'DECLARED_SUBSTANTIVE_COHERENCE_CANDIDATE',
        'source_g1_sha256':g1['report_sha256'],
        'documents':g1['documents'],'ledger_sha256':ledger['ledger_sha256'],
        'ledger_filename':ledger['ledger_filename'],
        'graph':graph_record,'findings':findings,
        'graph_metrics':{'nodes':graph.number_of_nodes(),'edges':graph.number_of_edges(),
                         'evidence_link_declarations':evidence_links.number_of_edges(),
                         'findings':len(findings),'status_counts':dict(sorted(count.items()))},
        'boundary_notice':('Synthetic pre-review only. Claims and excerpts are operator-supplied in a '
            'structured ledger, not verified canonical SourceUnit excerpts. No semantic, legal or causal '
            'relation is approved without original-document verification and human review.'),
    }
    result['report_sha256']=_digest(result)
    validate_result(result)
    return result
