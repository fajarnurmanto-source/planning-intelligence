"""Generate known synthetic XLSX fixtures using openpyxl, not a custom file format."""
from pathlib import Path
from openpyxl import Workbook
from .intake import SYNTHETIC_MARKER

HEADERS = ['Indikator', 'Tahun', 'Target', 'Satuan', 'Lingkup', 'Jenis Target']
ROWS = [
    ['Persentase layanan selesai', 2026, 85, '%', 'Layanan publik', 'Outcome'],
    ['Rasio layanan digital', 2026, 70, '%', 'Layanan publik', 'Outcome'],
]

def create_sample_pair(directory: Path) -> tuple[Path,Path]:
    directory=Path(directory)
    directory.mkdir(parents=True,exist_ok=True)
    created=[]
    for idx,title in enumerate(['Renstra', 'Renja']):
        wb=Workbook(); ws=wb.active; ws.title='Target'
        ws['A1']=SYNTHETIC_MARKER
        ws.append(HEADERS)
        for row in ROWS:
            values=list(row)
            if idx==1 and values[0]=='Persentase layanan selesai':
                values[2]=80
            ws.append(values)
        target=directory/(title+'_PI_SYNTHETIC.xlsx')
        wb.save(target); wb.close(); created.append(target)
    return tuple(created)
