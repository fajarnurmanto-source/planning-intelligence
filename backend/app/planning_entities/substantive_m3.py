"""M3.1: evidence-linked numeric target triage over existing M2 candidates.

Uses canonical M2 matching, VS1 normalization and target parser.
Never infers a parent-child link, semantic contradiction, cause, or legal breach.
"""
from __future__ import annotations

from collections import Counter

from app.vs1.core import norm, num
from .coherence import ROLES, ROLE_PAIRS, assess_candidates
from .pipeline import _digest

SCHEMA = "pi.m3.substantive_screen.synthetic.v1"
MAX_FINDINGS = 5000


def _attribute(entity: dict, key: str) -> str:
    attrs = entity.get("attributes") or {}
    value = attrs.get(key)
    return str(value).strip() if value is not None else ""


def _screen_pair(left: dict, right: dict) -> tuple[str, str, list[str]]:
    """Only compare explicitly declared indicator, year, unit, and numeric target."""
    lhs, rhs = (_attribute(left, "target"), _attribute(right, "target"))
    if not lhs or not rhs:
        return "TARGET_NOT_DECLARED", "One or both targets are absent.", []
    indicator_l, indicator_r = (_attribute(left, "indicator"), _attribute(right, "indicator"))
    if not indicator_l or not indicator_r or norm(indicator_l) != norm(indicator_r):
        return "INDICATOR_CONTEXT_NOT_COMPARABLE", "Same code alone is not evidence of the same indicator.", []
    year_l, year_r = (_attribute(left, "year"), _attribute(right, "year"))
    if not year_l or not year_r or year_l != year_r:
        return "YEAR_CONTEXT_NOT_COMPARABLE", "Both declared target years must match.", []
    unit_l, unit_r = (_attribute(left, "unit"), _attribute(right, "unit"))
    if not unit_l or not unit_r or norm(unit_l) != norm(unit_r):
        return "UNIT_CONTEXT_NOT_COMPARABLE", "Units must be declared and match before numeric screening.", []
    try:
        target_l, target_r = num(lhs), num(rhs)
    except ValueError:
        return "NONNUMERIC_TARGET_ABSTAIN", "Numeric target interpretation is not established.", []
    if target_l != target_r:
        return ("POTENTIAL_NUMERIC_TARGET_DIFFERENCE",
                "An explicit indicator/year/unit pair contains different numeric target strings. "
                "Scope, target type, semantic lineage, and policy contradiction remain unverified.",
                [target_l, target_r])
    return ("NUMERIC_MATCH_NOT_PROOF_OF_ALIGNMENT",
            "The declared numeric values match; substantive policy alignment remains unverified.",
            [target_l, target_r])


def screen_substantive_candidates(m2_result: dict) -> dict:
    """Screen the M2 result without re-parsing, re-implementing matches, or writing state."""
    if not isinstance(m2_result, dict) or m2_result.get("schema") != "pi.m2.pipeline.synthetic.v1":
        raise ValueError("M3_REQUIRES_M2_PIPELINE")
    if (m2_result.get("rights_admission") != "SYNTHETIC_ONLY"
            or m2_result.get("production_admission") != "DENIED"
            or m2_result.get("review_authority") != "NOT_GRANTED"
            or m2_result.get("regulatory_taxonomy") != "NOT_ACTIVATED"):
        raise PermissionError("M3_SYNTHETIC_BOUNDARY_REQUIRED")
    digest = m2_result.get("report_sha256")
    unsigned = {key: value for key, value in m2_result.items() if key != "report_sha256"}
    if not isinstance(digest, str) or len(digest) != 64 or _digest(unsigned) != digest:
        raise ValueError("M3_M2_REPORT_DIGEST_MISMATCH")
    documents = m2_result.get("documents")
    if not isinstance(documents, dict) or set(documents) != set(ROLES):
        raise ValueError("M3_FOUR_SOURCE_ROLES_REQUIRED")

    # Reuse the established M2 matching/evidence and scope guards.
    recomputed = assess_candidates(documents)
    if recomputed != m2_result.get("coherence"):
        raise ValueError("M3_M2_COHERENCE_MISMATCH")
    findings = recomputed["findings"]
    if len(findings) > MAX_FINDINGS:
        raise ValueError("M3_FINDING_LIMIT")

    indexes = {}
    for role in ROLES:
        entities = documents[role].get("entities", [])
        ids = [entity.get("id") for entity in entities]
        if len(ids) != len(set(ids)):
            raise ValueError("M3_DUPLICATE_ENTITY_ID")
        indexes[role] = {entity["id"]: entity for entity in entities}

    # An entity pair may match by both CODE and INDICATOR_YEAR in M2. The
    # evidence-backed numeric observation must be counted only once.
    pair_groups = {}
    for finding in findings:
        upstream, downstream = finding["upstream_role"], finding["downstream_role"]
        if (upstream, downstream) not in ROLE_PAIRS:
            raise ValueError("M3_ROLE_PAIR_NOT_DECLARED")
        pair_key = (upstream, downstream, finding["upstream_entity_id"],
                    finding["downstream_entity_id"])
        pair_groups.setdefault(pair_key, []).append(finding)

    rows = []
    for pair_key, matches in pair_groups.items():
        upstream, downstream, upstream_id, downstream_id = pair_key
        try:
            lhs = indexes[upstream][upstream_id]
            rhs = indexes[downstream][downstream_id]
        except KeyError as exc:
            raise ValueError("M3_UNRESOLVED_M2_ENTITY") from exc
        status, reason, numeric_targets = _screen_pair(lhs, rhs)
        rows.append({
            "id": _digest(["PI_M3_ENTITY_PAIR", *pair_key])[:32],
            "source_m2_finding_ids": sorted(f["id"] for f in matches),
            "upstream_role": upstream,
            "downstream_role": downstream,
            "match_types": sorted({f["match_type"] for f in matches}),
            "status": status,
            "reason": reason,
            "declared_target_texts": [_attribute(lhs, "target"), _attribute(rhs, "target")],
            "normalized_numeric_targets": numeric_targets,
            "evidence": matches[0]["evidence"],
            "review_required": True,
            "relationship_verification": "NOT_VERIFIED",
            "semantic_entailment": "NOT_TESTED",
            "regulatory_applicability": "NOT_ASSESSED",
        })

    counts = Counter(f["status"] for f in rows)
    rows.sort(key=lambda f: (f["upstream_role"], f["downstream_role"], f["id"]))
    result = {
        "schema": SCHEMA,
        "source_m2_report_sha256": digest,
        "rights_admission": "SYNTHETIC_ONLY",
        "production_admission": "DENIED",
        "review_authority": "NOT_GRANTED",
        "regulatory_taxonomy": "NOT_ACTIVATED",
        "analysis_kind": "EXPLICIT_NUMERIC_TARGET_PRE_REVIEW_SCREEN",
        "graph_lineage": "NOT_VERIFIED",
        "findings": rows,
        "metrics": {
            "candidate_findings": len(rows),
            "source_m2_match_findings": len(findings),
            "status_counts": dict(sorted(counts.items())),
            "verified_substantive_relationships": 0,
            "confirmed_contradictions": 0,
        },
        "boundary_notice": (
            "Numeric observations are evidence-linked screening candidates, not proven "
            "substantive coherence, contradiction, cause, legal compliance, or institutional decisions."
        ),
    }
    result["report_sha256"] = _digest(result)
    return result
