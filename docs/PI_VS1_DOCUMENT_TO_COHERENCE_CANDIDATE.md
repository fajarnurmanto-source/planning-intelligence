# PI VS1 — Canonical SourceUnit integration candidate

**Branch target:** `feat/pi-document-coherence-vs1` based on commit `e57f748993ebc76f36349e0835b49f0e594ed682`.

## Real product result

The native PI parser (`app.parser`) creates addressable `SourceUnit` records for XLSX/DOCX/PDF, and the candidate reads the existing `DocumentVersion → ParsingRun → SourceUnit` contract. The VS1 deterministic comparison yields source-linked findings and an **offline synthetic reviewer preview** (HTML+JSON).

This branch only introduces `backend/app/vs1/`, a synthetic test fixture, focused tests and a scoped CI workflow. There is **no second parser, database, FastAPI application, migration, auth service, or production route**. Current `/api/*` routes and frontend are untouched. The old demo `pi_vs1/server.py` and SQLite are **not** copied to the PI source.

## Quick result (from isolated branch, Windows)

```powershell
$env:PYTHONPATH = (Join-Path (Get-Location) 'backend')
python -m app.vs1.cli --fixture backend/tests/fixtures/pi_vs1_canonical_pair.json --out "$env:TEMP\PI_VS1_SYNTHETIC_REVIEW"
```

Open `PI_VS1_SYNTHETIC_REVIEW.html` from the output folder to inspect two findings and simulate human review. Click **Ekspor hasil review JSON**. This action does **not** constitute authenticated sign-off.

## Boundaries

* `SOURCE_ADMISSION_NOT_VERIFIED` for non-synthetic `DocumentVersion` source kinds; existing `UPLOAD` is *never* implicitly approved.
* `PARSING_RUN_NOT_SUCCESSFUL` or checksum mismatch: refused.
* Source unit IDs/fingerprints must resolve back to one canonical run and version.
* Jurisdiction boundary, mismatched metadata, ambiguous targets and unsupported relations: fail closed / not comparable.
* No official audit, legal judgment, fiscal decision, external AI calls or population profiling.
* `app.vs1.orm_loader` can read *only* explicitly authorized `SYNTHETIC_FIXTURE` rows through a PostgreSQL read-only transaction/credential. There is no production API endpoint wired to it.
* Reviewer HTML/JSON is a non-binding **synthetic simulation**. There is no institution-approved identity or durable review workflow yet.

## Next real product milestone (one PR, no micro-gates)

Integrate a true authenticated reviewer workflow into the existing React/FastAPI source only after accepted identity middleware + I0 data-rights admission are available in mainline. Do not enable via anonymous port 8080. End-to-end regression should check upload/parser of rights-cleared fixture → persisted SourceUnits → finding → authorized reviewer decision → report. Production acceptance remains denied.
