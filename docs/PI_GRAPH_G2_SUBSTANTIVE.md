# Graph G2 – Substantive Planning Coherence (candidate)

G2 is **not** a regulatory knowledge graph or proof of causality. It extends a
validated immutable G1 graph **already archived in VS4** with a synthetic XLSX
claim ledger, allowing the reviewer to scrutinize explicitly linked planning
entities and identify scope, period and incomplete evidence chains.

## Adopt/keep/build

- KEEP G1 NetworkX, VS1 `SourceUnit` provenance for observed indicators,
  SQLAlchemy VS2 review event store, VS4 archive and Word/JSON exports.
- ADOPT openpyxl to *read a strict, structured, synthetic claim ledger*. This
  is not a replacement for the canonical free-form PDF/DOCX/XLSX parser.
- BUILD only PI-specific entity ontology, allowed relation types, evidence
  sufficiency, temporal/scope guard and path checks.

## Test contract

First upload four marked synthetic RPJMD/RKPD/Renstra/Renja source files under
G1, then upload the G2 claim ledger. The G2 adapter only reads the prior G1
archived analysis and produces its own immutable hash-bound candidate report.

An empty quote, missing locator or missing entity excerpt is treated as a
**link evidence gap**. G2 also checks for a path to SASARAN along explicitly
declared and fully located link claims; a path alone never proves causality.

Evidence provenance for G2 entries is labeled `OPERATOR_LEDGER_ASSERTION_NOT_SOURCE_PROOF`.
The 4 G1 source documents remain in the graph record as metadata and their
original source units are untouched. No G2 ledger assertion is mislabeled as
an extracted canonical source unit.

## Constraints

- Synthetic marked XLSX only, 2 MB max, 250 nodes, 500 edges.
- No formula evaluation, unrestricted file paths, network access or LLM.
- No insertion into PI production PostgreSQL, no activation of regulatory rules.
- Reviewer decisions remain append-only in candidate SQLite and must use an
  authenticated local reviewer, not viewer.
- Source assertions require review against original text and regulation before
  official application.
