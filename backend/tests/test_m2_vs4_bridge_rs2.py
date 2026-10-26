"""RS2: M2 candidate reuse of the existing VS4 synthetic review lifecycle."""
from __future__ import annotations

from copy import deepcopy
import json

import pytest

from app.planning_entities.synthetic_demo import cached_synthetic_result
from app.planning_entities.vs4_bridge_rs2 import (adapt_m2_to_vs4,
                                                   archive_cached_m2_for_vs4)
from app.vs4 import service
from app.vs2.reviews import validate_result

ROLES = {"alice": "reviewer", "bob": "viewer"}


@pytest.fixture
def store(tmp_path):
    path = tmp_path / "rs2_candidate.sqlite"
    engine = service.build_candidate_store("sqlite:///" + str(path))
    yield engine, path
    engine.dispose()


def test_reuse_archive_review_resume_export(store, tmp_path):
    engine, path = store
    pipeline = cached_synthetic_result()
    projected = adapt_m2_to_vs4(pipeline)
    assert projected["source_m2_pipeline_sha256"] == pipeline["report_sha256"]
    assert projected["production_admission"] == "DENIED"
    assert projected["review_authority"] == "NOT_GRANTED"
    assert projected["regulatory_taxonomy"] == "NOT_ACTIVATED"
    assert len(projected["findings"]) == len(pipeline["coherence"]["findings"])
    assert validate_result(projected)[0] == projected["report_sha256"]

    archived = archive_cached_m2_for_vs4(engine, actor="alice", roles=ROLES)
    assert archived == projected
    assert archive_cached_m2_for_vs4(engine, actor="alice", roles=ROLES) == archived
    assert len(service.history(engine, actor="bob", roles=ROLES)) == 1

    finding = archived["findings"][0]
    assert len(finding["id"]) == 24
    assert len(finding["m2_finding_id"]) == 32
    assert any(x["unit_fingerprint"] for x in finding["evidence"])
    assert "SourceUnit:" in service.proof(engine, archived["report_sha256"],
                                          finding["id"], actor="bob", roles=ROLES)
    with pytest.raises(PermissionError, match="REVIEWER_ROLE_REQUIRED"):
        service.decision(engine, archived["report_sha256"], finding["id"],
                         "bob", ROLES, "NEEDS_EVIDENCE")

    action = service.decision(engine, archived["report_sha256"], finding["id"],
                              "alice", ROLES, "NEEDS_EVIDENCE", "Synthetic audit")
    assert action["decision"] == "NEEDS_EVIDENCE"
    engine.dispose()
    restarted = service.build_candidate_store("sqlite:///" + str(path))
    try:
        assert service.resume(restarted, archived["report_sha256"],
                              actor="bob", roles=ROLES) == archived
        report_json, report_word = service.export(restarted, archived["report_sha256"],
                                                  tmp_path / "exports", "bob", ROLES)
        assert report_json.is_file() and report_word.is_file()
        exported = json.loads(report_json.read_text(encoding="utf-8"))
        assert exported["analysis"]["source_m2_pipeline_sha256"] == pipeline["report_sha256"]
        assert exported["review_actions"][0]["decision"] == "NEEDS_EVIDENCE"
    finally:
        restarted.dispose()


def test_unauthorized_and_viewer_cannot_archive(store):
    engine, _ = store
    for actor in ("bob", "outsider", None):
        with pytest.raises(PermissionError):
            archive_cached_m2_for_vs4(engine, actor=actor, roles=ROLES)
    assert service.history(engine, actor="alice", roles=ROLES) == []


def test_mutated_pipeline_or_evidence_refused():
    baseline = cached_synthetic_result()
    forged = deepcopy(baseline)
    forged["coherence"]["findings"][0]["target_comparison"] = "VERIFIED"
    with pytest.raises(ValueError, match="RS2_M2_DIGEST_MISMATCH"):
        adapt_m2_to_vs4(forged)

    modified = deepcopy(baseline)
    modified["production_admission"] = "ALLOWED"
    with pytest.raises(PermissionError, match="RS2_SYNTHETIC_BOUNDARY_REQUIRED"):
        adapt_m2_to_vs4(modified)

    bad_evidence = deepcopy(baseline)
    finding = bad_evidence["coherence"]["findings"][0]
    finding["evidence"][finding["upstream_role"]][0]["unit_fingerprint"] = "invalid"
    coherence = bad_evidence["coherence"]
    coherence["report_sha256"] = _local_digest(coherence)
    bad_evidence["report_sha256"] = _local_digest(bad_evidence)
    with pytest.raises(ValueError, match="RS2_EVIDENCE_PROVENANCE_REQUIRED"):
        adapt_m2_to_vs4(bad_evidence)


def _local_digest(value):
    import hashlib
    raw = {k: v for k, v in value.items() if k != "report_sha256"}
    return hashlib.sha256(json.dumps(raw, ensure_ascii=False, sort_keys=True,
                                    separators=(",", ":")).encode()).hexdigest()
