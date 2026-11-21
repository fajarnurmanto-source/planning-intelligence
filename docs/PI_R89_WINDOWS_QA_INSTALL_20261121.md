# R89 Cloud BYOK — Windows isolated QA installation and handoff

Date: 21 November 2026 (Asia/Jakarta). Basis: PI-HYBRID-001 and CD-20261121-05. **Installation is local-only, not performed by GitHub CI.** GitHub CI validates script parsing, SQLite backup semantics and Laravel source tests; it cannot access the operator's C:/D: drives.

## Goal

Install the R89 cloud-first semantic engine in a **new isolated QA runtime**, preserving the accepted R88 instance on port 8822 and all existing project data. No reset, clean, migration, data synthesis, production deployment or automatic PR merge.

- Existing Git bridge/root: C:/Users/fajar/.codex/worktrees/pi-r87-lean-runtime/PI_GITHUB_BRIDGE_20261108
- New QA Git worktree: C:/Users/fajar/.codex/worktrees/pi-r89-cloud-qa/PI_GITHUB_BRIDGE_20261108
- Original QA database: D:/Research/PlanningIntelligence/08_RUNTIME/R88_QA_20261121/r88-qa.sqlite (READ ONLY)
- New QA database: D:/Research/PlanningIntelligence/08_RUNTIME/R89_CLOUD_QA_20261121/r89-qa.sqlite
- R89 QA URL: http://127.0.0.1:8823/development-case-r85.html
- Admin API provider settings: http://127.0.0.1:8823/ai-settings.html
- Other existing QA: 8822, untouched.

## Operator requirements

Windows PowerShell 5.1, Git, Composer, PHP >= 8.3 with sqlite3 extension, access to repository and Composer packages, and the prior R88 QA state. Installer validates the known SHA-256 of two official R81 PDFs, the R81/R82 manifests, and availability of the R70C/R70D paths from **only the two known safe path keys** in the old QA .env. It never prints/copies unrelated credentials. The new APP_KEY is random, vault starts empty.

Installer script: scripts/pi-install-r89-cloud-qa.ps1.
SQLite backup helper: scripts/pi-r89-backup-sqlite.php.

The operator must obtain the final, reviewed PR #77 HEAD SHA and set it as ExpectedCommit. The installer fetches the R89 branch and refuses if its remote tip differs from this pin. It creates a detached git worktree, which does not change the checked-out R88 branch. It refuses to run if the target runtime or target worktree already exists (no overwrite). Do not retry a partially completed installation by deleting directories until diagnosed.

A typical PowerShell 5.1 invocation **after independently downloading/reviewing the installer from that exact HEAD** is:

    $Commit = '<reviewed-full-40-character-PR77-HEAD-SHA>'
    & "$env:TEMP\pi-install-r89-cloud-qa.ps1" -ExpectedCommit $Commit -StartServer

No secrets should be pasted into ChatGPT. The -StartServer flag starts only PHP artisan serve on loopback 127.0.0.1:8823, with stdout/stderr under the NEW QA runtime. Without the flag, it installs and tests but does not start a server.

## Runtime acceptance

1. Installer output must include R89_SOURCE_PIN=VERIFIED, R89_ISOLATED_WORKTREE=VERIFIED, R89_QA_SQLITE_BACKUP=PASS, R89_QA_SQLITE_INTEGRITY=ok, R89_PREVIEW_AND_INFERENCE_ROUTES=VERIFIED, R89_FOCUSED_TESTS=PASS, R89_WINDOWS_ISOLATED_QA_INSTALL=PASS.
2. Authenticated web page on 8823: check R88 2026 still returns one documentary case and ABSTAIN_MISSING_COMPARISON_VERSION for 2027. Verify R70A HTTP 200 decision ABSTAIN, R82 HTTP 200 candidate set, R84 HTTP 200 storage READY. **Do not claim these pass until checked in the new runtime**.
3. Admin logs into the separate 8823 instance, opens AI Settings, and registers chosen provider model + API key via the encrypted server-side settings form (role superadmin/institution_admin, password re-verification). No API key belongs in PowerShell history, Git or chat.
4. The QA config enables R89 cloud *capability* but no API provider is configured or contacted by the installer. The user chooses DeepSeek/OpenAI/Gemini/Groq/OpenRouter, opens R89 preview, inspects the exact bounded documentary labels/locators and SHA-256, and explicitly authorizes metadata transfer and potential provider charges before POST. This user action is separate from installation consent.
5. Verify R89 status is CANDIDATE_REVIEW_REQUIRED only for a supported structured proposal; unverified 2027, insufficient source, bad preview and missing credentials must ABSTAIN. Legal and fiscal/numeric results stay unapproved/blocked.
6. Record browser acceptance desktop 1440px and mobile 390px, auth/tenant guards, provider response, provider terms/quotas/charged amounts, and no unintended AI egress.

## CI versus actual runtime

- Windows PowerShell parser check in GitHub Actions is NOT proof that this installer executed on the user machine.
- SQLite fixture backup CI demonstrates read-only source, checksum stability, integrity and no-overwrite.
- PHP tests use the phpunit.xml in-memory SQLite, not the new QA clone.
- No live DeepSeek/OpenAI etc. key, model, billing, or provider call is installed by this script.
- If any step fails, stop, retain the runtime and its logs; do not reset/clean the existing R88 worktree or migrate the main/QA database.

## Release gate

R88 PR #74, R88.1 PR #75, initial R89 PR #76 and cloud BYOK PR #77 are stacked drafts. The isolated installation prepares a real browser QA step, not production admission. Product readiness still requires independently verified legal provisions, versioned 2027 source, expert-labeled semantic benchmark, data rights/privacy, spend controls, and a separate merge decision.

Status before operator runs installer: R89_GITHUB_PACKAGE=SOURCE_READY; R89_WINDOWS_LOCAL_INSTALL=NOT_RUN; R89_PROVIDER_LIVE_ACCEPTANCE=NOT_RUN; PRODUCTION_ADMISSION=DENIED.
