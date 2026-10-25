"""G4 archive-to-archive evidence screening; requires no new source uploads."""
from app.graph_g4.engine import assess_relationships
from app.vs4 import service
from app.vs4.archive import archive_analysis


def analyze_archived_g2_g3(store, g3_digest, *,actor,roles):
    user=service.require_role(actor,roles)
    g3=service.resume(store,g3_digest,actor=user,roles=roles)
    if g3.get('schema')!='pi.graph_g3.document_grounded.synthetic.v1':
        raise ValueError('G4_SELECT_ARCHIVED_G3')
    g2=service.resume(store,g3['source_g2_sha256'],actor=user,roles=roles)
    result=assess_relationships(g2,g3)
    archive_analysis(store,result,actor=user)
    return result


def synthetic_g4_example(store, sample_dir, *,actor,roles):
    """One-click archived G1-G4 synthetic fixture. Idempotent on fixed files."""
    from pathlib import Path
    from app.graph_g4.samples import make_assessment_set
    from app.graph_core.intake import analyze_four_files
    from app.graph_g2.engine import parse_ledger,analyze_substantive
    from app.graph_g3.engine import analyze_g2_and_originals
    user=service.require_role(actor,roles)
    folder=Path(sample_dir)
    if not folder.is_absolute() or not folder.parent.is_dir():
        raise ValueError('G4_SYNTHETIC_SAMPLE_FOLDER_REQUIRED')
    expected=folder/'claims'/'G2_CLAIM_LEDGER_SYNTHETIC.xlsx'
    if expected.is_file():
        ledger=expected
        files={r: folder/'originals'/f'{r}_PI_SYNTHETIC.xlsx'
               for r in ('RPJMD','RKPD','RENSTRA','RENJA')}
        if not all(p.is_file() for p in files.values()):
            raise ValueError('G4_SAMPLE_SET_INCOMPLETE_REVIEW')
    else:
        if folder.exists() and any(folder.iterdir()):
            raise ValueError('G4_SAMPLE_FOLDER_NOT_EMPTY_REVIEW')
        files,ledger=make_assessment_set(folder)
    g1=analyze_four_files(files,group='Sasaran',scope='Target tahunan')
    g2=analyze_substantive(g1,parse_ledger(ledger))
    g3=analyze_g2_and_originals(g2,ledger,files)
    for result in (g1,g2,g3):archive_analysis(store,result,actor=user)
    return analyze_archived_g2_g3(store,g3['report_sha256'],actor=user,roles=roles)
