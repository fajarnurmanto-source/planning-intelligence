"""M3 synthetic candidate screen: focused reuse/regression coverage."""
import copy
import unittest

from app.planning_entities.coherence import ROLES, assess_candidates
from app.planning_entities.pipeline import _digest
from app.planning_entities.substantive_m3 import screen_substantive_candidates


def make_pipeline(*, targets=("80", "82", "90", "90.0"), unit="%"):
    docs = {}
    for index, role in enumerate(ROLES):
        evidence = [{"unit_fingerprint": f"{index+1:064x}",
                     "locator": {"format": "xlsx", "sheet_name": "synthetic", "row": 2, "column": 1},
                     "excerpt": "synthetic test evidence"}]
        entity = {
            "id": f"{index+1:032x}",
            "kind": "PROGRAM",
            "title": "Layanan Informasi",
            "code": "2.16.01",
            "attributes": {"indicator": "Persentase layanan", "year": "2026",
                           "unit": unit, "target": targets[index]},
            "document_role": role,
            "evidence": evidence,
        }
        docs[role] = {
            "document_role": role,
            "document_sha256": f"{index+1:064x}",
            "rights_admission": "SYNTHETIC_ONLY",
            "production_admission": "DENIED",
            "jurisdiction_id": "SYNTHETIC-JOGJA",
            "planning_period_id": "2025-2029",
            "planning_scope": "SYNTHETIC-DEMO",
            "entities": [entity],
        }
    pipeline = {
        "schema": "pi.m2.pipeline.synthetic.v1",
        "rights_admission": "SYNTHETIC_ONLY",
        "production_admission": "DENIED",
        "review_authority": "NOT_GRANTED",
        "regulatory_taxonomy": "NOT_ACTIVATED",
        "documents": docs,
        "parser_runs": {},
        "coherence": assess_candidates(docs),
    }
    pipeline["report_sha256"] = _digest(pipeline)
    return pipeline


def resign(pipeline):
    pipeline["coherence"] = assess_candidates(pipeline["documents"])
    pipeline["report_sha256"] = _digest({k: v for k, v in pipeline.items() if k != "report_sha256"})
    return pipeline


class SubstantiveScreenTests(unittest.TestCase):
    def test_reuses_m2_candidates_and_vs1_numeric_normalization(self):
        result = screen_substantive_candidates(make_pipeline())
        self.assertEqual(result["schema"], "pi.m3.substantive_screen.synthetic.v1")
        self.assertEqual(len(result["findings"]), 3)
        counts = result["metrics"]["status_counts"]
        self.assertEqual(counts["POTENTIAL_NUMERIC_TARGET_DIFFERENCE"], 2)
        self.assertEqual(counts["NUMERIC_MATCH_NOT_PROOF_OF_ALIGNMENT"], 1)
        self.assertEqual(result["metrics"]["source_m2_match_findings"], 6)
        self.assertTrue(all(len(f["source_m2_finding_ids"]) == 2 for f in result["findings"]))
        self.assertTrue(all(f["review_required"] and f["evidence"] for f in result["findings"]))
        self.assertEqual(result["metrics"]["confirmed_contradictions"], 0)
        self.assertEqual(result["metrics"]["verified_substantive_relationships"], 0)
        self.assertEqual(result["production_admission"], "DENIED")

    def test_missing_unit_abstains_instead_of_claiming_contradiction(self):
        pipeline = make_pipeline()
        pipeline["documents"]["RENJA"]["entities"][0]["attributes"].pop("unit")
        output = screen_substantive_candidates(resign(pipeline))
        affected = [x for x in output["findings"] if x["downstream_role"] == "RENJA"]
        self.assertEqual(len(affected), 1)
        self.assertTrue(all(x["status"] == "UNIT_CONTEXT_NOT_COMPARABLE" for x in affected))

    def test_code_match_with_different_indicator_abstains(self):
        pipeline = make_pipeline()
        pipeline["documents"]["RENJA"]["entities"][0]["attributes"]["indicator"] = "Indikator lain"
        output = screen_substantive_candidates(resign(pipeline))
        affected = [x for x in output["findings"] if x["downstream_role"] == "RENJA"]
        self.assertEqual(len(affected), 1)
        self.assertEqual(affected[0]["match_types"], ["CODE"])
        self.assertEqual(affected[0]["status"], "INDICATOR_CONTEXT_NOT_COMPARABLE")

    def test_tamper_and_rights_fail_closed(self):
        pipeline = make_pipeline()
        corrupted = copy.deepcopy(pipeline)
        corrupted["documents"]["RENJA"]["entities"][0]["attributes"]["target"] = "999"
        with self.assertRaisesRegex(ValueError, "DIGEST_MISMATCH"):
            screen_substantive_candidates(corrupted)
        denied = copy.deepcopy(pipeline)
        denied["rights_admission"] = "REAL"
        with self.assertRaises(PermissionError):
            screen_substantive_candidates(denied)

    def test_deterministic_without_new_parser_or_model(self):
        pipeline = make_pipeline()
        before = screen_substantive_candidates(pipeline)
        after = screen_substantive_candidates(copy.deepcopy(pipeline))
        self.assertEqual(before["report_sha256"], after["report_sha256"])
        self.assertEqual([x["id"] for x in before["findings"]],
                         [x["id"] for x in after["findings"]])

    def test_canonical_multiformat_fixture_surfaces_screenable_target_difference(self):
        from app.planning_entities.synthetic_demo import cached_synthetic_result

        result = screen_substantive_candidates(cached_synthetic_result())
        self.assertEqual(result["metrics"]["candidate_findings"], 1)
        self.assertEqual(result["metrics"]["source_m2_match_findings"], 2)
        self.assertEqual(result["metrics"]["status_counts"],
                         {"POTENTIAL_NUMERIC_TARGET_DIFFERENCE": 1})
        self.assertEqual(result["metrics"]["confirmed_contradictions"], 0)
