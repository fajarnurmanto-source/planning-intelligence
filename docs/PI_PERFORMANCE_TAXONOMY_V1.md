# PI-PERF-TAX-01 — Development Performance Taxonomy v1

Status: **IMPLEMENTED DOMAIN CONTRACT / PRE-REVIEW ONLY**. Date: 2026-10-29.
Production admission: **DENIED**. Regulatory applicability: **NOT ASSESSED**.

## Problem this contract solves

Searching the same indicator name and a nearby numeric string in RPJMD and RKPD is not a valid comparison of their 2026 targets. A PDF text block can interleave target, realization, achievement, multiple years and even a table of contents. Classify document structure, indicator definition, measurement role, period and source-cell lineage **before** a substantive verdict.

## Three different objects

1. **Planning entity:** `VISI`, `MISI`, `TUJUAN`, `SASARAN`, `PRIORITAS`, `PROGRAM`, `KEGIATAN`, `SUBKEGIATAN`. This is a vocabulary, not a fixed universal parent chain. A hierarchy is a source-supported, versioned relation, not an assumption from indentation or document order.
2. **Indicator definition:** canonical name, *explicitly declared* aliases, level, nature (impact/outcome/output/process), unit, formula, improvement direction, jurisdiction/scope, owner and definition version. The identity includes scope/version. Similar prefixes are not equal identities.
3. **Measurement occurrence:** indicator identity, document version/parsing run, target or realization or baseline or achievement, year and period, raw and parsed value, original page/table/row/column and header-cell provenance. A measurement occurrence is not an indicator definition.

M2 existing kinds are preserved and reused by the new registry. G2's intervention graph ontology is retained; the new `SAME_INDICATOR`, `DERIVED_FROM`, `CONTRIBUTES_TO`, `TARGET_FOR_YEAR`, `REALIZATION_FOR_PERIOD`, and `REVISED_BY` are proposed cross-document lineage types, **not verified edges**.

## Context rules

- The scoped column header, not a page-wide string, is the only input that can suggest whether a value is `TARGET`, `REALIZATION`, `BASELINE`, or `ACHIEVEMENT`.
- `Target 2026` is a target-year **candidate**. `Target 2026 Realisasi TW II Capaian` as one concatenated text block is **ambiguous**.
- `2026` selected in the UI is a comparison request, not proof of the year represented by a value. A mismatched or absent column year remains unresolved.
- The source value and header are looked up by persisted SourceUnit ID in a `SUCCEEDED` parsing run. A fingerprint mismatch is rejected. Optional header SourceUnits must belong to the same run/version.
- A physical header-cell linkage is currently accepted as a *candidate* only for structured DOCX/XLSX `TABLE_CELL` entries with the same sheet/table and column, and an earlier header row. The outcome is still **not independently verified** or reviewer-approved.
- PDF `TEXT_BLOCK` does not supply enough cell geometry; it must abstain from header/column verification. Never treat nearby text as proof.
- Numbers, ranges and percentages are parsed without interpreting whether more/less is desirable. No automatic target equality, policy finding or legality claim is produced.
- Operator declarations, including indicator aliases, are not automatically an institutionally verified dictionary. Alias collisions are refused.

## Current API and UI

- `GET /api/performance-taxonomy/registry`: versioned vocabulary and required fields.
- `POST /api/performance-taxonomy/classify-measurements`: source-bound candidate classification. This is read-only: no parsing, no data mutation and no PDF upload.
- UI menu **Taksonomi Kinerja**: inspect the registry and a real persisted SourceUnit using IDs/fingerprint from an existing export. A successful source lookup does not mean a target column has been verified.

## Example triage, not evidence admission

| Indicator | Exported evidence | Proper taxonomic state |
|---|---|---|
| Indeks Keterpaduan Pembangunan | RPJMD `86,09`; RKPD Perubahan text also includes `Indeks Keterpaduan Pembangunan Infrastruktur 87,01 86,40` | Identity/alias and measurement roles unresolved. No numeric comparison yet. |
| Crime Rate | `159 - 173 130,84 168 81,45` in a PDF block | Multiple roles may share the block. `COLUMN_ROLE_UNRESOLVED`. |
| Indeks Keberdayaan Masyarakat | Target 2026 / Realisasi TW II labels and nearby values | A page-wide block is not a column path. Explicit table-cell association required. |

## Next integration (separate product work)

1. Reconstruct PDF tables into row/column geometry, repeated headers, merged cells and evidence locators; no wholesale 67k-unit browser transfer.
2. Versioned indicator dictionary and reviewer-approved aliases, sourced from each jurisdiction's documents and authorized nomenclature; avoid hardcoded 14-indicator assumptions.
3. Build explicit source-supported relations between planning entities, indicators and annual targets, including RKPD Perubahan version lineage.
4. Compare compatible indicator identity, unit, year, measure role and source context. Otherwise emit `UNKNOWN` with reason and actionable evidence request.
5. Persist human decisions with audit trail and export revisitable, provenance-linked reports.

Reference frameworks for domain modeling: Permendagri 86/2017 (planning/evaluation), Permendagri 90/2019 and its nomenclature updates, and the applicable RKPD 2026 guideline (Permendagri 10/2025). These inform classification, not automatic regulatory applicability decisions.
