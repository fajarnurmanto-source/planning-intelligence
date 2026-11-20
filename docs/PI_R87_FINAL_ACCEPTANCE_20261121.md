# R87 stabilization evidence — 21 November 2026

Base: 980e93e031273a8f328b46f3cfa7012d63086b8c. Starting candidate: 045cfd5.

## Root causes and changes

- Mobile overflow appeared only after R70K data rendered. DOM measurements identified `r70k-results` (309 client / 356 scroll) and `.r70k-abstain` (307 / 355). Scoped wrapping fixes the unbroken status token without hiding evidence. At 390 px the document now measures 375 client / 375 scroll; 768 measures 753/753; 1440 measures 1425/1425.
- Runtime logs identified `Normative evidence cache is unavailable or incompatible` in the readiness controller. Planning lineage had already succeeded. User-triggered ancestry populated the real cache; R75 subsequently displayed one candidate, one chronology anomaly, and zero verified provision relationships. No synthetic evidence or automatic legal approval was introduced.
- Readiness 503 responses now expose a bounded dependency identifier and recovery action, without exception details. UI distinguishes ancestry cache, planning source, and contract failures.
- Browser screenshot revealed pale text on pale R75 cards; scoped text/link colors restore readability.
- Local validator fix and user-triggered ancestry remain in regenerated bundles. PR #72 source was merged into the local candidate without overwriting 2969dca.

## Tests and runtime

Frontend/JS: 49/49 PASS. Bundle reproduction PASS. PHP controller syntax PASS.
Runtime full backend: 103/103 PASS, 1135 assertions. Initial 94/98 run exposed four old baseline tests not yet synchronized from merged PR #70; after guarded test synchronization all passed, including five R87 tests.

R70F authenticated load PASS. R74 partial graph displayed. R75 real candidates PASS after explicit ancestry action. Responsive document widths PASS at 1440/768/390. R84 initially encountered HTTP 429 after repeated reloads; final reacceptance remains pending. R85/R86 browser export reacceptance and logout remain pending; automated regression passed.

Windows source applied with backups under `PI_GITHUB_BRIDGE_BACKUPS/R87_FINAL_20261121`, `R87_FINAL_CONTRAST_20261121`, and `R87_BASELINE_TEST_SYNC_20261121`. Marker unchanged. No database migration or secret change.

## Benchmark limitations

Direct static asset references: 21 to 6 (browser observed). This is not a latency, CPU or RAM measurement. Comparable cold/warm load time, transfer bytes, API p95, RAM and CPU: NOT_MEASURED. No controlled before/after sample series has yet been collected. No performance improvement percentage is claimed.

## Integration and rollback

Push the reconciled branch as a fast-forward to the existing PR #72 head only after checking its latest SHA. Do not merge automatically. For rollback restore only overwritten source paths from their corresponding backups; new bundles can remain unreferenced. Do not restore database, secrets or bridge marker.

LEGAL_HARMONIZATION=NOT_ASSESSED
NUMERIC_COMPARISON=BLOCKED
REVIEW_DECISION=NO_ACTIVE_DECISION
PRODUCTION_ADMISSION=DENIED
ACCEPTANCE=BLOCKED
MERGE_READINESS=NOT_READY

Remaining: finish R84, source/export/auth browser scenarios and controlled performance comparison; inspect CI for final PR head.
