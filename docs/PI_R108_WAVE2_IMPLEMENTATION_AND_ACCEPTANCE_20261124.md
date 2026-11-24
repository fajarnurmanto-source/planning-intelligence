# PI R108 Wave 2 — actual implementation and CI acceptance

Date 2026-11-24 WIB. Tested SOURCE commit 63ee3c30d5846ef3b28dee8b8ee8239a97ba417b; isolated branch codex/pi-single-runtime-windows-app-20261122. PR #78 DRAFT.

**Acceptance: SOURCE/CI PASS (4/4). Original Windows 127.0.0.1:8820 Wave2 NOT YET INSTALLED.** R108 whole-product UX workstream remains active.

## Owner screenshot problem and repair
Wave1 screenshots show four selected context fields and a stale placeholder. The existing R85 service populates selectors asynchronously by replacing options. The new Wave2 producer emits a presentation-only case-state event only after the authenticated server response has populated the options; the consumer re-reads current selected values at that point. No polling, duplicate DB, new source-of-truth or cloud calls. Changed/unsubmitted filters are reported as pending reload; server load errors are not shown as an accepted case.

## Exact six source assets changed
1. laravel/public/development-case-r85.js: signal after real server-filter hydration and on failure, accurate Indonesian status mapping in five case questions, case status, evidence sources and legal/numeric/production guards. Underlying machine enum values untouched; original relation IDs available in optional technical disclosure. No changes to API request, origin allowlist, data validation or reviewer approval.
2. laravel/public/planner-workflow-r108.js: authoritative selected context, one stage-specific next task, unsubmitted change warning, explicit one pilot-case scope, no duplicate R106 annex action. Keeps original six task stages and hash routes.
3. laravel/public/planner-workflow-r108.css: smaller case/home hero, better focus of actions, 390px layout, technical disclosure, hidden-attribute precedence.
4. laravel/public/development-case-r85.html: professional planner labels, real "Muat kasus" request button, shorter purpose, accurate legal and planning caveats, original R106 controls and evidence source links preserved.
5. laravel/public/index.html: concise purpose, explicit pilot-case tag rather than implying a full portfolio, developer release label removed from home footer.
6. laravel/public/pi-ia-r69e.js: subscription moved from primary work navigation to settings; module entitlement provider and server auth preserved.

## Test evidence on exact source commit
- R88 run https://github.com/fajarnurmanto-source/planning-intelligence/actions/runs/45393109175: 60 PHPUnit tests/654 assertions PASS, 67 frontend tests PASS (0 fails), R93 authenticated HTTP PASS; Chrome async case context and plain-status check PI_R108_W2_OWNER_CASE_CONTEXT_AND_PLAIN_STATUS=PASS; work tab navigation PASS; desktop 1440px and mobile 390px no overflow.
- Windows unified launcher audit run https://github.com/fajarnurmanto-source/planning-intelligence/actions/runs/45393109325: R108 Wave2 Windows PowerShell 5.1 script parser PASS, exact SIX replacement files preflight PASS, synthetic second-write fault rollback PASS, source SHA/idempotence PASS, unknown local drift HOLD without overwrite.
- R73 run https://github.com/fajarnurmanto-source/planning-intelligence/actions/runs/45393109348 SUCCESS.
- R74 run https://github.com/fajarnurmanto-source/planning-intelligence/actions/runs/45393109180 SUCCESS.

## Owner original Windows gate
Wave2 new guarded script: scripts/pi-r108-wave2-planner-cutover.ps1.
Baseline for ALL six original Windows files: owner accepted Wave1 source SHA a1837a3c5b3c22e0f8cb0631800d1f0140bba18b. It is a separate explicit -Apply operation after a no-write preflight, against the existing original PHP PID on 8820 with file backups and automatic rollback. It never changes env, SQLite, APP_KEY, routes, account, reviewer, original PDF archive, source hash pins, or launcher. If source drift is detected, stop instead of overwrite.

Owner browser after cutover must demonstrate: selected context now reads wilayah/tahun/topik/kasus; unsubmitted changes ask for "Muat kasus"; readable badges and backend audit details; compact home and honest pilot tag; stage next action, retained six R106 candidate accounts, both authenticated PDF links; desktop and mobile. This acceptance is NOT reported as completed until actual owner evidence appears.

## Mandate continues to R108 Wave3
Whole product still needs redesign inside compiled React workspace, specialist regulatory views, fiscal/monitoring and reviewer workbenches; role-based end-to-end planner task acceptance and return context. No fabricated multi-case portfolio, RKA/DPA/realisasi data or instant legal approval. Fiscal numeric admission ZERO, production DENIED; PR #78 Draft.
