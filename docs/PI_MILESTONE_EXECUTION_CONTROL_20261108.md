# Planning Intelligence — GitHub milestone execution control

**Control ID:** PI-MILESTONE-BRIDGE-20261108  
**Repository:** `fajarnurmanto-source/planning-intelligence`  
**Development branch:** `pilot/pi-laravel-github-bridge-20261108`  
**Status:** DEVELOPMENT_PILOT_ONLY, not a production or legal/numeric admission.

## Sources of authority

1. The latest **local canonical master roadmap and state capsule**, when their exact source and revision can be inspected, govern milestone ordering and definition of done.
2. Until that source is copied here, `02_PI_MASTER_ROADMAP_20261025.md` (`PI-MR-20261025-V1`) is a *historical baseline* only; the November 2 regulatory hierarchy, multi-Pemda, outcome-first/reuse-first, installation and taxonomy mandates are mandatory **addenda**, not proof that the canonical Windows file was amended.
3. The user's November 8 authorization permits continuing development through this private GitHub pilot and guarded local Laravel bridge. It does not itself prove specific QA, data rights or production gates passed.

**Current known local result (user-reported):** baseline `ffc044679461f8221d965bcb5e208ad70eccbb95`; R70H excerpt lookup `6bddb09a68772ddb634ed1f12db213eae66658ac` applied with a four-file backup; PHP syntax checks on three files and registration of the R70H route passed. `PI_LARAVEL_GITHUB_BRIDGE` was enabled on Windows every 15 minutes. A full authenticated functional R70H acceptance and UI integration are **not yet evidenced**.

## Immediate work queue — execute capability slices, not repetitive paperwork

1. `PI-BRIDGE-UI-01`: Publish the **existing** local `laravel/public/index.html` to this branch as a verified baseline without overwriting the local copy. If uniquely identifiable, publish the canonical master roadmap as read-only documentation. The on-laptop publication must never stage `.env`, databases, credentials, vendor, caches or business documents. Do not guess among multiple roadmap candidates.
2. `PI-R70H-E2E-01`: Connect the existing Regulatory Intelligence UI to the hash-pinned excerpt lookup. The reviewer must see original excerpt, PDF page, source IDs/hashes and explicit limitations, with meaningful 401/403/404/503 handling. Use the verified on-laptop UI baseline before proposing changes.
3. `PI-ROADMAP-ALIGN-01`: Reconcile the November 2 addenda and the most recent local roadmap into an auditable execution map (1 Superior + 5 Specialized; Regulatory Intelligence first). Milestone IDs used in unrelated streams must not be silently reused or called complete.
4. `PI-REGULATORY-CORE-01+`: Continue the first **end-to-end** Regulation → Planning → Budget flow with verified primary sources, hierarchy/authority/delegation, temporal scope, vertical/horizontal conflict **candidates**, change-impact dependencies, reviewer decisions, provenance and multi-Pemda tenant isolation. Reuse mature Laravel/open-source modules where they materially help. Move to other specialized apps only per the reconciled roadmap.

## Execution contract for each automatic iteration

- Check the current pilot GitHub HEAD, PR #31, prior capability outcome and the authoritative roadmap. Choose **one bounded user-visible capability** whose prerequisites actually exist. If source is missing, fix acquisition or report the precise blocker; don't invent an acceptance pass.
- Prefer reuse-first, Laravel-first, real authorized data early, one focused regression plus meaningful working workflow. Keep SHA/hash anchors and explicit abstention where evidence is insufficient.
- Commit source changes only to the private pilot branch and update the draft PR with scope, verification and SHA. Never merge automatically, force-push, replace dirty local changes, or publish credentials/data.
- The local WSL task applies only safe source changes with drift check and backups. Migrations, dependencies, builds, restarts, `.env`, SQLite, user accounts and production operations remain **separate actions requiring explicit safe handling and authority**.
- Never report actual local installation merely because a GitHub commit exists. Until there is a local apply result, report `LOCAL_APPLY=UNCONFIRMED`.
- Preserve `REVIEW_REQUIRED`, `LEGAL_HARMONIZATION=NOT_ASSESSED`, `NUMERIC_COMPARISON=BLOCKED`, `PRODUCTION_ADMISSION=DENIED`. No automatic legal ruling or government budget decision.
- Log milestone completion **only** when a functional acceptance outcome is evidenced; then proceed to the next eligible capability, avoiding tests/audits that do not change the decision.

## Bridge lifecycle

The local Windows scheduled task is an auto-puller, **not** a perpetual ChatGPT worker. Separately, an authorized ChatGPT recurring task or working session must author new code on GitHub. The bootstrap publisher `tools/pi-bridge/publish-local-ui-and-roadmap.sh` needs one local WSL invocation to import the latest UI/roadmap. Its guarded marker update means the copied UI is recognized as an **already-present local baseline** rather than an attempted new deployment.
