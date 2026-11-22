# PI R98 — 8820 R88 HTTP 503 dependency diagnosis and missing-only registry recovery

Date: 2026-11-22. PR #78 Draft. Protected original PI superior main: D:\Research\PlanningIntelligence\planning-intelligence-v0.0-starter\planning-intelligence-v0.0\laravel, single localhost 8820; original account and PID12460 previously working.

## Real owner Edge evidence
- Original R97 script now EXECUTED in the signed-in Edge Console (after earlier clipboard confusion). It printed PI_R97_ORIGINAL_AUTH_TENANT_ROLE=PASS and PI_R97_R85_R86_READ_MODELS=PASS.
- Browser GET /api/qa-superior-r88/planning-crosswalk?code=2.11.11.2.01.0004&year=2026&as_of=2026-11-21 returned **HTTP503**. R97 accurately reported PI_R97_BROWSER_READ_ONLY_ACCEPTANCE=HOLD_R88_2026_NO_EVIDENCE. R89 preview and mobile PASS are not established from that run. No provider cloud POST, approval or reviewer writes occurred.
- Controller RegulatoryPlanningCrosswalkController catches any service throwable and responds `{status:ABSTAIN,code:CROSSWALK_SOURCE_UNAVAILABLE}` HTTP503. Thus frontend and authentication succeeded; backend source/registry dependency is implicated. Actual root exception message in Laravel log has NOT been independently inspected.
- R88 service new source code requires EXACT two local JSON registries: `resources/pi/affairs-nomenclature-seed-r87.json` for AffairsFirstNomenclature classification and `resources/pi/operational-definitions-r88.json` for bounded related-candidate definitions. The earlier R94 install strictly allowed 17 app/config/public/routes files, **not the resources directory**. R85 and R86 remain working with their different registry dependencies. Most likely, one/both R87/R88 seed resources are missing on original D:; this is a hypothesis pending local read-only preflight, NOT confirmed missing.

## Implemented source-only repair
- `scripts/pi-r98-r88-registry-repair.ps1` is READ-ONLY by default, requires correct clean exact Git commit PIN and checks immutable candidate SHA of the above two reviewed JSON registry resources, required schema and fail-closed definition statuses.
- Prints per-file state MATCH, MISSING or DIFFERENT_HOLD, missing/different counts, no DB/env/account/launcher touched.
- Explicit `-Apply` is allowed to **add MISSING only**; existing matching file remains untouched; if any existing file has different SHA, fail CLOSED without overwrite. Never delete/change local evidence or original 28 main-only files, SQLite, .env, APP_KEY, password, sessions, PHP PID, launcher or port. No artisan, migration, Composer, paid API/network requests.
- Before writing requires original PHP-owned single 8820 listener, produces timestamped+GUID backup manifest in existing launcher backup folder, stages hashed source and adds only missing files. If an addition fails, remove only files created by R98; no overwrite and no user DB rollback.
- After original -Apply, always rerun owner Edge R97: R88 2026 should return data, R89 preview GET and 2027 ABSTAIN should PASS. The R98 script itself only verifies filesystem hashes, not the authenticated browser 200. Do not report original R88 restored until Edge shows PASS.

## Windows CI evidence
- Windows PowerShell5.1 CI at tested Git SHA `88c0f43aab3f4222212c1a42842af6cbd235ae45` PASS with source parse, two missing fixture registry additions, exact SHA, 5 protected dummy sentinels unmodified, repeat idempotent no-op, existing different original HOLD, injected exception after first add followed by complete rollback.
- Workflow labels: PI_R98_CI_TWO_MISSING_REGISTRIES_APPLY=PASS, PI_R98_CI_PROTECTED_DB_ENV_LAUNCHER_PRESERVED=PASS, PI_R98_CI_IDEMPOTENT_MATCHING_REGISTRIES=PASS, PI_R98_CI_EXISTING_DIFFERENT_SOURCE_HOLD=PASS, PI_R98_CI_INJECTED_REGISTRY_ADD_ROLLBACK=PASS.
- R88 crosswalk CI also continues to validate candidate Laravel resources on the isolated ephemeral CI origin. That does NOT prove live original resources are present.

## Required on original Windows
- Checkout clean immutable candidate code PIN `88c0f43aab3f4222212c1a42842af6cbd235ae45` in already existing QA Git worktree. Run `scripts/pi-r98-r88-registry-repair.ps1` without -Apply first, expect registry states and PI_R98_REGISTRY_PREFLIGHT=COMPLETE_READ_ONLY.
- If DIFFERENT_COUNT > 0 or MISSING_COUNT=0 despite 503: stop with HOLD, inspect redacted Laravel error category; do NOT overwrite registry.
- If 1 or 2 MISSING and 0 DIFFERENT, apply SAME pinned script explicitly once; require PI_R98_R88_REGISTRY_FIX=APPLIED_VERIFIED and PHP PID preserved; do not rerun R94/R95.
- Rerun R97 in original signed-in Edge and share only PI_R97_* outcome lines. Owner original 390px, logout, and natural reboot remain independent acceptance gates.

R97_ORIGINAL_EDGE_AUTH_R85_R86=PASS
R97_ORIGINAL_R88_CROSSWALK=HTTP503_HOLD
R98_WINDOWS_MISSING_ONLY_REPAIR_CI=PASS
R98_OWNER_ORIGINAL_REGISTRY_STATES=NOT_YET_READ
R98_OWNER_ORIGINAL_REPAIR=NOT_YET_APPLIED
R97_ORIGINAL_EDGE_R88_R89_AFTER_REPAIR=NOT_YET_TESTED
PRODUCTION_ADMISSION=DENIED
