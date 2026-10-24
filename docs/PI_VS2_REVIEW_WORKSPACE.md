# PI VS2 — Reviewer Workspace (product capability increment)

## Reuse, not rebuild

This is a *stacked PR* on `feat/pi-document-coherence-vs1` (PI PR #1). It does **not** introduce another parser, duplicate the SourceUnit store, change PI's canonical database, or touch P5 frozen predecessors. It reuses VS1 analysis, SQLAlchemy from PI, python-docx from PI, and ready-made Gradio components instead of creating custom SPA form widgets.

## Actual behavior

1. VS1 canonical `DocumentVersion`/`ParsingRun`/`SourceUnit` evidence analysis, currently only `SYNTHETIC_FIXTURE` rights. Original PDFs are never repackaged.
2. Gradio reviewer table, evidence browser, and decision controls, login-protected on localhost; no new handwritten frontend.
3. Append-only review action rows through SQLAlchemy (candidate database path outside repository).
4. Each review action is bound to the exact SHA-256 analysis and specific finding ID. No unbound/orphan actions accepted.
5. Exports machine-readable JSON and Word with evidence IDs and reviewer decisions using existing `python-docx`.

No app ports except an optional localhost-only demo on **8093**. No changes to existing 8080–8086, no operational DB migration, no fake approval. A successful VS2 test means an institutional reviewer system is NOT yet authorized.

## Local product demo (Windows)

Run from your VS2 branch clone, within an isolated Python 3.12 environment where `backend/requirements.txt` and `gradio>=5,<7` are installed. Set *your own* 16-character-or-longer demo password in the PowerShell session. Never paste the value into chat:

```powershell
$env:PI_VS2_DEMO_USER = 'localreviewer'
$env:PI_VS2_DEMO_PASS = Read-Host 'Create local demo password (>=16 chars)'
$env:GRADIO_ANALYTICS_ENABLED = 'False'
Set-Location .\backend
python -m app.vs2.reviewer_ui --fixture tests/fixtures/pi_vs1_canonical_pair.json --store "$env:TEMP\pi_vs2_candidate_reviews.sqlite" --out "$env:TEMP\pi_vs2_candidate_exports" --port 8093
```

Open `http://127.0.0.1:8093`. When done, stop with Ctrl+C. Use a synthetic fixture only; do not upload or use real restricted PI datasets on this candidate.

## Decisions explicitly deferred

- Mounting on operational FastAPI under `/api`, Nginx on 8080, or authorizing POST/write before upstream identities are approved.
- Database migration into PI's operational DB. A future reviewed Alembic migration can use this standalone SQLAlchemy model; the candidate store is **not a second canonical PI system of record**.
- Institutional approval semantics, provider attestation or production admission.
- Migration to Laravel/Filament. Reevaluate only if Gradio proves insufficient for full multi-tenant institutional workflow; avoid two conflicting primary frontends.

## Definition of done for this PR

- Local acceptance and GitHub CI run original VS1 tests plus new review/Word/Gradio smoke tests.
- Decision statuses are test-only and tied to immutable VS1 finding fingerprints.
- The production repository remains untouched; branch is created in a disposable Git clone.
- PR is reviewable but NOT auto-merged; CI PASS is not production admission.
