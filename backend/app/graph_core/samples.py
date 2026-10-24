"""Four synthetic role-selected documents; explicit titles never infer legal hierarchy."""
from __future__ import annotations
from pathlib import Path
from openpyxl import Workbook
from app.vs5.intake import SYNTHETIC_MARKER
from .engine import ROLES

HEADERS=['Indikator', 'Tahun', 'Target', 'Satuan', 'Lingkup', 'Jenis Target']

def make_four(directory: Path) -> dict[str,Path]:
    directory = Path(directory)
    directory.mkdir(parents=True, exist_ok=True)
    targets = {'RPJMD': (85, 70), 'RKPD': (85, 70), 'RENSTRA': (84, 70), 'RENJA': (80, 70)}
    result={}
    for role in ROLES:
        wb=Workbook(); ws=wb.active;ws.title='Target'
        ws['A1']=SYNTHETIC_MARKER
        ws['B1']=role # distinct synthetic source bytes, not inferred legal authority
        ws.append(HEADERS)
        for indicator, target in zip(('Persentase layanan selesai', 'Rasio layanan digital'),targets[role]):
            ws.append([indicator,2026,target,'%', 'Layanan publik', 'Outcome'])
        path=directory/(role+'_PI_SYNTHETIC.xlsx')
        wb.save(path); wb.close()
        result[role]=path
    return result
