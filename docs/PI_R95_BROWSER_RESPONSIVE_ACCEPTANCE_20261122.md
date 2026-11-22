# PI R95 — Real Chrome Browser Acceptance and 390 px Mobile Correction

Evidence PIT: 22 Nov 2026; user actual original R94 8820 source update SUCCESS. CI source pinned e2ca5fe22312ab39e1639b759901d0dd26691021. PR #78 DRAFT.

## Owner Windows original—what is confirmed
- Original R94 -Apply was successful: 17 R85–R89 code files patched or added; original single port 8820, PHP PID 12460 unchanged, original account setup/CSRF verified; new anonymous R88/R89 API routes 401 and HTTP HTML/JS asset SHA match. Exact backup D:\Research\PlanningIntelligence\PI_UNIFIED_LAUNCHER_R74_20261111\backups\R94_CODE_BEFORE_ONE_ORIGIN_20261122-114401. Protected SQLite/.env/APP_KEY/launcher/public assets unchanged.
- Connected browser tab authenticated as institution_admin at http://127.0.0.1:8820/development-case-r85.html; R85 data, R86 38 catalogue candidates and 10 lifecycle stages visible. Screenshot corroborates.
- Original browser R88 button action, R89 metadata preview, logout, actual mobile viewport and natural Windows cold-start are NOT yet observed as completed; GitHub CI synthetic browser results do not replace owner acceptance.

## R95 real browser CI execution
- Existing GitHub Action R88 crosswalk workflow boots temporary Laravel server localhost:19893 with disposable SQLite and synthetic accounts. The independent R93 HTTP cookie/CSRF/reviewer tests run first. Chrome headless uses built-in Node 22 WebSocket and Chrome DevTools Protocol, no npm package or external model provider.
- New scripts/pi-r95-isolated-chrome-browser-qa.mjs does actual Chrome login and navigates authenticated R85 case. R85 and R86 render PASS, real DOM submit of R88 crosswalk PASS, R89 preview displays bounded context but consent stays unchecked and paid cloud action remains disabled PASS.
- Desktop at 1440 px had no horizontal overflow. Initial mobile Chrome viewport at physical 390 px expanded to 425 px; DOM diagnostics identified R85 filter controls (selects + submit button) with 392 px intrinsic tracks forcing page width to 425 px. Not an auth/backend error.
- Minimal fix in laravel/public/development-case-r85.css changes @media (max-width:680px) R85 filter grid from 1fr to minmax(0,1fr), constrains grid children and native select/input to width:100%/max-width:100%/min-width:0/box-sizing:border-box; also constrains R85 flow grid to minmax(0,1fr). No PHP, DB or session code changes.
- With correction, CI Chrome reports exact viewport/visual/screen/doc/body widths all 390, no overflow elements, all R85/R86/R88/R89 areas visible. After changing R88 case year to 2027, UI must remain ABSTAIN and R89 preview/run/consent disabled. All PASS.
- On code SHA e2ca5fe22312ab39e1639b759901d0dd26691021, GitHub Windows launcher audit safety SUCCESS and R88 crosswalk CI SUCCESS (35 Laravel tests/321 assertions, 61 JS tests, 3 R93 integrated Laravel tests/56 assertions, real R93 HTTP and R95 Chrome viewport flow PASS).

## Safe original Windows CSS-only follow-up
- R95 CSS fix exists only in GitHub candidate; original user 8820 still serves original R94 CSS until explicitly upgraded.
- Reuse exactly the existing R94 verified installer script at pin e2ca5fe22312ab39e1639b759901d0dd26691021. It has 17 allowed files but only ONE should differ on already-patched R94 Windows: public\development-case-r85.css:REPLACE. Preflight must say PI_R94_CHANGED_OR_ADDED_COUNT=1 and no other PI_R94_CODE_CHANGE lines; otherwise HOLD.
- After successful preflight, explicit -Apply replaces only CSS, backs up all originals to new timestamped directory, verifies source sha/served HTML/JS, protected account, new route 401, PHP PID and protected assets; auto code rollback on failure.
- Do not rerun Composer, migrate original SQLite, restart PHP, reset login or add another launcher. After success open original Edge DevTools responsive dimensions 390x844 or phone, verify no side-scroll and R88/R89 remain functional.

R94_ORIGINAL_SOURCE_DEPLOY=PASS
R95_CI_CHROME_DESKTOP_1440=PASS
R95_CI_CHROME_MOBILE_390=PASS
R95_CI_R88_CLICK_R89_PREVIEW_2027_ABSTAIN=PASS
R95_ORIGINAL_CSS_HOTFIX=NOT_YET_APPLIED
R95_ORIGINAL_MOBILE_VISUAL=NOT_YET_CONFIRMED
R74_ORIGINAL_ICON_COLD_BOOT=NOT_YET_TESTED
PRODUCTION_ADMISSION=DENIED


## CD-20261122-07D — Owner Windows CSS-only hotfix accepted
Actual owner PowerShell confirmed clean pinned source `e2ca5fe22312ab39e1639b759901d0dd26691021`, R94 read-only preflight exactly 1 change: `public\development-case-r85.css:REPLACE`, not the 17-file module deploy again. Explicit -Apply returned account and CSRF HTTP verified, R88/R89 anonymous protected route and static HTML/JS hashes verified, `PI_R94_ORIGINAL_PHP_PID_PRESERVED=12460`, protected data/asset SHA unchanged and `PI_R94_CODE_CUTOVER=APPLIED_VERIFIED`. Independent read-back of HTTP CSS SHA returned `PI_R95_MOBILE_CSS_PATCH=APPLIED_AND_HTTP_VERIFIED`. Exact second R94 backup: `D:\Research\PlanningIntelligence\PI_UNIFIED_LAUNCHER_R74_20261111\backups\R94_CODE_BEFORE_ONE_ORIGIN_20261122-132001`. New source is installed on original 8820. Chrome CI automated 1440/390, R88 button and R89 preview remains PASS, but original Windows Edge 390 screenshot and R88/R89 click are separate manual browser acceptance NOT YET VERIFIED; one-click cold start after a natural Windows reboot remains NOT YET TESTED. Never claim release approved from CSS/hash acceptance alone.


## R96 — Incremental CSS-only deploy and unique backup regression
Windows CI now explicitly reproduces the local history: original full R94 17-file deployment in a synthetic temporary fixture, then a single CSS drift and a strict R95 1-file preflight, protected SHA-backed CSS apply, followed by an unchanged no-op that must HOLD. This exposed a guardrail issue: the two deploys occurred within the same second so the source installer used identical `yyyyMMdd-HHmmss` backup folder names and correctly stopped before mutating the second change (`PI_R94_BACKUP_COLLISION`). GitHub source-only installer has been hardened to timestamp + milliseconds + random GUID suffix at pinned commit `3ed7d73857675c9a921fb6c759828280c6399aca`, preserving readable date prefixes and preventing same-second backup name collisions. That new installer code is NOT deployed on the user's Windows original; the actual R95 CSS patch there already succeeded safely with the previous installer. New CI acceptance pending; do not request local rerun or reset app. Further owner UAT is Edge 390 px, R88/R89 clicks/logout and natural cold startup.


## R96 Windows unique-backup and incremental acceptance completed
Pinned code `3ed7d73857675c9a921fb6c759828280c6399aca` Windows workflow SUCCESS. Temporary fixture: 17-file R94 Apply+backup PASS, then only R95 `development-case-r85.css` drift accepted with exactly one `CODE_CHANGE`, second Apply and SHA verified, subsequent identical run held `PI_R94_NO_SOURCE_DIFFERENCE_HOLD`, injected first-file failure restored prior originals, all protected original sentinel SHA equal. Backup filename collision from two same-second calls corrected in source-only R94 installer via millisecond timestamp and random GUID suffix. No code installer upgrade necessary on actual Windows original: R95 was already applied and HTTP verified. Owner browser/phone and natural Windows cold boot still unobserved.
