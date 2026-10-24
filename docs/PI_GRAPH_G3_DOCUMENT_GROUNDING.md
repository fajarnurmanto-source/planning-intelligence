# Planning Intelligence G3 — Document-grounded excerpt verification

G3 reads **the existing archived G2** plus the **same four original source files** used to build G1. It reparses through the existing deterministic PI parser and rejects inputs whose SHA-256 does not match the document metadata stored with G1/G2. No original file is copied into the archive.

**Verified fact**: an explicitly claimed quote equals normalized text at a specific SourceUnit, including parser-generated unit fingerprint, ID, document hash, and format-specific locator. This is NOT semantic entailment, policy alignment, factual sufficiency, legal compliance, causal impact or approval of the G2 relationship. Unlocated claims become G3 gap findings. Never report 0 gaps as "coherent".

The claim ledger is frozen by its SHA-256 from G2. An altered ledger requires a fresh G2 run. The source paths must be the exact four source documents. G3 reuses the existing VS5 parser, G2 ledger parser, SQLAlchemy archive, append-only reviewer actions, Gradio, and Word/JSON exporter.

## Format-specific exact SourceUnit locators

- XLSX `xlsx:Evidence!B3`
- PDF `pdf:page=1:block=2`
- DOCX `docx:paragraph=3` or `docx:table=1:row=2:col=3`

Node excerpts point into their declared document role; relation excerpts point into the **source entity's** declared document role. A relation needs all three excerpts: two endpoints and a source-language relational assertion. A quote existing on a page is still insufficient to prove a causal relationship.

## Synthetic end-to-end

`app.graph_g3.samples.make_grounded_set(root)` makes four SOURCE Excel files with a `Target` sheet and an `Evidence` sheet plus a matching G2 ledger. Run G1 with the originals, G2 with that ledger, then G3 with *that exact same ledger and originals*. The tampered sample forces a mismatch.

Only synthetic documents with marker `PI_SYNTHETIC_FIXTURE_ONLY`, up to 2 MB per file. All inputs remain localhost. No SQLite or PostgreSQL admission into the canonical PI database; G3 results and reviewer actions persist only in the separate candidate review store. No production access, no legal or causal conclusions.
