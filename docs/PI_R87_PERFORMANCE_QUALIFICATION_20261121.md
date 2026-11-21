# R87 performance and closing browser evidence

Test date: 21 November 2026, Asia/Jakarta. Candidate source: 375e6f4.

## Closing browser checks

- R75 export downloaded through its actual button: `PI_R75_REGULATORY_REVIEW_CANDIDATES.json`, 2211 bytes. JSON parsed: tenant ID-DIY-YOG, readiness ABSTAIN, legal NOT_ASSESSED, numeric BLOCKED, production DENIED.
- R86 selected 2028-04-10 / external_audit and displayed 1/10 stages. BPK 2017 superseded; BPK 2026 current in temporal metadata.
- JSON and Markdown downloaded again, with `(1)` filename suffix: 42142 and 1678 bytes respectively. JSON parses and retains gates; source links and metadata remain separate from legal conclusions.
- Official SPKN source link opened `https://www.peraturan.go.id/files/peraturan-bpk-no-1-tahun-2026.pdf`. This proves navigation to the official URL, not independent revalidation of remote bytes.
- Logout returned the login screen; direct access to the R86 endpoint returned `{"message":"Unauthenticated."}`.

## Reproducible HTTP asset-layout experiment

Run `scripts/pi-r87-http-benchmark.ps1` from repository root. Same Windows machine, same localhost server, sequential GETs, ten samples per variant. BEFORE URL list comes from baseline HTML at 980e93e; AFTER list comes from candidate HTML. Both fetch currently installed asset sources. Consequently this isolates asset request layout and does NOT reproduce the complete historical application or browser rendering. No client HTTP cache; OS/server warm state uncontrolled; BEFORE runs first. First sample is reported separately, not labelled true cold.

| Metric | BEFORE | AFTER | Interpretation |
|---|---:|---:|---|
| Actual HTTP asset GETs per sample | 21 | 6 | All returned 200 |
| Decoded UTF-8 response bytes | 144983 | 147922 | Bundle boundaries and current source changes add bytes; no payload saving |
| First sequential batch ms | 549.27 | 34.95 | Warm-up/order bias; not a comparable cold-load claim |
| Samples 2–10 ms | 96.98,80.92,79.87,85.32,83.27,88.34,88.36,92.46,86.42 | 38.42,45.25,41.43,49.17,41.90,39.00,36.41,54.81,41.26 | Lower sequential request overhead observed; not browser readiness |
| Browser cold/warm DCL, load, FCP/LCP, heap | NOT_MEASURED | NOT_MEASURED | Controlled browser performance trace not collected |
| Wire transfer bytes | NOT_MEASURED | NOT_MEASURED | Decoded bodies are not wire transfer totals |
| API p50/p95 | NOT_MEASURED | NOT_MEASURED | No comparable authenticated API sample series |
| PI RAM/CPU/startup | NOT_MEASURED | NOT_MEASURED | No paired process measurements |

The experiment supports fewer asset requests and lower sequential HTTP batch overhead. It does not prove faster browser rendering or lower CPU/RAM. A complete before/after browser benchmark remains outstanding; acceptance stays BLOCKED/NOT_READY until qualification is complete.

Backend 103/103 and frontend 49/49 evidence remains applicable: only measurement scripts/documentation changed in this qualification step. No migration, reviewer decision, production data or bridge marker change.

LEGAL_HARMONIZATION=NOT_ASSESSED
NUMERIC_COMPARISON=BLOCKED
REVIEW_DECISION=NO_ACTIVE_DECISION
PRODUCTION_ADMISSION=DENIED
