"""Launch G2 synthetic continuation of existing VS4/VS5/G1 in separate localhost 8098."""
from __future__ import annotations
from pathlib import Path
import json
import os
import secrets
import subprocess
import sys


def main():
    root=Path(__file__).resolve().parents[1]
    if not (root/'backend/app/graph_g2/engine.py').is_file():
        raise SystemExit('G2_MODULE_NOT_FOUND')
    state=Path.home()/'PI_GRAPH_G2_SYNTHETIC_CANDIDATE'
    state.mkdir(parents=True,exist_ok=True)
    existing=Path.home()/'PI_VS4_SYNTHETIC_CANDIDATE'/'venv'
    venv= existing if (existing/'pyvenv.cfg').is_file() else state/'venv'
    if not (venv/'pyvenv.cfg').is_file():
        subprocess.run([sys.executable,'-m','venv',str(venv)],check=True)
    python=venv/('Scripts/python.exe' if os.name=='nt' else 'bin/python')
    if not python.is_file():raise SystemExit('VENV_PYTHON_NOT_FOUND')
    check=subprocess.run([str(python),'-c','import gradio,networkx,sqlalchemy,pymupdf,docx,openpyxl'],
                         capture_output=True,text=True)
    if check.returncode:
        subprocess.run([str(python),'-m','pip','install','-r',str(root/'backend/requirements-graph-g2.txt')],check=True)
    inputs=state/'inputs'
    code=("from pathlib import Path; from app.graph_core.samples import make_four; "
          "from app.graph_g2.samples import make_ledger; "
          "p=Path(r"+repr(str(inputs))+"); make_four(p); make_ledger(p); make_ledger(p,break_link=True)")
    subprocess.run([str(python),'-c',code],cwd=str(root/'backend'),check=True)
    reviewer=secrets.token_urlsafe(28)
    viewer=secrets.token_urlsafe(28)
    auth_file=state/'login-local.txt'
    fd=os.open(str(auth_file),os.O_WRONLY|os.O_CREAT|os.O_TRUNC,0o600)
    with os.fdopen(fd,'w',encoding='utf-8') as out:
        out.write('PI G2 — SYNTHETIC, LOCAL ONLY\n')
        out.write('reviewer: local-reviewer\npassword: '+reviewer+'\n')
        out.write('viewer: local-viewer\npassword: '+viewer+'\n')
        out.write('Regenerated each launch; do not post credentials.\n')
    env=dict(os.environ)
    env.update({'PI_VS4_LOCAL_AUTH_JSON':json.dumps({'local-reviewer':reviewer,'local-viewer':viewer}),
                'PI_VS4_ROLES_JSON':json.dumps({'local-reviewer':'reviewer','local-viewer':'viewer'}),
                'PI_VS4_REVIEW_SQLITE_PATH':str(state/'review.sqlite'),
                'PI_VS4_OUTPUT_DIR':str(state/'reports')})
    env.pop('PI_VS4_CANONICAL_DATABASE_URL',None)
    print('PI_GRAPH_G2=SYNTHETIC_SUBSTANTIVE_PLANNING_COHERENCE',flush=True)
    print('LOCAL_URL=http://127.0.0.1:8098',flush=True)
    print('LOCAL_CREDENTIALS_FILE='+str(auth_file),flush=True)
    print('SAMPLE_FOLDER='+str(inputs),flush=True)
    print('PRODUCTION_ADMISSION=DENIED',flush=True)
    subprocess.run([str(python),'-m','app.vs4.reviewer_ui','--port','8098'],
                   cwd=str(root/'backend'),env=env,check=True)

if __name__=='__main__':main()
