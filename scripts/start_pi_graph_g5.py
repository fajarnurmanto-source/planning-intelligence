"""Start G5 as a synthetic localhost review, with unchanged DPAPI and G3 archive."""
from __future__ import annotations
import json
import os
from pathlib import Path
import subprocess


def main():
    repo=Path(__file__).resolve().parents[1]
    if not (repo/'backend/app/graph_g5/engine.py').is_file():
        raise SystemExit('G5_MODULE_NOT_FOUND')
    state=Path.home()/'PI_GRAPH_G3_SYNTHETIC_CANDIDATE'
    archive=state/'review.sqlite'
    if not archive.is_file():raise SystemExit('G5_EXISTING_G3_ARCHIVE_REQUIRED')
    venv=Path.home()/'PI_VS4_SYNTHETIC_CANDIDATE'/'venv'
    py=venv/('Scripts/python.exe' if os.name=='nt' else 'bin/python')
    if not py.is_file():raise SystemExit('G5_EXISTING_VS4_VENV_REQUIRED')
    probe=subprocess.run([str(py),'-c','import gradio, networkx, sqlalchemy, pymupdf, docx, openpyxl'],
                         capture_output=True,text=True)
    if probe.returncode:raise SystemExit('G5_EXISTING_VENV_MISSING_MODULE')
    from pi_shared_auth import load_credentials
    user,password=load_credentials()
    env=dict(os.environ)
    env.update({'PI_VS4_LOCAL_AUTH_JSON':json.dumps({user:password}),
                'PI_VS4_ROLES_JSON':json.dumps({user:'reviewer'}),
                'PI_VS4_REVIEW_SQLITE_PATH':str(archive),
                'PI_VS4_OUTPUT_DIR':str(state/'reports'),
                'PI_G4_SYNTHETIC_SAMPLES_DIR':str(state/'inputs_g4')})
    env.pop('PI_VS4_CANONICAL_DATABASE_URL',None)
    print('PI_GRAPH_G5=DIRECTION_AND_MODALITY_SYNTHETIC',flush=True)
    print('PI_G5_ARCHIVE=REUSE_EXISTING_G3_G4',flush=True)
    print('PI_G5_AUTH=REUSE_WINDOWS_DPAPI',flush=True)
    print('LOCAL_URL=http://127.0.0.1:8101',flush=True)
    print('PRODUCTION_ADMISSION=DENIED',flush=True)
    subprocess.run([str(py),'-m','app.vs4.reviewer_ui','--port','8101'],
                   cwd=str(repo/'backend'),env=env,check=True)

if __name__=='__main__':main()
