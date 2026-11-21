# R87 final resource qualification — 21 November 2026

Candidate lineage: BEFORE 980e93e; AFTER bd1af33. Both isolated Laravel sources installed from identical composer.lock, separate SQLite account clones, session cookies, keys, caches, storage and logs. Both receive the identical inline favicon overlay. Original source comparison therefore excludes that declared shared HTML correction. Production runtime/database and bridge marker untouched. Existing snapshots and original results retained.

## Proven resource failures and correction

Diagnostic `results-20261121-123458` recorded BEFORE cold favicon 404; AFTER cold favicon 404 and R75 readiness 503; AFTER warm R75 readiness 503. No other failed resources were recorded. The 404 was an absent optional icon, corrected by an explicit inline SVG favicon. The 503 was real fail-closed evidence unavailability: AFTER had no ancestry cache, while BEFORE's eager initialization had populated one. Both isolated caches were refreshed with the existing official-source Laravel command: two seeds, eleven edges, eight unresolved identities. Both cache schemas/tenants then matched and both readiness endpoints returned 200. API failure validation remains intact. These are explicitly warm backend evidence caches, even during browser cold tests.

## Final measurement

Raw evidence: [evidence/r87-final-20261121](evidence/r87-final-20261121). Source run: `qualification/benchmark-kit/results-20261121-153028`. Ten iterations per variant, each cold then warm; AB/BA ordering alternates. One authenticated session per clone; no main-browser cookies copied. Edge sandbox enabled. Cold clears browser HTTP cache; warm reloads. Not OS cold. Cooldown between variants limits throttling. PHP sampling every 500 ms across both requests and idle cooldown; instantaneous peaks can be missed.

All 40 page samples: HTTP 200, UI-ready, zero HTTP resource errors and zero network loading failures. Both API series: 10/10 HTTP 200. Static asset references: 21 to 6. Actual page requests include document and API, not login.

| Median unless noted | BEFORE | AFTER |
|---|---:|---:|
| Cold ready ms (p95) | 320.23 (414.09) | 285.85 (313.59) |
| Warm ready ms (p95) | 245.04 (363.69) | 194.88 (218.43) |
| Cold DCL / load ms | 167.65 / 189.80 | 131.65 / 157.75 |
| Warm DCL / load ms | 80.15 / 92.30 | 52.55 / 60.80 |
| Cold FCP / LCP ms | 212 / 212 | 154 / 154 |
| Warm FCP / LCP ms | 108 / 108 | 70 / 70 |
| Actual requests cold / warm | 34 / 34 | 18 / 18 |
| CDP encoded bytes cold / warm | 278933 / 278933 | 267456 / 267456 |
| Cold JS heap bytes | 1626472 | 1603160 |
| Warm JS heap bytes | 3128216 | 3078992 |
| API R70F p50 / p95 ms | 75.68 / 111.22 | 77.55 / 113.49 |
| PHP working set median / sampled peak MB | 47.03 / 47.25 | 49.80 / 50.20 |
| PHP total CPU seconds across run | 8.375 | 7.391 |
| PHP CPU machine % median / sampled peak | 0 / 8.98 | 0 / 9.04 |

Observed cold/warm ready medians improved by 34.38/50.16 ms. This is a small local benchmark, not a statistical significance or fleet-wide claim. AFTER PHP working set is about 2.77 MB higher; API median/p95 are 1.87/2.27 ms higher. CPU peak essentially unchanged. No material user-visible regression appeared in this run; do not claim general RAM savings or faster backend. Warm reload still transfers bytes on this development server, so it is not a demonstrated cache-transfer reduction. Cache-hit counts, task durations, timings and request records are retained in raw evidence. The runner's old negative TaskDuration subtraction across navigations was corrected; original samples are not rewritten.

## Acceptance and review

Previous accepted browser workflows R70F/R74/R75/R84, R85/R86 filter/temporal, real R75/R86 exports, source navigation, logout/endpoint denial, and 1440/768/390 layouts are retained. Backend 104/104 PASS and frontend 49/49 PASS from prior source qualification; this change only adds favicon and measurement artifacts, with frontend 49/49 rechecked. No reviewer decision submitted.

Original run `results-20261121-122145` sample SHA remains `F1D53AC788C190CEB0AE553B4A45C3AFCF7A9D12EA2D0E5E5CCDE5D95E888F74`. Root-cause diagnostic evidence is included separately. Final CI must pass on the pushed head before human merge approval.

Rollback: restore the prior R70G HTML to remove the inline icon; no migration or account change required. Measurement artifacts are read-only evidence. Keep Windows backups. Do not alter marker or auto-merge.

R87_ACCEPTANCE=PASS
MERGE_READINESS=READY_FOR_HUMAN_APPROVAL_AFTER_FINAL_CI
LEGAL_HARMONIZATION=NOT_ASSESSED
NUMERIC_COMPARISON=BLOCKED
REVIEW_DECISION=NO_ACTIVE_DECISION
PRODUCTION_ADMISSION=DENIED
MERGE_PERFORMED=NO
