"""CLI for PI canonical SourceUnit VS1 candidate. Default = synthetic fixture.

Does not expose an API, migrate databases, parse user uploads, or open port 8080.
"""
from __future__ import annotations
import argparse
import json
from pathlib import Path
from .canonical_bridge import compare_existing_sources, Refused
from .report import export_report


def main(argv=None):
    p=argparse.ArgumentParser(description='PI VS1 canonical-source synthetic candidate, no production admission')
    p.add_argument('--fixture',type=Path, help='Offline JSON containing left/right canonical-shaped test records')
    p.add_argument('--out',type=Path,required=True,help='Write reports outside repository')
    p.add_argument('--left-run',help='Existing SYNTHETIC_FIXTURE parsing run UUID')
    p.add_argument('--right-run',help='Existing SYNTHETIC_FIXTURE parsing run UUID')
    p.add_argument('--group',help='Explicit synthetic comparison group, not lineage evidence')
    p.add_argument('--scope',help='Explicit synthetic comparison scope, not legal evidence')
    args=p.parse_args(argv)
    if args.fixture is not None and (args.left_run or args.right_run):p.error('Choose fixture OR two synthetic PostgreSQL run IDs')
    if args.fixture:
        raw=json.loads(args.fixture.read_text(encoding='utf-8'))
        result=compare_existing_sources(tuple(raw['left']),tuple(raw['right']))
    elif args.left_run and args.right_run:
        if not args.group or not args.scope:p.error('Database synthetic run comparison requires explicit --group and --scope')
        from app.db import SessionLocal
        from .orm_loader import compare_synthetic_parsing_runs
        with SessionLocal() as session:
            result=compare_synthetic_parsing_runs(session,args.left_run,args.right_run,group=args.group,scope=args.scope)
            session.rollback()
    else:p.error('Provide --fixture OR both --left-run and --right-run')
    paths=export_report(result,args.out)
    print('PI_VS1_SYNTHETIC_ONLY=TRUE')
    print('FINDINGS='+str(len(result['findings'])))
    print('JSON='+str(paths[0]))
    print('REVIEW_HTML='+str(paths[1]))
    print('PRODUCTION_ADMISSION=DENIED')
    return 0

if __name__=='__main__':raise SystemExit(main())
