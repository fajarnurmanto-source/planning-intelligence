# PI R108 — UX information architecture audit from actual owner screenshots

Date: 2026-11-24 (Asia/Jakarta). Status: documented backlog, NOT IMPLEMENTED. Evidence: owner-provided screenshots of original PI Superior R85 page and successful R106 annex table in browser after R107 cutover. Screenshots have not been committed or made public.

## Observed, source-grounded deficiencies

1. **Discoverability:** R106 is panel section 06 near the footer, after R100 official sources and multiple R85/R86/R88 graphs and evidence lists; the two existing sidebar destinations most semantically appropriate (Evidence Intelligence: Dokumen & bukti; Fiscal Intelligence: Penganggaran & APBD) do not provide direct access to R106.
2. **Long monolithic task flow:** case background, legal graph, source catalogue, evidence provenance, and fiscal evidence are loaded into one very long scroll. Users must search visually or use browser Find rather than navigate by the desired task.
3. **State language:** "Kandidat ekstraksi lokal berhasil", "Perlu review", and production/admission denial are all correct but not distinguished as ingestion, evidence review, or fiscal authorization. Users may misread zero delta as fiscal approval.
4. **Evidence workflow:** per-row page numbers exist, but only two top-level PDF links are visible in screenshot; clicking a row does not currently launch the exact page for that row. Opening original PDF links has not been verified by owner.
5. **Unclear affordance:** developer milestone IDs R85/R88/R100/R106 are more visually prominent than plain user task labels; the interface primarily reflects internal milestone chronology rather than how an operator thinks about cases and documents.

## Proposed task-first IA — scoped, non-duplicative

- PI Superior: Beranda / Kasus / Ringkasan keputusan / Agenda review.
- Evidence Intelligence: **Dokumen & Lampiran** with direct R106 path, verified/needs-review badges, evidence register, source page reader. This should be an access route to ONE backend, not a second evidence repository.
- Regulatory Intelligence: Landasan hukum / Versi peraturan / Hubungan kewenangan.
- Fiscal Intelligence: **Anggaran & Perubahan** with a deep link into the same R106 fiscal annex findings; separate documentary candidates from approved fiscal analysis.
- R85 case page: concise case overview + 4-6 section tabs or navigable subpages; collapsible advanced graphs/evidence; fixed breadcrumb/case context; direct shortcut to the relevant evidence.

## UI implementation acceptance criteria (future separate guarded release)

1. R106 accessible in ≤2 clicks after PI login from Evidence menu, no scrolling giant page required; deep link preserves case context and account list.
2. The R106 result shows explicit three levels: text extraction PASS, legal/source review PENDING, fiscal production admission DENIED. No auto reviewer decision.
3. Each account row has an accessible **Buka sumber** action to its respective document/page, with both original PDF links verified under authenticated session. Perwal74 rows 1–4 at PDF384 and rows 5–6 at PDF385; Perwal12 all six at PDF561.
4. Desktop and mobile 390px navigation, keyboard focus, horizontal overflow and authenticated/error/ABSTAIN states tested.
5. No new DB, launcher, server, shadow PDF store or invented approved numbers. Existing R105+R106 immutable SHA guard and existing R107 source-only cutover remain intact.

## Current acceptance

R107 original source install PASS and authenticated R106 successful button/table render PASS, PDF viewer open NOT YET VERIFIED. UX R108 design/backlog only; nothing here has been deployed to original 8820. Numeric admission ZERO, legal/reviewer status PENDING, production admission DENIED.

## Source-viewer acceptance evidence update

As of 2026-11-24 owner successfully opened BOTH original protected PDF URLs in Edge: base source PDF p384/1155 and amendment 3 source PDF p561/1596. Screenshot of amendment p561 visibly shows subactivity 2.11.11.2.01.0004 and first three target rows with 0 change. R108 is now explicitly a UI/navigation improvement, NOT a prerequisite to functional PDF viewer acceptance. Preserve both working PDF endpoints and existing security, add per-account exact page/deep-link context and shorten giant R85 case scroll. Do NOT silently conflate Edge signature warning with verification. No R108 source or UI code has been deployed.
