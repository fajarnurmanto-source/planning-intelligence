# VS5 — Synthetic Browser Document Intake

VS5 closes one functional gap: compare two fresh user-created synthetic PDF/DOCX/XLSX
from Gradio directly using the existing PI `app.parser.parse_document`,
VS1 `compare_existing_sources`, VS4 analysis archive, reviewer event log, Word/JSON.

- **No Laravel introduced.** Existing FastAPI + Gradio workflows + SQLAlchemy suffice
  for this slice. Add Laravel + Filament only when an actual workflow and permissions
  benchmark beats the existing approach, not merely because it exists.
- Two sample XLSX sheets (Renstra and Renja) are supplied at `backend/tests/fixtures`.
  Upload both to the VS5 accordion after running `scripts/start_pi_vs5_demo.py`.
- Synthetic documents must contain `PI_SYNTHETIC_FIXTURE_ONLY` inside the first
  60 parser source units. This marker is NOT a validation of actual document rights.
- The source files are parsed in memory on local host, and not stored in the
  candidate review database. Evidence excerpts/locators and analysis are archived.
- Reviewer actions and exports behave as in VS4. Local credentials are temporary.
- No production, third-party data, institutional identity, server-side sharing,
  or migration. Original 8080 remains untouched.

Success indicator: two freshly uploaded files produce fingerprinted evidence and
repeatable comparison; a reviewer decision and Word/JSON report survive restart.
