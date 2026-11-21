# PI R74 — Existing Windows icon, one port and automatic Laravel startup

**Status 22 Nov 2026: implementation source in PR #78; no owner's Windows files have been modified from chat. Original launcher chain and origin now confirmed.**

## Actual existing R74 chain — owner PowerShell confirmed

- OneDrive desktop shortcut `Planning Intelligence.lnk` runs WScript with `D:\Research\PlanningIntelligence\PI_UNIFIED_LAUNCHER_R74_20261111\BUKA-PI-HIDDEN.vbs` (SHA256-12 `9FC0BF471E18`).
- VBS line 2 invokes `BUKA-PI-LARAVEL-HIDDEN.cmd` with window hidden. That original CMD SHA256-12 is `D59A6681D3A0`.
- The manual CMD `BUKA-PI-LARAVEL.cmd` has SHA256-12 `A0238A4B33E5`. It is not linked directly by the shortcut.
- Both CMDs use **127.0.0.1:8820**, probe `/regulatory-genealogy-r74.js`, and target the historical Laravel app at `D:\Research\PlanningIntelligence\planning-intelligence-v0.0-starter\planning-intelligence-v0.0\laravel`. Both start `php artisan serve` if the port is free. No Docker or 8080 is in this current chain.
- This is the existing *legacy/local* main Laravel directory, **not necessarily** the later R87/R88/R89 GitHub bridge source. Launcher reliability and current-code deployment are separate milestones.

## Problem in actual original CMD

The original CMD checks only HTTP 200 for a static R74 JavaScript file, with no HTTP asset hash/process identity comparison; it has no double-click startup lock and separate hidden/manual launch logic. A hidden `pause` makes failures difficult to diagnose; per-directory logs may be missing. The script already autostarts Laravel on an unused port, but its reliability and provenance need improvement.

## Source-only prepared repair

1. `scripts/pi-r74-ensure-runtime.ps1`: defaults to **read-only** `-Mode Audit`; `-Mode Ensure` guards the original 8820 process with named mutex, loopback-only listen check, Win32 PHP process command line under the precise Laravel root, and SHA-256 equality between local original `public/regulatory-genealogy-r74.js` and the HTTP response. Reuses the correct live Laravel process; starts it hidden only if absent; waits for validated health. It neither stops any existing process nor touches account/DB/APP_KEY. Inspect `%LOCALAPPDATA%\PlanningIntelligence\Runtime` logs for PHP errors.
2. `scripts/windows/r74/BUKA-PI-LARAVEL-HIDDEN.cmd`: replacement for **same-named existing CMD**, with one invocation of `PI-R74-RUNTIME.ps1` in original launcher folder, port 8820 only. Opens the same browser URL after successful startup.
3. `scripts/windows/r74/BUKA-PI-LARAVEL.cmd`: manual diagnostic delegates to the same hidden CMD. No independent server.
4. `scripts/pi-r74-inplace-upgrade.ps1`: read-only preflight by default. Checks the exact three original hashes, original VBS chain, installed Laravel files, PHP availability and `.env` APP_KEY + APP_URL=127.0.0.1:8820 without displaying secrets. Explicit `-Apply` creates timestamped SHA256 backups of the three original launcher files and then replaces only two CMDs + adds the helper **inside original R74 folder**. Keeps original `.lnk` and `.vbs`, Laravel source, DB, APP_KEY, QA8822/8823 and user accounts intact. If source hashes or app configuration drift, refuse and do **not** modify files.

### Owner-side next step: read-only preflight first

Use a checked-out branch of PR #78, not the original R87 worktree. For example, clone/fetch this draft branch into a separate QA checkout and run:

```powershell
Set-Location 'PATH_TO_CHECKOUT_OF_PR_78'
powershell.exe -NoProfile -File '.\scripts\pi-r74-inplace-upgrade.ps1'
```

Expected only when all actual current prerequisites match:
```text
PI_R74_BASELINE=VERIFIED
PI_R74_ONE_MAIN_ORIGIN=http://127.0.0.1:8820
PI_R74_UPGRADE=READ_ONLY_PREFLIGHT_PASS
PI_R74_APPLY=NOT_REQUESTED
```

If it reports `PI_R74_APP_URL_MISMATCH_HOLD_NO_MUTATION`, do not edit `.env` just to pass: audit original authenticated sessions, other QA cookies and release identity before any main cutover. If preflight succeeds, explicitly agree to apply the change after checking backups/QA; the installer accepts `-Apply`. **Do not run `-Apply` until acceptance**.

### Acceptance after in-place upgrade

- Original desktop icon opens existing 8820 even when no PHP process is running; click twice does not produce a second service.
- Existing main 8820 listener is reused only if tied to expected PHP root and exact static asset hash; port occupied by another service fails closed without stopping it.
- Existing login/session/account/reviewer evidence works; browser opens `http://127.0.0.1:8820/`, no command prompt needed.
- On reboot/logon, one click recovers; no manual `php artisan serve`.
- Original shortcut/VBS SHA unchanged, original CMD backup SHA receipt valid; main DB, .env, APP_KEY, tenant/session and evidence unchanged.
- Full R85-R89 product cutover is **a separate guarded deployment**. Current local Laravel may not contain those later code branches. Don't claim R89 production based on launch behavior.
- `PRODUCTION_ADMISSION=DENIED`, `MERGE_READINESS=NOT_READY` until owner-machine verified, regardless of GitHub CI.

**Limit:** PHP built-in Laravel dev server is adequate for owner-local click-to-open workflow but not a production-grade Windows service or native binary. An always-on managed service/MSI installer is a subsequent productization milestone only if needed; never create a duplicate application launcher.

## 22 Nov 2026 — owner Windows in-place upgrade receipt and acceptance

**Owner-reported local execution:** the isolated QA worktree was added at `C:\Users\fajar\.codex\worktrees\pi-r74-autostart\PI_GITHUB_BRIDGE_20261108` from immutable source commit `b918e46c7e9111c0259f3e052f37213e3e7d1e20`. `pi-r74-inplace-upgrade.ps1` read-only preflight output `PI_R74_BASELINE=VERIFIED`, `PI_R74_LIVE_PORT_8820_IDENTITY_PREFLIGHT=PASS`, `PI_R74_UPGRADE=READ_ONLY_PREFLIGHT_PASS`, and `PI_R74_APPLY=NOT_REQUESTED`. Owner then invoked `-Apply` and got `PI_R74_UPGRADE=APPLIED_TO_ORIGINAL_FOLDER`, with backup `D:\Research\PlanningIntelligence\PI_UNIFIED_LAUNCHER_R74_20261111\backups\R74_BEFORE_ONE_ORIGIN_20261122-061856`, plus `PI_R74_LNK_VBS_APP_DB_ENV=UNCHANGED`. This is **installation evidence** based on owner PowerShell, not yet observed live browser/runtime acceptance.

**New post-install acceptance source:** `scripts/pi-r74-windows-acceptance.ps1`. Default invocation makes no startup changes and validates original three-file backup receipt (full SHA256), retained original VBS/shortcut WScript chain, and byte-for-byte installed versions of both new CMDs and the runtime supervisor. It determines whether a 8820 PHP listener is already present, and when present verifies loopback binding, process identity, and the exact original R74 static asset hash. It never restarts/terminates PHP or changes credentials. Switch `-ExerciseShortcut` opens the **existing** Windows desktop icon and checks expected origin; optional `-SecondClick` confirms server owning PID remains unchanged. If server is not running, the shortcut under test may start it, as intended. Does not claim cold-reboot/logon or authenticated user acceptance.

**Run only from the matching updated PR #78 source in an isolated QA worktree, not old R87:** Fetch branch to a clean worktree and pin the current accepted SHA before running:
```powershell
$QA = 'C:\Users\fajar\.codex\worktrees\pi-r74-autostart\PI_GITHUB_BRIDGE_20261108'
$Repo = 'C:\Users\fajar\.codex\worktrees\pi-r87-lean-runtime\PI_GITHUB_BRIDGE_20261108'
git -C $Repo fetch --no-tags origin codex/pi-single-runtime-windows-app-20261122
# Replace <reviewed HEAD SHA> only after verifying source and clean QA worktree
git -C $QA status --porcelain
git -C $QA switch --detach <reviewed HEAD SHA>
$Test = Join-Path $QA 'scripts\pi-r74-windows-acceptance.ps1'
powershell.exe -NoProfile -ExecutionPolicy Bypass -File $Test
# Only when the read-only test succeeds:
powershell.exe -NoProfile -ExecutionPolicy Bypass -File $Test -ExerciseShortcut -SecondClick
```

Expected when original icon installation and runtime match:
```text
PI_R74_ORIGINAL_BACKUP_MANIFEST=VERIFIED
PI_R74_TWO_CMDS_AND_SUPERVISOR=EXACT_SOURCE_MATCH
PI_R74_DESKTOP_SHORTCUT_AND_VBS=UNCHANGED
PI_R74_ACCEPTANCE=READ_ONLY_INSTALLED_INTEGRITY_PASS
...
PI_R74_AFTER_SECOND_CLICK_SAME_PID=<observed PID>
PI_R74_EXISTING_DESKTOP_ICON_RUNTIME=PASS
```

**Acceptance gates still open:** actual owner execution of new integrity/desktop test, authenticated login to existing account, status after a natural Windows reboot, and later guarded R88/R89 release source/data reconciliation. Source CI is not proof of local browser acceptance. Preserve original main 8820 and QA8822/8823. Maintain `PRODUCTION_ADMISSION=DENIED`.

## 22 Nov 2026 — receipt acceptance blocker and safe recovery

Owner ran post-install `pi-r74-windows-acceptance.ps1` at `ea45cecd1ae516b23f13e7b84467de3df7dca87f`. The script stopped immediately at `PI_R74_BACKUP_RECEIPT_INVALID=BUKA-PI-HIDDEN.vbs`, both in default read-only mode and with `-ExerciseShortcut -SecondClick`. Consequently **no click/open test occurred from that test script**; it failed before launching the Windows shortcut. This does **not** prove that the actual original backup was corrupted: the previous checker grouped three independent conditions (JSON entry lookup, stored full SHA, original pinned 12-character SHA) under one generic exception. The installer itself had previously completed `-Apply` with backup receipt and unchanged original shortcut/VBS.

Added `scripts/pi-r74-verify-backup.ps1` (read-only, PS5.1 JSON array normalization, strict exactly 3 expected records, unique names, 64-character hash form, actual full file hashes and original baseline 12-char prefixes). Updated acceptance to call this helper first and display classification `PI_R74_BACKUP_MANIFEST_...`, `PI_R74_BACKUP_FILE_FULL_HASH_MISMATCH=...`, or `PI_R74_BACKUP_BASELINE_HASH_MISMATCH=...`. Reproduce installer's exact serializer on Windows runner plus tampered-file negative test: **CI PASS at d95359c4a2b08836ee071e7988f024b2bd24b188**. This is source/fixture acceptance, not verification of owner D: backup contents.

**Owner recovery (never run `-Apply` again):**

```powershell
$Repo = 'C:\Users\fajar\.codex\worktrees\pi-r87-lean-runtime\PI_GITHUB_BRIDGE_20261108'
$QA = 'C:\Users\fajar\.codex\worktrees\pi-r74-autostart\PI_GITHUB_BRIDGE_20261108'
$Pin = 'd95359c4a2b08836ee071e7988f024b2bd24b188'
if (@(git -C $QA status --porcelain).Count -ne 0) { throw 'PI_R74_QA_NOT_CLEAN' }
git -C $Repo fetch --no-tags origin codex/pi-single-runtime-windows-app-20261122
if ($LASTEXITCODE -ne 0) { throw 'PI_R74_FETCH_FAILED' }
git -C $QA switch --detach $Pin
if ($LASTEXITCODE -ne 0) { throw 'PI_R74_SOURCE_PIN_FAILED' }
$Backup = 'D:\Research\PlanningIntelligence\PI_UNIFIED_LAUNCHER_R74_20261111\backups\R74_BEFORE_ONE_ORIGIN_20261122-061856'
$Verifier = Join-Path $QA 'scripts\pi-r74-verify-backup.ps1'
powershell.exe -NoProfile -ExecutionPolicy Bypass -File $Verifier -BackupRoot $Backup
```

If `PI_R74_ORIGINAL_BACKUP_MANIFEST=VERIFIED`, run `pi-r74-windows-acceptance.ps1` **without switches**; only if that succeeds, run `-ExerciseShortcut -SecondClick`. If any backup mismatch remains, keep the original backup immutable; return the nonsecret error classification. Do not rewrite the manifest, refresh backups, reinstall or perform shortcut/app/database changes to force a green result.

`R74_INSTALLED=OWNER_REPORTED`, `BACKUP_LOCAL_REVERIFICATION=PENDING`, `DESKTOP_ICON_E2E=NOT_RUN`, `PRODUCTION_ADMISSION=DENIED`.

## 22 Nov 2026 — recurring authentication regression (post-install)

Owner screenshot shows `http://127.0.0.1:8820/login.html` with visible static `INSTITUTIONAL ACCESS · R69E QA` and UI `Nama pengguna atau kata sandi tidak sesuai.`. **The R69E label is not proof of old code:** `laravel/public/login.html` on the latest draft branch still hardcodes that label. `laravel/public/login.js` sends `POST /api/auth/login`, and `app/Http/Controllers/Auth/SessionController.php` looks up normalized `users.username` and verifies its stored hash before issuing `INVALID_CREDENTIALS` (401). The screenshot does not, by itself, establish which database that running PHP process used, the accuracy of browser-autofilled credentials, or whether the account was changed.

Owner subsequent detailed Windows file `Pasted text(20261121-233423).txt` proves:
- Backup manifest validation recovered: `PI_R74_ORIGINAL_BACKUP_MANIFEST=VERIFIED`, three backed-up launchers valid;
- Installed scripts match exact GitHub source and shortcut/VBS unchanged;
- Read-only acceptance saw `PI_R74_BEFORE_LAUNCH=NO_SERVER`;
- After explicit original shortcut exercise, `PI_R74_LAUNCH_DID_NOT_REACH_VERIFIED_ORIGIN`, so **the cold-start and same-PID test have NOT passed**.
These are independent of the screenshot authentication refusal; one should not presume the server is consistently running on 8820.

**Previous R87 account recovery on 21 Nov 2026 must be preserved:** user logs in `Pasted text(20261121-044825).txt` show account username `fajar.nurmanto` unique, tenant `ID-DIY-YOG`, role `institution_admin`, original app `D:\Research\PlanningIntelligence\planning-intelligence-v0.0-starter\planning-intelligence-v0.0\laravel\database\database.sqlite`, `ACCOUNT_VERIFIED=YES`, `BACKUP=VERIFIED`, `PASSWORD_CHECK=MATCH`, and `ACCOUNT_RECOVERY=COMPLETE`. A different older bridge DB `PI_DEVELOPMENT_ARTIFACTS\PI_LARAVEL_REUSE_FOUNDATION_QA_20261105\laravel_pi_bridge\database\database.sqlite` had the same username with `HASH_RELATION=DIFFERENT_FROM_ACTIVE`. This is evidence of historical database fork, **not proof that the current 8820 process is pointing to that historical DB**.

New read-only diagnostics staged:
- `scripts/pi-r74-auth-runtime-diagnostic.ps1`: checks the real PHP owner (or absence) on 8820, whether process command line roots to protected original Laravel, redacted `.env` DB driver, DB_URL override, APP_URL, config cache presence, expected database path and no-login SQLite account inventory. It prints only safe counts, role/tenant match, hash **algorithm name** (never hash/password), and safe PI_* launcher error codes. No HTTP login, reset, session write, migrations, or server start/stop.
- `scripts/pi-r74-auth-db-readonly.php`: uses `SQLite3(SQLITE3_OPEN_READONLY)`, `PRAGMA quick_check`, a bound username selector; no writes.

**Next gate:** owner runs read-only diagnostics on existing D: machine, shares statuses *without passwords*, then root-cause analysis differentiates inactive server/startup bug, wrong PHP source/process, cached Laravel DB config drift, historical DB branch, and genuine password mismatch. Do not reset or overwrite current recovered account just because the UI shows `INVALID_CREDENTIALS`. `R74_BACKUP_INTEGRITY=PASS`, `R74_COLD_START_ACCEPTANCE=FAIL`, `R74_LOGIN_E2E=FAIL`, `ACCOUNT_RECOVERY_PRIOR=PASS`, `ROOT_CAUSE=UNDER_INVESTIGATION`, `PRODUCTION_ADMISSION=DENIED`.

## 22 Nov — R74 active runtime and recovered SQLite account both verified

Owner ran pinned diagnostic from `f4ed7d5638931aa7b674142ae23f605f2b9730ef`: `PI_R74_ENV_SQLITE_DRIVER=True`, `DB_URL_OVERRIDE_PRESENT=False`, `APP_URL_EXPECTED=True`, `LARAVEL_CONFIG_CACHE_PRESENT=False`, `ENV_DB_PATH_MATCHES_RECOVERED=True`, `SQLITE_QUICK_CHECK=ok`, single PHP loopback listener port 8820 owner PID 12460 rooted at original protected Laravel app. SQLite (opened read-only) held exactly one `fajar.nurmanto` account, expected tenant ID-DIY-YOG / role institution_admin, password algorithm bcrypt. Launcher log showed `PI_R74_RUNTIME` and `PI_R74_START_PID` codes: this is evidence startup had succeeded at that time, not proof every subsequent cold start succeeds. The latest login screenshot is still `INVALID_CREDENTIALS`. Do not reset original account by default.

A narrower candidate remains: password typed in Edge (which may have autofill saved before the 21 Nov recovery) may not match active bcrypt. The `R87 20261121` recovery had reported `PASSWORD_CHECK=MATCH` *with the password entered into the recovery script*, but it did not verify what Edge is currently submitting. Another possibility if direct bcrypt matches is HTTP/Laravel auth context differing from CLI and must be investigated separately.

Added `scripts/pi-r74-verify-entered-password.php` (reads protected original SQLite using `SQLITE3_OPEN_READONLY`, exact account/role/tenant, uses `password_verify`, reads password from STDIN without echo/CLI argument) and `scripts/pi-r74-credential-parity-local.ps1` (owner-local `Read-Host -AsSecureString`, zeroed BSTR, no temp file/account/session writes). It only prints `PI_R74_ENTERED_PASSWORD_MATCHES_RECOVERED_ACCOUNT=YES|NO`. CI fixture tests both matching and non-matching invented credentials against a disposable bcrypt SQLite database and checks before/after file hash. The scripts **do not repair or change credentials**; if NO, owner must confirm which password was supplied at prior recovery versus browser. If YES but browser login continues returning 401, investigate actual Laravel effective auth context and browser autofill/cookies without touching the original DB first.

**Acceptance gates:** `R74_PROCESS_ORIGIN=VERIFIED_AT_OBSERVATION`, `R74_ACCOUNT_ROW=VERIFIED`, `R74_TYPED_PASSWORD_PARITY=NOT_YET_TESTED`, `R74_BROWSER_AUTH=FAIL`, `R74_COLD_START_REPRODUCIBILITY=NOT_YET_ACCEPTED`, `PRODUCTION_ADMISSION=DENIED`. Do not merge launcher work while login remains broken.

## 22 Nov — Windows PowerShell STDIN BOM and historical R87 recovery hypothesis

The owner read-only R74 diagnostic reports live original Laravel process on port 8820 and one recovered bcrypt account, so do not repeat account creation or randomly restore historical SQLite copies.

During Windows PowerShell 5.1 CI for a new secure bcrypt comparator, a *synthetic fixture password* was submitted into PHP through native STDIN and bcrypt returned NO despite matching visible text. A byte-level fixture-specific probe identified leading `EF BB BF` (UTF-8 BOM) on redirected STDIN; this is a **confirmed CI transport artifact**. The owner's original R87 recovery script on 21 Nov read `stream_get_contents(STDIN)` and removed trailing `\r?\n` only; it **did not remove a leading UTF8 BOM** before calling Laravel `Hash::make`. The R87 log `PASSWORD_CHECK=MATCH` only proves equality with the original transport bytes, so a BOM-bearing recovery password could explain why the real browser refuses an otherwise visually identical password. This is an important **hypothesis**; there is not yet proof that the **owner's R87 stored hash** contains a BOM.

The new `scripts/pi-r74-verify-entered-password.php` strips a single transport BOM from the *incoming* test input and compares BOTH exact visible password bytes and BOM-prefixed bytes against the stored hash, using `SQLITE3_OPEN_READONLY`. It reports:
- `PI_R74_ENTERED_PASSWORD_MATCHES_RECOVERED_ACCOUNT=YES`: normal bcrypt parity succeeds.
- `PI_R74_ENTERED_PASSWORD_MATCHES_RECOVERED_ACCOUNT=NO` and `PI_R74_R87_RECOVERY_BOM_PREFIX_MATCH=YES`: historical recovery likely persisted an unwanted UTF-8 BOM prefix; only then prepare narrowly scoped backed-up transactional rehash of the confirmed account, **never silently**.
- Both NO: password differs, or another issue; no reset without further evidence.

`scripts/pi-r74-credential-parity-local.ps1` collects password *only* with `Read-Host -AsSecureString` in the owner's local console and sends private UTF-8 bytes by redirected STDIN, no CLI password args, no HTTP login, no plaintext/hashes in output, no DB writes. Windows runner CI must prove normal match, mismatch, BOM-prefixed seeded fixture, plus fixture SQLite unchanged before owner-side execution.

This is a separate auth incident from the launcher source baseline. Preserve port 8820, protected original Laravel path, original backup and previous R87 account recovery snapshots. `BOM_R87_STORAGE_ON_OWNER=NOT_ASSESSED`, `LOGIN_E2E=FAIL`, `PRODUCTION_ADMISSION=DENIED`.
