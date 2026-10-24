"""Launch PI VS4 SYNTHETIC demo on localhost without Docker.

Reuse Python venv, Gradio login, and VS4 persistence. Never touches PI's
current data store or Docker stack. No sample username/password committed.
"""
from __future__ import annotations
import json
import os
from pathlib import Path
import subprocess
import sys


def main():
    if sys.version_info < (3, 12) or sys.version_info >= (3, 15):
        raise SystemExit('PYTHON_3_12_TO_3_14_REQUIRED')
    root=Path(__file__).resolve().parents[1]
    state=Path.home() / 'PI_VS4_SYNTHETIC_CANDIDATE'
    if state == root or state.is_relative_to(root):
        raise SystemExit('STATE_MUST_BE_OUTSIDE_REPO')
    state.mkdir(parents=True,exist_ok=True)
    envdir=state / 'venv'
    if not (envdir/'pyvenv.cfg').exists():
        subprocess.run([sys.executable,'-m','venv',str(envdir)],check=True)
    interpreter=envdir/('Scripts/python.exe' if os.name=='nt' else 'bin/python')
    if not interpreter.exists():
        raise SystemExit('VENV_PYTHON_MISSING')
    subprocess.run([str(interpreter),'-m','pip','install','-r',
                    str(root/'backend'/'requirements-vs4.txt')],check=True)
    # PI_UNIFIED_LOCAL_LOGIN_V1: shared encrypted reviewer account across phases.
    from pi_shared_auth import load_credentials
    user, password = load_credentials()
    env = dict(os.environ)
    env.update({
        'PI_VS4_LOCAL_AUTH_JSON': json.dumps({user: password}),
        'PI_VS4_ROLES_JSON': json.dumps({user: 'reviewer'}),
        'PI_VS4_REVIEW_SQLITE_PATH': str(state / 'reviews.sqlite'),
        'PI_VS4_OUTPUT_DIR': str(state / 'exports'),
    })
    env.pop('PI_VS4_CANONICAL_DATABASE_URL', None)
    print('PI_SHARED_ACCOUNT=' + user, flush=True)
    print('PI_SHARED_AUTH=WINDOWS_DPAPI', flush=True)

    print('PI_VS4_MODE=SYNTHETIC_ONLY')
    print('PI_VS4_URL=http://127.0.0.1:8095')
    print('CANDIDATE_STORE='+str(state))
    subprocess.run([str(interpreter),'-m','app.vs4.reviewer_ui','--port','8095'],
                   check=True,cwd=str(root/'backend'),env=env)

if __name__=='__main__':main()
