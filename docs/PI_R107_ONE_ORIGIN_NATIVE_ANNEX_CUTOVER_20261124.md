# R107 — single existing PI 8820 native annex cutover

R107 provides a PowerShell 5.1 default read-only preflight, exact 11-file allowlist, SHA pin, R100 known source baseline, staged backups and automatic rollback for R106. No separate server, shortcut, DB migration, .env or credential changes. It updates 3 existing source files and adds 8 bounded R105/R106 source assets. Existing original launcher backup folder holds immutable snapshots and manifest. Any unknown local code drift BLOCKS. Authenticated PI UI click check is still required after operator -Apply; anonymous API PDF and annex routes must both give HTTP401 and JS/CSS/HTML bytes must match local. The R107 config enables only if an existing R104 isolated local archive is present under active Windows PHP user's LOCALAPPDATA; the R105 service validates SHA/receipts and unreviewed fiscal gates. This is not legal, fiscal, commercial, or production admission. PR #78 Draft.

Owner next: pinned clean QA source; run scripts/pi-r107-one-origin-native-annex-cutover.ps1 -CandidateRepo <QA> -ExpectedCommit <SHA> (no -Apply) to inspect exact 11 changes and R100 baseline. After acceptance explicitly rerun with -Apply. Always maintain one original PHP 8820 and no config/route cache. Never rerun R94 or R100 installers.

CI-only -CiFixtureRoot restricted to GitHub Actions RUNNER_TEMP; -CiFailAfterSecondWrite tests restoration on disposable copies and is denied on original.

## Source CI acceptance — 24 November 2026

Tested source SHA: 1f0620fbf0c2571a6b8aa13fe5a719c0ca8da24b. Four GitHub Actions workflows completed SUCCESS: Windows launcher audit run 45350535884, R88 crosswalk run 45350535776, R73 45350535769, R74 45350535930. R88 Laravel: 60 tests / 654 assertions PASS, isolated authenticated HTTP/CSRF and Chrome desktop1440/mobile390 PASS.

R107 genuine Windows PowerShell5.1 CI fixture used an isolated synthetic R100 original baseline and demonstrated:
- PI_R107_WINDOWS_POWERSHELL51_SYNTAX=PASS
- PI_R107_WINDOWS_EXACT_ELEVEN_READ_ONLY_PREFLIGHT=PASS
- PI_R107_WINDOWS_FAULT_INJECTION_ROLLBACK=PASS
- PI_R107_WINDOWS_APPLY_SOURCE_SHA_AND_IDEMPOTENCE=PASS
- PI_R107_WINDOWS_UNKNOWN_LOCAL_DRIFT=HOLD_NO_OVERWRITE

The owner original 8820 Windows app R107 preflight and Apply have NOT been run. Actual authenticated R106 UI/API/PDF viewer must still be accepted on the original session. If any original shared file differs from R100 and from the exact R107 candidate, stop and reconcile drift separately. The read-only native PDF feature activates only if the owner R104 quarantine exists under the active PHP process Windows profile and R105 passes; no .env, DB, APP_KEY, launcher or reviewer changes. Production/fiscal admission still DENIED.
