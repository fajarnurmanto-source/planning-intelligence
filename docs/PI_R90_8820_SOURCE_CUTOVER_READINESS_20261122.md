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


## CD-20261122-06T — Owner partial risk census and regex failure repaired
Operator read-only QA run e5eba4c on original D: main established no Composer direct requirement changes, composer.lock not identical, Composer setup unsafe for main, app auth route/controller/tenant middleware different, original login HTML/JS SAME, R88 crosswalk and R89 semantic controllers absent. Missing runtime inventory exactly 8: app/Http/Controllers/Api/R89SemanticRegulatoryController.php; app/Http/Controllers/Api/RegulatoryPlanningCrosswalkController.php; app/Services/Pi/Development/CloudSemanticCandidateProvider.php; app/Services/Pi/Development/HybridSemanticReasoner.php; app/Services/Pi/Development/R89SemanticRegulatoryAnalysis.php; app/Services/Pi/Development/RegulatoryPlanningCrosswalk.php; app/Services/Pi/Development/SemanticCandidateProvider.php; public/regulatory-planning-r88.js. The script then errored on invalid PowerShell regex pattern '\' in main-only path conversion; no migration summary or audit terminal PASS was produced. Corrected source in PR #78 uses literal character replacement and GitHub Actions Windows now runs the ACTUAL audit on its own synthetic TEMP root through full file enumeration, main-only detection and migration filename census, verifying no fixture bytes changed. Tested pinned HEAD 327d38ef293e920b8bb3be3338e0680fc9497c32 (Windows CI and R88 CI PASS). This is CI proof only; owner original audit must rerun to finish real migration/main-only inventory. No migration, main database read/write, server kill or APP_KEY change allowed.


## CD-20261122-06U — Owner live audit complete; normalized source classification ready
Live Windows owner R90 run pinned `327d38ef293e920b8bb3be3338e0680fc9497c32` completed in read-only mode with `PI_R90_CUTOVER_RISK_AUDIT=COMPLETE_READ_ONLY`. Composer direct requirement delta 0 but composer.lock not identical; composer setup unsafe on protected 8820. Main auth route/session controller/tenant middleware differ from candidate; R88/R89 new controllers missing; original repaired login.html/js exact match. 8 missing tracked files enumerated (two API controllers, five crosswalk/semantic providers, one R88 JS). **28 main-only files** including original public favicon, robots, overview JSON, uu12-2011-r70a.pdf, compiled indexed assets must be preserved, not cleaned as garbage. **Five migrations different SHA:** 0001_01_01_000000_create_users_table.php, 0001_01_01_000001_create_cache_table.php, 0001_01_01_000002_create_jobs_table.php, 2026_11_07_033435_add_pi_identity_fields_to_users_table.php, 2026_11_15_150000_create_regulatory_evidence_reviews_table.php; zero identical, zero missing. Hash mismatch does not prove SQL behavior mismatch: CRLF/BOM could account for some. New source-only `scripts/pi-r90-drift-classifier-readonly.ps1` with pinned-clean-source gate, original main root, CI fixture-only exception, classifies runtime and migration byte differences into content-vs-line-ending vs BOM; separately compares Composer lock package versions, added/removed references and dev scope. Fixture-tested on Windows PowerShell 5.1 with synthetic CRLF and changed migration bodies and appended lock metadata, database/password/server not accessed or modified; Windows/R88 CI SUCCESS at 3fad03fa81caabd50b792921367f6752aef66388. Actual original owner classification pending. Cutover remains HOLD; next after classification is read-only SQLite schema/migration ledger check in isolated planning and protected QA; no blanket source copy or `composer run setup` on production.


## CD-20261122-06V — Owner normalized source evidence, migrations and lock identities
Real owner run of pinned 3fad03fa81caabd50b792921367f6752aef66388 succeeded end-to-end in READ ONLY. Runtime files = 153: BYTE_IDENTICAL=10, LINE_ENDING_ONLY=126, BOM_OR_LINE_ENDING_ONLY=0, TEXT_CONTENT_DIFFERENT=8, BINARY_OR_UNSUPPORTED_DIFFERENT=1, MISSING_ON_MAIN=8, large-text/nonUTF8=0. Thus 136/153 equal after line normalization (~88.9%). Five candidate migration source files ALL `LINE_ENDING_ONLY`; no substantive source differences in migration PHP. Composer lock: main 119 packages, candidate 119, ADDED=0, REMOVED=0, VERSION_CHANGED=0, SOURCE_REF_CHANGED=0, DEV_SCOPE_CHANGED=0, byte drift with same package identities=YES. The earlier claim that five migration hashes differed remains historically accurate at byte level but must no longer be treated as five different migration implementations. No SQLite schema/ledger has yet been inspected and package lock metadata differences could still matter. Candidate original 28 main-only assets and 8 missing new runtime files protected. Next source-only code in PR #78 prints every actionable text/binary divergent filename independent of sample cap and probes original SQLite table-column metadata and five migration ledger names via SQLite3 OPEN_READONLY, no row data, password or session reading, and no schema mutations. Synthetic Windows CI verification pending at authoring; never mark actual main R91 gate PASS until owner output. Cutover remains HOLD.


## CD-20261122-06W — R91 metadata gate ready, actual owner schema not yet assessed
GitHub PR #78 tested source commit `87fe9e23b71e1ab7eba0c230793098be3940d216` Windows PS5.1 and R88 regression SUCCESS. R90 Drift Classifier now emits `PI_R90_ACTIONABLE_RUNTIME_DIFF=path:CLASS` for all 8 substantive text differences plus the one binary/unsupported difference, rather than only 20 earlier CRLF samples. A new independent `scripts/pi-r91-sqlite-schema-readonly.php` metadata-only CLI probe opens the **original** D: `database/database.sqlite` strictly with `SQLITE3_OPEN_READONLY`, checks SQLite quick_check, minimum column presence in migrations/users/sessions/cache/cache_locks/jobs/job_batches/failed_jobs/regulatory_evidence_reviews, and the 5 expected migration names in Laravel migrations ledger. It reads no user/credential/session/reviewer row values and performs no SQL writes, migrations or config changes. CI fixture under RUNNER_TEMP validated complete 9/9 + 5/5 schema, missing-migration negative detection and unchanged database SHA. The operator has **not** yet run either updated R90 actionable list or live R91 probe; do not report local schema PASS prematurely. CRLF-normalized migration source parity alone is not a database schema proof. Independent 8820 verified login and desktop warm launch remain PASS, cold reboot and R88/R89 cutover still pending.
