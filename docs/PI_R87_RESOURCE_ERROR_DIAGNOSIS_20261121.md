# R87 resource error diagnosis

Original run `qualification/benchmark-kit/results-20261121-122145` is preserved. Browser sample SHA-256: `F1D53AC788C190CEB0AE553B4A45C3AFCF7A9D12EA2D0E5E5CCDE5D95E888F74`.

Diagnostic run: `results-20261121-123458`, one cold and one warm per variant. CDP failure records contain pathname, resource type, status, version, scenario and iteration; no query strings, headers, request bodies, cookies or credentials.

| Version/scenario | Path | HTTP | Frequency in diagnostic run | Classification |
|---|---|---:|---:|---|
| BEFORE cold | /favicon.ico | 404 | 1/1 | Missing optional icon |
| AFTER cold | /favicon.ico | 404 | 1/1 | Missing optional icon |
| AFTER cold | /api/qa-planning-r71/normative-readiness | 503 | 1/1 | Required reviewer dependency unavailable |
| AFTER warm | /api/qa-planning-r71/normative-readiness | 503 | 1/1 | Required reviewer dependency unavailable |

No other failures occurred in the diagnostic records. BEFORE warm had none. R70F succeeded in the original run.

Read-only cache inspection proved BEFORE had `PI_R70R_AUTOMATED_ANCESTRY_V1` for `ID-DIY-YOG` and R75 200; AFTER had no ancestry cache and R75 503. BEFORE eagerly fills ancestry on page load; AFTER intentionally waits for user action. Therefore original results mixed different data-readiness preconditions, not a failure of R70F or authentication. The 503 fail-closed response is retained.

## Corrections

- Explicit inline SVG favicon added to the R70G HTML. Identical overlay applied to BEFORE and AFTER benchmark HTML; this must be recorded as a shared benchmark fixture correction, not claimed as byte-identical baseline HTML.
- Both isolated caches refreshed via existing `php artisan pi:regulatory-ancestry:refresh`, using official source retrieval: 2 seeds, 11 edges, 8 unresolved identities, legal ABSTAIN. No synthetic cache, session copying, production database write, or status promotion.
- Subsequent inspection: both caches present with expected schema/tenant; both readiness controllers return 200.
- Benchmark TaskDuration subtraction across navigation was invalid (negative warm samples). New runs record the current document's CDP TaskDuration/ScriptDuration instead. Old samples remain unchanged.

Full rerun: `results-20261121-153028`, 10 cold + 10 warm per version, all 40 UI-ready with zero resource/network errors. See `PI_R87_FINAL_RESOURCE_QUALIFICATION_20261121.md`. Cold means browser HTTP cache cleared, not OS cold. Backend evidence cache is explicitly warm and equivalent. The production runtime and bridge marker are untouched.

Frontend/JS regression: 49/49 PASS. Bundle generation check PASS. Resource-error qualification PASS; final CI required before human merge approval.
