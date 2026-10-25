"""Archive G5 findings from existing G2-G4; no re-upload, no new account."""
from app.vs4 import service
from app.vs4.archive import archive_analysis
from .engine import assess_direction


def analyze_archived_g4(store, g4_digest, *,actor,roles):
    user=service.require_role(actor,roles)
    g4=service.resume(store,g4_digest,actor=user,roles=roles)
    if g4.get('schema')!='pi.graph_g4.literal_relation_screen.synthetic.v1':
        raise ValueError('G5_SELECT_ARCHIVED_G4')
    g3=service.resume(store,g4['source_g3_sha256'],actor=user,roles=roles)
    g2=service.resume(store,g4['source_g2_sha256'],actor=user,roles=roles)
    result=assess_direction(g2,g3,g4)
    archive_analysis(store,result,actor=user)
    return result


def synthetic_g5_example(store,sample_dir,*,actor,roles):
    from app.graph_g4.intake import synthetic_g4_example
    user=service.require_role(actor,roles)
    g4=synthetic_g4_example(store,sample_dir,actor=user,roles=roles)
    return analyze_archived_g4(store,g4['report_sha256'],actor=user,roles=roles)
