# PI R74 — Existing-icon single-origin runtime adapter (staged 22 Nov 2026)

**State: SOURCE_ONLY / NOT_INSTALLED / R74 ENTRY VBS AND TWO CMD FILES CONFIRMED; CONTENT/PORT CHAIN NOT YET AUDITED.**

The existing Windows shortcut is protected. The guarded PowerShell helper `scripts/pi-r74-ensure-runtime.ps1` is not a second launcher, icon, service, or installer. No default port is selected. The old Docker 8080 origin, historical R84 8820, and QA 8822/8823 must not be presumed to be the R74 shortcut origin.

The adapter defaults to read-only `-Mode Audit`. `-Mode Ensure` is opt-in, to be wired **only** into the inspected existing R74 WScript chain. It requires explicit Laravel `-AppRoot`, `-Port`, and `-PhpExe`; verifies `artisan`, vendor, original `.env` existence, configured APP_KEY/SESSION_COOKIE, APP_URL equal to chosen origin, and a static release identity marker. Before reusing any listener it checks loopback-only binding, exact Laravel root in the PHP process command line and marker HTTP response. A mutex prevents double-start; when the correct server is absent it starts only that Laravel instance (hidden PHP artisan server) and waits for identity before reporting ready. It never edits, migrates, resets, backs up or deletes the DB, credentials, shortcut, or files. It never stops processes, installs services, or enables provider egress.

**Confirmed by owner PowerShell on 22 Nov 2026:** `PI_R74_ENTRY_SCRIPT=D:\Research\PlanningIntelligence\PI_UNIFIED_LAUNCHER_R74_20261111\BUKA-PI-HIDDEN.vbs` and `PI_R74_ENTRY_EXISTS=True`; the `.lnk` references this file. Snapshot SHA256-12: `BUKA-PI-HIDDEN.vbs=9FC0BF471E18` (`UsedByShortcut=True`), `BUKA-PI-LARAVEL-HIDDEN.cmd=D59A6681D3A0`, `BUKA-PI-LARAVEL.cmd=A0238A4B33E5` (`UsedByShortcut=False` for both, but they may be chained by VBS). **These files exist on the owner D: drive and are not tracked in the PI GitHub repository.**

**Required operator evidence before actual integration:** contents of all three R74 scripts (with sensitive lines redacted), including the real process, relative paths, browser launch and port. Shortcut identity is no longer a blocker. Read the actual entrypoint and identify its existing origin, process owner, DB and startup semantics. If the existing R74 chain is Docker, PHP service, or an alternate managed runtime, adapt *that same mechanism*, not this assumed PHP candidate.

**Release and acceptance:** prepare source-only R88/R89 stack in a reconciled candidate, preserve main APP_KEY/DB/tenant/user/ledger/PDF and backup receipt, verify session-cookie separation from temporary QA, then run test on original desktop icon: first click cold start; second click reuse with same origin/PID; Windows logon/restart recovery; authenticated PI Superior R85-R89, existing AI Settings, reviewer/export/390px and privacy/ABSTAIN; unrelated port owner fails closed. Do not claim Windows E2E or production readiness until actually observed on owner machine.

The built-in PHP server here is a **local desktop development/QA mechanism**, not an enterprise production service. For an always-on Windows deployment, reuse a verified managed local HTTP service and health supervision after acceptance, not duplicated app servers.

`PRODUCTION_ADMISSION=DENIED`, `MERGE_READINESS=NOT_READY`.


## Exact next chain inspection (READ-ONLY)

Run in PowerShell on owner PC. It reads only the three verified files, checks the observed hashes, and outputs nonsecret command lines with their line numbers. Sensitive lines are **fully omitted**, not partially masked. Inspect all output before sharing.

```powershell
$R74 = 'D:\Research\PlanningIntelligence\PI_UNIFIED_LAUNCHER_R74_20261111'
$expected = @{
    'BUKA-PI-HIDDEN.vbs'='9FC0BF471E18'
    'BUKA-PI-LARAVEL-HIDDEN.cmd'='D59A6681D3A0'
    'BUKA-PI-LARAVEL.cmd'='A0238A4B33E5'
}
foreach ($name in @('BUKA-PI-HIDDEN.vbs','BUKA-PI-LARAVEL-HIDDEN.cmd','BUKA-PI-LARAVEL.cmd')) {
    $file = Join-Path $R74 $name
    if (-not (Test-Path -LiteralPath $file -PathType Leaf)) { Write-Host "PI_R74_MISSING=$name"; continue }
    $hash = (Get-FileHash -LiteralPath $file -Algorithm SHA256).Hash.Substring(0,12)
    Write-Host "---- $name SHA256_12=$hash EXPECTED=$($expected[$name]) ----"
    $number = 0
    Get-Content -LiteralPath $file | ForEach-Object {
        $number++
        $line = [string]$_
        if ($line -match '(?i)(password|passwd|secret|token|credential|authorization|bearer|api.?key|APP_KEY|DB_|DATABASE_URL|SESSION_|PRIVATE.KEY|[?&]key=)') {
            Write-Host ('{0,3}: [SENSITIVE LINE REDACTED]' -f $number)
        } elseif ($line -match '(?i)https?://' -and $line -notmatch '(?i)https?://(127\.0\.0\.1|localhost)([:/]|$)') {
            Write-Host ('{0,3}: [NON-LOOPBACK URL REDACTED]' -f $number)
        } else {
            Write-Host ('{0,3}: {1}' -f $number,$line)
        }
    }
}
Write-Host 'PI_R74_CHAIN_INSPECTION=READ_ONLY'
```

If a sensitive line is essential for understanding the chain, share the command shape only and redact its actual value manually. Never paste API keys, APP_KEY or DB credentials. This run does not start/stop servers or change shortcuts.
