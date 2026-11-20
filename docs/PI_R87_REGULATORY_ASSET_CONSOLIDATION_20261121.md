# R87 — Regulatory R70G Static Asset Consolidation

**Date:** 2026-11-21. **Base:** merged PR #69, #70, #71 on pilot at `980e93e031273a8f328b46f3cfa7012d63086b8c`.

## Goal and result

Consolidate R70G's static assets **without rewriting business logic, changing legal gates or creating a new frontend**. Previously the R70G HTML referenced 11 JS + 10 CSS (21 direct static file references). It now references 3 JS + 3 CSS (6); reduction of **15 references, or 71.43%**.

Four shared files remain independent for cross-page caching: `pi-auth-shell.js`, `pi-ia-r69e.js`, `institution-home.css` and `pi-ia-r69e.css`. Original nine R70G feature scripts and eight feature stylesheets remain unchanged and are bundled in original order into the two `regulatory-workbench-r87.bundle.*` generated files.

## Reproduction / QA

```sh
node scripts/pi-build-regulatory-assets.mjs --check
node scripts/pi-build-regulatory-assets.mjs --metrics
node --check laravel/public/regulatory-workbench-r87.bundle.js
node --test laravel/tests/frontend/pi_r87_regulatory_asset_bundle.test.cjs
node --test laravel/tests/frontend/*.test.cjs laravel/tests/js/*.test.cjs
```

No package download required; uses Node standard modules only. The existing `.github/workflows/pi-laravel-r73-ci.yml` was expanded to run these checks. Its preexisting PHP baseline job is preserved.

## Performance and product limitations

The 21→6 number measures **direct asset references in HTML**. It does NOT prove 15 fewer real network requests under caching, fewer dynamic API requests, lower RAM, faster TTI, or improved p95. Source content is included unchanged; uncompressed feature payload is not meaningfully smaller and the generator adds small boundaries for traceability. Assess cold/warm loads, JS heap, waterfall, errors, CSS visual parity, desktop/tablet/mobile after guarded local apply. In particular, defer-on-interaction API requests are not proof that all initial feature JS is lazy-loaded; this bundle still loads nine feature scripts.

Do not claim production readiness or R86 browser re-acceptance based on static tests.

## Parallel local Work candidate

This branch is **independent** of the unpushed Windows worktree `codex/r87-lean-runtime` commit `2969dca`. Its changed four local files and guarded backup `D:\Research\PlanningIntelligence\PI_GITHUB_BRIDGE_BACKUPS\R87_LEAN_20261121` are not present here and must be reviewed and reconciled before any integration. The local bridge marker must remain unchanged. **Do not auto-merge** the new PR or deploy to Windows: the bridge allowlist may not include new generated bundle names.

## Acceptance still required after local apply

1. Dry-run bridge allowlist and source SHA reconciliation; backup, ensure no unrelated drift.
2. Node determinism, syntax, full frontend, PHP baseline and HTTP auth/tenant checks.
3. Browser R70G desktop 1440, tablet 768, mobile 390: navigation, genealogy, review, source links and JS console. Verify previously completed R86 acceptance remains unchanged.
4. Browser request capture for initial direct assets and dynamic API; collect cold and warm timings, performance p50/p95, RAM/CPU, DOM and console.
5. Compare with pre-change baseline on equal machine/session/cache conditions; report effects honestly.

## Canonical gates

`LEGAL_HARMONIZATION=NOT_ASSESSED`  
`NUMERIC_COMPARISON=BLOCKED`  
`REVIEW_DECISION=NO_ACTIVE_DECISION`  
`PRODUCTION_ADMISSION=DENIED`
