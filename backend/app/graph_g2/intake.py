"""Reuse archived G1 graph, SQLAlchemy reviewer store and Word/JSON exporter."""
from pathlib import Path
from app.vs4 import service
from app.vs4.archive import archive_analysis
from .engine import parse_ledger, analyze_substantive


def analyze_g1_archive_and_ledger(store, g1_digest, ledger_path, *, actor, roles):
    user=service.require_role(actor,roles)
    old=service.resume(store,g1_digest,actor=user,roles=roles)
    analysis=analyze_substantive(old,parse_ledger(Path(ledger_path)))
    archive_analysis(store,analysis,actor=user)
    return analysis
