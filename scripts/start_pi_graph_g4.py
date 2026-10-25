"""Launch G4 with prior G3 candidate archive and existing Windows DPAPI account.

No new datastore or credentials. Purely synthetic localhost. No Docker, canonical
PostgreSQL, original working tree or production source document touched.
"""
from __future__ import annotations
import json
import os
from pathlib import Path
import subprocess
import sys


def main():
    repo=Path(__file__).resolve().parents[1]
    if not (repo/'backend/app/graph_g4/engine.py').is_file():
        raise SystemExit('G4_MODULE_NOT_FOUND')
    state=Path.home()/'PI_GRAPH_G3_SYNTHETIC_CANDIDATE'
    archive=state/'review.sqlite'
    if not archive.is_file():
        raise SystemExit('G4_EXISTING_G3_ARCHIVE_REQUIRED_RUN_G3_FIRST')
    venv=Path.home()/'PI_VS4_SYNTHETIC_CANDIDATE'/'venv'
    python=venv/('Scripts/python.exe' if os.name=='nt' else 'bin/python')
    if not python.is_file():
        raise SystemExit('G4_EXISTING_VS4_VENV_REQUIRED')
    requirements=('import gradio, networkx, sqlalchemy, pymupdf, docx, openpyxl',)
    check=subprocess.run([str(python),'-c',requirements[0]],capture_output=True,text=True)
    if check.returncode:
        raise SystemExit('G4_MISSING_DEPENDENCY_IN_EXISTING_VENV')
    from pi_shared_auth import load_credentials
    user,password=load_credentials()
    env=dict(os.environ)
    env.update({'PI_VS4_LOCAL_AUTH_JSON':json.dumps({user:password}),
                'PI_VS4_ROLES_JSON':json.dumps({user:'reviewer'}),
                'PI_VS4_REVIEW_SQLITE_PATH':str(archive),
                'PI_VS4_OUTPUT_DIR':str(state/'reports'),
                'PI_G4_SYNTHETIC_SAMPLES_DIR':str(state/'inputs_g4')})
    env.pop('PI_VS4_CANONICAL_DATABASE_URL',None)
    print('PI_GRAPH_G4=EXPLICIT_EVIDENCE_SCREEN_SYNTHETIC',flush=True)
    print('PI_G4_ARCHIVE=REUSE_EXISTING_G3',flush=True)
    print('PI_G4_AUTH=REUSE_WINDOWS_DPAPI',flush=True)
    print('LOCAL_URL=http://127.0.0.1:8100',flush=True)
    print('PRODUCTION_ADMISSION=DENIED',flush=True)
    subprocess.run([str(python),'-m','app.vs4.reviewer_ui','--port','8100'],
                   cwd=str(repo/'backend'),env=env,check=True)

if __name__=='__main__':main()
