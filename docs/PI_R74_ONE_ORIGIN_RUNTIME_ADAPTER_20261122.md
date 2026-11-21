# PI R74 — Existing-icon single-origin runtime adapter (staged 22 Nov 2026)

**State: SOURCE_ONLY / NOT_INSTALLED / REAL R74 WScript ENTRYPOINT NOT YET VERIFIED.**

The existing Windows shortcut is protected. The guarded PowerShell helper `scripts/pi-r74-ensure-runtime.ps1` is not a second launcher, icon, service, or installer. No default port is selected. The old Docker 8080 origin, historical R84 8820, and QA 8822/8823 must not be presumed to be the R74 shortcut origin.

The adapter defaults to read-only `-Mode Audit`. `-Mode Ensure` is opt-in, to be wired **only** into the inspected existing R74 WScript chain. It requires explicit Laravel `-AppRoot`, `-Port`, and `-PhpExe`; verifies `artisan`, vendor, original `.env` existence, configured APP_KEY/SESSION_COOKIE, APP_URL equal to chosen origin, and a static release identity marker. Before reusing any listener it checks loopback-only binding, exact Laravel root in the PHP process command line and marker HTTP response. A mutex prevents double-start; when the correct server is absent it starts only that Laravel instance (hidden PHP artisan server) and waits for identity before reporting ready. It never edits, migrates, resets, backs up or deletes the DB, credentials, shortcut, or files. It never stops processes, installs services, or enables provider egress.

**Required operator evidence before actual integration:** from the provided read-only PowerShell, `PI_R74_ENTRY_SCRIPT`, `PI_R74_ENTRY_EXISTS`, `UsedByShortcut`, and the script contents/redacted start chain (without secrets). Read the actual entrypoint and identify its existing origin, process owner, DB and startup semantics. If the existing R74 chain is Docker, PHP service, or an alternate managed runtime, adapt *that same mechanism*, not this assumed PHP candidate.

**Release and acceptance:** prepare source-only R88/R89 stack in a reconciled candidate, preserve main APP_KEY/DB/tenant/user/ledger/PDF and backup receipt, verify session-cookie separation from temporary QA, then run test on original desktop icon: first click cold start; second click reuse with same origin/PID; Windows logon/restart recovery; authenticated PI Superior R85-R89, existing AI Settings, reviewer/export/390px and privacy/ABSTAIN; unrelated port owner fails closed. Do not claim Windows E2E or production readiness until actually observed on owner machine.

The built-in PHP server here is a **local desktop development/QA mechanism**, not an enterprise production service. For an always-on Windows deployment, reuse a verified managed local HTTP service and health supervision after acceptance, not duplicated app servers.

`PRODUCTION_ADMISSION=DENIED`, `MERGE_READINESS=NOT_READY`.
