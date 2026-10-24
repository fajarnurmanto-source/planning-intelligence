"""Launch VS5 using ready-made Gradio, PI parser, and isolated local candidate store.

No Docker, no PI production database, no port 8080, no static credential.
"""
from __future__ import annotations
import json
import os
from pathlib import Path
import subprocess
import sys


def main():
    if not (3, 12) <= sys.version_info[:2] <= (3, 14):
        raise SystemExit('PYTHON_3_12_TO_3_14_REQUIRED')
    root=Path(__file__).resolve().parents[1]
    state=Path.home()/'PI_VS5_SYNTHETIC_CANDIDATE'
    if state.is_relative_to(root):
        raise SystemExit('CANDIDATE_STORE_MUST_BE_OUTSIDE_REPOSITORY')
    state.mkdir(parents=True,exist_ok=True)
    # Reuse the already installed VS4 venv when available; no duplicate installation.
    prior=Path.home()/'PI_VS4_SYNTHETIC_CANDIDATE'/'venv'
    venv=prior if (prior/'pyvenv.cfg').is_file() else state/'venv'
    if not (venv/'pyvenv.cfg').is_file():
        subprocess.run([sys.executable,'-m','venv',str(venv)],check=True)
    py=venv/('Scripts/python.exe' if os.name=='nt' else 'bin/python')
    if not py.is_file():
        raise SystemExit('VENV_INTERPRETER_MISSING')
    imports=subprocess.run([str(py),'-c','import gradio,sqlalchemy,fitz,docx,openpyxl'],
                           capture_output=True,text=True)
    if imports.returncode:
        subprocess.run([str(py),'-m','pip','install','-r',str(root/'backend'/'requirements-vs4.txt')],check=True)
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

    print('PI_VS5=SYNTHETIC_BROWSER_DOCUMENT_INTAKE',flush=True)
    print('LOCAL_URL=http://127.0.0.1:8096',flush=True)
    print('SAMPLE_1='+str(root/'backend'/'tests'/'fixtures'/'Renstra_PI_SYNTHETIC.xlsx'),flush=True)
    print('SAMPLE_2='+str(root/'backend'/'tests'/'fixtures'/'Renja_PI_SYNTHETIC.xlsx'),flush=True)
    subprocess.run([str(py),'-m','app.vs4.reviewer_ui','--port','8096'],
                   check=True,cwd=str(root/'backend'),env=env)

if __name__=='__main__': main()
