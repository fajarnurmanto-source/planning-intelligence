"""M2 candidate: convert existing PI SourceUnits into typed planning entities.

No LLM, legal rule activation, data admission, database write, or causal inference.
Every emitted entity is tied to one original SourceUnit fingerprint and locator.
Unresolved hierarchy remains a candidate, never a verified coherence edge.
"""
from __future__ import annotations

from collections import defaultdict
from dataclasses import asdict, is_dataclass
from hashlib import sha256
import json
import re

SCHEMA = "pi.planning_entities.synthetic.v1"
KINDS = ("TUJUAN", "SASARAN", "PROGRAM", "KEGIATAN", "SUBKEGIATAN", "INDIKATOR")
PARENTS = {"SASARAN": "TUJUAN", "PROGRAM": "SASARAN", "KEGIATAN": "PROGRAM", "SUBKEGIATAN": "KEGIATAN"}
HEADING = re.compile(r"^\s*(TUJUAN|SASARAN|PROGRAM|KEGIATAN|SUB\s*KEGIATAN|INDIKATOR)\s*[:\-–]\s*(.+?)\s*$", re.I)
CODE = re.compile(r"^(?:\d{1,2}(?:\.\d{1,3}){1,8})$")
FIELD_LABELS = {
    "jenis": "kind", "jenis entitas": "kind", "level": "kind",
    "kode": "code", "kode program": "code", "kode kegiatan": "code",
    "uraian": "title", "nama": "title", "nomenklatur": "title", "nama program": "title",
    "indikator": "indicator", "indikator kinerja": "indicator",
    "target": "target", "target kinerja": "target",
    "tahun": "year", "periode": "year", "satuan": "unit",
    "pagu": "budget", "anggaran": "budget", "pagu indikatif": "budget",
    "opd": "owner", "perangkat daerah": "owner", "penanggung jawab": "owner",
}


def _digest(value):
    return sha256(json.dumps(value, ensure_ascii=False, sort_keys=True, separators=(",", ":")).encode()).hexdigest()


def _kind(value):
    return re.sub(r"\s+", "", value.upper())


def _as_unit_dict(unit):
    if is_dataclass(unit) and not isinstance(unit, type):
        return asdict(unit)
    if isinstance(unit, dict):
        return unit
    raise ValueError("M2_UNIT_DICT_OR_PARSEDUNIT_REQUIRED")


def _header(value):
    return FIELD_LABELS.get(re.sub(r"\s+", " ", value.casefold().strip()))


def _cell_text(unit):
    return str(unit.get("raw_text") or "").strip()


def _row_candidate(cells, mapping):
    values = {field: _cell_text(cells[col]) for field, col in mapping.items() if col in cells}
    kind = _kind(values.get("kind", ""))
    if kind not in KINDS or not values.get("title"):
        return None
    code = values.get("code") or None
    if code and (len(code) > 100 or not re.fullmatch(r"[A-Za-z0-9.\-/]+", code)):
        code = None  # ambiguous codes must not be accepted as canonical
    attrs = {k: v for k, v in values.items() if k in
             ("indicator", "target", "year", "unit", "budget", "owner") and v}
    if "year" in attrs and not re.fullmatch(r"(?:19|20|21)\d{2}", attrs["year"]):
        attrs.pop("year")
    return kind, values["title"], code, [cells[c] for c in sorted(cells)], attrs


def _source(unit, document_sha256):
    fingerprint = unit.get("unit_fingerprint")
    locator = unit.get("locator")
    if not isinstance(fingerprint, str) or not re.fullmatch(r"[0-9a-f]{64}", fingerprint):
        raise ValueError("M2_SOURCE_FINGERPRINT_REQUIRED")
    if not isinstance(locator, dict) or not locator:
        raise ValueError("M2_SOURCE_LOCATOR_REQUIRED")
    return {"document_sha256": document_sha256, "source_unit_id": str(unit.get("id", unit.get("sequence_index"))),
            "unit_fingerprint": fingerprint, "locator": locator,
            "excerpt": str(unit.get("raw_text") or "")[:2000]}


def normalize_source_units(units, *, document_sha256, document_role, rights_admission="SYNTHETIC_ONLY",
                           jurisdiction_id=None, planning_period_id=None, planning_scope=None):
    """Conservatively extract labeled headings and structured table rows.

    SourceUnit shape follows app.parser ParsedUnit (dict or ORM-mapped dict).
    Only synthetic intake is accepted; real-document admission is separate.
    """
    if rights_admission != "SYNTHETIC_ONLY":
        raise PermissionError("M2_RESEARCH_SYNTHETIC_ONLY")
    if not isinstance(document_sha256, str) or not re.fullmatch(r"[0-9a-f]{64}", document_sha256):
        raise ValueError("M2_DOCUMENT_SHA_REQUIRED")
    if document_role not in ("RPJMD", "RKPD", "RENSTRA", "RENJA"):
        raise ValueError("M2_DOCUMENT_ROLE_REQUIRED")
    for scope_value in (jurisdiction_id, planning_period_id, planning_scope):
        if scope_value is not None and (not isinstance(scope_value, str) or not scope_value.strip()
                                        or len(scope_value) > 200):
            raise ValueError("M2_INVALID_SCOPE")
    if not isinstance(units, list) or len(units) > 10000:
        raise ValueError("M2_UNIT_BOUNDARY")
    entities = []
    candidates = []
    table_rows = defaultdict(dict)
    for original in units:
        u = _as_unit_dict(original)
        raw = str(u.get("raw_text") or "")
        if len(raw) > 500000:
            raise ValueError("M2_UNIT_TEXT_BOUNDARY")
        typ = u.get("unit_type")
        loc = u.get("locator") or {}
        if typ == "TABLE_CELL" and isinstance(loc, dict) and loc.get("format") in ("xlsx", "docx"):
            if loc["format"] == "xlsx":
                group = ("xlsx", loc.get("sheet_name"), loc.get("sheet_index"))
                row, col = loc.get("row"), loc.get("column")
            else:
                group = ("docx", loc.get("table_index"))
                row, col = loc.get("row_index"), loc.get("column_index")
            if isinstance(row, int) and isinstance(col, int) and 1 <= row <= 50000 and 1 <= col <= 100:
                table_rows[(group, row)][col] = u
        elif typ in ("PARAGRAPH", "TEXT_BLOCK", "TEXT", "LIST_ITEM"):
            for line in raw.splitlines():
                match = HEADING.match(line)
                if match:
                    kind, title = _kind(match.group(1)), match.group(2).strip()
                    if title:
                        candidates.append((kind, title, None, [u], {}))
    grouped = defaultdict(list)
    for (group, row), cells in table_rows.items():
        grouped[group].append((row, cells))
    for group in sorted(grouped, key=str):
        header = None
        for row, cells in sorted(grouped[group]):
            labels = {field: col for col, u in sorted(cells.items())
                      if (field := _header(_cell_text(u))) is not None}
            if "kind" in labels and "title" in labels:
                header = labels
                continue
            if header:
                parsed = _row_candidate(cells, header)
                if parsed:
                    candidates.append(parsed)
                continue
            # Compatibility with R0 explicit-label rows, never infer a label from context.
            values = [_cell_text(c) for _, c in sorted(cells.items())]
            if not values:
                continue
            kind = _kind(values[0])
            if kind in KINDS and len(values) >= 2 and values[1]:
                code = next((v for v in values[1:3] if CODE.fullmatch(v)), None)
                title = next((v for v in values[1:] if v and v != code), "")
                if title:
                    candidates.append((kind, title, code, [cells[c] for c in sorted(cells)], {}))
    seen = set()
    for kind, title, code, evidence_units, attrs in candidates:
        sources = [_source(u, document_sha256) for u in evidence_units]
        fingerprints = [s["unit_fingerprint"] for s in sources]
        unique = (kind, title.casefold(), code, tuple(fingerprints), _digest(attrs))
        if unique in seen:
            continue
        seen.add(unique)
        eid = _digest([document_sha256, kind, title, code, fingerprints, attrs])[:32]
        entities.append({"id": eid, "kind": kind, "title": title[:500], "code": code,
                         "attributes": attrs,
                         "document_role": document_role, "evidence": sources,
                         "regulatory_references": [], "regulatory_applicability": "NOT_ASSESSED",
                         "extraction_status": "EXPLICIT_LABEL", "review_required": True})
    # Deliberately no inferred parent edges: a document's order does not prove hierarchy.
    return {"schema": SCHEMA, "rights_admission": rights_admission, "production_admission": "DENIED",
            "document_sha256": document_sha256, "document_role": document_role,
            "jurisdiction_id": jurisdiction_id, "planning_period_id": planning_period_id,
            "planning_scope": planning_scope,
            "entities": entities, "relationships": [],
            "unresolved_parent_candidates": [e["id"] for e in entities if e["kind"] in PARENTS],
            "metrics": {"entities": len(entities), "verified_relationships": 0,
                        "structured_records": sum(bool(e["attributes"]) for e in entities),
                        "unresolved_parent_candidates": sum(e["kind"] in PARENTS for e in entities)}}
