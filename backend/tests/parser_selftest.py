import hashlib
import json
import tempfile
from pathlib import Path

import fitz
from docx import Document
from openpyxl import Workbook

from app.parser import DEFAULT_CONFIG, parse_document


def file_sha(path: Path) -> str:
    return hashlib.sha256(path.read_bytes()).hexdigest()


def parse_twice(path: Path, ext: str):
    digest = file_sha(path)
    first = parse_document(path, input_sha256=digest, extension=ext, config=dict(DEFAULT_CONFIG))
    second = parse_document(path, input_sha256=digest, extension=ext, config=dict(DEFAULT_CONFIG))
    assert first["result_sha256"] == second["result_sha256"], ext
    assert first["source_unit_count"] == second["source_unit_count"], ext
    assert first["source_unit_count"] > 0, ext
    fp1 = [u.unit_fingerprint for u in first["units"]]
    fp2 = [u.unit_fingerprint for u in second["units"]]
    assert fp1 == fp2, ext
    return {
        "result_sha256": first["result_sha256"],
        "source_unit_count": first["source_unit_count"],
        "page_count": first["page_count"],
        "unit_types": sorted(set(u.unit_type for u in first["units"])),
    }


with tempfile.TemporaryDirectory() as td:
    d = Path(td)

    pdf = d / "fixture.pdf"
    doc = fitz.open()
    p1 = doc.new_page()
    p1.insert_text((72, 72), "Tujuan Pembangunan Daerah")
    p1.insert_text((72, 100), "Meningkatkan kualitas pelayanan publik.")
    p2 = doc.new_page()
    p2.insert_text((72, 72), "Indikator: Indeks Kepuasan Masyarakat")
    doc.save(pdf)
    doc.close()

    docx = d / "fixture.docx"
    word = Document()
    word.add_heading("Sasaran Daerah", level=1)
    word.add_paragraph("Meningkatnya kualitas tata kelola.")
    table = word.add_table(rows=2, cols=2)
    table.cell(0, 0).text = "Indikator"
    table.cell(0, 1).text = "Target"
    table.cell(1, 0).text = "IKM"
    table.cell(1, 1).text = "90"
    word.save(docx)

    xlsx = d / "fixture.xlsx"
    wb = Workbook()
    ws = wb.active
    ws.title = "RKPD"
    ws["A1"] = "Program"
    ws["B1"] = "Anggaran"
    ws["A2"] = "Pelayanan Publik"
    ws["B2"] = 1000000
    ws["C2"] = "=B2*1"
    wb.save(xlsx)
    wb.close()

    results = {
        "pdf": parse_twice(pdf, "pdf"),
        "docx": parse_twice(docx, "docx"),
        "xlsx": parse_twice(xlsx, "xlsx"),
    }

    print(json.dumps({"status": "PASS", "formats": results}, ensure_ascii=False, sort_keys=True))
