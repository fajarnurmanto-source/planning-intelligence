#!/usr/bin/env python3
"""Automated local synthetic G1→G2→G3 end-to-end acceptance, no password needed.

Creates ephemeral synthetic sources; never reads/mutates user projects, PG or I0.
Review events and exports are verified in an isolated temporary SQLite archive.
"""
from __future__ import annotations
import json
from pathlib import Path
import sys
import tempfile

ROOT=Path(__file__).resolve().parents[1]
sys.path.insert(0,str(ROOT/'backend'))


def require(value, message):
    if not value:
        raise RuntimeError('G3_E2E_FAILED_'+message)


def main():
    from app.graph_core.intake import analyze_four_files
    from app.graph_g2.engine import analyze_substantive,parse_ledger
    from app.graph_g3.engine import analyze_g2_and_originals
    from app.graph_g3.samples import make_grounded_set
    from app.graph_g3.intake import analyze_g2_archive_and_originals
    from app.vs4.archive import archive_analysis
    from app.vs4 import service
    from docx import Document

    with tempfile.TemporaryDirectory(prefix='pi-g3-synthetic-e2e-') as directory:
        root=Path(directory)
        roles={'synthetic-reviewer':'reviewer','synthetic-viewer':'viewer'}
        positive_files,positive_ledger=make_grounded_set(root/'positive')
        g1=analyze_four_files(positive_files,group='Sasaran',scope='Target tahunan')
        g2=analyze_substantive(g1,parse_ledger(positive_ledger))
        g3=analyze_g2_and_originals(g2,positive_ledger,positive_files)
        m=g3['verification_metrics']
        require(m['node_quotes_verified']==6 and m['edge_quotes_verified']==6 and m['source_gaps']==0,
                'POSITIVE_QUOTE_VERIFICATION')
        require(g3['production_admission']=='DENIED', 'PRODUCTION_BOUNDARY')
        print('PI_G3_E2E_POSITIVE=PASS_6_OF_6_NODES_AND_EDGES')

        bad_files,bad_ledger=make_grounded_set(root/'adversarial',change_quote=True)
        bad_g1=analyze_four_files(bad_files,group='Sasaran',scope='Target tahunan')
        bad_g2=analyze_substantive(bad_g1,parse_ledger(bad_ledger))
        bad_g3=analyze_g2_and_originals(bad_g2,bad_ledger,bad_files)
        bad=bad_g3['verification_metrics']
        require(bad['edge_quotes_verified']==5 and bad['source_gaps']>0, 'TAMPERED_QUOTE_REJECTION')
        require(any(f['status']=='G3_RELATION_SOURCE_GAP' for f in bad_g3['findings']), 'FINDING_FOR_TAMPERING')
        print('PI_G3_E2E_ADVERSARIAL=PASS_TAMPERED_QUOTE_REFUSED')

        store_path=root/'synthetic-review.sqlite'
        store=service.build_candidate_store('sqlite:///'+store_path.as_posix())
        archive_analysis(store,bad_g1,actor='synthetic-reviewer')
        archive_analysis(store,bad_g2,actor='synthetic-reviewer')
        archived=analyze_g2_archive_and_originals(store,bad_g2['report_sha256'],bad_ledger,bad_files,
                             actor='synthetic-reviewer',roles=roles)
        require(archived['report_sha256']==bad_g3['report_sha256'],'ARCHIVED_DIGEST')
        finding=archived['findings'][0]
        event=service.decision(store,archived['report_sha256'],finding['id'],'synthetic-reviewer',roles,
                               'NEEDS_EVIDENCE','Exact source quote altered')
        require(event['decision']=='NEEDS_EVIDENCE','REVIEW_DECISION')
        store.dispose()
        reopened=service.build_candidate_store('sqlite:///'+store_path.as_posix())
        rows=service.table(reopened,archived['report_sha256'],actor='synthetic-viewer',roles=roles)
        require(any(r[3]=='NEEDS_EVIDENCE' for r in rows),'REVIEW_PERSISTENCE')
        paths=service.export(reopened,archived['report_sha256'],root/'reports','synthetic-viewer',roles)
        require(len(paths)==2 and {p.suffix for p in paths}=={'.docx','.json'}, 'EXPORT_FILES')
        json_report=next(p for p in paths if p.suffix=='.json')
        json.loads(json_report.read_text(encoding='utf-8'))
        word_report=next(p for p in paths if p.suffix=='.docx')
        require(bool(Document(word_report).paragraphs),'WORD_NONEMPTY')
        reopened.dispose()
        print('PI_G3_E2E_ARCHIVE_REVIEW_EXPORT=PASS')
        print('PI_G3_E2E_PRODUCTION_ADMISSION=DENIED')
    print('PI_G3_E2E_OVERALL=PASS')


if __name__=='__main__':
    try: main()
    except Exception as exc:
        print(f'PI_G3_E2E_OVERALL=FAIL:{type(exc).__name__}:{exc}',file=sys.stderr)
        sys.exit(1)
