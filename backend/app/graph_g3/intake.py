"""Archive G3 findings using the existing append-only review store."""
from app.graph_g3.engine import analyze_g2_and_originals
from app.vs4 import service
from app.vs4.archive import archive_analysis


def analyze_g2_archive_and_originals(store,g2_digest,ledger_path,source_paths,*,actor,roles):
    user=service.require_role(actor,roles)
    g2=service.resume(store,g2_digest,actor=user,roles=roles)
    result=analyze_g2_and_originals(g2,ledger_path,source_paths)
    archive_analysis(store,result,actor=user)
    return result
