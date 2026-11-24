# PI R108 — Product-wide planner-centred UX/IA mandatory redesign

Date: 2026-11-24 WIB
Status: **mandatory product design decision and source-backed audit; not yet implemented, not deployed**.
Scope: **ALL of Planning Intelligence**: PI Superior and Evidence, Regulatory, Performance, Fiscal and Evaluation internal intelligence components, including home/workspaces/cases/review/source/PDF/AI entrypoints, not merely R106 PDF panel.
Supersedes the previous interpretation of R108 as cosmetic polish, PDF shortcuts, or only the overly long R85 page.

## User's explicit problem statement

The user (owner) observes the entire PI UI/UX is confusing:
- pages are unmanageably long;
- shipping a complete catalogue of features on every screen creates cognitive overload rather than utility;
- the application has no recognizable end-to-end work process from the perspective of a government planning practitioner;
- labels are taken from programming/development concepts rather than the terms of daily planning work.

**Canonical product requirement:** "PI harus mengikuti cara seorang perencana berpikir, menyusun, memeriksa, menindaklanjuti, dan mempertanggungjawabkan perencanaan pembangunan; bukan mengikuti struktur source code atau urutan milestone developer."

Do NOT interpret this as a request to rename one sidebar label and append another panel.

## Observed source evidence and structural diagnosis

Audited at GitHub branch codex/pi-single-runtime-windows-app-20261122:
- laravel/public/pi-ia-r69e.js: top-level grouping PI Superior + five Specialized; labels "Evidence Intelligence", "Regulatory Intelligence", "Performance Intelligence", "Fiscal Intelligence", "Evaluation Intelligence" and links containing developer milestone numbers (R79/R78/R72/R70F/R74/R75).
- laravel/public/development-case-r85.html: a single scrolling page joins R88 crosswalk, R89 provider consent, R86 regulatory lifecycle, 5 core questions, four graphs, provenance, safeguards, R100 document catalogue, R106 annex comparison. Feature-display chronology dominates task order.
- laravel/public/index.html: source tags QA / R69E, actions to case/AI/budget, lists, flags and internal release concepts instead of a single explicit "pekerjaan saya" task progression.
- Owner full-page R85 screenshot confirms excessive scrolling; owner R106, 6-row table and BOTH protected original PDF source URLs operationally accepted through R107 original Windows cutover.
- Existing R108_UX_INFORMATION_ARCHITECTURE_AUDIT covers specific R106 discoverability, but not the whole product's workflows.

## Product design principles — normative

1. **Task-first, not feature-first:** top-level navigation uses work outcomes and the practitioner's vocabulary, not module names or R-numbers. Internal domain-engine modularity is preserved in Laravel; interface composition is independent of service hierarchy.
2. **Understand first, act second, inspect detail on demand:** first viewport shows user context, purpose, present stage, evidence readiness, one recommended action, then expandable optional materials. Never automatically render all charts/JSON/regulations/AI provider choices and large catalogues in one uninterrupted page.
3. **Planner-centered, evidence-bound journey:** select geographical/organizational/time/topic context; diagnose development issues and legal responsibilities; assess objectives/targets/plans; reconcile programs, activities, subactivities, performance indicators; assess financing and APBD changes; compare implementation and results; review exceptions and issue actionable recommendations. This is a task guide, NOT a claim that formal governmental documents are always legally created in that sequential order.
4. **Multiple entry routes and backtracking:** allow a user to begin from a subactivity code, document, budget anomaly, performance target, regulation, or case; retain time scope, jurisdiction, organization, document version and evidence provenance while navigating back and forward.
5. **One evidence object, many views:** the same pinned PDF, regulation, program and review record can be referenced from planning, fiscal or audit pages. Do not make duplicate archives, new reviewer ledgers, multiple backend services or conflicting numerical facts.
6. **Decision-appropriate states:** never merge "file available", "text extracted", "source authenticity verified", "planner reviewed", "fiscal number approved", or "production admission". Explicitly separate AVAILABLE / EXTRACTED / NEEDS_REVIEW / VERIFIED or ABSTAIN in understandable Indonesian with legal boundaries.
7. **Plain Indonesian domain-language contract:** use official document names RPJPD/RPJMD/RKPD/Renstra/Renja/KUA–PPAS/RKA/APBD/DPA where appropriate; use "hubungan antar dokumen", "kesesuaian program/subkegiatan", "sumber dan halaman bukti", "belum cukup bukti", "perlu telaah" for normal work. Place crosswalk, provenance, regulatory backbone, Rxx, API, SHA256, JSON, code hashes and provider model settings behind "Rincian teknis/Audit", not primary labels. Avoid casual synonyms that change formal legal meaning.
8. **Role-context, not fake permission:** present entry tasks for Perencana Perangkat Daerah, Koordinator Bappeda, Keuangan/TAPD, Monitoring dan Evaluasi, Reviewer/Pengawas, Pimpinan. UI role preferences MUST NOT grant backend permission, alter account/tenant access or fabricate institution-specific entitlements. Always server-auth and tenant guard.
9. **No artificial numerical success:** different account codes across subactivities may recur. Join evidence using (territory, period, agency/authority, subactivity, account code, source version, time/PIT). Missing dataset or equivalence yields "belum dapat disimpulkan", not a false 0, approval or decision.
10. **No second system and no destructive migration:** keep original Laravel 127.0.0.1:8820 single runtime, login, APP_KEY, sessions, DB, R84 reviewer, R105/R106 PDF byte pins, R107 backup. No additional launcher, forced reset, separate "UX" app, broad rewrites before regression.

## Planner's conceptual work journey

The navigation and guided content should use the following families:

A. **Mulai pekerjaan / pilih konteks.** Wilayah, Perangkat Daerah, urusan/suburusan, periode dan Tahun Anggaran, isu/masalah, jenis telaah, dokumen/versi acuan. The app should offer entry from an existing case or a specific code/document without making users repeat fields.

B. **Telaah permasalahan dan arah kebijakan.** Problem, baseline data, condition, public service need, mandates and assigned authority; links to RPJPD, RPJMD, and Renstra where relevant. Show why this is a governmental responsibility, what is documented versus still uncertain, and what is missing.

C. **Telaah rencana, sasaran dan indikator.** RKPD/Renja; goal, objective, target, program, activity and subactivity with valid year/version; indicators, outputs/outcomes, consistency between planning docs and responsibilities; mismatch and crosswalk findings in domain language.

D. **Telaah anggaran dan perubahannya.** KUA–PPAS, RKA, APBD, DPA, versions of revisions, matched budget accounts, allocated/realized data only when corresponding evidence exists. Highlight source page, variance reason and not-yet-admitted amount. Source R106 is one bounded annex reader feeding THIS workflow, not an independent user-facing silo.

E. **Pelaksanaan, pemantauan dan evaluasi.** Implementation progress, actual outputs, target-versus-realization, spending evidence, results, impediments, deviations, corrective proposals. Do not imply complete operational/evaluation systems exist if source coverage is partial.

F. **Tindak lanjut dan keputusan berbukti.** Findings, affected documents and periods, named responsible institution, reviewer role and required action, unresolved evidence, recommended changes. Human authorization governs decisions; system may propose but not approve.

**Important:** formal planning, financial and audit timelines overlap and branch. The UI uses this as navigable problem-solving scaffolding, not a new binding sequence of statutory acts.

Legal background to VERIFY BY DOCUMENT VERSION before implementation:
- Permendagri 86/2017 planning, control and evaluation (official BPK: https://peraturan.bpk.go.id/Details/311927/permendagri-no-86-tahun-2017).
- Permendagri 90/2019 classification/codification/nomenclature (official BPK: https://peraturan.bpk.go.id/Details/139075/permendagri-no-90-tahun-2019).
- Permendagri 77/2020 financial administration (official BPK: https://peraturan.bpk.go.id/Details/162792/permendagri-no-77-tahun-2020).
No assumption that these constitute a complete or most recent legal corpus; use PI existing regulatory-version backbone and later amendments when judging any particular year.

## Proposed navigation: two user-facing levels, domain engines behind the scenes

Level 1 — **Beranda kerja**: "Lanjutkan pekerjaan", recent case/context, unresolved findings, next verified action.

Level 1 — **Telaah perencanaan**: "Permasalahan dan kewenangan", "Arah kebijakan dan sasaran", "Program, kegiatan, subkegiatan", "Indikator dan target", "Hubungan antar dokumen".

Level 1 — **Anggaran dan pendanaan**: "Kesesuaian rencana–anggaran", "APBD dan perubahan", "Rekening dan lampiran", "RKA/DPA" (show actual availability, not claimed completeness).

Level 1 — **Pelaksanaan dan evaluasi**: "Realisasi dan capaian", "Penyimpangan dan hambatan", "Perbaikan rencana berikutnya" (data-dependent).

Level 1 — **Dokumen dan dasar hukum**: "Cari dokumen", "Peraturan dan kewenangan", "Bukti dan halaman sumber", "Riwayat perubahan".

Level 1 — **Temuan dan tindak lanjut**: "Perlu ditelaah", "Tugas reviewer", "Rekomendasi dan keputusan", "Riwayat bukti".

Level 1 — **Pengaturan**: account, permissions, AI provider/consent and advanced system diagnostics, visible only where existing entitlement permits.

Internal composition, NOT user-facing app chooser:
- Evidence Intelligence -> source catalogue, document viewer, provenance and versioned byte receipt.
- Regulatory Intelligence -> legislation/authority/temporal relations.
- Performance Intelligence -> plans, targets, indicators and results.
- Fiscal Intelligence -> planned and realized budgets, account linkage.
- Evaluation Intelligence -> performance/control/evaluation and recommendation traces.
- PI Superior -> guided, cross-domain work cases, summaries and decisions.

**Do not switch subscription/entitlement policies by CSS.** Existing entitlements remain enforced on server; show unavailable areas candidly and avoid fake "ready" states.

## Exact language changes for primary UI

| Developer/source label | Planner-facing wording | Preserve in technical details |
|---|---|---|
| R85 Kasus Pembangunan Terpadu | Telaah isu atau program pembangunan | R85 internal ID |
| R86 Regulatory Backbone | Dasar kewenangan dan tahapan perencanaan | Registry/graph/version |
| R88 Crosswalk | Kesesuaian program, subkegiatan dan dokumen | crosswalk engine |
| R89 Analisis semantik | Bantuan AI untuk menelaah kesesuaian istilah | provider, prompts, consent |
| Provenance | Sumber, halaman, dan riwayat bukti | SHA/PIT/data lineage |
| ABSTAIN | Belum dapat disimpulkan — bukti belum cukup | decision engine state |
| R106 Native PDF Annex | Periksa lampiran APBD | parser revision |
| Export JSON | Ekspor data pemeriksaan (opsi lanjutan) | machine-readable JSON |
| Reviewer R84 | Tugas telaah dan persetujuan | R84 permission checks |
| QA LOCAL | Lingkungan uji / Belum untuk keputusan resmi | runtime/channel metadata |

Do not replace formal document, administrative or technical terms when they are necessary to accurately distinguish a legal identity, code or measured unit. Keep "kode subkegiatan" and "kode rekening" as genuine domain concepts.

## Concrete redesign of the example R85 -> R106 long page

Initial view: selected context ("Persampahan Kota Yogyakarta · 2026 · subkegiatan 2.11.11.2.01.0004"), no gigantic graph; 3-5 evidence status indicators; "Yang perlu Anda periksa selanjutnya" with **one primary action** and a short to-do list; current stage/path.
Secondary navigation within case: (1) Ringkasan; (2) Kewenangan & dasar hukum; (3) Rencana & indikator; (4) Anggaran & perubahan; (5) Dokumen & bukti; (6) Temuan & rekomendasi. Each tab/route only loads relevant workflow and preserves filters.
R106 annex is embedded from "Anggaran & perubahan > Lampiran dan rekening", with an alternate "Dokumen & bukti > Lampiran APBD"; same source API, not two copies.
Each account row shows subactivity, code and description, base, before/after, variance, evidence state and "Buka sumber" to exact PDF page. PDF top of page may start previous subactivity; provide contextual line or search/highlight where technically feasible. Never claim native Edge page fragment highlights specific account if it only opens page.
Advanced graphs, temporal lineage, detailed AI provider selection and JSON evidence are collapsible/specialist mode, not initially expanded.

## Required delivery phases (this is a PRODUCT-WIDE workstream)

P0 — inventory ALL routes/screens, core actions, real versus placeholder features, duplicated views, domain terms, owner/user roles, evidence states and access entitlements; create testable menu map and screen owner matrix. Confirm actual practitioner work journeys before wholesale rewrite.
P1 — shared shell/IA and navigation: route-aware breadcrumbs, working context, task entrypoints and feature availability, consistent terms; no new backend runtime.
P2 — guided end-to-end work case: context picker, staged summary, progressive disclosure, persistent return path; refactor excessive R85 vertical flow into task screens while preserving every working ability.
P3 — integrate existing R106 annex, R88/R86 regulatory, evidence, fiscal and evaluation tools under relevant task screens with no duplicated data sources; maintain independent source/reviewer legal gates.
P4 — usability and accessibility evaluation with 3 distinct planning tasks (start from problem, start from subactivity, start from budget discrepancy), across roles, keyboard, 390px mobile and 1440px desktop, authenticated/guest/ABSTAIN cases.
P5 — incremental code-only guarded rollout to existing original Windows 8820 after source pin and CI, original backups/rollback and owner acceptance. A subphase is not production release.

## Acceptance criteria

1. A planner can describe their NEXT action in plain language from initial screen and find main task in <=2 primary clicks without knowing module architecture or milestone numbers.
2. A planner can go from a named development issue -> responsible authority -> planning document -> program/subactivity -> target -> budget evidence -> review gaps -> recommended follow-up, with visible source/period/uncertainty; any missing data MUST yield "Belum tersedia/Belum dapat disimpulkan".
3. A planner can start in the middle from a known 2.11.11.2.01.0004, an APBD document or a target/realisasi discrepancy and navigate both directions preserving context.
4. In normal mode, no Rxx milestones, backend IDs, "crosswalk", "provenance", "ABSTAIN", JSON exports, technical hashes or cloud-provider debug panels dominate first viewport. Technical evidence remains accessible to reviewers and administrators.
5. The long R85 content becomes navigable screens/sections with progressive disclosure and a meaningful breadcrumb/back path; no critical function is deleted or merely hidden behind inaccessible navigation.
6. Existing R106 original owner acceptance preserved (six rows and 2/2 source links). Both source links remain protected and page-targeted; zero numeric/fiscal/production admission and reviewer/legal status unchanged.
7. Existing login, tenant, entitlements, R84 reviewer, R105 original immutable receipt/pins, original Windows PHP PID single server and existing launcher remain untouched by UI refactor.
8. Screen tests measure user task completion rather than counting elements: find assignment, classify evidence status, open source, identify required next review. Browser CI tests desktop 1440 and mobile 390, keyboard accessibility and no overflow.
9. Product vocabulary is mapped to official domain semantics and document versions, not arbitrary software labels; "perencana" may identify proper workflow without support from a developer.
10. QA and real source rollout evidence is reported separately. No claim of full R108 implementation until all scoped workflows have been validated.

## Not in scope / do not overclaim

This mandate does NOT by itself implement all planner workflows, acquire missing DPA/RKA/realisasi data, confirm fiscal/legal approval, validate a statute's current force or create another government system of record. Do not turn an information-architecture refactor into fake data completeness. The R107 original install and R106 scoped operational acceptance remain accepted. Original Windows PI UI **not modified** by this documentation/roadmap decision. PR #78 remains Draft.

## First-wave actual code progress (24 November 2026)

First-wave task-first navigation, home job cards and six task case views have been built and tested at SHA a1837a3c5b3c22e0f8cb0631800d1f0140bba18b (4/4 CI green). Windows code-only preflight and rollback fixture passed. The owner Windows original 8820 R108 change and user acceptance are not yet done. This does not close the whole-product UX mandate; especially compiled React workspace and legacy specialist page bodies require separate redesign.

## Owner Wave1 source cutover status update — 24 November 2026

The owner original Windows 8820 in-place six-file R108 Wave1 installation is now ACCEPTED from observed preflight and -Apply PowerShell transcript, using source a1837a3c5b3c22e0f8cb0631800d1f0140bba18b and preserving original PHP PID 5704, login/CSRF, R106 anonymous 401 and unchanged DB, APP_KEY and launcher. Browser task usability and R106 post-cutover manual regression are still pending; whole-product R108 work and specialist/React screen redesign remain in progress. See PI_R108_WAVE1_IMPLEMENTATION_AND_ACCEPTANCE_20261124.md, milestone CD-20261124-10L.

## Mandatory R108 progress — Wave2 source completed, owner rollout pending (24 Nov 2026)
Async case-context correctness, plain-language state and technical disclosure, first-viewport task hierarchy, clear next actions, pilot truthfulness and subscription relocation implemented at source SHA 63ee3c30d5846ef3b28dee8b8ee8239a97ba417b. All four GitHub CI workflows passed; Windows fixture rollback passed. Owner original Windows Wave2 not yet run. Wave3 specialist and React workspace bodies and role-based representative planner use remain mandatory, not waived by Wave2 source acceptance.

## Wave2 original Windows deployment acceptance update (24 Nov 2026)
The guarded Wave2 six-file cutover has now been explicitly executed by the owner on original Windows localhost8820. Preflight and -Apply verified all six source matches, PHP PID 5704 preserved, login/CSRF PASS, anonymous R106 endpoints HTTP401, live static-asset SHA PASS, .env/SQLite/APP_KEY/launcher unchanged. Original UX browser acceptance remains pending, and entire R108 mandatory product-wide Wave3 beyond the first two waves remains OPEN. Do not install Wave2 again. See CD-20261124-10O and PI_R108_WAVE2_IMPLEMENTATION_AND_ACCEPTANCE_20261124.md.

## Wave3A source progress (24 November 2026)
Wave2 original browser screenshots confirm context and clearer status but show Dokumen page still monolithic. Wave3A introduces canonical four graph-based expandable evidence groups retaining all 18 original relations and source links, plus closed official source catalogue. Source SHA e33ef1aceb636602ed94cd82fd571f3b2841e3cf; 4 CI workflows PASS including Chrome 390px and Windows 3-file rollback. Owner original Wave3A not yet applied; whole-product specialist/React workflow redesign remains open. No new services, data admission or automatic review.
