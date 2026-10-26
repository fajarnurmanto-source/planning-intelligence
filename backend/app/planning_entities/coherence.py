"""Synthetic, provenance-bearing candidate comparisons; not legal conclusions."""
from __future__ import annotations

from collections import defaultdict
from hashlib import sha256
import json

ROLES = ("RPJMD", "RKPD", "RENSTRA", "RENJA")
ROLE_PAIRS = (("RPJMD", "RKPD"), ("RPJMD", "RENSTRA"), ("RENSTRA", "RENJA"))


def _hash(value):
    return sha256(json.dumps(value, sort_keys=True, ensure_ascii=False,
                             separators=(",", ":")).encode()).hexdigest()


def assess_candidates(normalized_documents):
    """Compare *explicit* same-kind codes or indicator labels between four roles.

    Caller supplies normalized M2 results, never source documents or AI output.
    All findings are pre-review candidates. Numeric target strings are compared
    exactly, not interpreted as progress, units, or causal effects.
    """
    if not isinstance(normalized_documents, dict) or set(normalized_documents) != set(ROLES):
        raise ValueError("M2_FOUR_ROLES_REQUIRED")
    hashes = []
    scopes = []
    indexed = {}
    for role in ROLES:
        doc = normalized_documents[role]
        if doc.get("rights_admission") != "SYNTHETIC_ONLY" or doc.get("production_admission") != "DENIED":
            raise PermissionError("M2_SYNTHETIC_BOUNDARY")
        if doc.get("document_role") != role:
            raise ValueError("M2_ROLE_MISMATCH")
        scope = tuple(doc.get(k) for k in ("jurisdiction_id", "planning_period_id", "planning_scope"))
        if any(not isinstance(v, str) or not v.strip() for v in scope):
            raise ValueError("M2_SCOPE_REQUIRED_FOR_COMPARISON")
        scopes.append(scope)
        hashes.append(doc["document_sha256"])
        entities = doc.get("entities", [])
        if len(entities) > 10000:
            raise ValueError("M2_ENTITIES_LIMIT")
        for entity in entities:
            if not entity.get("evidence") or not all(ev.get("unit_fingerprint") and ev.get("locator") for ev in entity["evidence"]):
                raise ValueError("M2_ENTITY_EVIDENCE_REQUIRED")
        idx = defaultdict(list)
        for entity in entities:
            if entity.get("code"):
                idx[(entity["kind"], "CODE", entity["code"].casefold())].append(entity)
            attrs = entity.get("attributes") or {}
            if attrs.get("indicator") and attrs.get("year"):
                idx[(entity["kind"], "INDICATOR_YEAR",
                     attrs["indicator"].casefold().strip(), attrs["year"])].append(entity)
        indexed[role] = idx
    if len(set(hashes)) != 4:
        raise ValueError("M2_DISTINCT_DOCUMENTS_REQUIRED")
    if len(set(scopes)) != 1:
        raise ValueError("M2_DOCUMENT_SCOPE_MISMATCH")
    findings = []
    for upstream, downstream in ROLE_PAIRS:
        for key in sorted(indexed[upstream]):
            left = indexed[upstream][key]
            right = indexed[downstream].get(key, [])
            if not right:
                continue
            # Uniqueness is necessary for a useful candidate, not sufficient for validation.
            if len(left) != 1 or len(right) != 1:
                continue
            a, b = left[0], right[0]
            target_a = (a.get("attributes") or {}).get("target")
            target_b = (b.get("attributes") or {}).get("target")
            finding = {
                "id": _hash([upstream, downstream, key, a["id"], b["id"]])[:32],
                "upstream_role": upstream, "downstream_role": downstream,
                "match_type": key[1], "match_key": list(key),
                "upstream_entity_id": a["id"], "downstream_entity_id": b["id"],
                "target_comparison": "DIFFERENT_TEXT" if target_a is not None and
                    target_b is not None and target_a != target_b else
                    "SAME_TEXT" if target_a is not None and target_a == target_b else "NOT_COMPARABLE",
                "evidence": {upstream: a["evidence"], downstream: b["evidence"]},
                "status": "CANDIDATE_REVIEW_REQUIRED", "regulatory_applicability": "NOT_ASSESSED",
            }
            findings.append(finding)
            if len(findings) > 5000:
                raise ValueError("M2_FINDING_LIMIT")
    result = {"schema": "pi.m2.coherence.synthetic.v1", "production_admission": "DENIED",
              "rights_admission": "SYNTHETIC_ONLY", "regulatory_taxonomy": "NOT_ACTIVATED",
              "review_authority": "NOT_GRANTED", "findings": findings,
              "metrics": {"candidate_findings": len(findings), "verified_links": 0}}
    result["report_sha256"] = _hash(result)
    return result
