# PI R74 — Regulatory Taxonomy & Evidence Genealogy

Status: **source-only pilot**, no DB migrations, no provider calls, no production admission.

## Scope

- Article 7 of UU 12/2011: exact seven formal ranks (UU and Perppu share level 3).
- Article 8: recognized issuers/types without invented universal rank.
- Special regional subtypes (Perdais, Qanun) remain unrated pending legal-context review.
- Other legal instruments (decisions/circulars/instructions) are explicitly separate.
- Topic concepts may have multiple parents; thematically related does not mean legally subordinate.
- Dependency/newer-to-parent links and their reverse traversal use one canonical stored edge.
- Provision-level links and change-to-provision links are structurally distinct.
- Existing R70R graph is ingested **only from previously populated cache**, retaining
  \`CANDIDATE_NOT_LEGAL_FINDING\` semantics. No live crawl is started by R74.

## Implemented sources (not an exhaustive statutory census)

The tracked JSON resource includes source links for UU 18/2008, PP 81/2012,
Permendagri 86/2017, Permendagri 10/2025, UU 12/2011 and its selected amending
instruments (UU 15/2019, UU 13/2022, UU 1/2026). It records three provision
locators and five indicative legal/version relations. Original PDF bytes have
**not** been hash-pinned in the R74 seed; current legal effect is NOT assessed.

## Interfaces

- \`GET /api/qa-regulatory-r74/taxonomy\`: registry types, topic vocabulary,
  and available seed instruments.
- \`GET /api/qa-regulatory-r74/genealogy?root=PP-81-2012&direction=ancestors&lens=legal&depth=3\`
  provides typed bounded traversal.
- \`direction\`: \`ancestors\`, \`descendants\`, \`both\`.
- \`lens\`: \`legal\`, \`version\`, \`thematic\`, \`all\`; thematic is undirected.
- Authenticated, tenant-protected, throttled, no-store, read-only.
- Graph defaults to \`REVIEW_REQUIRED\`, \`NOT_ASSESSED\`, \`NUMERIC_COMPARISON=BLOCKED\`,
  \`PRODUCTION_ADMISSION=DENIED\`.

## Non-negotiable distinction

An R70R \`Mengingat\` citation is \`CITES_AS_BASIS\`; it never becomes
\`IMPLEMENTS_PROVISION_OF\` or \`DELEGATED_BY\` without explicit provision evidence.
An \`AMENDS\` edge is not a whole-instrument repeal.
Topic co-occurrence never proves statutory or jurisdictional hierarchy.

## Local installation (Windows PI source-only bridge)

After a reviewed GitHub commit is merged into
\`pilot/pi-laravel-github-bridge-20261108\`, run the established
\`tools/pi-bridge/apply-laravel-updates.sh\` using the existing WSL workflow.
The script checks ancestry, source drift and backups; it explicitly refuses
dependency or migration changes. R74 introduces neither.

Then, from the canonical Laravel root, run:

\`\`\`powershell
php -l app\Services\Pi\Regulatory\RegulatoryTaxonomyRegistry.php
php -l app\Services\Pi\Regulatory\RegulatoryGenealogyGraph.php
php -l app\Http\Controllers\Api\RegulatoryGenealogyController.php
php vendor\phpunit\phpunit\phpunit --filter RegulatoryGenealogyTest
\`\`\`

Open \`http://127.0.0.1:8820/regulatory-laravel-r70g.html\` in an authenticated
local session. Confirm the R74 panel displays ancestors for Permendagri 10/2025,
descendants for UU 18/2008 and thematic family for persampahan.

## Next increment / unimplemented by R74

1. Hash-pin official primary PDFs, add provision text and precise anchors,
   and reconcile amendment chains including partial effects and transitions.
2. Expand official discovery from the current two R70R seeds into a bounded
   multi-issuer, cross-jurisdiction collector with provenance and refresh.
3. Add persistence and indexes when source coverage warrants migration; preserve
   the source-only pilot until a separately reviewed deployment gate.
4. Add as-of-date effectivity and instrument-family consolidation; no temporal
   legal conclusion is provided by R74.
5. Connect evidence-backed provision-to-RPJMD/RKPD/APBD relationships only after
   verification. R71 \`ABSTAIN\` and numeric-comparison \`BLOCKED\` remain intact.
