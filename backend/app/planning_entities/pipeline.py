"""M2 read-only synthetic pipeline over the canonical PI parser.

Does not create database records, send data to AI APIs, admit real documents,
activate regulations, or issue regulatory/causal conclusions.
"""
from __future__ import annotations

from hashlib import sha256
from pathlib import Path
import json

from .normalize import normalize_source_units, _as_unit_dict, _source
from .coherence import assess_candidates, ROLES

EXTENSIONS = {".pdf", ".docx", ".xlsx"}
DECISIONS = {"ACCEPT_CANDIDATE", "REJECT_CANDIDATE", "NEEDS_MORE_EVIDENCE"}
MAX_INDEXED_SOURCE_UNITS_PER_DOCUMENT = 512


def _digest(obj):
    return sha256(json.dumps(obj, sort_keys=True, ensure_ascii=False,
                             separators=(",", ":")).encode("utf-8")).hexdigest()


def run_synthetic_pipeline(documents, *, jurisdiction_id, planning_period_id,
                           planning_scope="SYNTHETIC-DEMO", declared_source_kind="SYNTHETIC_FIXTURE"):
    """Read four explicitly-declared, synthetic fixture files with existing PI parser.

    No file writes. The caller's declaration is not a rights-admission mechanism.
    """
    if declared_source_kind != "SYNTHETIC_FIXTURE":
        raise PermissionError("M2_RESEARCH_SYNTHETIC_ONLY")
    if not isinstance(documents, dict) or set(documents) != set(ROLES):
        raise ValueError("M2_FOUR_DOCUMENTS_REQUIRED")
    from app.parser import parse_document  # reuse canonical parser; no reimplementation

    normalized = {}
    parser_runs = {}
    source_unit_index = {}
    source_index_dropped_units = {}
    for role in ROLES:
        path = Path(documents[role])
        if path.suffix.lower() not in EXTENSIONS or not path.is_file():
            raise ValueError("M2_SUPPORTED_SYNTHETIC_SOURCE_FILE_REQUIRED")
        if path.stat().st_size > 10 * 1024 * 1024:
            raise ValueError("M2_SYNTHETIC_FIXTURE_TOO_LARGE")
        contents = path.read_bytes()
        digest = sha256(contents).hexdigest()
        parsed = parse_document(path, input_sha256=digest, extension=path.suffix)
        normalized[role] = normalize_source_units(
            parsed["units"], document_sha256=digest, document_role=role,
            jurisdiction_id=jurisdiction_id, planning_period_id=planning_period_id,
            planning_scope=planning_scope,
        )
        # Reuse units already parsed for M2. This is a bounded, synthetic-only
        # quote index for M3; it does not initiate another parser run.
        indexed = []
        dropped = 0
        for parsed_unit in parsed["units"]:
            unit = _as_unit_dict(parsed_unit)
            if not str(unit.get("raw_text") or "").strip():
                continue
            if len(indexed) < MAX_INDEXED_SOURCE_UNITS_PER_DOCUMENT:
                indexed.append(_source(unit, digest))
            else:
                dropped += 1
        source_unit_index[role] = indexed
        source_index_dropped_units[role] = dropped
        parser_runs[role] = {
            "parser_name": parsed["parser_name"], "parser_version": parsed["parser_version"],
            "run_fingerprint": parsed["run_fingerprint"],
            "source_unit_count": parsed["source_unit_count"],
            "source_result_sha256": parsed["result_sha256"],
        }
    assessment = assess_candidates(normalized)
    output = {
        "schema": "pi.m2.pipeline.synthetic.v1",
        "rights_admission": "SYNTHETIC_ONLY", "production_admission": "DENIED",
        "review_authority": "NOT_GRANTED",
        "regulatory_taxonomy": "NOT_ACTIVATED",
        "documents": normalized, "parser_runs": parser_runs,
        "source_unit_index": source_unit_index,
        "source_index_dropped_units": source_index_dropped_units,
        "coherence": assessment,
    }
    output["report_sha256"] = _digest(output)
    return output


def apply_synthetic_review(pipeline_result, decisions):
    """Attach synthetic reviewer *annotations*, never authority or approval."""
    if pipeline_result.get("schema") != "pi.m2.pipeline.synthetic.v1":
        raise ValueError("M2_PIPELINE_RESULT_REQUIRED")
    if pipeline_result.get("production_admission") != "DENIED":
        raise PermissionError("M2_PRODUCTION_ADMISSION_DENIED")
    if not isinstance(decisions, dict):
        raise ValueError("M2_DECISIONS_DICT_REQUIRED")
    valid = {f["id"] for f in pipeline_result["coherence"]["findings"]}
    if not set(decisions).issubset(valid):
        raise ValueError("M2_UNKNOWN_FINDING_ID")
    annotations = []
    for fid in sorted(decisions):
        choice = decisions[fid]
        if choice not in DECISIONS:
            raise ValueError("M2_INVALID_REVIEW_DECISION")
        annotations.append({"finding_id": fid, "annotation": choice,
                            "authority": "SYNTHETIC_REVIEW_ONLY"})
    result = {"schema": "pi.m2.review.synthetic.v1",
              "pipeline_report_sha256": pipeline_result["report_sha256"],
              "production_admission": "DENIED", "review_authority": "NOT_GRANTED",
              "annotations": annotations}
    result["report_sha256"] = _digest(result)
    return result


def render_synthetic_report(pipeline_result, review):
    """Return self-contained Markdown, without storing or uploading source data."""
    if review.get("pipeline_report_sha256") != pipeline_result.get("report_sha256"):
        raise ValueError("M2_REVIEW_PIPELINE_MISMATCH")
    if review.get("production_admission") != "DENIED":
        raise PermissionError("M2_REVIEW_PRODUCTION_DENIED")
    findings = pipeline_result["coherence"]["findings"]
    annotations = {a["finding_id"]: a["annotation"] for a in review["annotations"]}
    lines = [
        "# Planning Intelligence — Synthetic M2 Review",
        "",
        "**Internal synthetic demonstration; not a legal, causal or production conclusion.**",
        "",
        f"Pipeline digest: `{pipeline_result['report_sha256']}`",
        f"Candidate findings: {len(findings)}",
        "Regulatory taxonomy: NOT_ACTIVATED",
        "",
        "## Extracted entities",
    ]
    for role in ROLES:
        doc = pipeline_result["documents"][role]
        lines.append(f"- {role}: {len(doc['entities'])} entities; "
                     f"{pipeline_result['parser_runs'][role]['source_unit_count']} parser units")
    lines += ["", "## Candidate observations"]
    for finding in findings:
        lines.append(f"- `{finding['id']}`: {finding['upstream_role']} → "
                     f"{finding['downstream_role']}; {finding['match_type']}; "
                     f"target text: {finding['target_comparison']}; "
                     f"review: {annotations.get(finding['id'], 'NOT_REVIEWED')}. "
                     "Source fingerprints and locators are retained in the JSON result.")
    lines += ["", "No verified parent-child relationships or legal applicability are asserted."]
    return "\n".join(lines) + "\n"
