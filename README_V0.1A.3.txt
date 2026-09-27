Planning Intelligence V0.1A.3 — Deterministic Document Parsing

Apply this package only after V0.1A.2 Provenance Schema has passed and repository
hygiene is clean.

Files:
- backend/requirements.txt                 (replace)
- backend/app/main.py                      (replace)
- backend/app/parser.py                    (new)
- backend/tests/parser_selftest.py         (new)
- docs/V0.1A3_DETERMINISTIC_PARSING_CONTRACT.md
- scripts/v0_1a3_apply_and_test.ps1
- 09_V0.1A3_APPLY_AND_TEST.bat

No Alembic migration is needed in this stage. V0.1A.2 already created
parsing_runs and source_units.

Before applying:
- branch should be v0.1-provenance-ingestion
- git status should be clean
- Docker should be healthy

Run:
  .\09_V0.1A3_APPLY_AND_TEST.bat

Acceptance requires:
- safety backup
- backend builds
- PDF/DOCX/XLSX pure parser self-test PASS
- existing immutable registered document version parses successfully
- two forced runs produce identical run fingerprint/result hash/unit count
- source-unit hashes are present
- V0.0 core entity counts remain unchanged
- DOCUMENT_PARSED events are written

Do not commit until acceptance PASS.
