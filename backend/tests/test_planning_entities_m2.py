import unittest
from hashlib import sha256
from pathlib import Path
import sys

sys.path.insert(0, str(Path(__file__).resolve().parents[1] / "backend"))
from app.planning_entities import normalize_source_units, assess_candidates
from dataclasses import dataclass

D = "a" * 64


def source(text, index, kind="PARAGRAPH", locator=None):
    return {"id": str(index), "sequence_index": index, "unit_type": kind,
            "raw_text": text, "unit_fingerprint": sha256(f"{index}:{text}".encode()).hexdigest(),
            "locator": locator or {"format": "pdf", "page": 1, "block": index}}


class NormalizationTests(unittest.TestCase):
    def test_parsedunit_dataclass_adapter(self):
        @dataclass
        class ParsedUnit:
            unit_type: str
            raw_text: str
            sequence_index: int
            unit_fingerprint: str
            locator: dict
        x = source("Sasaran: Infrastruktur membaik", 3)
        unit = ParsedUnit(x["unit_type"], x["raw_text"], x["sequence_index"],
                          x["unit_fingerprint"], x["locator"])
        result = normalize_source_units([unit], document_sha256=D, document_role="RPJMD")
        self.assertEqual(result["entities"][0]["kind"], "SASARAN")

    def test_structured_planning_table(self):
        headers = ["Jenis", "Kode", "Uraian", "Indikator Kinerja", "Target", "Tahun", "Satuan", "Pagu Indikatif", "Perangkat Daerah"]
        values = ["Program", "2.16.01", "Layanan Informasi", "Persentase layanan", "85", "2026", "%", "150000000", "Diskominfosan"]
        units = [source(v, i + 1, "TABLE_CELL", {"format": "xlsx", "sheet_name": "Rencana", "sheet_index": 1, "row": row, "column": col})
                 for row, entries in ((1, headers), (2, values)) for col, (i, v) in enumerate(enumerate(entries), 1)]
        result = normalize_source_units(units, document_sha256=D, document_role="RENJA")
        self.assertEqual(len(result["entities"]), 1)
        e = result["entities"][0]
        self.assertEqual(e["code"], "2.16.01")
        self.assertEqual(e["attributes"]["indicator"], "Persentase layanan")
        self.assertEqual(e["attributes"]["budget"], "150000000")
        self.assertEqual(len(e["evidence"]), 9)

    def test_coherence_exact_candidate_with_provenance(self):
        roles = ("RPJMD", "RKPD", "RENSTRA", "RENJA")
        docs = {}
        for i, role in enumerate(roles):
            docs[role] = normalize_source_units([source("Program", 1, "TABLE_CELL", {"format":"xlsx", "sheet_name":"a", "sheet_index":1,"row":1,"column":1}),
                                                 source("2.16.01", 2, "TABLE_CELL", {"format":"xlsx", "sheet_name":"a", "sheet_index":1,"row":1,"column":2}),
                                                 source("Layanan Informasi", 3, "TABLE_CELL", {"format":"xlsx", "sheet_name":"a", "sheet_index":1,"row":1,"column":3})],
                                               document_sha256=f"{i+1:064x}", document_role=role,
                                               jurisdiction_id="KOTA-YOGYAKARTA", planning_period_id="2025-2029",
                                               planning_scope="SYNTHETIC-DEMO")
        report = assess_candidates(docs)
        self.assertEqual(len(report["findings"]), 3)
        self.assertEqual(report["metrics"]["verified_links"], 0)
        self.assertEqual(report["regulatory_taxonomy"], "NOT_ACTIVATED")
        with self.assertRaises(ValueError):
            assess_candidates({"RPJMD": docs["RPJMD"]})
        docs["RENJA"]["planning_period_id"] = "2030-2034"
        with self.assertRaisesRegex(ValueError, "SCOPE_MISMATCH"):
            assess_candidates(docs)

    def test_labeled_entities_and_provenance(self):
        units = [source("Tujuan: Kesejahteraan warga meningkat", 1),
                 source("Sasaran: Kemiskinan menurun", 2),
                 source("Program: Pemberdayaan UMKM", 3)]
        result = normalize_source_units(units, document_sha256=D, document_role="RPJMD")
        self.assertEqual([x["kind"] for x in result["entities"]], ["TUJUAN", "SASARAN", "PROGRAM"])
        self.assertEqual(result["metrics"]["entities"], 3)
        self.assertEqual(result["relationships"], [])
        self.assertEqual(result["entities"][1]["evidence"][0]["locator"]["page"], 1)

    def test_xlsx_row(self):
        units = [source("Kegiatan", 1, "TABLE_CELL", {"format": "xlsx", "sheet_name": "Rencana", "sheet_index": 0, "row": 5, "column": 1}),
                 source("Pendampingan UMKM", 2, "TABLE_CELL", {"format": "xlsx", "sheet_name": "Rencana", "sheet_index": 0, "row": 5, "column": 2})]
        result = normalize_source_units(units, document_sha256=D, document_role="RENJA")
        self.assertEqual(result["entities"][0]["title"], "Pendampingan UMKM")
        self.assertEqual(len(result["entities"][0]["evidence"]), 2)
        self.assertEqual(result["entities"][0]["regulatory_applicability"], "NOT_ASSESSED")

    def test_refuse_real_and_missing_evidence(self):
        with self.assertRaises(PermissionError):
            normalize_source_units([], document_sha256=D, document_role="RENJA", rights_admission="REAL")
        with self.assertRaises(ValueError):
            normalize_source_units([source("Tujuan: A", 1) | {"unit_fingerprint": ""}], document_sha256=D, document_role="RENJA")

    def test_no_hallucinated_relationship(self):
        result = normalize_source_units([source("Program: Digitalisasi", 1), source("Kegiatan: Pelatihan", 2)],
                                        document_sha256=D, document_role="RKPD")
        self.assertEqual(result["metrics"]["verified_relationships"], 0)
        self.assertEqual(len(result["unresolved_parent_candidates"]), 2)


if __name__ == "__main__":
    unittest.main()
