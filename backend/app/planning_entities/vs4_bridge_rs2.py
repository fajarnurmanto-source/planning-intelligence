"""Synthetic M2 -> existing VS4 candidate archive/reviewer contract.

No source documents, production admissions, new database tables or API writes.
The 32-character M2 IDs are preserved in the adapted finding alongside the
24-character VS2 review ID; the full pipeline remains immutable and in memory.
"""
from __future__ import annotations

from hashlib import sha256
import json
import re

from app.vs2.reviews import validate_result
from app.vs4.archive import archive_analysis
from app.vs4.service import require_role

from .coherence import ROLES
from .synthetic_demo import cached_synthetic_result

_SHA = re.compile(r"[0-9a-f]{64}\Z")
_M2_ID = re.compile(r"[0-9a-f]{32}\Z")
MAX_FINDINGS = 100
MAX_EVIDENCE_PER_SIDE = 20


def _digest(value):
    return sha256(json.dumps(value, ensure_ascii=False, sort_keys=True,
                             separators=(",", ":")).encode("utf-8")).hexdigest()


def adapt_m2_to_vs4(pipeline: dict) -> dict:
    """Convert a verified synthetic pipeline to the *existing* VS2/VS4 shape.

    This is a presentation/review projection, not a new assessment. It must
    never transform an unverified or non-synthetic payload into an admission.
    """
    if not isinstance(pipeline, dict) or pipeline.get("schema") != "pi.m2.pipeline.synthetic.v1":
        raise ValueError("RS2_M2_PIPELINE_REQUIRED")
    if (pipeline.get("rights_admission") != "SYNTHETIC_ONLY" or
            pipeline.get("production_admission") != "DENIED" or
            pipeline.get("review_authority") != "NOT_GRANTED" or
            pipeline.get("regulatory_taxonomy") != "NOT_ACTIVATED"):
        raise PermissionError("RS2_SYNTHETIC_BOUNDARY_REQUIRED")
    source_digest = pipeline.get("report_sha256")
    if not isinstance(source_digest, str) or not _SHA.fullmatch(source_digest):
        raise ValueError("RS2_M2_DIGEST_INVALID")
    if _digest({k: v for k, v in pipeline.items() if k != "report_sha256"}) != source_digest:
        raise ValueError("RS2_M2_DIGEST_MISMATCH")

    coherence = pipeline.get("coherence")
    if not isinstance(coherence, dict) or coherence.get("schema") != "pi.m2.coherence.synthetic.v1":
        raise ValueError("RS2_M2_COHERENCE_REQUIRED")
    if (coherence.get("rights_admission") != "SYNTHETIC_ONLY" or
            coherence.get("production_admission") != "DENIED" or
            coherence.get("review_authority") != "NOT_GRANTED"):
        raise PermissionError("RS2_COHERENCE_SYNTHETIC_BOUNDARY")
    if coherence.get("report_sha256") != _digest({k: v for k, v in coherence.items()
                                                  if k != "report_sha256"}):
        raise ValueError("RS2_COHERENCE_DIGEST_MISMATCH")
    originals = coherence.get("findings")
    if not isinstance(originals, list) or len(originals) > MAX_FINDINGS:
        raise ValueError("RS2_FINDINGS_LIMIT")

    findings = []
    used_ids = set()
    for original in originals:
        if not isinstance(original, dict):
            raise ValueError("RS2_FINDING_INVALID")
        original_id = original.get("id")
        left, right = original.get("upstream_role"), original.get("downstream_role")
        if (not isinstance(original_id, str) or not _M2_ID.fullmatch(original_id) or
                left not in ROLES or right not in ROLES or left == right or
                original.get("status") != "CANDIDATE_REVIEW_REQUIRED"):
            raise ValueError("RS2_FINDING_UNTRUSTED")
        review_id = sha256(f"{source_digest}:{original_id}".encode()).hexdigest()[:24]
        if review_id in used_ids:
            raise ValueError("RS2_FINDING_ID_COLLISION")
        used_ids.add(review_id)

        source_evidence = original.get("evidence")
        if not isinstance(source_evidence, dict):
            raise ValueError("RS2_EVIDENCE_REQUIRED")
        evidence = []
        for role in (left, right):
            items = source_evidence.get(role)
            if not isinstance(items, list) or not 1 <= len(items) <= MAX_EVIDENCE_PER_SIDE:
                raise ValueError("RS2_EVIDENCE_BOUNDARY")
            for item in items:
                if not isinstance(item, dict):
                    raise ValueError("RS2_EVIDENCE_INVALID")
                fingerprint = item.get("unit_fingerprint")
                locator = item.get("locator")
                if (not isinstance(fingerprint, str) or not _SHA.fullmatch(fingerprint) or
                        not isinstance(locator, dict) or not locator):
                    raise ValueError("RS2_EVIDENCE_PROVENANCE_REQUIRED")
                source_unit_id = str(item.get("source_unit_id", ""))
                if not source_unit_id or len(source_unit_id) > 120:
                    raise ValueError("RS2_SOURCE_UNIT_ID_INVALID")
                locator_text = json.dumps(locator, sort_keys=True, ensure_ascii=False)
                excerpt = item.get("excerpt")
                if not isinstance(excerpt, str) or not excerpt.strip():
                    raise ValueError("RS2_EVIDENCE_EXCERPT_REQUIRED")
                if len(locator_text) > 500:
                    raise ValueError("RS2_SOURCE_LOCATOR_TOO_LARGE")
                evidence.append({"document_title": f"{role} (SYNTHETIC)",
                                 "locator": locator_text,
                                 "source_unit_id": source_unit_id,
                                 "unit_fingerprint": fingerprint,
                                 "excerpt": excerpt[:500]})

        match_key = original.get("match_key")
        if not isinstance(match_key, list) or len(match_key) < 2:
            raise ValueError("RS2_MATCH_KEY_REQUIRED")
        indicator = str(match_key[2] if len(match_key) > 2 else match_key[0])[:160]
        year = str(match_key[-1]) if len(match_key) == 4 else "NOT_SPECIFIED"
        findings.append({
            "id": review_id,
            "m2_finding_id": original_id,
            "indicator": indicator,
            "year": year,
            "status": "CANDIDATE_REVIEW_REQUIRED",
            "rationale": (f"M2 {left} -> {right}: {original.get('match_type')}; "
                          f"target comparison {original.get('target_comparison')}. "
                          "Synthetic candidate only; no verified relationship."),
            "evidence": evidence,
        })

    analysis = {
        "schema": "pi.rs2.m2_vs4_candidate.synthetic.v1",
        "rights_admission": "SYNTHETIC_ONLY",
        "production_admission": "DENIED",
        "review_authority": "NOT_GRANTED",
        "regulatory_taxonomy": "NOT_ACTIVATED",
        "source_m2_pipeline_sha256": source_digest,
        "source_m2_coherence_sha256": coherence["report_sha256"],
        "findings": findings,
    }
    analysis["report_sha256"] = _digest(analysis)
    validate_result(analysis)
    return analysis


def archive_cached_m2_for_vs4(store, *, actor: str, roles: dict[str, str]) -> dict:
    """A VS4-authenticated caller may archive *derived* M2 synthetic candidates."""
    require_role(actor, roles, write=True)
    analysis = adapt_m2_to_vs4(cached_synthetic_result())
    archive_analysis(store, analysis, actor=actor)
    return analysis
