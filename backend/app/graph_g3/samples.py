"""Make synthetic four originals with explicit SourceUnit quotes and exact cell locators."""
from pathlib import Path
from openpyxl import load_workbook
from app.graph_core.samples import make_four
from app.graph_g2.samples import make_ledger


def make_grounded_set(root: Path, *, change_quote=False, change_locator=False):
    root=Path(root);root.mkdir(parents=True,exist_ok=True)
    source_files=make_four(root/'originals')
    ledger_path=make_ledger(root/'claims')
    ledger_wb=load_workbook(ledger_path)
    recorded={role:[] for role in source_files}
    for i in range(2,ledger_wb['Nodes'].max_row+1):
        row=ledger_wb['Nodes']
        role=row.cell(i,3).value
        quote=row.cell(i,7).value
        recorded[role].append(('node',i,quote))
    role_by_id={ledger_wb['Nodes'].cell(i,1).value:ledger_wb['Nodes'].cell(i,3).value
                for i in range(2,ledger_wb['Nodes'].max_row+1)}
    for i in range(2,ledger_wb['Edges'].max_row+1):
        row=ledger_wb['Edges']
        role=role_by_id[row.cell(i,2).value]
        quote=row.cell(i,5).value
        if quote:recorded[role].append(('edge',i,quote))
    for role,path in source_files.items():
        wb=load_workbook(path)
        ws=wb.create_sheet('Evidence')
        ws['A1']='PI_SYNTHETIC_FIXTURE_ONLY'
        ws['B1']='Direct document excerpt test cells'
        for idx,(kind,ledger_row,quote) in enumerate(recorded[role],start=2):
            ws.cell(idx,1).value=f'{kind.upper()}_{ledger_row}'
            ws.cell(idx,2).value=quote
            claimed_locator=f'xlsx:Evidence!B{idx}'
            if kind=='node':
                ledger_wb['Nodes'].cell(ledger_row,8).value=claimed_locator
            else:
                ledger_wb['Edges'].cell(ledger_row,6).value=claimed_locator
        wb.save(path);wb.close()
    if change_quote:
        # The actual source remains intact, only operator's ledger claim is false.
        ledger_wb['Edges']['E6']='This claim is NOT present in the original document'
    if change_locator:
        ledger_wb['Nodes']['H2']='xlsx:Evidence!Z1234'
    ledger_wb.save(ledger_path);ledger_wb.close()
    return source_files, ledger_path
