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
