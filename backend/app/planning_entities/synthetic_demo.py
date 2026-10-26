"""Build a bounded in-memory M2 showcase from generated synthetic documents."""
from __future__ import annotations

from functools import lru_cache
from datetime import datetime, timezone
from io import BytesIO
from pathlib import Path
import re
from tempfile import TemporaryDirectory
from zipfile import ZipFile, ZipInfo, ZIP_DEFLATED


FIXTURE_TIME = datetime(2026, 1, 1, 0, 0, 0, tzinfo=timezone.utc)


def _stable_office_archive(path: Path) -> None:
    """Remove creation-time ZIP metadata from synthetic Office fixtures only.

    ZIP member timestamps and order can vary between process invocations.
    Repack in-memory with fixed metadata while retaining each member's bytes.
    Never call this on user-provided documents.
    """
    with ZipFile(path, "r") as archive:
        members = {item.filename: archive.read(item) for item in archive.infolist()}
    if "docProps/core.xml" in members:
        # openpyxl overwrites `modified` during save even if explicitly set.
        members["docProps/core.xml"] = re.sub(
            rb"(<dcterms:modified\b[^>]*>)[^<]*(</dcterms:modified>)",
            rb"\g<1>2026-01-01T00:00:00Z\g<2>",
            members["docProps/core.xml"],
        )

    output = BytesIO()
    with ZipFile(output, "w", compression=ZIP_DEFLATED, compresslevel=6) as archive:
        for name in sorted(members):
            info = ZipInfo(name, (2026, 1, 1, 0, 0, 0))
            info.compress_type = ZIP_DEFLATED
            info.create_system = 3
            info.external_attr = 0o600 << 16
            archive.writestr(info, members[name], compress_type=ZIP_DEFLATED, compresslevel=6)
    path.write_bytes(output.getvalue())


def make_documents(root: Path):
    import pymupdf
    from docx import Document
    from openpyxl import Workbook

    pdf = root / "synthetic_rpjmd.pdf"
    document = pymupdf.open()
    page = document.new_page()
    page.insert_text((72, 72), "Tujuan: Kesejahteraan masyarakat")
    page.insert_text((72, 94), "Sasaran: Pelayanan publik membaik")
    document.set_metadata({"creationDate": "D:20260101000000Z", "modDate": "D:20260101000000Z"})
    document.save(str(pdf), no_new_id=True)
    document.close()

    docx = root / "synthetic_rkpd.docx"
    word = Document()
    word.core_properties.created = FIXTURE_TIME
    word.core_properties.modified = FIXTURE_TIME
    word.add_paragraph("Program: Transformasi layanan publik")
    # Explicit synthetic claim for M3.2; the relationship is still not
    # independently established merely because a source sentence says so.
    word.add_paragraph("Transformasi layanan publik mendukung Pelayanan publik membaik.")
    table = word.add_table(rows=2, cols=7)
    for i, value in enumerate(("Jenis", "Kode", "Uraian", "Indikator Kinerja", "Target", "Tahun", "Satuan")):
        table.cell(0, i).text = value
    for i, value in enumerate(("Program", "2.16.01", "Layanan Informasi", "Persentase layanan", "82", "2026", "%")):
        table.cell(1, i).text = value
    word.save(docx)
    _stable_office_archive(docx)

    documents = {"RPJMD": pdf, "RKPD": docx}
    for role, target in (("RENSTRA", 85), ("RENJA", 90)):
        wb = Workbook()
        wb.properties.created = FIXTURE_TIME
        wb.properties.modified = FIXTURE_TIME
        sheet = wb.active
        sheet.title = "Rencana"
        sheet.append(["Jenis", "Kode", "Uraian", "Indikator Kinerja", "Target", "Tahun", "Satuan"])
        sheet.append(["Program", "2.16.01", "Layanan Informasi", "Persentase layanan", target, 2026, "%"])
        path = root / f"synthetic_{role.lower()}.xlsx"
        wb.save(path)
        wb.close()
        _stable_office_archive(path)
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

