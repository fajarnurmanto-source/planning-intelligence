"""
Deterministic document parsing for Planning Intelligence V0.1A.4.

This module deliberately does not use any generative AI provider.
It converts immutable document versions into reproducible, addressable source
units with format-specific locators and stable hashes.

Supported in V0.1A.4:
- PDF  -> PAGE + TEXT_BLOCK
- DOCX -> PARAGRAPH + TABLE + TABLE_ROW + TABLE_CELL
- XLSX -> SHEET + TABLE_ROW + TABLE_CELL

Semantic interpretation (Tujuan/Sasaran/Indikator/etc.) belongs to later
ontology/entity-extraction stages and is intentionally not performed here.
"""

from __future__ import annotations

import hashlib
import json
import re
from dataclasses import dataclass, asdict
from datetime import date, datetime
from pathlib import Path
from typing import Any

import fitz  # PyMuPDF
from docx import Document as DocxDocument
from docx.table import Table as DocxTable
from docx.text.paragraph import Paragraph as DocxParagraph
from docx.oxml.table import CT_Tbl
from docx.oxml.text.paragraph import CT_P
from openpyxl import load_workbook


PARSER_NAME = "planning-intelligence-deterministic"
PARSER_VERSION = "0.1A.4"

DEFAULT_CONFIG: dict[str, Any] = {
    "normalize_whitespace": True,
    "pdf_text_mode": "blocks",
    "pdf_sort": True,
    "include_page_units": True,
    "xlsx_include_formula_text": True,
    "max_source_units": 250000,
}


def canonical_json(value: Any) -> str:
    return json.dumps(
        value,
        sort_keys=True,
        ensure_ascii=False,
        separators=(",", ":"),
        default=str,
    )


def sha256_text(value: str) -> str:
    return hashlib.sha256(value.encode("utf-8")).hexdigest()


def config_sha256(config: dict[str, Any]) -> str:
    return sha256_text(canonical_json(config))


def build_run_fingerprint(input_sha256: str, config: dict[str, Any]) -> str:
    return sha256_text(
        canonical_json(
            {
                "input_sha256": input_sha256,
                "parser_name": PARSER_NAME,
                "parser_version": PARSER_VERSION,
                "parser_config_sha256": config_sha256(config),
            }
        )
    )


def _database_safe_text(value: Any) -> Any:
    """Replace PostgreSQL-prohibited NUL characters without altering source files.

    This also covers structured locator values that may be persisted as JSONB.
    U+FFFD is intentionally visible to reviewers; removing NUL silently could
    concatenate labels or numbers and create false evidence matches.
    """
    if isinstance(value, str):
        return value.replace("\x00", "\uFFFD")
    if isinstance(value, list):
        return [_database_safe_text(item) for item in value]
    if isinstance(value, dict):
        return {
            _database_safe_text(key): _database_safe_text(item)
            for key, item in value.items()
        }
    return value


def normalize_text(value: str | None, collapse_whitespace: bool = True) -> str:
    if value is None:
        return ""
    text = value.replace("\r\n", "\n").replace("\r", "\n").strip()
    if collapse_whitespace:
        text = re.sub(r"\s+", " ", text)
    return text


def stable_scalar(value: Any) -> str:
    if value is None:
        return ""
    if isinstance(value, (datetime, date)):
        return value.isoformat()
    if isinstance(value, bool):
        return "TRUE" if value else "FALSE"
    return str(value)


@dataclass
class ParsedUnit:
    unit_type: str
    sequence_index: int
    parent_sequence_index: int | None
    ordinal_in_parent: int | None
    page_start: int | None
    page_end: int | None
    section_path: list
    locator: dict
    structural_path: str | None
    raw_text: str | None
    normalized_text: str | None
    content_sha256: str
    unit_fingerprint: str


def _unit(
    *,
    input_sha256: str,
    config: dict[str, Any],
    unit_type: str,
    sequence_index: int,
    parent_sequence_index: int | None = None,
    ordinal_in_parent: int | None = None,
    page_start: int | None = None,
    page_end: int | None = None,
    section_path: list | None = None,
    locator: dict | None = None,
    structural_path: str | None = None,
    raw_text: str | None = None,
) -> ParsedUnit:
    section_path = _database_safe_text(section_path or [])
    locator = _database_safe_text(locator or {})
    structural_path = _database_safe_text(structural_path)
    nul_count = raw_text.count("\x00") if raw_text else 0
    raw_text = _database_safe_text(raw_text)
    if nul_count:
        locator = {**locator, "nul_text_replacements": nul_count}
    normalized = normalize_text(raw_text, config.get("normalize_whitespace", True))
    content_basis = normalized or canonical_json(
        {
            "unit_type": unit_type,
            "locator": locator,
            "structural_path": structural_path,
        }
    )
    content_hash = sha256_text(content_basis)
    fingerprint = sha256_text(
        canonical_json(
            {
                "input_sha256": input_sha256,
                "parser_name": PARSER_NAME,
                "parser_version": PARSER_VERSION,
                "unit_type": unit_type,
                "sequence_index": sequence_index,
                "parent_sequence_index": parent_sequence_index,
                "ordinal_in_parent": ordinal_in_parent,
                "page_start": page_start,
                "page_end": page_end,
                "section_path": section_path,
                "locator": locator,
                "structural_path": structural_path,
                "content_sha256": content_hash,
            }
        )
    )
    return ParsedUnit(
        unit_type=unit_type,
        sequence_index=sequence_index,
        parent_sequence_index=parent_sequence_index,
        ordinal_in_parent=ordinal_in_parent,
        page_start=page_start,
        page_end=page_end,
        section_path=section_path,
        locator=locator,
        structural_path=structural_path,
        raw_text=raw_text,
        normalized_text=normalized if normalized else None,
        content_sha256=content_hash,
        unit_fingerprint=fingerprint,
    )


def _guard(units: list[ParsedUnit], config: dict[str, Any]) -> None:
    maximum = int(config.get("max_source_units", 250000))
    if len(units) > maximum:
        raise ValueError(
            f"Source-unit safety limit exceeded: {len(units)} > {maximum}"
        )


def parse_pdf(path: Path, input_sha256: str, config: dict[str, Any]) -> tuple[list[ParsedUnit], int]:
    units: list[ParsedUnit] = []
    sequence = 0
    with fitz.open(path) as doc:
        page_count = doc.page_count
        for page_index in range(doc.page_count):
            page_number = page_index + 1
            page = doc.load_page(page_index)
            page_parent = None

            if config.get("include_page_units", True):
                page_unit = _unit(
                    input_sha256=input_sha256,
                    config=config,
                    unit_type="PAGE",
                    sequence_index=sequence,
                    page_start=page_number,
                    page_end=page_number,
                    locator={"format": "pdf", "page": page_number},
                    structural_path=f"pdf/page[{page_number}]",
                )
                units.append(page_unit)
                page_parent = sequence
                sequence += 1

            blocks = page.get_text(
                "blocks",
                sort=bool(config.get("pdf_sort", True)),
            )
            text_ordinal = 0
            for block in blocks:
                if len(block) < 5:
                    continue
                x0, y0, x1, y1, text = block[:5]
                raw = str(text or "").replace("\r\n", "\n").replace("\r", "\n").strip()
                if not normalize_text(raw, config.get("normalize_whitespace", True)):
                    continue
                text_ordinal += 1
                locator = {
                    "format": "pdf",
                    "page": page_number,
                    "bbox": [round(float(x0), 3), round(float(y0), 3), round(float(x1), 3), round(float(y1), 3)],
                    "block_ordinal": text_ordinal,
                }
                units.append(
                    _unit(
                        input_sha256=input_sha256,
                        config=config,
                        unit_type="TEXT_BLOCK",
                        sequence_index=sequence,
                        parent_sequence_index=page_parent,
                        ordinal_in_parent=text_ordinal,
                        page_start=page_number,
                        page_end=page_number,
                        locator=locator,
                        structural_path=f"pdf/page[{page_number}]/text_block[{text_ordinal}]",
                        raw_text=raw,
                    )
                )
                sequence += 1
                _guard(units, config)

    return units, page_count


def _iter_docx_blocks(document: DocxDocument):
    body = document.element.body
    for child in body.iterchildren():
        if isinstance(child, CT_P):
            yield DocxParagraph(child, document)
        elif isinstance(child, CT_Tbl):
            yield DocxTable(child, document)


def parse_docx(path: Path, input_sha256: str, config: dict[str, Any]) -> tuple[list[ParsedUnit], int | None]:
    doc = DocxDocument(path)
    units: list[ParsedUnit] = []
    sequence = 0
    block_index = 0
    paragraph_index = 0
    table_index = 0

    for block in _iter_docx_blocks(doc):
        block_index += 1
        if isinstance(block, DocxParagraph):
            raw = block.text or ""
            if not normalize_text(raw, config.get("normalize_whitespace", True)):
                continue
            paragraph_index += 1
            units.append(
                _unit(
                    input_sha256=input_sha256,
                    config=config,
                    unit_type="PARAGRAPH",
                    sequence_index=sequence,
                    ordinal_in_parent=paragraph_index,
                    locator={
                        "format": "docx",
                        "block_index": block_index,
                        "paragraph_index": paragraph_index,
                        "style": block.style.name if block.style else None,
                    },
                    structural_path=f"docx/body/paragraph[{paragraph_index}]",
                    raw_text=raw,
                )
            )
            sequence += 1

        elif isinstance(block, DocxTable):
            table_index += 1
            table_text = "\n".join(
                " | ".join(normalize_text(cell.text) for cell in row.cells)
                for row in block.rows
            )
            table_seq = sequence
            units.append(
                _unit(
                    input_sha256=input_sha256,
                    config=config,
                    unit_type="TABLE",
                    sequence_index=sequence,
                    ordinal_in_parent=table_index,
                    locator={
                        "format": "docx",
                        "block_index": block_index,
                        "table_index": table_index,
                    },
                    structural_path=f"docx/body/table[{table_index}]",
                    raw_text=table_text,
                )
            )
            sequence += 1

            for row_index, row in enumerate(block.rows, start=1):
                row_text = " | ".join(normalize_text(cell.text) for cell in row.cells)
                row_seq = sequence
                units.append(
                    _unit(
                        input_sha256=input_sha256,
                        config=config,
                        unit_type="TABLE_ROW",
                        sequence_index=sequence,
                        parent_sequence_index=table_seq,
                        ordinal_in_parent=row_index,
                        locator={
                            "format": "docx",
                            "table_index": table_index,
                            "row_index": row_index,
                        },
                        structural_path=f"docx/body/table[{table_index}]/row[{row_index}]",
                        raw_text=row_text,
                    )
                )
                sequence += 1

                for col_index, cell in enumerate(row.cells, start=1):
                    raw = cell.text or ""
                    units.append(
                        _unit(
                            input_sha256=input_sha256,
                            config=config,
                            unit_type="TABLE_CELL",
                            sequence_index=sequence,
                            parent_sequence_index=row_seq,
                            ordinal_in_parent=col_index,
                            locator={
                                "format": "docx",
                                "table_index": table_index,
                                "row_index": row_index,
                                "column_index": col_index,
                            },
                            structural_path=(
                                f"docx/body/table[{table_index}]/row[{row_index}]/cell[{col_index}]"
                            ),
                            raw_text=raw,
                        )
                    )
                    sequence += 1
                    _guard(units, config)

        _guard(units, config)

    # DOCX does not expose reliable final rendered page numbers without a layout engine.
    return units, None


def parse_xlsx(path: Path, input_sha256: str, config: dict[str, Any]) -> tuple[list[ParsedUnit], int | None]:
    wb = load_workbook(path, read_only=False, data_only=False)
    units: list[ParsedUnit] = []
    sequence = 0

    try:
        for sheet_index, ws in enumerate(wb.worksheets, start=1):
            sheet_seq = sequence
            units.append(
                _unit(
                    input_sha256=input_sha256,
                    config=config,
                    unit_type="SHEET",
                    sequence_index=sequence,
                    ordinal_in_parent=sheet_index,
                    locator={
                        "format": "xlsx",
                        "sheet_index": sheet_index,
                        "sheet_name": ws.title,
                    },
                    structural_path=f"xlsx/sheet[{sheet_index}:{ws.title}]",
                    raw_text=ws.title,
                )
            )
            sequence += 1

            for row_index, row in enumerate(ws.iter_rows(), start=1):
                nonempty = [cell for cell in row if cell.value is not None]
                if not nonempty:
                    continue

                row_text = " | ".join(
                    f"{cell.coordinate}={stable_scalar(cell.value)}" for cell in nonempty
                )
                row_seq = sequence
                units.append(
                    _unit(
                        input_sha256=input_sha256,
                        config=config,
                        unit_type="TABLE_ROW",
                        sequence_index=sequence,
                        parent_sequence_index=sheet_seq,
                        ordinal_in_parent=row_index,
                        locator={
                            "format": "xlsx",
                            "sheet_index": sheet_index,
                            "sheet_name": ws.title,
                            "row": row_index,
                        },
                        structural_path=f"xlsx/sheet[{sheet_index}:{ws.title}]/row[{row_index}]",
                        raw_text=row_text,
                    )
                )
                sequence += 1

                for cell in nonempty:
                    units.append(
                        _unit(
                            input_sha256=input_sha256,
                            config=config,
                            unit_type="TABLE_CELL",
                            sequence_index=sequence,
                            parent_sequence_index=row_seq,
                            ordinal_in_parent=cell.column,
                            locator={
                                "format": "xlsx",
                                "sheet_index": sheet_index,
                                "sheet_name": ws.title,
                                "row": cell.row,
                                "column": cell.column,
                                "coordinate": cell.coordinate,
                                "data_type": cell.data_type,
                            },
                            structural_path=f"xlsx/sheet[{sheet_index}:{ws.title}]/cell[{cell.coordinate}]",
                            raw_text=stable_scalar(cell.value),
                        )
                    )
                    sequence += 1
                    _guard(units, config)

            _guard(units, config)
    finally:
        wb.close()

    return units, None


def parse_document(
    path: Path,
    *,
    input_sha256: str,
    extension: str | None,
    config: dict[str, Any] | None = None,
) -> dict[str, Any]:
    config = dict(DEFAULT_CONFIG if config is None else config)
    ext = (extension or path.suffix).lower().lstrip(".")

    if ext == "pdf":
        units, page_count = parse_pdf(path, input_sha256, config)
    elif ext == "docx":
        units, page_count = parse_docx(path, input_sha256, config)
    elif ext == "xlsx":
        units, page_count = parse_xlsx(path, input_sha256, config)
    else:
        raise ValueError(
            f"Unsupported deterministic parser format: {ext or '(none)'}. "
            "Supported: pdf, docx, xlsx."
        )

    _guard(units, config)
    ordered_fingerprints = [u.unit_fingerprint for u in units]
    result_hash = sha256_text(canonical_json(ordered_fingerprints))

    return {
        "parser_name": PARSER_NAME,
        "parser_version": PARSER_VERSION,
        "config": config,
        "parser_config_sha256": config_sha256(config),
        "run_fingerprint": build_run_fingerprint(input_sha256, config),
        "page_count": page_count,
        "source_unit_count": len(units),
        "result_sha256": result_hash,
        "units": units,
    }
