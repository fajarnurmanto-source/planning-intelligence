"""Local G6 E2E on same trusted PI venv; default no model, no download, no spend."""
from __future__ import annotations
import json,os
from pathlib import Path
import subprocess,sys,tempfile


def main():
    repo=Path(__file__).resolve().parents[1]
    if '--internal' not in sys.argv:
        venv=Path.home()/'PI_VS4_SYNTHETIC_CANDIDATE'/'venv'
        py=venv/('Scripts/python.exe' if os.name=='nt' else 'bin/python')
        if not py.is_file():raise RuntimeError('G6_EXISTING_PI_VENV_REQUIRED')
        rc=subprocess.run([str(py),__file__,'--internal'],cwd=str(repo)).returncode
        if rc:raise RuntimeError('G6_E2E_CHILD_FAILED')
        return
    sys.path.insert(0,str(repo/'backend'))
    from app.graph_core.intake import analyze_four_files
    from app.graph_g2.engine import parse_ledger,analyze_substantive
    from app.graph_g3.engine import analyze_g2_and_originals
    from app.graph_g4.engine import assess_relationships
    from app.graph_g4.samples import make_assessment_set
    from app.graph_g5.engine import assess_direction
    from app.graph_g6.engine import assess_semantic
    from app.graph_g6.benchmark import evaluate_benchmark
    from app.graph_g6.intake import analyze_archived_g5
    from app.vs4 import service
    from app.vs4.archive import archive_analysis
    with tempfile.TemporaryDirectory(prefix='pi-g6-synthetic-') as td:
        root=Path(td);files,ledger=make_assessment_set(root/'docs')
        g1=analyze_four_files(files,group='Sasaran',scope='Target tahunan')
        g2=analyze_substantive(g1,parse_ledger(ledger))
        g3=analyze_g2_and_originals(g2,ledger,files)
        g4=assess_relationships(g2,g3);g5=assess_direction(g2,g3,g4)
        g6=assess_semantic(g2,g3,g4,g5)
        assert g6['assessment_metrics']['scored']==0
        assert g6['assessment_metrics']['status_counts'].get('NO_MODEL_ABSTAIN')==5
        assert g6['assessment_metrics']['status_counts'].get('BLOCKED_BY_DIRECTION_OR_NEGATION')==1
        assert evaluate_benchmark()['status']=='NOT_RUN_NO_LOCAL_MODEL'
        print('PI_G6_NO_MODEL_FAIL_CLOSED=PASS',flush=True)
        roles={'reviewer':'reviewer','viewer':'viewer'}
        store=service.build_candidate_store('sqlite:///'+str(root/'r.sqlite'))
        for item in (g1,g2,g3,g4,g5):archive_analysis(store,item,actor='reviewer')
        result=analyze_archived_g5(store,g5['report_sha256'],actor='reviewer',roles=roles)
        assert result['report_sha256']==g6['report_sha256']
        e=service.decision(store,result['report_sha256'],result['findings'][0]['id'],
                           'reviewer',roles,'NEEDS_EVIDENCE','Model unavailable; abstain')
        assert e['decision']=='NEEDS_EVIDENCE'
        store.dispose()
        restore=service.build_candidate_store('sqlite:///'+str(root/'r.sqlite'))
        assert any(row[3]=='NEEDS_EVIDENCE' for row in service.table(restore,result['report_sha256'],actor='viewer',roles=roles))
        out=service.export(restore,result['report_sha256'],root/'out','viewer',roles)
        assert all(x.exists() for x in out)
        assert json.loads(next(x for x in out if x.suffix=='.json').read_text('utf8'))
        restore.dispose()
        print('PI_G6_ARCHIVE_REVIEW_EXPORT=PASS',flush=True)
        print('PI_G6_PRODUCTION_ADMISSION=DENIED',flush=True)
    print('PI_G6_E2E_OVERALL=PASS',flush=True)

if __name__=='__main__':
    try:main()
    except Exception as exc:
        print('PI_G6_E2E_OVERALL=FAIL:'+type(exc).__name__+':'+str(exc),file=sys.stderr,flush=True)
        raise SystemExit(1)
