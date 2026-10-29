"""Read-only PI-PERF-TAX-01 API over persisted provenance-bearing SourceUnits."""

from __future__ import annotations

from uuid import UUID

from fastapi import APIRouter, Depends, HTTPException
from pydantic import BaseModel, Field
from sqlalchemy.orm import Session

from app.db import get_db
from app.models import Document, DocumentVersion, ParsingRun, SourceUnit

from .performance_taxonomy import (
    taxonomy_registry, classify_measurement_candidate,
)


router = APIRouter(prefix="/api/performance-taxonomy", tags=["Performance Taxonomy"])


class MeasurementProbe(BaseModel):
    parsing_run_id: UUID
    source_unit_id: UUID
    unit_fingerprint: str = Field(min_length=64, max_length=64)
    indicator_label: str = Field(min_length=1, max_length=300)
    raw_value: str = Field(min_length=1, max_length=100)
    comparison_year: int | None = Field(default=None, ge=1900, le=2100)
    # Optional actual source units for column headings; user-supplied header
    # text and user-asserted verification are intentionally not accepted.
    header_source_unit_ids: list[UUID] = Field(default_factory=list, max_length=8)


class MeasurementBatch(BaseModel):
    items: list[MeasurementProbe] = Field(min_length=1, max_length=30)


def _cell_location(unit: SourceUnit):
    """Return stable column coordinates only for parser-structured cells."""
    if unit.unit_type != "TABLE_CELL" or not isinstance(unit.locator, dict):
        return None
    loc = unit.locator
    if loc.get("format") == "xlsx":
        return ("xlsx", loc.get("sheet_name"), loc.get("sheet_index"),
                loc.get("column"), loc.get("row"))
    if loc.get("format") == "docx":
        return ("docx", loc.get("table_index"), None,
                loc.get("column_index"), loc.get("row_index"))
    return None


def _verified_header_link(value_unit: SourceUnit, header_units: list[SourceUnit]) -> bool:
    value = _cell_location(value_unit)
    if not value or not header_units:
        return False
    group, table, scope, col, row = value
    if not isinstance(row, int) or not isinstance(col, int):
        return False
    for item in header_units:
        header = _cell_location(item)
        if header is None or header[:4] != (group, table, scope, col):
            return False
        if not isinstance(header[4], int) or header[4] >= row:
            return False
    return True


@router.get("/registry")
def read_performance_taxonomy_registry():
    return taxonomy_registry()


@router.post("/classify-measurements")
def classify_persisted_measurements(payload: MeasurementBatch, db: Session = Depends(get_db)):
    """Bind value/header candidates to existing SourceUnit fingerprints.

    No reparse, edits, persist, adjudication, document upload or production use.
    A text-block candidate without independently reconstructible table cells
    must remain unresolved even if a number appears next to an indicator.
    """
    findings = []
    for item in payload.items:
        run = db.get(ParsingRun, item.parsing_run_id)
        unit = db.get(SourceUnit, item.source_unit_id)
        if not run or run.status != "SUCCEEDED" or not unit or unit.parsing_run_id != run.id:
            raise HTTPException(422, "TAXONOMY_SOURCE_RUN_NOT_AVAILABLE")
        if unit.unit_fingerprint != item.unit_fingerprint:
            raise HTTPException(409, "TAXONOMY_SOURCE_FINGERPRINT_MISMATCH")
        version = db.get(DocumentVersion, run.document_version_id)
        document = db.get(Document, version.document_id) if version else None
        if not version or not document or unit.document_version_id != version.id:
            raise HTTPException(409, "TAXONOMY_DOCUMENT_VERSION_MISMATCH")
        if item.raw_value not in (unit.raw_text or "") and item.raw_value not in (unit.normalized_text or ""):
            raise HTTPException(422, "TAXONOMY_VALUE_NOT_PRESENT_IN_SOURCE_UNIT")

        header_units = []
        for header_id in item.header_source_unit_ids:
            header = db.get(SourceUnit, header_id)
            if not header or header.parsing_run_id != run.id or header.document_version_id != version.id:
                raise HTTPException(422, "TAXONOMY_HEADER_SOURCE_MISMATCH")
            header_units.append(header)
        linked = _verified_header_link(unit, header_units)
        result = classify_measurement_candidate({
            "indicator_label": item.indicator_label,
            "raw_value": item.raw_value,
            "document_role": document.doc_type if document.doc_type in ("RPJMD", "RKPD", "RENSTRA", "RENJA", "RPJPD") else "OTHER",
            "source_unit_id": str(unit.id),
            "unit_fingerprint": unit.unit_fingerprint,
            "comparison_year": item.comparison_year,
            "column_header_path": [h.normalized_text or h.raw_text or "" for h in header_units] if linked else [],
            "header_cell_link_verified": linked,
        })
        result["document_version_id"] = str(version.id)
        result["parsing_run_id"] = str(run.id)
        result["header_source_unit_ids"] = [str(h.id) for h in header_units]
        result["source_page"] = unit.page_start
        result["source_kind"] = unit.unit_type
        findings.append(result)
    return {"schema": "pi.performance-taxonomy.measurements.v1",
            "status": "PRE_REVIEW_TAXONOMY_CANDIDATES", "findings": findings,
            "production_admission": "DENIED"}
