# PI R92 — Protected Single-Origin R88/R89 Reconciliation Gate

Date/PIT: 2026-11-22. STATUS: SOURCE-ONLY QA PLAN, NO DEPLOYMENT.
Protected product: existing OneDrive Desktop shortcut, original Laravel D:\Research\PlanningIntelligence\planning-intelligence-v0.0-starter\planning-intelligence-v0.0\laravel, sole primary 127.0.0.1:8820.

## Actually observed, not inferred
- Existing original icon reused the SAME PHP PID 12460 twice on warm start; no second owner-facing runtime. Authenticated owner login and institutional dashboard PASS.
- R91 SQLite on protected original: quick_check=ok; 9/9 required tables+columns present, 5/5 expected migration names applied, zero missing; no user/password/session/reviewer row values read and no writes. This is minimum metadata parity, NOT full schema/index/foreign-key or R88/R89 feature acceptance.
- R90: 153 tracked runtime files, 10 byte-identical, 126 LF/CRLF-only, 8 real content differences, 1 Apache .htaccess classified as unsupported because extensionless, 8 new files missing on original. FIVE migrations differ only LF/CRLF; no text-content change in migration PHP. Original and candidate composer.lock both have 119 identical package identities/version/ref/dev-scope but raw lock bytes differ.
- Exactly 28 original-only runtime/public assets must be preserved, including favicon.ico, robots.txt, overview-r69c.json, local uu12-2011-r70a.pdf and compiled versioned JS/CSS assets; this does NOT include more sensitive original DB, .env, APP_KEY, storage, accounts and source rights/PIT which must also remain protected.

## Nine actionable original/candidate file comparisons

| File | Gate |
|---|---|
| app/Providers/AppServiceProvider.php | Explicit R89 semantic provider interface and cloud binding; zero external egress without selected provider, tenant credentials and consent |
| config/pi.php | Tenant, regulatory registry paths, semantic cloud enablement. Existing live env/data paths remain authoritative |
| routes/web.php | Login, logout, protected session/tenant, R84 reviewer and R88/R89 routing/CSRF integrity |
| public/.htaccess | Apache plaintext, initially unsupported due extensionless filename. Reclassify LF/CRLF and inspect auth/rewrites before touching |
| public/development-case-r85.html | Adds R88 crosswalk and R89 semantic approval UI; no passive cloud transmission |
| public/development-case-r85.css | Responsive and evidence link layout at desktop and 390px |
| public/regulatory-laravel-r70g.html | Keep R87 read model navigation functional |
| public/regulatory-lifecycle-r86.css | R86 10-stage lifecycle/5 specialized responsive layout |
| public/regulatory-lifecycle-r86.js | Temporal validity and review ABSTAIN guards; no legal/numeric promotion |

## Eight missing new R88/R89 dependent files
- app/Http/Controllers/Api/R89SemanticRegulatoryController.php
- app/Http/Controllers/Api/RegulatoryPlanningCrosswalkController.php
- app/Services/Pi/Development/CloudSemanticCandidateProvider.php
- app/Services/Pi/Development/HybridSemanticReasoner.php
- app/Services/Pi/Development/R89SemanticRegulatoryAnalysis.php
- app/Services/Pi/Development/RegulatoryPlanningCrosswalk.php
- app/Services/Pi/Development/SemanticCandidateProvider.php
- public/regulatory-planning-r88.js

Never copy routes/controllers/providers separately; these modules form a dependency chain.

## R92 newly found CSRF defect (source only, main original untouched)
Both the original pi-auth-shell.js logout POST and R89 regulatory-planning-r88.js semantic POST used X-PI-CSRF. Working Laravel login uses recognized X-CSRF-TOKEN; no custom exception handler for this header is seen in main middleware. This is an unproven but concrete potential HTTP419. In PR #78 source-only, both were updated to X-CSRF-TOKEN; Node VM actual logout and explicit consent/CSRF tests added. DO NOT assert original D: logout/semantic E2E is fixed until installed after isolated QA.

## Full acceptance before a protected cutover
1. Pin a clean candidate commit, inspect 9 changed file bodies and .htaccess classification, all 8 dependency additions, and preserve the exact 28 original-only files and all owner data.
2. Run isolated, disposable candidate Laravel QA **on private data copies only**. Do not point test runner to live original SQLite, perform composer setup/key:generate, or migrate original production DB. No additional persistent Windows product/launcher or owner port.
3. Validate anonymous 401, owner login/session/logout+CSRF, tenant fence, R85 case, R86 lifecycle, R88 crosswalk, R89 GET preview (no egress), R89 POST without consent (rejected, no network), evidence PDF links, reviewer access/ABSTAIN, desktop/390px. External paid BYOK requests EXCLUDED until explicit approval.
4. Only after E2E: exact SHA256 manifest, immutable original backup, code-only atomic update respecting active PHP and data locks, protected rollback plan. Preserve database/.env/APP_KEY and 28 additional original assets. No blanket file deletion or source migration.
5. Verify existing icon warm reuse/login after proposed change and one-click cold recovery after next **natural Windows reboot**. Never claim reboot PASS from warm PID reuse.

R74_ICON_WARM_REUSE=PASS
R74_OWNER_BROWSER_AUTH_DASHBOARD=PASS
R91_SQLITE_MINIMUM_SCHEMA_LEDGER=PASS
R92_CSRF_SOURCE_PATCH=STAGED_ONLY
R92_ISOLATED_R88_R89_E2E=NOT_RUN
R74_ICON_COLD_REBOOT=NOT_TESTED
PRODUCTION_ADMISSION=DENIED
