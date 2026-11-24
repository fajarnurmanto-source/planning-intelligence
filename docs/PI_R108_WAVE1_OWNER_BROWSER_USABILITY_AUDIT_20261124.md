# R108 Wave1 — Owner browser screenshot acceptance and UX audit

Date 2026-11-24. Original Windows PI 127.0.0.1:8820, installed source a1837a3c5b3c22e0f8cb0631800d1f0140bba18b. The three owner screenshots are NOT uploaded to this repository. Product observations only. No live application change is made by this audit.

## Observed owner-browser results
1. HOME shows newly deployed planner-facing sidebar, Beranda kerja, work-category navigation, five Saya ingin… task cards, and optional metrics/source details collapsed. PASS: task-first home actually present in original browser.
2. CASE Ringkasan view shows four selectors Wilayah/Tahun/Topik/Kasus, six work tabs, only summary question cards and safety guards. PASS: summary workflow pane rendered, former all-panel long page reduced. NOT VERIFIED: every other pane through a complete planner task.
3. CASE Anggaran view selected, R106 table shows all six previously extracted accounts, equal source columns, zero delta, total candidate Rp1.020.366.250, two source PDF links visibly present and "Perlu review". PASS: R106 table nonregression after R108. NOT VERIFIED: both PDF links were not reopened in these R108 screenshots (earlier R106 viewers were 2/2 PASS before R108).

**Acceptance: R108 Wave1 owner-browser display PASS (scoped); product-wide planner usability NOT yet accepted.** All six tabs working, one guided end-to-end case, mobile real-owner and screen-reader tests, and specialist page design remain open.

## Confirmed source-linked priority issues

P0 — STALE SELECTED CONTEXT. Owner screenshot shows filters populated with wilayah/year/topic/case but R108 says "Konteks dipilih melalui formulir di atas". The new planner-workflow-r108.js initializes updateContext on DOMContentLoaded and on native form change/submit only, while existing case selections may populate asynchronously. Exact event sequence not yet reproduced; source suggests missing reactivity for programmatic option population. Fix to read current case context after data arrives and on relevant navigation without duplicate state. Include regression for async option addition and query/hash navigation.

P1 — OVERLARGE HERO / REDUNDANT ENTRY. Home hero repeats task options already shown as five "Saya ingin…" cards; case hero consumes significant first viewport before user can act. Replace with compact case context and one visible next action; keep optional details expandable.

P1 — DEVELOPMENT AND ENGLISH VOCABULARY. The normal screens still show developer terms "Kasus pembangunan terpadu / PI Superior", footer R85/R69E, "Langganan aplikasi" as a main menu action, English status markers such as "REVIEW READY WITH ABSTENTIONS" and "MIXED VERIFIED SOURCE AND CANDIDATE RELATION", and Relasi E01 etc. Translate displayed outcomes precisely into Indonesian government planning vocabulary while retaining underlying evidence IDs and system enums inside audit details. Preserve distinction extracted / needs review / NOT APPROVED. Do not imply authorized fiscal figures.

P1 — FALSE BREADTH OF WORK ENTRY. All five home task cards lead to the same fixed persampahan case and differing tabs. Present the pilot scope transparently; add actual case/context selection only when real source data and permission supports it. Do not fabricate personalized tasks/cases.

P1 — ACTION CLARITY. Form button "Tampilkan jejak", tab preview "Periksa lampiran APBD", and separate R106 action "Periksa lampiran dari arsip lokal" are confusingly adjacent. Identify changing case context vs analyzing an archive vs opening source PDF. One primary action at each stage.

P2 — SPECIALIST CONTENT. Shared sidebar labels changed, but compiled React workspace and regulation, reviewer and evaluation inner pages are not yet task-first; full R108 mandate remains open. Accessibility, keyboard and owner mobile usability need focused testing beyond no horizontal overflow.

## Next Wave2 tests and acceptance
- Populate selectors asynchronously and confirm case context labels update without reload.
- Display work context, current step and next evidence-bound action within first screen at 1440px and 390px.
- Convert technical statuses to accurate planner-facing explanations, retaining original audit source names.
- Avoid duplication and provide honest pilot scope; preserve existing R106 six rows and signed-in PDF links, R84 review/entitlements, APP_KEY and DB.
- Test entry from an issue, known subactivity code, APBD document and a planning mismatch; distinguish absent data from 0.
- No new runtime/launcher, no destructive migrations, no blanket approval. All fiscal/reviewer/production gates remain closed. PR #78 draft.
