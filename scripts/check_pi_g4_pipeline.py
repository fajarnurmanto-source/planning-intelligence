"""One-command G4 E2E; automatically uses existing PI test venv on Windows."""
from __future__ import annotations
import json
import os
from pathlib import Path
import subprocess
import sys
import tempfile


def main():
    repo=Path(__file__).resolve().parents[1]
    if '--internal' not in sys.argv:
        venv=Path.home()/'PI_VS4_SYNTHETIC_CANDIDATE'/'venv'
        python=venv/('Scripts/python.exe' if os.name=='nt' else 'bin/python')
        if not python.is_file():raise RuntimeError('G4_EXISTING_VS4_VENV_REQUIRED')
        cmd=[str(python),__file__,'--internal']
        proc=subprocess.run(cmd,cwd=str(repo),check=False)
        if proc.returncode:raise RuntimeError('G4_E2E_SUBPROCESS_FAILED')
        return
    sys.path.insert(0,str(repo/'backend'))
    from app.graph_core.intake import analyze_four_files
    from app.graph_g2.engine import analyze_substantive,parse_ledger
    from app.graph_g3.engine import analyze_g2_and_originals
    from app.graph_g4.engine import assess_relationships
    from app.graph_g4.samples import make_assessment_set
    from app.graph_g4.intake import analyze_archived_g2_g3
    from app.vs4 import service
    from app.vs4.archive import archive_analysis
    from docx import Document
    with tempfile.TemporaryDirectory(prefix='pi-g4-synthetic-') as temp:
        root=Path(temp)
        files,ledger=make_assessment_set(root/'documents')
        g1=analyze_four_files(files,group='Sasaran',scope='Target tahunan')
        g2=analyze_substantive(g1,parse_ledger(ledger))
        g3=analyze_g2_and_originals(g2,ledger,files)
        assert g3['verification_metrics']['edge_quotes_verified']==6
        print('PI_G4_E2E_G3_ORIGINAL_QUOTE_VERIFICATION=PASS_6_OF_6')
        g4=assess_relationships(g2,g3)
        counts=g4['assessment_metrics']['status_counts']
        assert counts=={'EXPLICIT_RELATION_ASSERTION_CANDIDATE':5,
                        'EXPLICIT_RELATION_NEGATION_CANDIDATE':1}
        assert g4['production_admission']=='DENIED'
        print('PI_G4_E2E_LITERAL_ASSESSMENT=PASS_5_ASSERTIONS_1_NEGATION')
        roles={'reviewer':'reviewer','viewer':'viewer'}
        store=service.build_candidate_store('sqlite:///'+str(root/'review.sqlite'))
        for x in (g1,g2,g3):archive_analysis(store,x,actor='reviewer')
        g4arc=analyze_archived_g2_g3(store,g3['report_sha256'],actor='reviewer',roles=roles)
        assert g4arc['report_sha256']==g4['report_sha256']
        decision=service.decision(store,g4arc['report_sha256'],g4arc['findings'][0]['id'],
                                  'reviewer',roles,'NEEDS_EVIDENCE','No causal conclusion')
        assert decision['decision']=='NEEDS_EVIDENCE'
        store.dispose()
        resumed=service.build_candidate_store('sqlite:///'+str(root/'review.sqlite'))
        assert any(r[3]=='NEEDS_EVIDENCE' for r in service.table(
            resumed,g4arc['report_sha256'],actor='viewer',roles=roles))
        exports=service.export(resumed,g4arc['report_sha256'],root/'exports','viewer',roles)
        assert len(exports)==2 and all(p.is_file() for p in exports)
        assert Document(next(p for p in exports if p.suffix=='.docx')).paragraphs
        assert json.loads(next(p for p in exports if p.suffix=='.json').read_text('utf8'))
        resumed.dispose()
        print('PI_G4_E2E_ARCHIVE_REVIEW_EXPORT=PASS')
        print('PI_G4_E2E_PRODUCTION_ADMISSION=DENIED')
    print('PI_G4_E2E_OVERALL=PASS')

if __name__=='__main__':
    try: main()
    except Exception as exc:
        print('PI_G4_E2E_OVERALL=FAIL:'+type(exc).__name__+':'+str(exc),file=sys.stderr)
        raise SystemExit(1)
