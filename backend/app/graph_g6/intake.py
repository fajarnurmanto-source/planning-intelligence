"""Read existing G5→G4→G3→G2 archive, append G6 without re-upload."""
from app.vs4 import service
from app.vs4.archive import archive_analysis
from .engine import assess_semantic


def analyze_archived_g5(store, g5_digest, *,actor,roles,embedder=None):
    user=service.require_role(actor,roles)
    g5=service.resume(store,g5_digest,actor=user,roles=roles)
    if g5.get('schema')!='pi.graph_g5.direction_modality.synthetic.v1':
        raise ValueError('G6_SELECT_ARCHIVED_G5')
    g4=service.resume(store,g5['source_g4_sha256'],actor=user,roles=roles)
    g3=service.resume(store,g5['source_g3_sha256'],actor=user,roles=roles)
    g2=service.resume(store,g5['source_g2_sha256'],actor=user,roles=roles)
    out=assess_semantic(g2,g3,g4,g5,embedder)
    archive_analysis(store,out,actor=user)
    return out
