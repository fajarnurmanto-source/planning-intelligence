"""M3.2 relation declaration + real M2 synthetic parser quote index E2E."""
import copy
from pathlib import Path
import sys
import unittest

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))

from app.planning_entities.pipeline import _digest
from app.planning_entities.relation_m3 import allowed_relations, screen_declared_relations
from app.planning_entities.synthetic_demo import cached_synthetic_result


def fixture_claim():
    pipeline = cached_synthetic_result()
    program = next(e for e in pipeline["documents"]["RKPD"]["entities"]
                   if e["kind"] == "PROGRAM" and e["title"] == "Transformasi layanan publik")
    goal = next(e for e in pipeline["documents"]["RPJMD"]["entities"]
                if e["kind"] == "SASARAN" and e["title"] == "Pelayanan publik membaik")
    source = next(e for e in pipeline["source_unit_index"]["RKPD"]
                  if "mendukung" in e["excerpt"])
    claim = {
        "from_role": "RKPD", "from_entity_id": program["id"],
        "to_role": "RPJMD", "to_entity_id": goal["id"],
        "relation": "SUPPORTS",
        "source_role": "RKPD", "source_unit_fingerprint": source["unit_fingerprint"],
        "quote": "Transformasi layanan publik mendukung Pelayanan publik membaik.",
    }
    return pipeline, claim


def resign(pipeline):
    pipeline["report_sha256"] = _digest({k: v for k, v in pipeline.items()
                                         if k != "report_sha256"})
    return pipeline


class DeclaredRelationsTests(unittest.TestCase):
    def test_reuses_g2_ontology_g4_literal_g5_direction_on_m2_parser_excerpt(self):
        pipeline, claim = fixture_claim()
        result = screen_declared_relations(pipeline, [claim])
        self.assertEqual(result["metrics"]["declared_relation_candidates"], 1)
        self.assertEqual(result["metrics"]["grounded_m2_excerpts"], 1)
        self.assertEqual(len(result["graph"]["nodes"]), 2)
        self.assertEqual(len(result["graph"]["edges"]), 1)
        finding = result["findings"][0]
        self.assertEqual(finding["status"], "SOURCE_DECLARED_RELATION_CANDIDATE")
        self.assertEqual(finding["grounding_status"], "MATCHED_M2_PARSER_EXCERPT")
        self.assertEqual(finding["lexical_status"], "EXPLICIT_RELATION_ASSERTION_CANDIDATE")
        self.assertEqual(finding["direction_status"], "DIRECTIONAL_ASSERTION_CANDIDATE")
        self.assertEqual(finding["relationship_verification"], "NOT_VERIFIED")
        self.assertEqual(result["metrics"]["verified_substantive_relationships"], 0)
        self.assertEqual(result["regulatory_taxonomy"], "NOT_ACTIVATED")
        self.assertEqual(result["production_admission"], "DENIED")
        self.assertEqual(result["source_m2_report_sha256"], pipeline["report_sha256"])

    def test_absent_claim_never_infers_relationship(self):
        pipeline, _ = fixture_claim()
        result = screen_declared_relations(pipeline, [])
        self.assertEqual(result["findings"], [])
        self.assertEqual(result["graph"]["edges"], [])
        self.assertEqual(result["metrics"]["verified_substantive_relationships"], 0)

    def test_bad_quote_abstains_before_lexical_and_direction_screen(self):
        pipeline, claim = fixture_claim()
        claim["quote"] = "Transformasi layanan publik menjamin Pelayanan publik membaik."
        result = screen_declared_relations(pipeline, [claim])
        finding = result["findings"][0]
        self.assertEqual(finding["status"], "QUOTE_NOT_GROUNDED_ABSTAIN")
        self.assertEqual(finding["lexical_status"], "NOT_TESTED")
        self.assertEqual(finding["direction_status"], "NOT_TESTED")
        self.assertIsNone(finding["source_evidence"])

    def test_negation_is_not_positive_relation(self):
        pipeline, claim = fixture_claim()
        pipeline = copy.deepcopy(pipeline)
        source = next(e for e in pipeline["source_unit_index"]["RKPD"]
                      if e["unit_fingerprint"] == claim["source_unit_fingerprint"])
        source["excerpt"] = "Transformasi layanan publik tidak mendukung Pelayanan publik membaik."
        claim["quote"] = source["excerpt"]
        result = screen_declared_relations(resign(pipeline), [claim])
        self.assertEqual(result["findings"][0]["status"], "EXPLICIT_NEGATION_CANDIDATE")
        self.assertEqual(result["metrics"]["confirmed_contradictions"], 0)

    def test_prospective_is_not_accomplishment(self):
        pipeline, claim = fixture_claim()
        pipeline = copy.deepcopy(pipeline)
        source = next(e for e in pipeline["source_unit_index"]["RKPD"]
                      if e["unit_fingerprint"] == claim["source_unit_fingerprint"])
        source["excerpt"] = "Transformasi layanan publik akan mendukung Pelayanan publik membaik."
        claim["quote"] = source["excerpt"]
        result = screen_declared_relations(resign(pipeline), [claim])
        self.assertEqual(result["findings"][0]["status"], "PROSPECTIVE_RELATION_CANDIDATE")

    def test_digest_rights_and_ontology_fail_closed(self):
        pipeline, claim = fixture_claim()
        changed = copy.deepcopy(pipeline)
        changed["source_unit_index"]["RKPD"][0]["excerpt"] = "tampered"
        with self.assertRaisesRegex(ValueError, "DIGEST_MISMATCH"):
            screen_declared_relations(changed, [claim])
        denied = copy.deepcopy(pipeline)
        denied["rights_admission"] = "REAL"
        with self.assertRaises(PermissionError):
            screen_declared_relations(denied, [claim])
        reversed_claim = claim | {
            "from_role": claim["to_role"], "from_entity_id": claim["to_entity_id"],
            "to_role": claim["from_role"], "to_entity_id": claim["from_entity_id"],
        }
        with self.assertRaisesRegex(ValueError, "ONTOLOGY_EDGE"):
            screen_declared_relations(pipeline, [reversed_claim])

    def test_duplicate_claims_and_limits_refused(self):
        pipeline, claim = fixture_claim()
        with self.assertRaisesRegex(ValueError, "DUPLICATE_RELATION_PAIR"):
            screen_declared_relations(pipeline, [claim, claim])
        with self.assertRaisesRegex(ValueError, "CLAIMS_LIMIT"):
            screen_declared_relations(pipeline, [claim] * 17)

    def test_determinism_and_existing_ontology_export(self):
        pipeline, claim = fixture_claim()
        before = screen_declared_relations(pipeline, [claim])
        after = screen_declared_relations(copy.deepcopy(pipeline), [copy.deepcopy(claim)])
        self.assertEqual(before["report_sha256"], after["report_sha256"])
        self.assertIn({"from_kind": "PROGRAM", "to_kind": "SASARAN", "relation": "SUPPORTS"},
                      allowed_relations())


if __name__ == "__main__":
    unittest.main()
