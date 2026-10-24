"""Structured synthetic claim ledger for G2: NEVER silently inferred from documents."""
from pathlib import Path
from openpyxl import Workbook
from .engine import MARKER,NODE_HEADERS,EDGE_HEADERS


def make_ledger(root: Path, *, break_link: bool=False) -> Path:
    root=Path(root);root.mkdir(parents=True,exist_ok=True)
    wb=Workbook(); meta=wb.active;meta.title='Meta';meta['A1']=MARKER
    n=wb.create_sheet('Nodes');n.append(list(NODE_HEADERS))
    for row in [
      ['T1','TUJUAN','RPJMD','Layanan publik lebih baik',2026,'Layanan publik','Tujuan daerah menyatakan peningkatan layanan publik','RPJMD:Bab2'],
      ['S1','SASARAN','RPJMD','Ketepatan pelayanan',2026,'Layanan publik','Sasaran pelayanan tepat waktu','RPJMD:Bab3'],
      ['P1','PROGRAM','RENSTRA','Digitalisasi layanan',2026,'Layanan publik','Program digitalisasi pelayanan dijabarkan','Renstra:Program1'],
      ['K1','KEGIATAN','RENJA','Modernisasi antrean',2026,'Layanan publik','Kegiatan menyediakan antrean digital','Renja:Kegiatan1'],
      ['O1','OUTPUT','RENJA','Perangkat antrean beroperasi',2026,'Layanan publik','Perangkat antrean tersedia di loket','Renja:Output1'],
      ['H1','OUTCOME','RKPD','Waktu layanan berkurang',2026,'Layanan publik','Waktu tunggu masyarakat menjadi singkat','RKPD:Outcome1'],
    ]:n.append(row)
    e=wb.create_sheet('Edges');e.append(list(EDGE_HEADERS))
    for row in [
      ['E1','T1','S1','DECOMPOSES_TO','Tujuan dijabarkan menjadi sasaran pelayanan','RPJMD:Bab3'],
      ['E2','P1','S1','SUPPORTS','Program dinyatakan mendukung sasaran pelayanan','Renstra:Program1'],
      ['E3','K1','P1','IMPLEMENTS','Kegiatan ditetapkan sebagai pelaksanaan program','Renja:Kegiatan1'],
      ['E4','O1','K1','DELIVERED_BY','Output dilaporkan dari kegiatan antrean','Renja:Output1'],
      ['E5','O1','H1','CONTRIBUTES_TO','Output diasumsikan mendukung outcome waktu tunggu','RKPD:Outcome1'],
      ['E6','H1','S1','ALIGNS_WITH','Outcome dikaitkan pada sasaran pelayanan','RPJMD:Bab3'],
    ]:
        if break_link and row[0]=='E5':row[4]='';row[5]=''
        e.append(row)
    dest=root/('G2_CLAIM_LEDGER_GAP_SYNTHETIC.xlsx' if break_link else 'G2_CLAIM_LEDGER_SYNTHETIC.xlsx')
    wb.save(dest);wb.close();return dest
