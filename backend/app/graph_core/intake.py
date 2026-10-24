"""Reuse VS5 secure synthetic parser for the four explicitly selected graph roles."""
from __future__ import annotations
from pathlib import Path
from typing import Mapping
from app.vs1.canonical_bridge import Refused
from app.vs5.intake import _parse_side
from app.vs4.archive import archive_analysis
from app.vs4.service import require_role
from .engine import ROLES, analyze_graph


def analyze_four_files(paths: Mapping[str, str | Path], *, group: str, scope: str) -> dict:
    if not isinstance(paths, Mapping) or set(paths) != set(ROLES):
        raise Refused('FOUR_DOCUMENTS_REQUIRED')
    if not isinstance(group, str) or not group.strip() or len(group) > 150:
        raise Refused('GROUP_REQUIRED')
    if not isinstance(scope, str) or not scope.strip() or len(scope) > 150:
        raise Refused('SCOPE_REQUIRED')
    tuples = {role: _parse_side(paths[role], role, group=group.strip(), scope=scope.strip())
              for role in ROLES}
    return analyze_graph(tuples)


def analyze_and_archive_four(store, paths, *, group: str, scope: str,
                             actor: str, roles: dict[str,str]):
    user = require_role(actor, roles)
    result = analyze_four_files(paths, group=group, scope=scope)
    archive_analysis(store, result, actor=user)
    return result
