"""G5 no-cost local E2E, reexec in the trusted PI venv to avoid global fitz bug."""
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
        py=venv/('Scripts/python.exe' if os.name=='nt' else 'bin/python')
        if not py.is_file():raise RuntimeError('G5_EXISTING_VENV_REQUIRED')
        rc=subprocess.run([str(py),__file__,'--internal'],cwd=str(repo)).returncode
        if rc:raise RuntimeError('G5_E2E_CHILD_FAILED')
        return
    sys.path.insert(0,str(repo/'backend'))
    from docx import Document
    from app.graph_core.intake import analyze_four_files
    from app.graph_g2.engine import parse_ledger,analyze_substantive
    from app.graph_g3.engine import analyze_g2_and_originals
    from app.graph_g4.engine import assess_relationships
    from app.graph_g4.samples import make_assessment_set
    from app.graph_g5.engine import assess_direction,orientation_screen
    from app.graph_g5.intake import analyze_archived_g4
    from app.vs4 import service
    from app.vs4.archive import archive_analysis
    with tempfile.TemporaryDirectory(prefix='pi-g5-local-synthetic-') as path:
        root=Path(path)
        files,ledger=make_assessment_set(root/'docs')
        g1=analyze_four_files(files,group='Sasaran',scope='Target tahunan')
        g2=analyze_substantive(g1,parse_ledger(ledger))
        g3=analyze_g2_and_originals(g2,ledger,files)
        g4=assess_relationships(g2,g3)
        g5=assess_direction(g2,g3,g4)
        assert g3['verification_metrics']['edge_quotes_verified']==6
        assert g5['assessment_metrics']['status_counts']=={
            'DIRECTIONAL_ASSERTION_CANDIDATE':5,'EXPLICIT_NEGATION':1}
        assert orientation_screen('Ketepatan pelayanan mendukung Digitalisasi layanan',
            'Digitalisasi layanan','Ketepatan pelayanan','SUPPORTS')[0]=='REVERSED_DIRECTION_CANDIDATE'
        assert orientation_screen('Digitalisasi layanan akan mendukung Ketepatan pelayanan',
            'Digitalisasi layanan','Ketepatan pelayanan','SUPPORTS')[0]=='PROSPECTIVE_RELATION'
        print('PI_G5_E2E_ORIENTATION_MODALITY=PASS_5_CLAIMS_1_DENIAL')
        roles={'reviewer':'reviewer','viewer':'viewer'}
        store=service.build_candidate_store('sqlite:///'+str(root/'review.sqlite'))
        for report in (g1,g2,g3,g4):archive_analysis(store,report,actor='reviewer')
        archived=analyze_archived_g4(store,g4['report_sha256'],actor='reviewer',roles=roles)
        assert archived['report_sha256']==g5['report_sha256']
        event=service.decision(store,archived['report_sha256'],archived['findings'][0]['id'],
                               'reviewer',roles,'NEEDS_EVIDENCE','Syntax does not establish causality')
        assert event['decision']=='NEEDS_EVIDENCE'
        store.dispose()
        restored=service.build_candidate_store('sqlite:///'+str(root/'review.sqlite'))
        assert any(x[3]=='NEEDS_EVIDENCE' for x in service.table(restored,archived['report_sha256'],
                                                         actor='viewer',roles=roles))
        out=service.export(restored,archived['report_sha256'],root/'export','viewer',roles)
        assert len(out)==2 and all(p.is_file() for p in out)
        assert Document(next(p for p in out if p.suffix=='.docx')).paragraphs
        assert json.loads(next(p for p in out if p.suffix=='.json').read_text('utf8'))
        restored.dispose()
        print('PI_G5_E2E_ARCHIVE_REVIEW_EXPORT=PASS')
    print('PI_G5_E2E_PRODUCTION_ADMISSION=DENIED')
    print('PI_G5_E2E_OVERALL=PASS')

if __name__=='__main__':
    try:main()
    except Exception as exc:
        print('PI_G5_E2E_OVERALL=FAIL:'+type(exc).__name__+':'+str(exc),file=sys.stderr,flush=True)
        raise SystemExit(1)
