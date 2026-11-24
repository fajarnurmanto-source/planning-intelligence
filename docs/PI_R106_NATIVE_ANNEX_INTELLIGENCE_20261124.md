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


## CI acceptance and page-locator correction

Source QA pinned to 2a6f5ca39003cd53c80872860086de04330abb2b. All four GitHub Actions success: R88 workflow 45337208008, R74 45337208020, R73 45337208025, Windows single-runtime safety 45337208005. R88 PHP targeted suite 56 tests/644 assertions passed; isolated authenticated HTTP and Chrome 390px passed; R106 CLI refuses unknown/mutating flag.

Actual attached original SHA-verified PDF analysis: Perwal74 first four account lines on PDF p384, two final leaf accounts on p385; Perwal12 all six on PDF p561. CI initially surfaced a deliberate synthetic page-boundary test failure; source was repaired to preserve form feed and account-level PDF page provenance and all tests now pass. Does NOT imply issuer identity authentication, legal signature, newer budget reconciliation or fiscal value admission.

Next: owner run same R106 QA PHP CLI against preexisting R104 quarantine in a clean pinned QA worktree; that is separate from code-only CI. No original app 8820 deployment or R84 reviewer approval occurred.
