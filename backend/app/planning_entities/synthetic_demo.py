"""Build a bounded in-memory M2 showcase from generated synthetic documents."""
from __future__ import annotations

from functools import lru_cache
from pathlib import Path
from tempfile import TemporaryDirectory


def make_documents(root: Path):
    import pymupdf
    from docx import Document
    from openpyxl import Workbook

    pdf = root / "synthetic_rpjmd.pdf"
    document = pymupdf.open()
    page = document.new_page()
    page.insert_text((72, 72), "Tujuan: Kesejahteraan masyarakat")
    page.insert_text((72, 94), "Sasaran: Pelayanan publik membaik")
    document.save(str(pdf))
    document.close()

    docx = root / "synthetic_rkpd.docx"
    word = Document()
    word.add_paragraph("Program: Transformasi layanan publik")
    table = word.add_table(rows=2, cols=6)
    for i, value in enumerate(("Jenis", "Kode", "Uraian", "Indikator Kinerja", "Target", "Tahun")):
        table.cell(0, i).text = value
    for i, value in enumerate(("Program", "2.16.01", "Layanan Informasi", "Persentase layanan", "82", "2026")):
        table.cell(1, i).text = value
    word.save(docx)

    documents = {"RPJMD": pdf, "RKPD": docx}
    for role, target in (("RENSTRA", 85), ("RENJA", 90)):
        wb = Workbook()
        sheet = wb.active
        sheet.title = "Rencana"
        sheet.append(["Jenis", "Kode", "Uraian", "Indikator Kinerja", "Target", "Tahun"])
        sheet.append(["Program", "2.16.01", "Layanan Informasi", "Persentase layanan", target, 2026])
        path = root / f"synthetic_{role.lower()}.xlsx"
        wb.save(path)
        wb.close()
        documents[role] = path
    return documents


@lru_cache(maxsize=1)
def cached_synthetic_result():
    """Fixture generation is process-local; no user data or file state retained."""
    from .pipeline import run_synthetic_pipeline

    with TemporaryDirectory(prefix="pi_m2_workbench_") as tmp:
        return run_synthetic_pipeline(
            make_documents(Path(tmp)),
            jurisdiction_id="SYNTHETIC-JOGJA",
            planning_period_id="2025-2029",
        )
