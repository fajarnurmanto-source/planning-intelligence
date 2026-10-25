"""Start G6 in the existing G3/G4/G5 review store, shared Windows DPAPI account."""
from __future__ import annotations
import json,os
from pathlib import Path
import subprocess


def main():
    repo=Path(__file__).resolve().parents[1]
    if not (repo/'backend/app/graph_g6/engine.py').exists():
        raise SystemExit('G6_ENGINE_MISSING')
    state=Path.home()/'PI_GRAPH_G3_SYNTHETIC_CANDIDATE'
    archive=state/'review.sqlite'
    if not archive.is_file():raise SystemExit('G6_EXISTING_G3_ARCHIVE_REQUIRED')
    venv=Path.home()/'PI_VS4_SYNTHETIC_CANDIDATE'/'venv'
    py=venv/('Scripts/python.exe' if os.name=='nt' else 'bin/python')
    if not py.is_file():raise SystemExit('G6_EXISTING_PI_VENV_REQUIRED')
    from pi_shared_auth import load_credentials
    user,password=load_credentials()
    env=dict(os.environ)
    env.update({'PI_VS4_LOCAL_AUTH_JSON':json.dumps({user:password}),
                'PI_VS4_ROLES_JSON':json.dumps({user:'reviewer'}),
                'PI_VS4_REVIEW_SQLITE_PATH':str(archive),
                'PI_VS4_OUTPUT_DIR':str(state/'reports'),
                'PI_G4_SYNTHETIC_SAMPLES_DIR':str(state/'inputs_g4')})
    env.pop('PI_VS4_CANONICAL_DATABASE_URL',None)
    provider=env.get('PI_G6_PROVIDER','disabled')
    if provider not in ('disabled','ollama-local','cached-sentence-transformer'):
        raise SystemExit('G6_PROVIDER_NOT_ALLOWED')
    print('PI_GRAPH_G6=LOCAL_SEMANTIC_RELEVANCE_SYNTHETIC',flush=True)
    print('PI_G6_PROVIDER='+provider,flush=True)
    print('PI_G6_AUTH=REUSE_WINDOWS_DPAPI',flush=True)
    print('PI_G6_ARCHIVE=REUSE_G3_G4_G5',flush=True)
    print('LOCAL_URL=http://127.0.0.1:8102',flush=True)
    print('PRODUCTION_ADMISSION=DENIED',flush=True)
    subprocess.run([str(py),'-m','app.vs4.reviewer_ui','--port','8102'],
                   cwd=str(repo/'backend'),env=env,check=True)

if __name__=='__main__':main()
