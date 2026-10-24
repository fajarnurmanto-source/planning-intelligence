"""NetworkX graph over canonical PI SourceUnit evidence (no legal or causal conclusions).

The *document-type roles* below are explicitly selected by the operator. These
structural edges are hypotheses for pre-review and NEVER a verified legal,
causal, budget, program, or semantic lineage. No original files are stored.
"""
from __future__ import annotations

from hashlib import sha256
import json
from typing import Mapping

import networkx as nx

from app.vs1.canonical_bridge import Refused, prepare_side
from app.vs1.core import analyze as compare, norm

ROLES = ('RPJMD', 'RKPD', 'RENSTRA', 'RENJA')
# Conceptual role pairing, not automatic attribution of document authority.
DECLARED_LINKS = (('RPJMD', 'RKPD'), ('RPJMD', 'RENSTRA'), ('RENSTRA', 'RENJA'))
MAX_EXTRACTED_INDICATORS_PER_DOCUMENT = 300
MAX_FINDINGS = 900


def _digest(obj) -> str:
    return sha256(json.dumps(obj, ensure_ascii=False, sort_keys=True,
                             separators=(',', ':')).encode('utf-8')).hexdigest()


def _evidence(doc, item):
    if not item.get('pi_source_unit_id') or not item.get('pi_unit_fingerprint'):
        raise Refused('UNRESOLVED_CANONICAL_EVIDENCE')
    return {
        'document_id':doc['id'], 'document_title':doc['title'],
        'document_version_id':doc['version_id'], 'parsing_run_id':doc['run_id'],
        'file_sha256':doc['sha256'], 'locator':item['locator'],
        'excerpt':item['excerpt'], 'target':item['target'],
        'source_unit_id':item['pi_source_unit_id'],
        'unit_fingerprint':item['pi_unit_fingerprint'],
    }


def analyze_graph(source_tuples: Mapping[str, tuple]) -> dict:
    """Four parsed, synthetic canonical tuples → bounded provenance-linked graph.

    Mapping shape: role -> (Document, DocumentVersion, ParsingRun, [SourceUnit]).
    Does not use database, internet, AI, ingestion rights, or approval authority.
    """
    if not isinstance(source_tuples, Mapping) or set(source_tuples) != set(ROLES):
        raise Refused('FOUR_EXPLICIT_DOCUMENT_ROLES_REQUIRED')
    prepared = {role: prepare_side(*source_tuples[role]) for role in ROLES}
    documents = {role: prepared[role][0] for role in ROLES}
    units_by_role = {role: prepared[role][1] for role in ROLES}
    if len({d['sha256'] for d in documents.values()}) != 4:
        raise Refused('FOUR_DISTINCT_SOURCE_DOCUMENTS_REQUIRED')
    if len({d['jurisdiction_id'] for d in documents.values()}) != 1:
        raise Refused('JURISDICTION_BOUNDARY_MISMATCH')
    if len({d['planning_period_id'] for d in documents.values()}) != 1:
        raise Refused('PERIOD_DECLARATION_MISMATCH')
    if len({d['group_name'] for d in documents.values()}) != 1:
        raise Refused('GROUP_DECLARATION_MISMATCH')
    if len({d['scope'] for d in documents.values()}) != 1:
        raise Refused('SCOPE_DECLARATION_MISMATCH')
    for role in ROLES:
        units = units_by_role[role]
        if len(units) > MAX_EXTRACTED_INDICATORS_PER_DOCUMENT:
            raise Refused('GRAPH_INDICATOR_LIMIT_EXCEEDED')
        keys = [(norm(u['indicator']), int(u['year'])) for u in units]
        if len(keys) != len(set(keys)):
            raise Refused('AMBIGUOUS_DUPLICATE_INDICATOR_YEAR_IN_' + role)

    graph = nx.DiGraph()
    obs_index = {}
    for role in ROLES:
        d = documents[role]
        graph.add_node('doc:' + role, kind='document', role=role, title=d['title'],
                       digest=d['sha256'])
        for u in units_by_role[role]:
            key = (role, u['locator'])
            node_id = 'obs:' + _digest([role, u['locator'], u['pi_source_unit_id']])[:24]
            obs_index[key] = node_id
            graph.add_node(node_id, kind='indicator_observation', role=role,
                           label=u['indicator'], year=u['year'], target=u['target'],
                           unit=u['unit'], source_unit_id=u['pi_source_unit_id'],
                           unit_fingerprint=u['pi_unit_fingerprint'], locator=u['locator'])
            graph.add_edge('doc:' + role, node_id, relation='HAS_EXTRACTED_OBSERVATION')

    schema_graph = nx.DiGraph()
    schema_graph.add_nodes_from(ROLES)
    schema_graph.add_edges_from(DECLARED_LINKS)
    if not nx.is_directed_acyclic_graph(schema_graph):
        raise Refused('DECLARED_PLANNING_HIERARCHY_CYCLE')
    for parent, child in DECLARED_LINKS:
        graph.add_edge('doc:' + parent, 'doc:' + child,
                       relation='OPERATOR_DECLARED_STRUCTURE_UNVERIFIED')
    findings = []
    edges_verified_as_document_relationships = 0
    for parent, child in DECLARED_LINKS:
        upstream, downstream = documents[parent], documents[child]
        p_units, c_units = units_by_role[parent], units_by_role[child]
        for finding in compare(upstream, downstream, p_units, c_units):
            finding['graph_parent_role'] = parent
            finding['graph_child_role'] = child
            finding['rationale'] += (' Role hierarchy is operator-declared; matching '
                                      'indicators are candidates, not verified causal or legal lineage.')
            for ev in finding['evidence']:
                role = parent if ev['document_id'] == upstream['id'] else child
                source = next((u for u in units_by_role[role]
                               if u['locator'] == ev['locator']), None)
                if source is None:
                    raise Refused('GRAPH_EVIDENCE_LOCATOR_UNRESOLVED')
                ev.update(_evidence(documents[role], source))
            findings.append(finding)
            if len(finding['evidence']) == 2:
                e0, e1 = finding['evidence']
                n0 = obs_index[(parent, e0['locator'])]
                n1 = obs_index[(child, e1['locator'])]
                graph.add_edge(n0, n1, relation='EXACT_INDICATOR_YEAR_CANDIDATE',
                               finding_id=finding['id'], status=finding['status'])
                edges_verified_as_document_relationships += 1
    if len(findings) > MAX_FINDINGS:
        raise Refused('GRAPH_FINDING_LIMIT_EXCEEDED')
    # JSON-safe graph; avoids arbitrary Python objects and graph execution in storage.
    graph_data = {
        'nodes': [{'id': n, **graph.nodes[n]} for n in sorted(graph.nodes)],
        'edges': [{'from': a, 'to': b, **graph.edges[a,b]}
                  for a,b in sorted(graph.edges)],
        'declared_role_links': [{'parent': a, 'child': b} for a,b in DECLARED_LINKS],
        'multi_hop_role_paths': {
            'RPJMD_TO_RENJA': nx.shortest_path(schema_graph, 'RPJMD', 'RENJA'),
        },
    }
    result = {
        'schema': 'pi.graph_core.g1.synthetic.v1',
        'production_admission': 'DENIED', 'rights_admission': 'SYNTHETIC_ONLY',
        'review_authority': 'NOT_GRANTED',
        'analysis_kind': 'OPERATOR_DECLARED_STRATEGIC_GRAPH_CANDIDATE',
        'regulatory_graph': 'NOT_IMPLEMENTED',
        'documents': {role: documents[role] for role in ROLES},
        'graph': graph_data, 'findings': findings,
        'graph_metrics': {
            'documents': 4, 'nodes': graph.number_of_nodes(),
            'edges': graph.number_of_edges(),
            'compared_observation_pairs': edges_verified_as_document_relationships,
            'findings': len(findings),
        },
        'boundary_notice': (
            'Synthetic pre-review only. Role links are user-declared, not '
            'verified legal, funding, causal, or regulatory associations.'
        ),
    }
    result['report_sha256'] = _digest(result)
    return result
