"""Fast, standalone deterministic checks for scoped evidence scoring."""

from app.evidence_search import anchor_token, best_candidate, evaluate_excerpt


def test_ambiguous_numbers_remain_candidates_not_policy_verdict():
    candidate = {
        "id": "unit-a", "page_start": 21, "unit_fingerprint": "f" * 64,
        "excerpt": "15 Crime Rate 159 - 173 130,84 168 81,45 151,52% Sangat",
    }
    result = best_candidate("Crime Rate", "168-182", [candidate])
    assert result["comparison_status"] == "INDICATOR_TEXT_FOUND"
    assert result["comparison_page"] == 21
    assert "substantive_policy_alignment" not in result


def test_target_near_label_is_not_column_verification():
    candidate = {
        "id": "unit-b", "page_start": 156, "unit_fingerprint": "e" * 64,
        "excerpt": "Target 2026 Realisasi TW II Indeks Keberdayaan Masyarakat 87,99 - 89,64 89,59",
    }
    result = best_candidate("Indeks Keberdayaan Masyarakat", "87,99", [candidate])
    assert result["comparison_status"] == "TARGET_TEXT_NEAR_LABEL"
    assert result["comparison_source_unit_id"] == "unit-b"
    assert "year_column_verified" not in result


def test_no_match_and_normalization():
    assert anchor_token("Indeks Keterpaduan Pembangunan") == "keterpaduan"
    assert evaluate_excerpt("Crime Rate", "168-182", "Hanya belanja modal") is None
    assert best_candidate("Crime Rate", "168-182", [])['comparison_status'] == "NO_TEXT_MATCH"
