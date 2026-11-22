# PI R94 — One Origin, Code-Only R88/R89 Cutover & Rollback Readiness

**Evidence PIT:** 22 November 2026
**Status:** source candidate on PR #78; protected original 8820 NOT YET DEPLOYED at this milestone.
**Do not confuse:** successful GitHub CI localhost:19893 is not user Windows localhost:8820.

## Completed source and isolated integration

- Owner original R74 desktop launcher twice reused original PHP PID 12460; browser login and PI Superior institutional dashboard successful. Original protected SQLite quick_check ok, nine expected tables and columns pass, five Laravel migration names applied. No original DB migration needed.
- R90 drift: of 153 candidate runtime source files, 136 have identical normalized text after stripping CRLF/LF difference; 8 substantive text changes, 1 initially extensionless .htaccess classification (Apache text classifier corrected), 8 new R88/R89 source assets. All five migration PHP source files differ only CRLF/LF. Composer 119 package names, versions and source refs match despite raw lock SHA mismatch. Preserve all 28 original-only public/runtime files.
- R92 discovered nonstandard X-PI-CSRF on logout and consented R89 POST, corrected to Laravel X-CSRF-TOKEN. Existing and new frontend regression tests pass. This header correction is NOT installed on protected original 8820.
- R93 CI now executes real Laravel on temporary 127.0.0.1:19893 with ephemeral SQLite, fake admin/viewer accounts, real HTTP CSRF and encrypted session cookies:
  - Anonymous protected R85/R86/R88/R89 and reviewer routes: 401.
  - Wrong legacy CSRF header: 419; correct CSRF login: 200 and authenticated session.
  - R85 case / R86 lifecycle / R88 crosswalk / R89 semantic GET preview: 200 with read model/gates intact.
  - R89 POST missing consent/payment approval: 422; no actual external AI call.
  - Reviewer read-only, unsafe PDF source denied, actual logout revokes access, viewer reviewer POST denied 403.
  - Laravel integration PHPUnit suite and JavaScript regressions pass.
- R94 changes cloud semantic default from enabled to disabled; explicit PI_R89_CLOUD_ENABLED is required even when a user has configured a provider. Provider mocks in Laravel tests explicitly enable this switch. No credential or API charge required for acceptance.

## Protected original app, unchanged

Main Laravel: D:\Research\PlanningIntelligence\planning-intelligence-v0.0-starter\planning-intelligence-v0.0\laravel
Existing launcher: D:\Research\PlanningIntelligence\PI_UNIFIED_LAUNCHER_R74_20261111
Original Windows desktop icon: the existing OneDrive Planning Intelligence shortcut (never replaced).
Single user-facing origin: http://127.0.0.1:8820

NO migration, db:wipe, new APP_KEY, user reset, changed password, Composer setup, service stop, new shortcut, bulk Git clean or data/public PDF deletion.

## R94 allowlist — exactly 17 source files, no other paths

1. app/Providers/AppServiceProvider.php
2. config/pi.php
3. routes/web.php
4. public/development-case-r85.css
5. public/development-case-r85.html
6. public/regulatory-laravel-r70g.html
7. public/regulatory-lifecycle-r86.css
8. public/regulatory-lifecycle-r86.js
9. public/pi-auth-shell.js
10. app/Http/Controllers/Api/R89SemanticRegulatoryController.php
11. app/Http/Controllers/Api/RegulatoryPlanningCrosswalkController.php
12. app/Services/Pi/Development/CloudSemanticCandidateProvider.php
13. app/Services/Pi/Development/HybridSemanticReasoner.php
14. app/Services/Pi/Development/R89SemanticRegulatoryAnalysis.php
15. app/Services/Pi/Development/RegulatoryPlanningCrosswalk.php
16. app/Services/Pi/Development/SemanticCandidateProvider.php
17. public/regulatory-planning-r88.js

The installer never touches .htaccess; the 28 original-only assets remain. Their untouched presence does not require copying or deleting anything.

## Hardened installer and deterministic acceptance

Script: scripts/pi-r94-one-origin-code-cutover.ps1

The script defaults to **read-only preflight**. It refuses an unclean or wrong pinned candidate, missing main original sentinels, symlinked target directories, missing CSRF or R89 consent gates, or cloud inference with default-on configuration. Explicit -Apply alone enables writes.

Before changing any live file:
- Validate original single 8820 listener owned by PHP, preserve its PID, abort if Laravel has cached routes or config.
- Snapshot all existing allowlist files with SHA256 into the original launcher backup folder and write an exact manifest.
- Stage each candidate code file with SHA256; no database or .env is read or copied.

Apply:
- Atomic replace of existing allowlist files, add exactly new R88/R89 dependencies and verify every SHA256.
- Reverify protected original APP/DB/.env and visible local sentinel files.
- Require **actual original 8820 HTTP** R88 and R89 anonymous GET endpoints to return JSON 401 (new routes exist and authentication is enforced).
- Require actual served HTML and JS asset SHA256 to match the new candidate, and preserve the original PHP PID.
- Only then output PI_R94_CODE_CUTOVER=APPLIED_VERIFIED.

Any code write or HTTP check error triggers restoration of every original code file from the fresh backup, removal of ONLY script-added paths, and SHA verification. No database rollback is attempted because this release never migrates or writes the original database. A failed preflight never performs a backup or mutation.

Windows GitHub CI tests actual -Apply on a disposable on-disk surrogate, backup hashes, fixture asset preservation, and injected exception after first swap with complete rollback. This CI result does NOT replace the owner's HTTP and desktop acceptance on original 8820.

## Operator rollout gates

1. Pin clean QA Git worktree to the **latest CI-green PR #78 source SHA**, NOT an outdated commit, then run the R94 script once without -Apply and require PI_R94_CODE_CUTOVER=READ_ONLY_PREFLIGHT_PASS.
2. Explicitly run same script with -Apply only after CI is green; accept only PI_R94_CODE_CUTOVER=APPLIED_VERIFIED and PI_R94_ORIGINAL_HTTP_PROTECTED_ROUTES_AND_ASSETS=VERIFIED. Any HOLD/ABORT must stop; never bypass.
3. Open original icon, login with existing account; navigate R85, R86, R88, R89 GET preview, R84 reviewer history, evidence PDF, and logout. **Never click consented paid cloud execution for acceptance**. Check desktop plus 390px. Verify one origin 8820 and PID reuse.
4. After natural next Windows reboot, open same icon cold with NO manual server command and verify login/dashboard, then record cold-start acceptance.
5. Keep draft PR and production admission denied until observed owner acceptance; no automatic merge.

R93_REAL_HTTP_EPHEMERAL=PASS
R94_WINDOWS_CODE_REHEARSAL=PASS_ON_TESTED_CI
R94_OWNER_ORIGINAL_8820_APPLY=NOT_YET_RUN
R94_BROWSER_MODULE_E2E_ON_ORIGINAL=NOT_YET_RUN
R74_ONE_ICON_COLD_REBOOT=NOT_YET_TESTED
PRODUCTION_ADMISSION=DENIED


## R94 final validated source pin: 1046b339
Owner-deployment source pin 1046b339ccc432795b1b2fd659cbcd77ffa65e21. Both Windows audit safety and R88 crosswalk GitHub workflows SUCCESS at this exact source commit. Installer now additionally requires the original HTTP /api/auth/setup-status on 8820 to respond needs_setup=false and with a valid nonempty CSRF token, before APPLIED_VERIFIED. No R94 code copied onto original D: by GitHub; owner must execute guarded script from a clean QA worktree and report real original success. Existing account, 28 original-only files, DB/APP_KEY and shortcut remain protected. PR #78 stays draft; no production admission.
