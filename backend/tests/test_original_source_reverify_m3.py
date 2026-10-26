"""M3.3 original-byte quote checks, fail-closed tamper cases and determinism."""
from __future__ import annotations

import copy
from pathlib import Path
from tempfile import TemporaryDirectory
import unittest

from app.planning_entities.pipeline import _digest
from app.planning_entities.source_reverify_m3 import reverify_declared_relation_sources
from app.planning_entities.synthetic_demo import cached_synthetic_result, make_documents


def fixture_claim(pipeline):
    left = next(e for e in pipeline["documents"]["RKPD"]["entities"]
                if e["title"] == "Transformasi layanan publik")
    right = next(e for e in pipeline["documents"]["RPJMD"]["entities"]
                 if e["title"] == "Pelayanan publik membaik")
    source = next(s for s in pipeline["source_unit_index"]["RKPD"]
                  if "mendukung" in s["excerpt"])
    return dict(from_role="RKPD", from_entity_id=left["id"],
                to_role="RPJMD", to_entity_id=right["id"], relation="SUPPORTS",
                source_role="RKPD", source_unit_fingerprint=source["unit_fingerprint"],
                quote=source["excerpt"])


def resign(pipeline):
    pipeline["report_sha256"] = _digest({k: v for k, v in pipeline.items()
                                         if k != "report_sha256"})
    return pipeline


class OriginalSourceReverificationTests(unittest.TestCase):
    def test_original_source_reparsing_reuses_g3_locator_and_canonical_parser(self):
        pipeline = cached_synthetic_result()
        with TemporaryDirectory(prefix="m33_test_") as tmp:
            output = reverify_declared_relation_sources(
                pipeline, [fixture_claim(pipeline)], make_documents(Path(tmp)))
        self.assertEqual(output["schema"], "pi.m3.original_source_reverification.synthetic.v1")
        self.assertEqual(output["metrics"]["exact_original_source_quotes_located"], 1)
        self.assertEqual(output["metrics"]["verified_substantive_relationships"], 0)
        self.assertEqual(output["findings"][0]["original_source_status"],
                         "EXACT_ORIGINAL_SOURCE_UNIT_LOCATED")
        self.assertEqual(output["findings"][0]["original_source_evidence"]["locator"],
                         "docx:paragraph=2")
        self.assertEqual(output["regulatory_taxonomy"], "NOT_ACTIVATED")
        self.assertEqual(output["production_admission"], "DENIED")

    def test_no_auto_claims_even_if_originals_are_present(self):
        pipeline = cached_synthetic_result()
        with TemporaryDirectory(prefix="m33_test_") as tmp:
            output = reverify_declared_relation_sources(pipeline, [], make_documents(Path(tmp)))
        self.assertEqual(output["findings"], [])
        self.assertEqual(output["metrics"]["exact_original_source_quotes_located"], 0)

    def test_tampered_original_file_bytes_are_refused(self):
        pipeline = cached_synthetic_result()
        with TemporaryDirectory(prefix="m33_test_") as tmp:
            originals = make_documents(Path(tmp))
            originals["RKPD"].write_bytes(originals["RKPD"].read_bytes() + b"tamper")
            with self.assertRaisesRegex(ValueError, "ORIGINAL_SHA256_MISMATCH_RKPD"):
                reverify_declared_relation_sources(pipeline, [fixture_claim(pipeline)], originals)

    def test_resigned_fabricated_index_text_does_not_pass_original_reparse(self):
        pipeline = copy.deepcopy(cached_synthetic_result())
        claim = fixture_claim(pipeline)
        source = next(s for s in pipeline["source_unit_index"]["RKPD"]
                      if s["unit_fingerprint"] == claim["source_unit_fingerprint"])
        source["excerpt"] = "Transformasi layanan publik akan mendukung Pelayanan publik membaik."
        claim["quote"] = source["excerpt"]
        with TemporaryDirectory(prefix="m33_test_") as tmp:
            result = reverify_declared_relation_sources(resign(pipeline), [claim],
                                                        make_documents(Path(tmp)))
        self.assertEqual(result["findings"][0]["original_source_status"],
                         "M2_INDEX_DIFFERS_FROM_ORIGINAL_ABSTAIN")
        self.assertEqual(result["metrics"]["exact_original_source_quotes_located"], 0)

    def test_partial_quote_is_not_entire_verified_source_unit(self):
        pipeline = cached_synthetic_result()
        claim = fixture_claim(pipeline)
        claim["quote"] = "mendukung"
        with TemporaryDirectory(prefix="m33_test_") as tmp:
            result = reverify_declared_relation_sources(pipeline, [claim],
                                                        make_documents(Path(tmp)))
        self.assertEqual(result["findings"][0]["original_source_status"],
                         "ORIGINAL_QUOTE_NOT_EXACT_UNIT_ABSTAIN")

    def test_missing_source_role_abstains(self):
        pipeline = cached_synthetic_result()
        claim = fixture_claim(pipeline)
        claim["source_role"] = ""
        claim["source_unit_fingerprint"] = ""
        with TemporaryDirectory(prefix="m33_test_") as tmp:
            result = reverify_declared_relation_sources(pipeline, [claim],
                                                        make_documents(Path(tmp)))
        self.assertEqual(result["findings"][0]["original_source_status"],
                         "ORIGINAL_SOURCE_REFERENCE_MISSING_ABSTAIN")

    def test_scope_and_metadata_tampering_fail_closed(self):
        pipeline = copy.deepcopy(cached_synthetic_result())
        claim = fixture_claim(pipeline)
        pipeline["rights_admission"] = "REAL"
        with TemporaryDirectory(prefix="m33_test_") as tmp:
            with self.assertRaises(PermissionError):
                reverify_declared_relation_sources(pipeline, [claim], make_documents(Path(tmp)))
        altered = copy.deepcopy(cached_synthetic_result())
        altered["parser_runs"]["RKPD"]["source_result_sha256"] = "0" * 64
        with TemporaryDirectory(prefix="m33_test_") as tmp:
            with self.assertRaisesRegex(ValueError, "REPARSE_PROVENANCE_MISMATCH_RKPD"):
                reverify_declared_relation_sources(resign(altered), [claim],
                                                   make_documents(Path(tmp)))

    def test_replay_digest_is_deterministic(self):
        pipeline = cached_synthetic_result()
        with TemporaryDirectory(prefix="m33_test_") as first:
            a = reverify_declared_relation_sources(pipeline, [fixture_claim(pipeline)],
                                                   make_documents(Path(first)))
        with TemporaryDirectory(prefix="m33_test_") as second:
            b = reverify_declared_relation_sources(pipeline, [fixture_claim(pipeline)],
                                                   make_documents(Path(second)))
        self.assertEqual(a["report_sha256"], b["report_sha256"])


if __name__ == "__main__":
    unittest.main()
