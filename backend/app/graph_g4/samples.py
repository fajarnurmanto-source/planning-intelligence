"""Synthetic G4 benchmark with source-matched explicit support/negation."""
from pathlib import Path
from openpyxl import load_workbook
from app.graph_g3.samples import make_grounded_set


def make_assessment_set(root: Path):
    files,ledger=make_grounded_set(root)
    wb=load_workbook(ledger)
    nodes={wb['Nodes'].cell(r,1).value:(wb['Nodes'].cell(r,3).value,wb['Nodes'].cell(r,4).value)
           for r in range(2,wb['Nodes'].max_row+1)}
    phrasing={
       'E1':'Layanan publik lebih baik dijabarkan menjadi Ketepatan pelayanan',
       'E2':'Digitalisasi layanan mendukung Ketepatan pelayanan',
       'E3':'Modernisasi antrean melaksanakan Digitalisasi layanan',
       'E4':'Perangkat antrean beroperasi dihasilkan oleh Modernisasi antrean',
       'E5':'Perangkat antrean beroperasi tidak berkontribusi terhadap Waktu layanan berkurang',
       'E6':'Waktu layanan berkurang selaras dengan Ketepatan pelayanan',
    }
    modified={}
    for row in range(2,wb['Edges'].max_row+1):
        sheet=wb['Edges']; eid=sheet.cell(row,1).value
        role=nodes[sheet.cell(row,2).value][0]
        quote=phrasing[eid]
        loc=sheet.cell(row,6).value
        assert loc.startswith('xlsx:Evidence!')
        modified.setdefault(role,[]).append((loc.split('!')[1],quote))
        sheet.cell(row,5).value=quote
    for role,items in modified.items():
        fw=load_workbook(files[role])
        for cell,quote in items:fw['Evidence'][cell]=quote
        fw.save(files[role]);fw.close()
    wb.save(ledger);wb.close()
    return files,ledger
