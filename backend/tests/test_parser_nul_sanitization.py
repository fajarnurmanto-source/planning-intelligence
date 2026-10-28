"""Regression for real-world PDFs whose extracted text contains a NUL byte."""

from dataclasses import asdict
import json

from app.parser import DEFAULT_CONFIG, _unit, sha256_text


def test_nul_in_extracted_pdf_text_is_database_safe_and_traceable():
    unit = _unit(
        input_sha256="a" * 64,
        config=dict(DEFAULT_CONFIG),
        unit_type="TEXT_BLOCK",
        sequence_index=1,
        page_start=17,
        page_end=17,
        locator={"format": "pdf", "page": 17, "note": "bbox\\x00marker".replace("\\x00", "\x00")},
        raw_text="Kemiskinan\\x00 5,84".replace("\\x00", "\x00"),
    )
    assert unit.raw_text == "Kemiskinan\ufffd 5,84"
    assert unit.normalized_text == "Kemiskinan\ufffd 5,84"
    assert unit.locator["nul_text_replacements"] == 1
    assert unit.locator["note"] == "bbox\ufffdmarker"
    assert unit.content_sha256 == sha256_text(unit.normalized_text)
    assert "\\u0000" not in json.dumps(asdict(unit), ensure_ascii=True)
    again = _unit(
        input_sha256="a" * 64,
        config=dict(DEFAULT_CONFIG),
        unit_type="TEXT_BLOCK",
        sequence_index=1,
        page_start=17,
        page_end=17,
        locator={"format": "pdf", "page": 17, "note": "bbox\\x00marker".replace("\\x00", "\x00")},
        raw_text="Kemiskinan\\x00 5,84".replace("\\x00", "\x00"),
    )
    assert unit.unit_fingerprint == again.unit_fingerprint
