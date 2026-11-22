# PI R90 — One-Origin 8820 Source Cutover Readiness

Evidence date: 2026-11-22. Owner PowerShell pin: 40363393e89a81c764233252fcb7fceb6f861214. This is an acceptance ledger, NOT production approval.

## Verified milestones

- The original Windows desktop icon and original WScript/VBS chain passed read-only baseline and backup verification. Double-opening the SAME icon with 8820 already running reused the SAME PHP PID 12460: first click 12460, second click 12460, PI_R74_EXISTING_DESKTOP_ICON_RUNTIME=PASS.
- The owner account fajar.nurmanto, expected tenant/role, bcrypt parity, actual HTTP login and session, and rendered authenticated PI Superior dashboard all PASS. The dashboard is QA and displayed 6 catalog documents, 6 available evidence PDFs, 3 review findings and 0/3 approved reviewer decisions.
- Original D: Laravel serves the corrected login.html and login.js with SHA256-matched HTTP responses; the old static files are protected by a timestamped backup.

## Actual source divergence to stacked R88/R89 candidate

| File group | Candidate files | Same | Different | Missing on original |
|---|---:|---:|---:|---:|
| app | 70 | 3 | 60 | 7 |
| routes | 2 | 0 | 2 | 0 |
| config | 11 | 0 | 11 | 0 |
| public | 69 | 6 | 62 | 1 |
| bootstrap | 1 | 1 | 0 | 0 |
| Total | 153 | 10 | 135 | 8 |

Only 6.54% are SHA256-identical, but this is NOT a measure of overall feature completeness. Samples show different Cloud AI/settings, R85 case, regulation, reviewer, and R89 semantic controllers; one R89 semantic controller is missing. Source-only replacements without route, middleware, installed package and schema compatibility would risk breaking the account and dashboard that have just recovered.

## Guardrails for controlled one-port cutover

1. The candidate Laravel composer.json includes setup commands for installing dependencies, generating APP_KEY, running migrations and building frontend assets. NEVER run composer run setup, key:generate, migrate, migrate:fresh, db:wipe or uncontrolled package updates on original D: main Laravel.
2. The original directory remains D:\Research\PlanningIntelligence\planning-intelligence-v0.0-starter\planning-intelligence-v0.0\laravel; original launcher remains D:\Research\PlanningIntelligence\PI_UNIFIED_LAUNCHER_R74_20261111; original desktop shortcut and sole owner product origin http://127.0.0.1:8820 are protected.
3. Preserve .env, APP_KEY, actual SQLite, uploads, sessions, accounts, reviewers, official evidence/provenance/PIT and prior backups. A newer source branch is NOT authority to overwrite real user data or reviewer judgments.
4. PR #74 R88 remains open; hybrid semantic PR #75, local semantic PR #76, cloud BYOK PR #77 and launcher PR #78 remain draft as observed on 22 Nov. GitHub stacked source HEAD is a candidate, not a released build.
5. Do not infer database schema compatibility or dependency compatibility from source hash checks. Determine required Composer packages, migrations, route/controller/tenant/CSRF interoperability, and frontend asset versions before any cutover.

## Implemented next read-only audit

Source-only scripts/pi-r90-cutover-risk-readonly.ps1 accepts a pinned, clean candidate QA worktree and audits protected original main Laravel without any modification. It compares Composer require/lock information, flags dangerous composer setup scripts, hashes key R85-R89 controllers and auth boundaries, and checks candidate migration files against original filenames/hashes. It does not run Composer, Laravel, PHP runtime, migrations, network requests or SQL queries; never reads .env, passwords, APP_KEY or SQLite content. It returns PI_R90_ONE_ORIGIN_CUTOVER=HOLD_PENDING_ISOLATED_ACCEPTANCE until actual integration acceptance.

## Acceptance still open

- Original icon cold-start and authenticated access after a normal Windows reboot, without a manual server command: NOT TESTED. Do not kill a healthy PHP process just to fake restart.
- Owner run of pinned R90 read-only risk audit, diagnosis of all 8 missing source files, Composer/package lock and migration compatibility.
- Isolated, protected candidate source+data-copy QA (no second persistent product origin): authentication, R85 case, R86 regulatory, R88 crosswalk, R89 semantic privacy boundary, reviewer permissions, evidence PDF, desktop/390px browser, data and tenant safeguards. No provider keys or paid AI calls without separate approval.
- Reversible single-directory cutover plan with exact file manifest, test, backup and rollback after owner acceptance. Do not merge or deploy all 143 divergent/missing files blindly.

R74_WARM_SAME_PID=PASS
R74_BROWSER_AUTH_DASHBOARD=PASS
R90_SOURCE_PARITY=NOT_YET_CONSOLIDATED
R90_CUTOVER_PREFLIGHT=PENDING
R74_COLD_RESTART=NOT_TESTED
PRODUCTION_ADMISSION=DENIED
