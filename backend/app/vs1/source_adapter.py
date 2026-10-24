"""Read-only bridge from PI committed v0.1 SourceUnit payloads.

Based on historical e57f748 backend `SourceUnit` schema and `/api/parsing-runs/{id}/source-units`.
Requires real-version schema reconciliation before admission. No DB writes or network.
"""
from __future__ import annotations
from collections import defaultdict
from .core import from_cells,from_line,header_map


def from_pi_source_units(rows: list[dict]) -> list[dict]:
    """Convert existing parser units to VS1 comparison units without reparsing originals.

    Support PDF BLOCK text, DOCX PARAGRAPH/TABLE_CELL, XLSX TABLE_CELL.
    Failed match means 'not extracted', never a fabricated indicator or legal finding.
    """
    if len(rows)>250000:
        raise ValueError('SOURCE_UNIT_LIMIT_EXCEEDED')
    out=[]
    tables=defaultdict(lambda:defaultdict(dict))
    row_units={}
    for u in sorted(rows,key=lambda v:int(v.get('sequence_index',0))):
        typ=u.get('unit_type')
        raw=str(u.get('raw_text') or '')
        loc=u.get('locator') or {}
        fmt=loc.get('format')
        if typ in ('PARAGRAPH','TEXT_BLOCK','TEXT','PAGE_TEXT','LIST_ITEM'):
            for ln,txt in enumerate(raw.splitlines(),1):
                converted=from_line(txt,f'pi:{u.get("id","?")}:line-{ln}')
                if converted:
                    item=converted.public()
                    item['pi_source_unit_id']=str(u.get('id',''))
                    item['pi_unit_fingerprint']=str(u.get('unit_fingerprint',''))
                    out.append(item)
        if typ=='TABLE_CELL' and fmt in ('docx','xlsx'):
            if fmt=='xlsx':
                group=(fmt,str(loc.get('sheet_name','')),int(loc.get('sheet_index',0)))
                rowno=int(loc.get('row',0)); colno=int(loc.get('column',0))
            else:
                group=(fmt,int(loc.get('table_index',0)))
                rowno=int(loc.get('row_index',0));colno=int(loc.get('column_index',0))
            if rowno<1 or colno<1 or colno>1000:continue
            tables[group][rowno][colno]=raw
        if typ=='TABLE_ROW' and fmt in ('docx','xlsx'):
            if fmt=='xlsx':
                group=(fmt,str(loc.get('sheet_name','')),int(loc.get('sheet_index',0)))
                rowno=int(loc.get('row',0))
            else:
                group=(fmt,int(loc.get('table_index',0)))
                rowno=int(loc.get('row_index',0))
            row_units[(group,rowno)]=u
    for group,group_rows in tables.items():
        heading=None
        for rowno,columns in sorted(group_rows.items()):
            if not columns:continue
            width=min(max(columns),1000)
            values=[columns.get(i,'') for i in range(1,width+1)]
            test=header_map(values)
            if all(k in test for k in ('indicator','year','target')):
                heading=test
                continue
            if heading:
                runit=row_units.get((group,rowno),{})
                locator=f'pi:{runit.get("id","?")};{group};row-{rowno}'
                parsed=from_cells(values,heading,locator)
                if parsed:
                    item=parsed.public()
                    item['pi_source_unit_id']=str(runit.get('id',''))
                    item['pi_unit_fingerprint']=str(runit.get('unit_fingerprint',''))
                    out.append(item)
    if len(out)>5000:
        raise ValueError('TOO_MANY_EXTRACTED_RECORDS')
    return out
