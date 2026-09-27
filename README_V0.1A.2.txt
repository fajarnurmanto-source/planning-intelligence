Planning Intelligence V0.1A.2 Patch
=======================================

This patch is based on the actual V0.0 source and schema supplied in
PI_V0.1A_SCHEMA_INPUT.zip.

What it adds:
- immutable document_versions;
- reproducible parsing_runs;
- addressable source_units;
- evidence links to document_version/source_unit;
- automatic backfill of existing V0.0 documents into version 1;
- new document uploads automatically create version 1;
- API to list versions and upload a later version of the same logical document;
- acceptance test that verifies V0.0 counts and Audit Ledger are preserved.

No AI provider is used.

INSTALL
1. Confirm current branch:
   git branch --show-current
   Expected: v0.1-provenance-ingestion

2. Extract this ZIP into the PROJECT ROOT:
   D:\Research\PlanningIntelligence\planning-intelligence-v0.0-starter\planning-intelligence-v0.0

3. Allow Windows to overwrite:
   backend\app\models.py
   backend\app\main.py
   and add the new migration/docs/scripts.

4. Run:
   .\08_V0.1A_APPLY_AND_TEST.bat

5. Do NOT commit until the acceptance test reports PASS.

Expected report:
docs\tests\V0.1A_SCHEMA_PROVENANCE_TEST.md

Rollback safety:
- The test creates a normal Planning Intelligence backup before migration.
- Git branch v0.1-provenance-ingestion isolates source changes from the tagged
  v0.0-foundation baseline.
- Alembic migration 0002 includes a downgrade.
- Do not manually delete PostgreSQL volumes.
