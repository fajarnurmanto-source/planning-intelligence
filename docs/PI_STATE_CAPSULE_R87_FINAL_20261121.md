# R87 final state capsule

Branch `codex/r87-lean-runtime`, existing draft PR #72. Final qualification and raw evidence: [PI_R87_FINAL_RESOURCE_QUALIFICATION_20261121.md](PI_R87_FINAL_RESOURCE_QUALIFICATION_20261121.md).

Root causes resolved: missing optional favicon; unequal ancestry-cache readiness between isolated BEFORE and AFTER. Preserved fail-closed 503 behavior, user-triggered discovery, original benchmark files and all accounts/databases/backups.

Final run `results-20261121-153028`: 40/40 UI-ready, zero resource/network failures, 20/20 API R70F HTTP 200. Cold/warm ready medians 320.23/245.04 to 285.85/194.88 ms. PHP RAM higher, API essentially unchanged; no general memory/backend speed claim. Source qualification backend 104/104, frontend 49/49, deterministic bundle.

Next: verify final GitHub CI then human review/merge decision. No automatic merge. No production migration, reviewer decision, password change or bridge-marker change. Keep isolated servers/data until owner no longer needs reproducibility; do not delete original results.

LEGAL_HARMONIZATION=NOT_ASSESSED
NUMERIC_COMPARISON=BLOCKED
REVIEW_DECISION=NO_ACTIVE_DECISION
PRODUCTION_ADMISSION=DENIED
