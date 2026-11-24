# PI R106 — Native PDF Annex Intelligence

Date 2026-11-24 WIB. Draft PR #78, source only. No new runtime, migration, or deployment to owner 8820.

Two attached real PDFs were independently SHA-256 verified against the R104/R105 owner pins, and a local pure PHP + Poppler text-layer prototype successfully reconstructed six leaf accounts and compared the amounts WITHOUT OCR or model API:
- Perwal 74/2025: 17,018,238 bytes, 1,155 PDF pages; relevant PDF page 384, printed page 335.
- Perwal 12/2026: 5,083,310 bytes, 1,596 PDF pages; relevant PDF page 561, printed page 520.
- Subactivity 2.11.11.2.01.0004; full accounts 5.1.02.01.001.00025, 5.1.02.01.001.00026, 5.1.02.01.001.00052, 5.1.02.02.001.00086, 5.1.02.02.009.00013, 5.1.02.05.002.00001.
- Six candidate leaf amounts IDR 18,000,000; 48,432,250; 27,060,000; 48,324,000; 75,000,000; 803,550,000. Derived sum IDR 1,020,366,250; same in base and third-amendment table before and after. None admitted.
- Pasal I in Perwal12 specifically names SETDA Kesra and BPKAD as modified Lampiran II organizations. Do not infer a DLH allocation changed directly.

New native Laravel service validates original SHA256, R105 immutable receipt check is mandatory before API/CLI, extracts full text locally using pdftotext -layout and existing R82-style Windows WSL fallback, reassembles column-wrapped account codes and refuses ambiguous or inconsistent six-leaf account data. Scoped specifically to these two pinned documents and one subactivity; NOT a universal PDF table reader.

Auth + tenant guarded opt-in GET /api/qa-superior-r106/annex-fiscal-review and /api/qa-superior-r106/annex-pdf?source=base|change3; configured only with PI_R106_NATIVE_ANNEX_ENABLED=true plus PI_R106_QUARANTINE_ROOT outside Laravel. Default OFF. The original PI Superior development case gains one R106 panel and same-origin PDF page links; no second launcher. Source-only QA CLI scripts/pi-r106-native-annex-review.php defaults to existing LOCALAPPDATA quarantine. No write/commit/approval flags.

CI tests synthetic fixtures and negative cases. Review: visual page checks, complete signatures and legal identity, BPK/JDIH originating version equivalence, Perwal 13/15, RKA/DPA/realization and reviewer decision still pending. 2027 ABSTAIN; numeric admission 0, production DENIED. Never install unreviewed source into original Windows 8820.

Page-accurate R106 correction: direct visual inspection of the two uploaded PDFs confirmed that the six base ledger lines cross the source PDF page boundary. The first FOUR accounts appear on Perwal74 PDF p384/printed335, and the final TWO on PDF p385/printed336. All SIX third-amendment lines appear on Perwal12 PDF p561/printed520. R106 now preserves form-feed delimiters in the exact source text and emits source page numbers per account, avoiding the earlier incorrect assumption that all base leaf rows were on PDF p384. Visual observation here is evidence for the QA case only; not an authenticated legal issuer signature or final fiscal approval.
