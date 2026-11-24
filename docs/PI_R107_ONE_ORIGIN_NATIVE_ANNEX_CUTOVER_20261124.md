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

## Owner original single-runtime Windows Apply PASS — 24 November 2026

The owner provided complete post-command PowerShell output for R107 original source cutover. GitHub tested source pinned at 1f0620fbf0c2571a6b8aa13fe5a719c0ca8da24b. Three original files matched canonical R100 baseline: config/pi.php, routes/web.php and public/development-case-r85.html. Exactly 11 allowable Laravel files: 3 replacements and 8 new R105/R106 service/controller/asset/resource files. Default dry-run PI_R107_READ_ONLY_PREFLIGHT=PASS.

The explicit -Apply operation reported:
- PI_R107_ORIGINAL_PHP_PID_PRESERVED=5704
- PI_R107_ORIGINAL_LOGIN_CSRF=PASS
- PI_R107_ANONYMOUS_R106_API_AND_PDF=401
- PI_R107_LIVE_HTML_JS_CSS_SHA=PASS
- PI_R107_BACKUP=D:\Research\PlanningIntelligence\PI_UNIFIED_LAUNCHER_R74_20261111\backups\R107_NATIVE_ANNEX_20261124-153454-794-fe294548
- PI_R107_CODE_CUTOVER=APPLIED_VERIFIED
- PI_R107_SQLITE_ENV_APPKEY_LAUNCHER=UNCHANGED
- PI_R107_NUMERIC_REVIEW_PRODUCTION_ADMISSION=DENIED.

**R107_ORIGINAL_WINDOWS_SOURCE_CUTOVER=OWNER_PASS.** Existing original server PID, login/CSRF and anonymous auth gate have passed. No need to rerun the R107 installer. No new server, launcher, DB migration or API-model spend. Keep the backup folder.

**Next independent gate, not yet observed:** owner browser, authenticated to original http://127.0.0.1:8820/development-case-r85.html, click "Periksa lampiran dari arsip lokal" and confirm 6 exact account rows and 1,020,366,250 candidate sum, and test BOTH source PDF page links. An authenticated 503 means the original PHP user's LOCALAPPDATA isolation / R104 quarantine visibility or R105 gate requires diagnosis; do not disable auth, invent receipt approval or change original env without review.

**Human review and fiscal gates remain:** legal identity and origin, annex completeness, later amendments 13/15/2026, RKA/DPA/realisasi, and R84 reviewer decisions still required. Candidate numeric admission zero, production admission denied. PR #78 remains DRAFT.

## Owner original authenticated browser evidence — 24 Nov 2026

Two owner-supplied screenshots demonstrate R106 user-facing execution on the original PI 8820 page after the R107 11-file source cutover. Visible message: "Kandidat ekstraksi lokal berhasil. Perlu review sumber dan hukum." Full account table displays 6 entries with verified-equivalent candidate amounts and zero deltas, source PDF pages 384/385 (Perwal 74/2025) and 561 (Perwal 12/2026), candidate total Rp1.020.366.250. Two source-page links are visibly rendered. **Acceptance status:** R106 authenticated click + six-row render PASS; the PDF links' actual open/read behavior remains NOT YET OBSERVED. This is not R84 review, legal document authenticity, Perwal13/15 harmonization, RKA/DPA/realisasi verification, fiscal numeric admission, or production approval.

The full-page screenshot reveals significant UX debt: Kasus pembangunan terpadu is a long, dense single page of unrelated phases; R106 is at the very bottom after R100. Navigation exposes "Dokumen & bukti" in Evidence Intelligence and "Penganggaran & APBD" in Fiscal Intelligence, but neither is an obvious direct route to the R106 annex. This is a navigation and information architecture issue, not an extraction defect. Proposed R108 remediation is recorded separately; don't alter the original Windows app without a new guarded UI release and acceptance.
