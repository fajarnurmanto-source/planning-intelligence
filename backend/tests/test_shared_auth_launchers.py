"""Regression guard: stable DPAPI login and executable G3 launcher.

Does not decrypt credentials on CI/Linux, start a server, or access PI I0 data.
"""
from __future__ import annotations
import ast
import importlib.util
import os
from pathlib import Path
import tempfile
import pytest

ROOT=Path(__file__).resolve().parents[2]
SCRIPTS=ROOT/'scripts'
LAUNCHERS={
    'start_pi_vs4_demo.py':8095,
    'start_pi_vs5_demo.py':8096,
    'start_pi_graph_g1.py':8097,
    'start_pi_graph_g2.py':8098,
    'start_pi_graph_g3.py':8099,
}

@pytest.mark.parametrize('name,port',LAUNCHERS.items())
def test_compiles_and_uses_persistent_single_login(name,port):
    path=SCRIPTS/name
    src=path.read_text(encoding='utf-8')
    compile(src, str(path), 'exec')
    assert 'from pi_shared_auth import load_credentials' in src
    assert "'PI_VS4_LOCAL_AUTH_JSON': json.dumps({user: password})" in src
    assert "'PI_VS4_ROLES_JSON': json.dumps({user: 'reviewer'})" in src
    assert 'PI_UNIFIED_LOCAL_LOGIN_V1' in src
    assert 'secrets.token_urlsafe' not in src
    assert 'login-local.txt' not in src
    assert 'writer.write(' not in src and 'out.write(' not in src
    assert "'--port'," in src and str(port) in src
    assert 'PI_SHARED_AUTH=WINDOWS_DPAPI' in src
    assert "env.pop('PI_VS4_CANONICAL_DATABASE_URL', None)" in src


def test_g3_sample_generator_command_compiles():
    script=SCRIPTS/'start_pi_graph_g3.py'
    tree=ast.parse(script.read_text())
    assignment=next(n for n in ast.walk(tree) if isinstance(n,ast.Assign) and
                    len(n.targets)==1 and isinstance(n.targets[0],ast.Name) and n.targets[0].id=='code')
    with tempfile.TemporaryDirectory(prefix='pi-g3-test-') as tmp:
        command=eval(compile(ast.Expression(assignment.value),str(script),'eval'),
                     {'str':str,'Path':Path,'inputs':Path(tmp)})
    compile(command,'generated_g3_sample_command','exec')
    assert 'make_grounded_set' in command
    assert "change_quote=True" in command


def test_shared_dpapi_reader_fails_closed_without_setup(tmp_path,monkeypatch):
    helper=SCRIPTS/'pi_shared_auth.py'
    compile(helper.read_text(), str(helper),'exec')
    spec=importlib.util.spec_from_file_location('pi_shared_auth_test',helper)
    m=importlib.util.module_from_spec(spec)
    spec.loader.exec_module(m)
    monkeypatch.setattr(m,'AUTH_FILE',tmp_path/'missing.dpapi.json')
    with pytest.raises(RuntimeError, match='PI_SHARED_LOGIN_NOT_INITIALIZED'):
        m.load_credentials()
    assert m.AUTH_FILE.name.endswith('.dpapi.json')


def test_auth_does_not_embed_secrets_or_enable_production():
    helper=(SCRIPTS/'pi_shared_auth.py').read_text(encoding='utf8')
    assert 'CryptUnprotectData' in helper
    assert 'LOCALAPPDATA' in helper
    assert 'PI_LOCAL_DPAPI_V1' in helper
    assert 'PI_SHARED_LOGIN_REQUIRES_WINDOWS' in helper
    for file in LAUNCHERS:
        source=(SCRIPTS/file).read_text(encoding='utf8')
        assert 'share=True' not in source
        assert 'PI_VS4_CANONICAL_DATABASE_URL' in source
