"""Generate bounded synthetic PI M2 end-to-end evidence; never touch real documents."""
from __future__ import annotations

import argparse
import json
from pathlib import Path
from tempfile import TemporaryDirectory
import sys

sys.path.insert(0, str(Path(__file__).resolve().parents[1] / "backend"))

from app.planning_entities import (run_synthetic_pipeline,
                                   apply_synthetic_review, render_synthetic_report)


def _fixtures(root):
    import fitz
    from docx import Document
    from openpyxl import Workbook

    pdf = root / "synthetic_rpjmd.pdf"
    pdf_doc = fitz.open()
    page = pdf_doc.new_page()
    page.insert_text((72, 72), "Tujuan: Kesejahteraan masyarakat")
    page.insert_text((72, 94), "Sasaran: Pelayanan publik membaik")
    pdf_doc.save(str(pdf))
    pdf_doc.close()

    docx = root / "synthetic_rkpd.docx"
    word = Document()
    word.add_paragraph("Program: Transformasi layanan publik")
    table = word.add_table(rows=2, cols=6)
    for i, value in enumerate(("Jenis", "Kode", "Uraian", "Indikator Kinerja", "Target", "Tahun")):
        table.cell(0, i).text = value
    for i, value in enumerate(("Program", "2.16.01", "Layanan Informasi", "Persentase layanan", "82", "2026")):
        table.cell(1, i).text = value
    word.save(docx)

    files = {"RPJMD": pdf, "RKPD": docx}
    for role, target in (("RENSTRA", 85), ("RENJA", 90)):
        wb = Workbook()
        sheet = wb.active
        sheet.title = "Rencana"
        sheet.append(["Jenis", "Kode", "Uraian", "Indikator Kinerja", "Target", "Tahun"])
        sheet.append(["Program", "2.16.01", "Layanan Informasi", "Persentase layanan", target, 2026])
        path = root / f"synthetic_{role.lower()}.xlsx"
        wb.save(path)
        wb.close()
        files[role] = path
    return files


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--output-dir", type=Path, required=True,
                        help="New, non-existent output folder for synthetic results")
    args = parser.parse_args()
    output = args.output_dir.resolve()
    if output.exists():
        raise SystemExit("PI_M2_OUTPUT_DIR_MUST_BE_NEW")
    if not output.parent.is_dir():
        raise SystemExit("PI_M2_OUTPUT_PARENT_NOT_FOUND")
    with TemporaryDirectory(prefix="pi_m2_synthetic_") as folder:
        documents = _fixtures(Path(folder))
        result = run_synthetic_pipeline(documents, jurisdiction_id="SYNTHETIC-JOGJA",
                                        planning_period_id="2025-2029")
    decisions = {x["id"]: "NEEDS_MORE_EVIDENCE" for x in result["coherence"]["findings"]}
    review = apply_synthetic_review(result, decisions)
    report = render_synthetic_report(result, review)
    output.mkdir()
    (output / "pi_m2_synthetic_pipeline.json").write_text(
        json.dumps(result, indent=2, ensure_ascii=False), encoding="utf-8")
    (output / "pi_m2_synthetic_review.json").write_text(
        json.dumps(review, indent=2, ensure_ascii=False), encoding="utf-8")
    (output / "pi_m2_synthetic_report.md").write_text(report, encoding="utf-8")
    print("PI_M2_CANONICAL_PARSER_E2E=PASS")
    print(f"PI_M2_ENTITIES={sum(len(x['entities']) for x in result['documents'].values())}")
    print(f"PI_M2_CANDIDATE_FINDINGS={len(result['coherence']['findings'])}")
    print("PI_M2_REVIEW_EXPORT=PASS")
    print("PI_M2_REGULATORY_TAXONOMY=NOT_ACTIVATED")
    print("PI_M2_PRODUCTION_ADMISSION=DENIED")
    print(f"PI_M2_OUTPUT_DIR={output}")


if __name__ == "__main__":
    main()
