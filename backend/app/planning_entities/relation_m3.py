"""M3.2: synthetic, evidence-linked screening of EXPLICITLY declared relationships.

ADOPT G2 ontology, G4 literal quote cues, G5 direction/modality, M2 provenance,
M3.1 digest guards and NetworkX. This never auto-infers a policy relationship.
An exact match against an M2 parser excerpt is not a G3 re-verification of the
original document, semantic entailment, or institutional authorization.
"""
from __future__ import annotations

from collections import Counter

import networkx as nx

from app.graph_g2.engine import RELATIONS
from app.graph_g4.engine import lexical_screen
from app.graph_g5.engine import orientation_screen

from .coherence import ROLES
from .pipeline import _digest
from .substantive_m3 import screen_substantive_candidates

SCHEMA = "pi.m3.declared_relations.synthetic.v1"
MAX_RELATION_CLAIMS = 16
MAX_QUOTE_CHARS = 800


def allowed_relations() -> list[dict]:
    """Expose the existing G2 ontology; do not maintain a second mapping."""
    return [
        {"from_kind": first, "to_kind": second, "relation": relation}
        for (first, second), relation in sorted(RELATIONS.items())
    ]


def _nonempty(value, field, limit=80):
    if not isinstance(value, str) or not value.strip() or len(value) > limit:
        raise ValueError("M3_INVALID_" + field.upper())
    return value.strip()


def screen_declared_relations(m2_result: dict, claims: list[dict]) -> dict:
    """Return cautious pre-review observations, never verified policy edges.

    Each relation, direction and source quote is an operator declaration.
    No claim is synthesized from label similarity, role order or M2 matches.
    """
    # Reuse the M3.1/M2 cryptographic digest, synthetic scope and rights gates.
    screen_substantive_candidates(m2_result)
    if not isinstance(claims, list) or len(claims) > MAX_RELATION_CLAIMS:
        raise ValueError("M3_RELATION_CLAIMS_LIMIT")
    documents = m2_result["documents"]
    source_index = m2_result.get("source_unit_index")
    if not isinstance(source_index, dict) or set(source_index) != set(ROLES):
        raise ValueError("M3_M2_SOURCE_INDEX_REQUIRED")
    dropped = m2_result.get("source_index_dropped_units")
    if not isinstance(dropped, dict) or set(dropped) != set(ROLES):
        raise ValueError("M3_M2_SOURCE_COVERAGE_REQUIRED")

    entities = {}
    for role in ROLES:
        for entity in documents[role]["entities"]:
            key = (role, entity["id"])
            if key in entities:
                raise ValueError("M3_AMBIGUOUS_ENTITY")
            entities[key] = entity

    sources = {}
    for role in ROLES:
        index = source_index[role]
        if not isinstance(index, list) or len(index) > 512:
            raise ValueError("M3_SOURCE_INDEX_BOUNDARY")
        if not isinstance(dropped[role], int) or dropped[role] < 0:
            raise ValueError("M3_INVALID_SOURCE_COVERAGE")
        for item in index:
            fingerprint = item.get("unit_fingerprint")
            if (not isinstance(fingerprint, str) or len(fingerprint) != 64
                    or not isinstance(item.get("locator"), dict)
                    or not isinstance(item.get("excerpt"), str)
                    or item.get("document_sha256") != documents[role]["document_sha256"]):
                raise ValueError("M3_INVALID_INDEXED_SOURCE_UNIT")
            key = (role, fingerprint)
            if key in sources:
                raise ValueError("M3_AMBIGUOUS_SOURCE_FINGERPRINT")
            sources[key] = item

    graph = nx.DiGraph()
    seen_pairs = set()
    observations = []
    for claim in claims:
        if not isinstance(claim, dict):
            raise ValueError("M3_DECLARED_RELATION_DICT_REQUIRED")
        first_role = _nonempty(claim.get("from_role"), "from_role")
        second_role = _nonempty(claim.get("to_role"), "to_role")
        first_id = _nonempty(claim.get("from_entity_id"), "from_entity_id")
        second_id = _nonempty(claim.get("to_entity_id"), "to_entity_id")
        relation = _nonempty(claim.get("relation"), "relation")
        if first_role not in ROLES or second_role not in ROLES:
            raise ValueError("M3_UNKNOWN_DOCUMENT_ROLE")
        left_key, right_key = (first_role, first_id), (second_role, second_id)
        if left_key == right_key or left_key not in entities or right_key not in entities:
            raise ValueError("M3_UNRESOLVED_RELATION_ENDPOINT")
        left, right = entities[left_key], entities[right_key]
        if RELATIONS.get((left["kind"], right["kind"])) != relation:
            raise ValueError("M3_INVALID_G2_ONTOLOGY_EDGE")
        pair = (left_key, right_key)
        if pair in seen_pairs:
            raise ValueError("M3_DUPLICATE_RELATION_PAIR")
        seen_pairs.add(pair)

        quote = claim.get("quote", "")
        if not isinstance(quote, str) or len(quote) > MAX_QUOTE_CHARS:
            raise ValueError("M3_INVALID_RELATION_QUOTE")
        quote = quote.strip()
        source_role = claim.get("source_role", "")
        source_fingerprint = claim.get("source_unit_fingerprint", "")
        if not isinstance(source_role, str) or not isinstance(source_fingerprint, str):
            raise ValueError("M3_INVALID_SOURCE_REFERENCE")
        if source_role and source_role not in ROLES:
            raise ValueError("M3_UNKNOWN_QUOTE_ROLE")
        source = sources.get((source_role, source_fingerprint))
        grounded = bool(quote and source and quote in source["excerpt"])
        lexical_status, lexical_reason = "NOT_TESTED", "Source quote not grounded."
        direction_status, direction_reason = "NOT_TESTED", "Source quote not grounded."
        if not quote or not source_role or not source_fingerprint:
            status = "SOURCE_QUOTE_NOT_DECLARED_ABSTAIN"
        elif not grounded:
            status = "QUOTE_NOT_GROUNDED_ABSTAIN"
        else:
            lexical_status, lexical_reason = lexical_screen(
                quote, left["title"], right["title"], relation)
            direction_status, direction_reason = orientation_screen(
                quote, left["title"], right["title"], relation)
            if direction_status == "MIXED_POLARITY_CANDIDATE":
                status = "MIXED_POLARITY_CANDIDATE"
            elif direction_status == "EXPLICIT_NEGATION":
                status = "EXPLICIT_NEGATION_CANDIDATE"
            elif direction_status == "PROSPECTIVE_RELATION":
                status = "PROSPECTIVE_RELATION_CANDIDATE"
            elif direction_status == "REVERSED_DIRECTION_CANDIDATE":
                status = "REVERSED_DIRECTION_CANDIDATE"
            elif (lexical_status == "EXPLICIT_RELATION_ASSERTION_CANDIDATE"
                  and direction_status == "DIRECTIONAL_ASSERTION_CANDIDATE"):
                status = "SOURCE_DECLARED_RELATION_CANDIDATE"
            else:
                status = "RELATION_CONTEXT_UNRESOLVED_ABSTAIN"

        claim_id = _digest(["PI_M3_DECLARED_RELATION", *left_key, *right_key,
                            relation, source_role, source_fingerprint, quote])[:32]
        for role, entity in ((first_role, left), (second_role, right)):
            node_id = role + ":" + entity["id"]
            graph.add_node(node_id, role=role, kind=entity["kind"],
                           title=entity["title"], relationship_verification="NOT_VERIFIED")
        graph.add_edge(first_role + ":" + first_id,
                       second_role + ":" + second_id,
                       relation=relation, claim_id=claim_id,
                       evidence_grounded_to_m2_excerpt=grounded,
                       status=status, verification="NOT_VERIFIED")
        observations.append({
            "id": claim_id,
            "from_role": first_role, "from_entity_id": first_id,
            "from_kind": left["kind"], "from_title": left["title"],
            "to_role": second_role, "to_entity_id": second_id,
            "to_kind": right["kind"], "to_title": right["title"],
            "relation": relation, "declared_quote": quote,
            "source_role": source_role, "source_unit_fingerprint": source_fingerprint,
            "source_evidence": source if grounded else None,
            "grounding_status": "MATCHED_M2_PARSER_EXCERPT" if grounded else "NOT_GROUNDED",
            "lexical_status": lexical_status, "lexical_reason": lexical_reason,
            "direction_status": direction_status, "direction_reason": direction_reason,
            "status": status,
            "relationship_verification": "NOT_VERIFIED",
            "semantic_entailment": "NOT_TESTED",
            "scope_and_year_causality": "NOT_VERIFIED",
            "review_required": True,
        })

    observations.sort(key=lambda item: item["id"])
    count = Counter(item["status"] for item in observations)
    result = {
        "schema": SCHEMA,
        "source_m2_report_sha256": m2_result["report_sha256"],
        "rights_admission": "SYNTHETIC_ONLY", "production_admission": "DENIED",
        "review_authority": "NOT_GRANTED", "regulatory_taxonomy": "NOT_ACTIVATED",
        "analysis_kind": "HUMAN_DECLARED_RELATION_QUOTE_PRE_REVIEW",
        "graph": {
            "nodes": [{"id": node_id, **graph.nodes[node_id]} for node_id in sorted(graph.nodes)],
            "edges": [{"from": src, "to": dst, **graph.edges[src, dst]}
                      for src, dst in sorted(graph.edges)],
        },
        "findings": observations,
        "metrics": {
            "declared_relation_candidates": len(observations),
            "grounded_m2_excerpts": sum(item["grounding_status"] == "MATCHED_M2_PARSER_EXCERPT"
                                        for item in observations),
            "status_counts": dict(sorted(count.items())),
            "verified_substantive_relationships": 0,
            "confirmed_contradictions": 0,
        },
        "source_index_dropped_units": dict(dropped),
        "boundary_notice": (
            "Only explicit user-declared relationships are screened. Matching an M2 parser "
            "excerpt does not independently verify original document bytes, entailment, "
            "causation, policy validity, regulatory compliance, or review authority."
        ),
    }
    result["report_sha256"] = _digest(result)
    return result
