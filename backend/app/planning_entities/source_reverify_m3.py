"""M3.3: recheck user-declared M3.2 quotes against original synthetic bytes.

ADOPT the canonical parser, G3 format locators and M3.2 rights/claim screening.
This verifies literal SourceUnit quotations only, not substantive policy links.
No real documents, uploads, network, database, models or legal decisions.
"""
from __future__ import annotations

from hashlib import sha256
from pathlib import Path
from typing import Mapping

from app.graph_g3.engine import source_locator
from app.parser import parse_document

from .coherence import ROLES
from .normalize import _as_unit_dict
from .pipeline import _digest, EXTENSIONS
from .relation_m3 import screen_declared_relations

SCHEMA = "pi.m3.original_source_reverification.synthetic.v1"
MAX_ORIGINAL_BYTES = 10 * 1024 * 1024


def reverify_declared_relation_sources(m2_result: dict, claims: list[dict],
                                       originals: Mapping[str, str | Path]) -> dict:
    """Independent reparse of the same four SHA-pinned synthetic originals.

    A report copied/re-signed from M2 cannot make fabricated excerpt text pass:
    the exact locator/fingerprint and entire normalized SourceUnit text must
    match a new parse of original file bytes. This is G3-style *literal*
    verification, not independently implemented semantic interpretation.
    """
    m32 = screen_declared_relations(m2_result, claims)
    if not isinstance(originals, Mapping) or set(originals) != set(ROLES):
        raise ValueError("M33_FOUR_ORIGINAL_SYNTHETIC_ROLES_REQUIRED")
    parser_runs = m2_result.get("parser_runs")
    if not isinstance(parser_runs, dict) or set(parser_runs) != set(ROLES):
        raise ValueError("M33_ORIGINAL_PARSER_PROVENANCE_REQUIRED")

    original_index = {}
    verified_documents = {}
    for role in ROLES:
        path = Path(originals[role])
        if (path.suffix.lower() not in EXTENSIONS or not path.is_file()
                or path.stat().st_size > MAX_ORIGINAL_BYTES):
            raise ValueError("M33_BOUNDED_SYNTHETIC_ORIGINAL_REQUIRED_" + role)
        expected_sha = m2_result["documents"][role]["document_sha256"]
        actual_sha = sha256(path.read_bytes()).hexdigest()
        if actual_sha != expected_sha:
            raise ValueError("M33_ORIGINAL_SHA256_MISMATCH_" + role)

        # Reuse the deterministic parser. This is a *new* parse of source bytes,
        # unlike M3.2's match to M2's archived parser excerpt.
        parsed = parse_document(path, input_sha256=actual_sha, extension=path.suffix)
        expected_run = parser_runs[role]
        for stored, recomputed in (
            (expected_run.get("parser_name"), parsed["parser_name"]),
            (expected_run.get("parser_version"), parsed["parser_version"]),
            (expected_run.get("run_fingerprint"), parsed["run_fingerprint"]),
            (expected_run.get("source_result_sha256"), parsed["result_sha256"]),
            (expected_run.get("source_unit_count"), parsed["source_unit_count"]),
        ):
            if stored != recomputed:
                raise ValueError("M33_REPARSE_PROVENANCE_MISMATCH_" + role)
        units = {}
        for parsed_unit in parsed["units"]:
            unit = _as_unit_dict(parsed_unit)
            fingerprint = unit["unit_fingerprint"]
            if fingerprint in units:
                raise ValueError("M33_DUPLICATE_ORIGINAL_SOURCE_UNIT_" + role)
            units[fingerprint] = unit
        original_index[role] = units
        verified_documents[role] = {"document_sha256": actual_sha,
                                    "source_result_sha256": parsed["result_sha256"],
                                    "source_unit_count": parsed["source_unit_count"]}

    m2_index = {
        (role, item["unit_fingerprint"]): item
        for role in ROLES for item in m2_result["source_unit_index"][role]
    }
    findings = []
    for candidate in m32["findings"]:
        role = candidate["source_role"]
        fingerprint = candidate["source_unit_fingerprint"]
        index_unit = m2_index.get((role, fingerprint))
        original = original_index.get(role, {}).get(fingerprint)
        canonical_locator = source_locator(original) if original is not None else None
        source_evidence = None
        if not role or not fingerprint:
            status = "ORIGINAL_SOURCE_REFERENCE_MISSING_ABSTAIN"
        elif original is None or index_unit is None:
            status = "ORIGINAL_SOURCE_UNIT_NOT_LOCATED_ABSTAIN"
        elif canonical_locator is None:
            status = "ORIGINAL_LOCATOR_UNSUPPORTED_ABSTAIN"
        elif (index_unit["locator"] != original["locator"]
              or index_unit["excerpt"] != str(original.get("raw_text") or "")[:2000]):
            status = "M2_INDEX_DIFFERS_FROM_ORIGINAL_ABSTAIN"
        elif not candidate["declared_quote"]:
            status = "ORIGINAL_QUOTE_MISSING_ABSTAIN"
        elif (" ".join(candidate["declared_quote"].split()) !=
              " ".join(str(original.get("raw_text") or "").split())):
            # G3 requires the entire original SourceUnit quote, not a substring.
            status = "ORIGINAL_QUOTE_NOT_EXACT_UNIT_ABSTAIN"
        else:
            status = "EXACT_ORIGINAL_SOURCE_UNIT_LOCATED"
            source_evidence = {
                "document_role": role,
                "document_sha256": verified_documents[role]["document_sha256"],
                "unit_fingerprint": fingerprint,
                "locator": canonical_locator,
                "parser_result_sha256": verified_documents[role]["source_result_sha256"],
                "verification_scope": "EXACT_TEXT_AT_REPARSED_SOURCE_UNIT_ONLY",
            }
        findings.append({
            "id": candidate["id"],
            "m32_candidate_status": candidate["status"],
            "original_source_status": status,
            "original_source_evidence": source_evidence,
            "relationship_verification": "NOT_VERIFIED",
            "semantic_entailment": "NOT_TESTED",
            "review_required": True,
        })

    verified_quotes = sum(f["original_source_status"] == "EXACT_ORIGINAL_SOURCE_UNIT_LOCATED"
                          for f in findings)
    result = {
        "schema": SCHEMA,
        "source_m2_report_sha256": m2_result["report_sha256"],
        "source_m32_report_sha256": m32["report_sha256"],
        "rights_admission": "SYNTHETIC_ONLY",
        "production_admission": "DENIED",
        "review_authority": "NOT_GRANTED",
        "regulatory_taxonomy": "NOT_ACTIVATED",
        "analysis_kind": "EXACT_ORIGINAL_SOURCE_UNIT_QUOTE_REVERIFICATION",
        "documents": verified_documents,
        "findings": sorted(findings, key=lambda item: item["id"]),
        "metrics": {
            "declared_relation_candidates": len(findings),
            "exact_original_source_quotes_located": verified_quotes,
            "original_source_gaps": len(findings) - verified_quotes,
            "verified_substantive_relationships": 0,
            "confirmed_contradictions": 0,
        },
        "boundary_notice": (
            "Fresh parsing of matching original synthetic bytes verifies only exact SourceUnit "
            "text and locator. It cannot verify policy relationships, semantic entailment, "
            "causation, legal applicability or approval. Human review remains required."
        ),
    }
    result["report_sha256"] = _digest(result)
    return result
