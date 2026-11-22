# PI R97 — Owner Edge browser and natural cold-start acceptance

Evidence PIT: 22 November 2026. PR #78 remains Draft, production admission DENIED.

## Owner Windows confirmed
- Original R94 17-file source rollout installed successfully on port 8820; protected login, 401 route gates, asset SHA and preserved PHP PID12460. No DB/APP_KEY/launcher or original-only public asset changes.
- Original R95 exactly one mobile CSS change was APPLIED_AND_HTTP_VERIFIED, backup at D:\Research\PlanningIntelligence\PI_UNIFIED_LAUNCHER_R74_20261111\backups\R94_CODE_BEFORE_ONE_ORIGIN_20261122-132001.
- R96 Windows CI verified full 17-file code rollout, exact one-CSS incremental rollout, no-op hold, rollback and collision-proof backups. No additional owner Windows source deployment is needed.
- R93 real Laravel HTTP login/CSRF/cookie/reviewer and R95 real Chrome desktop1440/mobile390/R88/R89 read-only are CI PASS, not evidence of original Edge user acceptance.

## R97 source-only browser acceptance
- Source: scripts/pi-r97-owner-browser-readonly.js, running exclusively in already-signed-in original 127.0.0.1:8820/development-case-r85.html via Edge Developer Tools Console.
- It checks GET /api/auth/session role and tenant without printing credentials, R85/R86 state, submits existing R88 form (GET only), clicks R89 preview (GET only), verifies cloud run button disabled / consent unchecked, changes year to 2027 and requires ABSTAIN, and optionally measures 390px viewport overflow.
- It NEVER clicks cloud-run, grants cloud-cost consent, posts reviewer decisions, logs or sends cookies/CSRF/password, or signs out. It restores original filter input values.
- Same EXACT code was executed by R95 Chrome CI with synthetic account at local port 19893. R88 workflow SUCCESS at commit 4c62b2cbc808dd09fd62c2c5d93b47640009f9d6; confirmed PI_R97_OWNER_EDGE_SNIPPET_REAL_CHROME_CI=PASS, PI_R95_CHROME_MOBILE_390PX_NO_OVERFLOW=PASS and PI_R93_LIVE_HTTP_ISOLATED_ACCEPTANCE=PASS. This does not prove original user Edge has been exercised.

## Owner execution
1. From existing clean QA worktree, pin immutable Git commit 4c62b2cbc808dd09fd62c2c5d93b47640009f9d6 after fetching the existing PR branch. No original Laravel source/DB write.
2. Copy source to clipboard using: Get-Content -LiteralPath (Join-Path $QA 'scripts\pi-r97-owner-browser-readonly.js') -Raw | Set-Clipboard
3. In logged-in original Edge at http://127.0.0.1:8820/development-case-r85.html open F12 > Console, paste and execute the locally audited snippet. Expected PI_R97_BROWSER_READ_ONLY_ACCEPTANCE=PASS with R88 click, R89 preview, 2027 ABSTAIN and no cloud writes.
4. For actual 390px acceptance, use Edge responsive Device Toolbar 390x844 and reload, then re-execute snippet; additionally require PI_R97_EDGE_MOBILE_390_NO_OVERFLOW=PASS. A viewport NOT_CHECKED is not a mobile PASS.
5. Test logout separately with existing Keluar button, see original login page then log back in using existing account (never print password).

## One-click cold start after NEXT NATURAL Windows reboot
- Do not kill or restart healthy PHP just to force this test. After a natural Windows restart, before running manual PHP server commands, execute existing scripts/pi-r74-windows-acceptance.ps1 with -ExerciseShortcut -SecondClick, using a clean pinned QA repo.
- Claim cold start only when BOTH PI_R74_BEFORE_LAUNCH=NO_SERVER and PI_R74_EXISTING_DESKTOP_ICON_RUNTIME=PASS appear in that run, AND original browser login/dashboard appears. If a listener was already present, that run proves warm reuse, not cold startup.
- Never create a second launcher or service or reset any user database to manufacture cold acceptance.

R97_CI_CHROME_OWNER_SCRIPT=PASS
R97_OWNER_ORIGINAL_EDGE=NOT_YET_RUN
R97_OWNER_MOBILE_390=NOT_YET_RUN
R97_OWNER_LOGOUT=NOT_YET_RUN
R74_NATURAL_REBOOT_ICON_COLD_START=NOT_YET_RUN
PRODUCTION_ADMISSION=DENIED


## CD-20261122-07J — Original Edge R88/R89 post-repair interaction confirmed
Owner screenshot and live connected tab after R98: R88 actual original browser displays `Jejak tersedia untuk review` with observed code, nomenclature hierarchy, and relations; R89 preview context JSON rendered and status `Pratinjau siap` without cloud consent/run. Original R88 503 for year2026 is resolved in observed UI; do not repeat R98. Remaining owner checks only year2027 ABSTAIN, real Edge 390 viewport no horizontal scroll, manual logout/re-login and natural OS restart cold icon (no deliberate PID kill). R97 complete automated script result after source fix not independently pasted; don't claim full R97 Edge=PASS until all expected logs.
