# PI VS3 — Canonical PostgreSQL Reviewer (candidate)

A capability increment, not a replacement application: Gradio + SQLAlchemy + existing VS1/VS2 evidence and review modules. No new parser, no new ORM, no migration of operational database, no new document-upload endpoint. No independent vendor or external tester needed to run engineering tests.

## User journey

1. Start the separate local Gradio workspace on port 8094; login via the built-in Gradio local candidate auth (`PI_VS3_DEMO_USER/PASS`).
2. Click **Muat daftar parsing run sintetis**. Existing PI `ParsingRun` / `DocumentVersion` / `Document` ORM, with `source_kind=SYNTHETIC_FIXTURE`, `status=SUCCEEDED`, valid source-unit counts, supplies selectable run IDs via a PostgreSQL transaction marked read-only.
3. Choose a pair of canonical run IDs and declare indicator group and comparison scope. The existing `app.vs1.orm_loader.compare_synthetic_parsing_runs` fetches canonical `SourceUnit`s and calls VS1 deterministic evidence-linked coherence.
4. Select finding, inspect raw locator/evidence in a plain textbox (not interpreted HTML), then save candidate review with Gradio-authenticated username into a **separate local SQLite review store** using existing VS2 SQLAlchemy event schema.
5. Export JSON and Word using existing VS2 `python-docx` workflow. Exports are segregated by analysis SHA-256.

## Install (isolated DEV candidate only)

Create isolated Python 3.12 environment; `python -m pip install -r backend/requirements-vs3.txt`. Do not modify the existing running 8080 Docker containers. Set:

- `PI_VS3_CANONICAL_DATABASE_URL`: `postgresql+psycopg://<read-only user>:<password>@<host>:<port>/<database>`; user must have SELECT-only rights on PI document tables and **no write permission**. Do not put this URL in scripts, chat, Git or screenshots. Code additionally executes `SET TRANSACTION READ ONLY`.
- `PI_VS3_REVIEW_SQLITE_PATH`: absolute local path outside source repository, e.g. `D:\Research\PlanningIntelligence\PI_DEVELOPMENT_ARTIFACTS\VS3_CANDIDATE\review.sqlite`.
- `PI_VS3_OUTPUT_DIR`: absolute output directory outside source repository.
- `PI_VS3_DEMO_USER`: local candidate reviewer username.
- `PI_VS3_DEMO_PASS`: strong local password (>=16 characters, do not commit).

Run from `backend`: `python -m app.vs3.reviewer_ui --port 8094`. Open `http://127.0.0.1:8094` on the same computer. No share tunnel, no other listening interfaces, no production identity assertion.

## Limitations / admission

**Only synthetic canonical records** may be compared. Existing VS1's `prepare_side()` rejects uploads or actual documents whose `source_kind` is not exactly `SYNTHETIC_FIXTURE`; decisions remain `*_TEST_ONLY`, no official approval, and no production admission. If the DB has no admitted synthetic parsing runs, the listing will be empty; this is expected, not a hidden auto-admission bypass. Real-document workflow requires a verified and integrated admission/authorization boundary and a real institutional IdP: local Gradio login is not a substitute. The current candidate does not change PI's original anonymous 8080 service or operational PostgreSQL schema.

The CI 43 tests provide deterministic synthetic/ORM contract coverage, including Gradio UI construction; **they do not assert a live PostgreSQL end-to-end test or browser-backed identity test**. A real live deployment is NOT claimed.
