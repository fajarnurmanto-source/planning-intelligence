# VS4 — Operational Continuity (synthetic candidate)

## What changed

VS1 parses canonical PI SourceUnits into evidence-linked comparisons. VS2 already stores review decisions in SQLAlchemy, and VS3 reads synthetic ParsingRuns from canonical PostgreSQL. VS4 **reuses all three** and adds exactly what was missing for a usable continuity workflow: an immutable, SHA-validated analysis archive, resume after restart, event-sourced reviewer actions, read-only viewer role, and one-click Word/JSON export from the same saved analysis.

No Laravel migration, new parser, login framework, bespoke ORM, or operational database migration.

## Try it without Docker

In a fresh clone **after the VS4 PR merges**, run:

```powershell
py -3 scripts/start_pi_vs4_demo.py
```

Or `python scripts/start_pi_vs4_demo.py` (Python 3.12–3.14). The launcher creates a virtual environment and state under `~/PI_VS4_SYNTHETIC_CANDIDATE/`, **outside the source repository**. After dependency installation, open `http://127.0.0.1:8095`; use the random reviewer login printed by the launcher (do not share passwords). Click **Jalankan demo sintetis**, inspect a finding, save a test-only review, export Word, close and reopen, and use **Muat arsip** to resume. Viewer credentials can read and export but cannot submit decisions.

Optional advanced source mode: set `PI_VS4_CANONICAL_DATABASE_URL` to a PostgreSQL `SELECT`-only credential before launching `python -m app.vs4.reviewer_ui`. VS3 permits only synthetic fixture source-kind. Use a separate local SQLite review store; original PI tables stay read-only.

## Boundaries and status

- All VS4 documents/analyses **SYNTHETIC_ONLY**, `production_admission=DENIED`, `review_authority=NOT_GRANTED`.
- **No formal identity provider**: Gradio's existing login is local candidate access, *not* government institution authentication or production approval.
- New SQLite analysis archive plus existing VS2 SQLite append-only review actions: both reside outside the repo. The source PI PostgreSQL is read-only.
- Analysis snapshot includes limited **synthetic evidence excerpts**, not original document binary data. No general document ingestion or production migration in this patch.
- Existing anonymous 8080 remains unchanged and is **not** reused.
- CI uses synthetic + parser interoperability tests. A PostgreSQL end-to-end runtime connection must be tested separately with a disposable DB and approved non-sensitive data; this PR cannot certify production.

## Acceptance

```bash
python -m pip install -r backend/requirements-vs4.txt
python -m pytest -q backend/tests/test_coherence_vs1.py backend/tests/test_review_vs2.py backend/tests/test_canonical_review_vs3.py backend/tests/test_operational_continuity_vs4.py
```
