# PI R108 — first-wave implementation and acceptance

Date: 2026-11-24 Asia/Jakarta.
Source-tested SHA: a1837a3c5b3c22e0f8cb0631800d1f0140bba18b.
State: **SOURCE/CI PASS; ORIGINAL 127.0.0.1:8820 R108 DEPLOY NOT YET RUN.**
This is first-wave code toward the whole-product planner-centred UX mandate, not a claim of complete redesign of every specialist screen or all government planner workflows.

## User intent and diagnosis
Owner: PI pages too long, stacked features without a recognizable planner journey, milestone/software terminology mixed with formal planning terminology. Actual source checked: pi-ia-r69e.js, index.html, development-case-r85.html, regulatory pages and authenticated R106 owner screenshot. Task completion is the objective; source architecture remains internal.

## Exact code changed
- Shared pi-ia-r69e.js/css: user-facing work categories Beranda kerja, Telaah perencanaan, Anggaran dan pendanaan, Pelaksanaan dan evaluasi, Dokumen dan dasar hukum, Temuan dan tindak lanjut, Telaah regulasi lanjutan, Pengaturan dan bantuan. Existing deep links to regulatory genealogy, chronology, PDF and reviewer remain, with plain-language labels. The existing module entitlement API still limits specialist links, backend authorization remains authoritative.
- index.html: 'Mulai dari pekerjaan yang perlu Anda selesaikan'; five task-oriented entry cards from the new planner-workflow-r108.js. Existing home metric, findings and document detail remain mounted but are collapsed until requested.
- development-case-r85.html: clearer human planning vocabulary and headers; original Laravel DOM IDs are preserved.
- NEW planner-workflow-r108.js/css: six workflow views (Ringkasan, Kewenangan, Perencanaan, Anggaran, Dokumen, Rincian) selected with #pi-view=... URL fragments. One view displayed at a time, original R86/R88/R89/R100/R106 panels remain in document and can be opened. Existing original R106 direct hash #r106-annex-review maps to Anggaran. No new database, provider requests or API.
- R95 real authenticated Chrome and R97 owner Edge acceptance now check stage-by-stage visibility, 390px overflow, provenance and non-admission rather than require all features visible simultaneously.
- NEW frontend pi_r108_planner_workflow.test.cjs.
- NEW scripts/pi-r108-planner-ux-cutover.ps1 and pi-r108-ci-windows-fixture.ps1: exact SIX file code-only in-place original Windows owner rollout, default read-only, requires explicit -Apply; four old-source replacements + two new assets. SHA pinned clean QA and source baseline R107 accepted commit 1f0620fbf0c2571a6b8aa13fe5a719c0ca8da24b, per-file backups, staged SHA, auto-rollback, drift HOLD, single original PID/login/CSRF, anonymous R106 API401 and six HTML/JS/CSS live SHA guards. Protect original env, SQLite, APP_KEY, routes, R105/R106 files, launcher and owner PDF archives.

## Independent actual CI
- Windows synthetic safe cutover run 45380816244 SUCCESS: PI_R108_WINDOWS_POWERSHELL51_SYNTAX=PASS, PI_R108_WINDOWS_EXACT_SIX_READ_ONLY_PREFLIGHT=PASS, PI_R108_WINDOWS_FAULT_INJECTION_ROLLBACK=PASS, PI_R108_WINDOWS_APPLY_SOURCE_SHA_AND_IDEMPOTENCE=PASS, PI_R108_WINDOWS_UNKNOWN_LOCAL_DRIFT=HOLD_NO_OVERWRITE.
- R88 45380815939 SUCCESS: Laravel 60 tests/654 assertions PASS, frontend 64 tests/0 failures; authenticated R93 HTTP, real R108 Chrome work view navigation, desktop and mobile390 PASS.
- R73 45380815648 SUCCESS; R74 45380815954 SUCCESS.
- Four workflow links: https://github.com/fajarnurmanto-source/planning-intelligence/actions/runs/45380816244 ; https://github.com/fajarnurmanto-source/planning-intelligence/actions/runs/45380815939 ; https://github.com/fajarnurmanto-source/planning-intelligence/actions/runs/45380815648 ; https://github.com/fajarnurmanto-source/planning-intelligence/actions/runs/45380815954 .

## P0 first-pass inventory: what is and is not redesigned
Home (index.html) and case (development-case-r85.html): actual first-wave body changes. Shared sidebar applies to pages loading pi-ia-r69e.js including regulatory-r69e.html, regulatory-laravel-r70g.html, regulatory-trace-r70a.html, regulatory-sources-r70b.html and workspace.html. Their specialized BODY layouts are NOT YET redesigned. In particular workspace.html is a compiled React screen and must not be overwritten blindly. AI settings, subscription, legacy policy pages require separate access and role review.
R106 scoped local evidence and 2/2 original protected PDF links remain accepted from R107. R84 reviewer security and fiscal/production denial unchanged. UI role cues cannot create permission.

## Owner original Windows action (not yet done)
Use existing isolated QA worktree; checkout exact tested SHA with clean status. Run R108 preflight script with CandidateRepo and ExpectedCommit; only after it prints PI_R108_READ_ONLY_PREFLIGHT=PASS and PI_R108_ALLOWLIST=6, run the same script with explicit -Apply. Original single PHP 8820 must already run; no migrations or new launchers. If any shared original file differs from baseline, stop; never reset or force overwrite.

Browser owner acceptance: open existing localhost8820 index.html, inspect task cards and collapsible detail; open development-case-r85.html#pi-view=anggaran, confirm R106 six rows and both source PDF links still open; visit Kewenangan, Perencanaan, Dokumen and Rincian views, confirm reviewer and cloud consent remain protected. Desktop, mobile390 and signed-in check required.

## Remaining mandatory R108 work
Complete all route/screen ownership and vocabulary mapping (especially React workspace), revise lengthy regulatory and specialist body screens, support nonlinear return context and multiple planner-role user journeys, make exact-source row navigation and accessible keyboard/browser paths, run in-person usability acceptance, and guard another original release. These are NOT marked done by first-wave code. PR #78 remains DRAFT; legal/financial/production admission remains DENIED.
