import sys
import tempfile
import unittest
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parents[1] / "backend"))
from app.planning_entities import run_synthetic_pipeline, apply_synthetic_review, render_synthetic_report


class PipelineTests(unittest.TestCase):
    def _documents(self, root):
        import fitz
        from docx import Document
        from openpyxl import Workbook

        pdf = root / "rpjmd.pdf"
        writer = fitz.open()
        page = writer.new_page()
        page.insert_text((50, 72), "Tujuan: Kesejahteraan")
        page.insert_text((50, 94), "Program: Layanan Informasi")
        writer.save(str(pdf))
        writer.close()

        docx = root / "rkpd.docx"
        word = Document()
        word.add_paragraph("Sasaran: Pelayanan membaik")
        table = word.add_table(rows=2, cols=5)
        for col, value in enumerate(["Jenis", "Kode", "Uraian", "Indikator", "Tahun"]):
            table.cell(0, col).text = value
        for col, value in enumerate(["Program", "2.16.01", "Layanan Informasi", "Persentase layanan", "2026"]):
            table.cell(1, col).text = value
        word.save(docx)

        out = {"RPJMD": pdf, "RKPD": docx}
        for role, target in (("RENSTRA", 85), ("RENJA", 90)):
            book = Workbook()
            sheet = book.active
            sheet.title = "Rencana"
            sheet.append(["Jenis", "Kode", "Uraian", "Indikator Kinerja", "Target", "Tahun"])
            sheet.append(["Program", "2.16.01", "Layanan Informasi", "Persentase layanan", target, 2026])
            path = root / f"{role.lower()}.xlsx"
            book.save(path)
            book.close()
            out[role] = path
        return out

    def test_multiformat_parser_normalizer_review_report(self):
        with tempfile.TemporaryDirectory() as td:
            documents = self._documents(Path(td))
            payload = run_synthetic_pipeline(documents, jurisdiction_id="KOTA-YOGYAKARTA",
                                             planning_period_id="2025-2029")
            self.assertEqual(payload["production_admission"], "DENIED")
            self.assertGreaterEqual(len(payload["documents"]["RPJMD"]["entities"]), 1)
            self.assertGreaterEqual(len(payload["documents"]["RKPD"]["entities"]), 1)
            findings = payload["coherence"]["findings"]
            self.assertGreaterEqual(len(findings), 1)
            self.assertTrue(any(f["target_comparison"] == "DIFFERENT_TEXT" for f in findings))
            annotations = {f["id"]: "NEEDS_MORE_EVIDENCE" for f in findings}
            review = apply_synthetic_review(payload, annotations)
            markdown = render_synthetic_report(payload, review)
            self.assertIn("NOT_ACTIVATED", markdown)
            self.assertIn("NEEDS_MORE_EVIDENCE", markdown)
            self.assertTrue(all(f["evidence"][f["upstream_role"]] for f in findings))
            with self.assertRaises(ValueError):
                apply_synthetic_review(payload, {"missing": "ACCEPT_CANDIDATE"})
            with self.assertRaises(ValueError):
                render_synthetic_report(payload, review | {"pipeline_report_sha256": "wrong"})

    def test_not_production_admission(self):
        with self.assertRaises(PermissionError):
            run_synthetic_pipeline({}, jurisdiction_id="a", planning_period_id="b",
                                   declared_source_kind="REAL")


if __name__ == "__main__":
    unittest.main()
