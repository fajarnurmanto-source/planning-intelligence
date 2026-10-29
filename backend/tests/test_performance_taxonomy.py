"""PI-PERF-TAX-01 deterministic classification guards."""

import pytest

from app.planning_entities.performance_taxonomy import (
    taxonomy_registry, indicator_identity, resolve_indicator_label,
    classify_measurement_candidate, parse_measure_value,
)


def definition(name="Indeks Keterpaduan Pembangunan Infrastruktur", aliases=None):
    return {"canonical_name": name, "declared_aliases": aliases or [],
            "scope": "Kota Yogyakarta", "definition_version": "2025-2029",
            "level": "SASARAN", "nature": "HASIL", "unit": "Indeks",
            "direction": "HIGHER_BETTER"}


def record(header=None, value="87,01"):
    return {"indicator_label": "Indeks Keterpaduan Pembangunan Infrastruktur",
            "document_role": "RKPD", "comparison_year": 2026,
            "source_unit_id": "source-217", "unit_fingerprint": "a" * 64,
            "raw_value": value, "column_header_path": header or []}


def test_registry_reuses_m2_kinds_and_separates_measurement():
    r = taxonomy_registry()
    assert "PROGRAM" in r["m2_compatible_kinds"]
    assert "measure_role" in r["measurement_fields"]
    assert "REALIZATION" in r["measure_roles"]


def test_prefix_is_not_a_safe_alias():
    registered = [definition()]
    assert resolve_indicator_label("Indeks Keterpaduan Pembangunan", registered)["status"] == "INDICATOR_UNRESOLVED"
    registered = [definition(aliases=["Indeks Keterpaduan Pembangunan"])]
    assert resolve_indicator_label("Indeks Keterpaduan Pembangunan", registered)["status"] == "EXPLICIT_LABEL_CANDIDATE"


def test_cross_definition_ambiguous_alias_rejected():
    a = definition(aliases=["Indeks X"])
    b = definition("Indeks Lain", aliases=["Indeks X"])
    assert resolve_indicator_label("Indeks X", [a, b])["status"] == "AMBIGUOUS_INDICATOR_DEFINITION"


def test_table_excerpt_does_not_confirm_measure_role():
    mixed = classify_measurement_candidate(record(["Target 2026 Realisasi TW II Capaian"]))
    assert mixed["context_status"] == "COLUMN_ROLE_UNRESOLVED"
    assert mixed["measure_role_candidate"] == "UNRESOLVED"
    assert mixed["substantive_alignment"] == "NOT_ASSESSED"


def test_header_link_and_year_must_be_grounded():
    without_link = classify_measurement_candidate(record(["Target 2026"]))
    assert without_link["context_status"] == "HEADER_CELL_LINK_UNVERIFIED"
    with_link = record(["Target 2026"])
    with_link["header_cell_link_verified"] = True
    assert classify_measurement_candidate(with_link)["context_status"] == "STRUCTURED_MEASUREMENT_CANDIDATE"
    with_link["comparison_year"] = 2025
    assert classify_measurement_candidate(with_link)["context_status"] == "YEAR_CONTEXT_UNRESOLVED"


def test_simple_numeric_shape_without_column_semantics():
    assert parse_measure_value("87,99 – 89,64")["parsed"] == ["87.99", "89.64"]
    assert parse_measure_value("0,381 – 0,446")["shape"] == "RANGE"
    assert parse_measure_value("168")["shape"] == "SCALAR"
    assert parse_measure_value("81,45%")["shape"] == "PERCENT"
    assert parse_measure_value("159 - 173 130,84")["shape"] == "UNRESOLVED"


def test_no_invalid_alias_or_unknown_level():
    with pytest.raises(ValueError):
        indicator_identity({**definition(), "level": "ILLEGAL"})
    with pytest.raises(ValueError):
        indicator_identity({**definition(), "declared_aliases": ["a", "a"]})
