"""PI-PERF-TAX-01: deterministic performance taxonomy and measurement context.

This domain contract separates a planning entity, indicator definition and a
measurement occurrence. It never turns nearby PDF text into verified target,
realization, table-column linkage, policy coherence or legal applicability.

No database writes, model calls, synthetic-only locks or production admission.
Real-document interpretation still requires independently grounded source
cells, authenticated reviewer decisions and the appropriate rights checks.
"""

from __future__ import annotations

from decimal import Decimal, InvalidOperation
from hashlib import sha256
import json
import re
import unicodedata

from .normalize import KINDS as EXISTING_M2_KINDS


SCHEMA_VERSION = "pi.performance-taxonomy.v1"
STRUCTURAL_KINDS = (
    "VISI", "MISI", "TUJUAN", "SASARAN", "PRIORITAS", "PROGRAM", "KEGIATAN", "SUBKEGIATAN"
)
INDICATOR_LEVELS = (
    "MAKRO_DAERAH", "TUJUAN", "SASARAN", "PROGRAM", "KEGIATAN", "SUBKEGIATAN"
)
INDICATOR_NATURES = ("DAMPAK", "HASIL", "KELUARAN", "PROSES", "UNDETERMINED")
DIRECTIONS = ("HIGHER_BETTER", "LOWER_BETTER", "WITHIN_RANGE", "DESCRIPTIVE", "UNDETERMINED")
MEASURE_ROLES = ("BASELINE", "TARGET", "REALIZATION", "ACHIEVEMENT", "UNRESOLVED")
DOCUMENT_ROLES = ("RPJMD", "RKPD", "RENSTRA", "RENJA", "RPJPD", "OTHER")

# Cross-document relation types are distinct from the existing G2 synthetic
# intervention graph. They state candidate lineage, not a legal judgement.
LINEAGE_TYPES = (
    "SAME_INDICATOR", "DERIVED_FROM", "CONTRIBUTES_TO", "TARGET_FOR_YEAR",
    "REALIZATION_FOR_PERIOD", "REVISED_BY"
)


def _normal(text: str) -> str:
    text = unicodedata.normalize("NFKD", text).casefold()
    return " ".join(re.sub(r"[^a-z0-9]+", " ", text).split())


def _hash(payload) -> str:
    return sha256(json.dumps(payload, sort_keys=True, ensure_ascii=False,
                             separators=(",", ":")).encode("utf-8")).hexdigest()


def taxonomy_registry() -> dict:
    """A stable, reusable vocabulary; no local jurisdiction's facts hardcoded."""
    return {
        "schema": SCHEMA_VERSION,
        "structural_kinds": list(STRUCTURAL_KINDS),
        "m2_compatible_kinds": list(EXISTING_M2_KINDS),
        "indicator_levels": list(INDICATOR_LEVELS),
        "indicator_natures": list(INDICATOR_NATURES),
        "improvement_directions": list(DIRECTIONS),
        "measure_roles": list(MEASURE_ROLES),
        "document_roles": list(DOCUMENT_ROLES),
        "lineage_types": list(LINEAGE_TYPES),
        "indicator_definition_fields": [
            "canonical_name", "declared_aliases", "level", "nature", "unit",
            "formula", "direction", "scope", "owner", "definition_version"
        ],
        "measurement_fields": [
            "indicator_definition_id", "document_version_id", "parsing_run_id",
            "year", "period", "measure_role", "raw_value", "parsed_value",
            "row_header", "column_header_path", "table_id", "source_unit_id",
            "unit_fingerprint", "header_evidence", "context_status"
        ],
        "boundary": "TAXONOMY_IS_NOT_COLUMN_VERIFICATION_OR_POLICY_VERDICT",
    }


def indicator_identity(definition: dict) -> dict:
    """Require explicit definition identity; never accept prefix/fuzzy aliases."""
    name = definition.get("canonical_name")
    scope = definition.get("scope")
    version = definition.get("definition_version")
    level = definition.get("level")
    unit = definition.get("unit")
    nature = definition.get("nature", "UNDETERMINED")
    direction = definition.get("direction", "UNDETERMINED")
    aliases = definition.get("declared_aliases", [])
    if any(not isinstance(v, str) or not v.strip() or len(v) > 300
           for v in (name, scope, version, unit)):
        raise ValueError("TAXONOMY_DEFINITION_FIELDS_REQUIRED")
    if level not in INDICATOR_LEVELS or nature not in INDICATOR_NATURES or direction not in DIRECTIONS:
        raise ValueError("TAXONOMY_INVALID_INDICATOR_CLASSIFICATION")
    if not isinstance(aliases, list) or len(aliases) > 30 or any(
        not isinstance(a, str) or not a.strip() or len(a) > 300 for a in aliases
    ):
        raise ValueError("TAXONOMY_INVALID_ALIASES")
    normalized_names = [_normal(value) for value in [name, *aliases]]
    if len(set(normalized_names)) != len(normalized_names):
        raise ValueError("TAXONOMY_DUPLICATE_ALIAS")
    ident = _hash([_normal(scope), _normal(name), version, level, _normal(unit)])[:32]
    return {
        "id": ident, "canonical_name": name.strip(), "aliases": aliases,
        "level": level, "nature": nature, "unit": unit.strip(),
        "direction": direction, "scope": scope.strip(), "definition_version": version.strip(),
        "formula": definition.get("formula") or None,
        "owner": definition.get("owner") or None,
        "verification": "DEFINITION_DECLARED_NOT_INSTITUTIONALLY_VERIFIED",
    }


def resolve_indicator_label(label: str, definitions: list[dict]) -> dict:
    """Exact canonical/explicit-alias equality, with ambiguity refused."""
    if not isinstance(label, str) or not label.strip() or len(label) > 300:
        raise ValueError("TAXONOMY_INDICATOR_LABEL_REQUIRED")
    if not isinstance(definitions, list) or len(definitions) > 500:
        raise ValueError("TAXONOMY_DEFINITIONS_BOUNDARY")
    matches = []
    for raw in definitions:
        definition = indicator_identity(raw)
        if _normal(label) in {_normal(definition["canonical_name"]),
                              *(_normal(alias) for alias in definition["aliases"])}:
            matches.append(definition)
    if not matches:
        return {"status": "INDICATOR_UNRESOLVED", "definition_id": None}
    if len({definition["id"] for definition in matches}) != 1:
        return {"status": "AMBIGUOUS_INDICATOR_DEFINITION", "definition_id": None}
    return {"status": "EXPLICIT_LABEL_CANDIDATE", "definition_id": matches[0]["id"]}


def _header_role(path: list[str]) -> tuple[str, int | None, str]:
    """Interpret only scoped column header texts, never a whole PDF excerpt."""
    text = " ".join(path).casefold()
    categories = []
    for role, patterns in (
        ("TARGET", (r"\btarget\b",)),
        ("REALIZATION", (r"\brealisasi\b", r"\brealisir\b")),
        ("ACHIEVEMENT", (r"\bcapaian\b",)),
        ("BASELINE", (r"\bbaseline\b", r"\bkondisi awal\b")),
    ):
        if any(re.search(pattern, text) for pattern in patterns):
            categories.append(role)
    years = sorted(set(int(value) for value in re.findall(r"\b(?:19|20|21)\d{2}\b", text)))
    if len(categories) != 1 or len(years) > 1:
        return "UNRESOLVED", None, "AMBIGUOUS_OR_MISSING_COLUMN_ROLE_OR_YEAR"
    return categories[0], years[0] if years else None, "COLUMN_HEADER_ROLE_CANDIDATE"


def parse_measure_value(raw: str) -> dict:
    """Locale-aware simple decimal/range; never compute percentages or verdicts."""
    if not isinstance(raw, str) or not raw.strip() or len(raw) > 100:
        raise ValueError("TAXONOMY_RAW_VALUE_REQUIRED")
    compact = re.sub(r"\s+", "", raw.strip()).replace("–", "-").replace("—", "-")
    percent = compact.endswith("%")
    number_part = compact[:-1] if percent else compact
    values = number_part.split("-")
    if len(values) not in (1, 2) or any(not re.fullmatch(r"\d+(?:[,.]\d+)?", v) for v in values):
        return {"shape": "UNRESOLVED", "raw": raw, "parsed": None}
    try:
        decimals = [Decimal(v.replace(",", ".")) for v in values]
    except InvalidOperation:
        return {"shape": "UNRESOLVED", "raw": raw, "parsed": None}
    if len(decimals) == 2 and decimals[0] > decimals[1]:
        return {"shape": "UNRESOLVED", "raw": raw, "parsed": None}
    return {
        "shape": "PERCENT" if percent else "RANGE" if len(decimals) == 2 else "SCALAR",
        "raw": raw, "parsed": [str(v) for v in decimals],
    }


def classify_measurement_candidate(record: dict) -> dict:
    """Return context gates without claiming a column or policy was verified.

    Header evidence MUST be a scoped original column-header path, not a
    concatenated table text block. The caller's header assertion remains
    unverified until a layout-aware parser/reviewer links the cells.
    """
    if not isinstance(record, dict):
        raise ValueError("TAXONOMY_RECORD_REQUIRED")
    required = ("indicator_label", "raw_value", "document_role", "source_unit_id", "unit_fingerprint")
    if any(not isinstance(record.get(k), str) or not record[k].strip() for k in required):
        raise ValueError("TAXONOMY_REQUIRED_FIELDS_MISSING")
    if record["document_role"] not in DOCUMENT_ROLES:
        raise ValueError("TAXONOMY_INVALID_DOCUMENT_ROLE")
    if not re.fullmatch(r"[0-9a-f]{64}", record["unit_fingerprint"]):
        raise ValueError("TAXONOMY_INVALID_SOURCE_FINGERPRINT")
    header = record.get("column_header_path") or []
    if not isinstance(header, list) or len(header) > 8 or any(
        not isinstance(item, str) or not item.strip() or len(item) > 180 for item in header
    ):
        raise ValueError("TAXONOMY_INVALID_HEADER_PATH")
    value = parse_measure_value(record["raw_value"])
    role, header_year, reason = _header_role(header)
    requested_year = record.get("comparison_year")
    if requested_year is not None and (not isinstance(requested_year, int) or not 1900 <= requested_year <= 2100):
        raise ValueError("TAXONOMY_INVALID_COMPARISON_YEAR")
    header_link = record.get("header_cell_link_verified") is True
    status = "CONTEXT_REQUIRED"
    if value["shape"] == "UNRESOLVED":
        status = "VALUE_SHAPE_UNRESOLVED"
    elif role == "UNRESOLVED":
        status = "COLUMN_ROLE_UNRESOLVED"
    elif header_year is None or (requested_year is not None and requested_year != header_year):
        status = "YEAR_CONTEXT_UNRESOLVED"
    elif not header_link:
        status = "HEADER_CELL_LINK_UNVERIFIED"
    else:
        # Caller confirmation is not independent proof of a physical link.
        status = "STRUCTURED_MEASUREMENT_CANDIDATE"
    return {
        "schema": SCHEMA_VERSION, "indicator_label": record["indicator_label"],
        "document_role": record["document_role"], "source_unit_id": record["source_unit_id"],
        "unit_fingerprint": record["unit_fingerprint"],
        "measure_role_candidate": role, "header_year_candidate": header_year,
        "comparison_year": requested_year, "raw_value": value["raw"],
        "value_shape": value["shape"], "parsed_value": value["parsed"],
        "column_header_path": header, "context_status": status,
        "header_reason": reason, "physical_header_link_verification": "NOT_INDEPENDENTLY_VERIFIED",
        "substantive_alignment": "NOT_ASSESSED", "reviewer_decision": "PENDING",
        "production_admission": "DENIED",
    }
