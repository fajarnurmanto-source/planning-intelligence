"""Launch merged G1 synthetic-only Gradio UI; no Docker or operational DB."""
from __future__ import annotations
import json
import os
from pathlib import Path
import subprocess
import sys


def main():
    root = Path(__file__).resolve().parents[1]
    if not (root/'backend/app/graph_core/engine.py').is_file():
        raise SystemExit('GRAPH_MODULE_NOT_FOUND_IN_REPOSITORY')
    state = Path.home()/'PI_GRAPH_G1_SYNTHETIC_CANDIDATE'
    state.mkdir(parents=True, exist_ok=True)
    former=Path.home()/'PI_VS4_SYNTHETIC_CANDIDATE'/'venv'
    venv = former if (former/'pyvenv.cfg').is_file() else state/'venv'
    if not (venv/'pyvenv.cfg').is_file():
        subprocess.run([sys.executable,'-m','venv',str(venv)],check=True)
    python=venv/('Scripts/python.exe' if os.name=='nt' else 'bin/python')
    if not python.is_file():
        raise SystemExit('VENV_INTERPRETER_NOT_FOUND')
    test=subprocess.run([str(python),'-c','import gradio,networkx,sqlalchemy,pymupdf,docx,openpyxl'],
                        capture_output=True,text=True)
    if test.returncode:
        subprocess.run([str(python),'-m','pip','install','-r',str(root/'backend/requirements-graph-g1.txt')],
                       check=True)
    samples = state/'inputs'
    code='from app.graph_core.samples import make_four; from pathlib import Path; make_four(Path(r"'+str(samples)+'"))'
    subprocess.run([str(python),'-c',code],check=True,cwd=str(root/'backend'))
    # PI_UNIFIED_LOCAL_LOGIN_V1: shared encrypted reviewer account across phases.
    from pi_shared_auth import load_credentials
    user, password = load_credentials()
    env = dict(os.environ)
    env.update({
        'PI_VS4_LOCAL_AUTH_JSON': json.dumps({user: password}),
        'PI_VS4_ROLES_JSON': json.dumps({user: 'reviewer'}),
        'PI_VS4_REVIEW_SQLITE_PATH': str(state / 'reviews.sqlite'),
        'PI_VS4_OUTPUT_DIR': str(state / 'reports'),
    })
    env.pop('PI_VS4_CANONICAL_DATABASE_URL', None)
    print('PI_SHARED_ACCOUNT=' + user, flush=True)
    print('PI_SHARED_AUTH=WINDOWS_DPAPI', flush=True)

    print('PI_GRAPH_G1=PLANNING_KNOWLEDGE_GRAPH_SYNTHETIC',flush=True)
    print('LOCAL_URL=http://127.0.0.1:8097',flush=True)
    print('SAMPLE_FOLDER='+str(samples),flush=True)
    print('PRODUCTION_ADMISSION=DENIED',flush=True)
    print('No local operational PostgreSQL, source repository or port 8080 mutations.',flush=True)
    subprocess.run([str(python),'-m','app.vs4.reviewer_ui','--port','8097'],
                   cwd=str(root/'backend'),env=env,check=True)

if __name__=='__main__':
    main()
