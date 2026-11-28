# R105 — BPK quarantine read-only receipt review

Date: 24 Nov 2026, Asia/Jakarta. Source-only work on draft PR #78.

R104 OWNER evidence: the original PowerShell output reports full local file SHA and byte count, two immutable archived candidates and original 8820 untouched. The assistant has NOT remotely inspected original Windows archive PDFs, and source downloads are BPK OFFICIAL REPUBLICATION, not verified JDIH issuing-body bytes.

Owner pins:
- Perwal 74/2025: 17,018,238 bytes; SHA256 E4AAA45F33B7254FDB7BBE3A0BC456999FD2CE3E19D8C07B874C640A5E1DEE24
- Perwal 12/2026: 5,083,310 bytes; SHA256 4E5194E6987468E30406911B277BACCA73506B3BF93BE413C0C19784AE3825EA

The R105 QA-only CLI checks immutable archive paths and R102 receipts; source pair IDs and BPK URL allowlist; byte/size/SHA/header/EOF; R100 source catalogue SHA consistency; no human/legal/numeric promotion. It separately verifies six-row arithmetic and R103 candidate statuses on Perwal 12/2026 PDF page 561, printed Lampiran II p520. It NEVER opens PDF pages to authenticate document title/signatures, complete annexes, account codes or compare two printed fiscal revisions. Read-only integrity success is not legal evidence admission.

From an up-to-date isolated QA repo, not the owner PI Laravel application:

    php scripts/pi-r105-review-bpk-quarantine.php

Optional existing isolated quarantine root:

    php scripts/pi-r105-review-bpk-quarantine.php --root="C:\path\to\R104_BPK_PDF_QUARANTINE"

No apply, commit, database, credentials, HTTP, cloud, launcher or original runtime changes are part of this tool.

Substantive pending manual review:
1. Open EACH SHA-verified archived PDF; confirm actual legal instrument number, title, issuer, date, authorizing signature if applicable, version, printed pages and annexes.
2. Compare BPK republication identity with source JDIH Kota Yogyakarta when available, record rights and permitted reuse. URL and filename do not prove byte-equivalence.
3. Visually verify Perwal 12/2026 Lampiran II p520 / PDF p561, six leaf account descriptions, full codes, amounts and no parent double count. Rp1,020,366,250 is a derived candidate sum, NOT admitted spending.
4. Compare Perwal 74/2025, Perwal 12/2026, and subsequent Perwal 13/2026 and 15/2026 plus RKA/DPA and actual expenditure before any fiscal comparison. Keep 2027 equivalence ABSTAIN.
5. Obtain explicit human decision in existing R84 reviewer governance; do not synthesize approval.

R105_SOURCE_IMPLEMENTED; R105_OWNER_ORIGINAL_OFFLINE_CHECK=NOT_RUN_BY_ASSISTANT; R105_DOCUMENT_VISUAL_IDENTITY=REVIEW_REQUIRED; R105_FISCAL_ADMISSION=ZERO; PRODUCTION_ADMISSION=DENIED.

Do not merge PR #78, install into original Windows 8820 or change source R100 indexed document status on the strength of candidate bytes alone.

## Owner Windows R105 acceptance — 24 November 2026

**Owner-provided PowerShell output (actual computer, not synthetic CI)**:
- Clean QA repository fetched branch `codex/pi-single-runtime-windows-app-20261122`, detached at exact source SHA `21be5abbb6a74b10e12a05b02647537cead683f5`.
- `YOG-APBD-DETAIL-2026`: 17,018,238 bytes; SHA256 `E4AAA45F33B7254FDB7BBE3A0BC456999FD2CE3E19D8C07B874C640A5E1DEE24`.
- `YOG-APBD-DETAIL-CHANGE3-2026`: 5,083,310 bytes; SHA256 `4E5194E6987468E30406911B277BACCA73506B3BF93BE413C0C19784AE3825EA`.
- `PI_R105_TWO_PDF_SHA_AND_RECEIPT=PASS_LOCAL_ONLY`
- `PI_R105_SIX_VISIBLE_ROWS_DERIVED_IDR=1020366250`
- `PI_R105_LEGAL_IDENTITY_ORIGIN_ANNEX_ACCOUNT_CODES=REVIEW_REQUIRED`
- `PI_R105_LATER_AMENDMENTS_13_15=NOT_RECONCILED`
- `PI_R105_RKA_DPA_REALIZATION=NOT_VERIFIED`
- `PI_R105_REVIEWER_APPROVAL=NOT_REQUESTED`
- `PI_R105_FISCAL_ADMISSION=ZERO`
- `PI_R105_PRODUCTION_ADMISSION=DENIED`
- `PI_R105_NO_MUTATIONS=YES`.

**Result:** `R105_OWNER_LOCAL_INTEGRITY_ACCEPTANCE=PASS`, scoped strictly to two local immutable PDF/receipt pairs and R103 six-row *arithmetic consistency*. R105 implementation CI previously succeeded at this SHA: four workflows, including crosswalk regression 50 PHPUnit tests/616 assertions, CLI mutating-option rejection, authenticated HTTP and browser 390 px. Operator results establish original-machine read-only script success; they do NOT establish that the assistant personally inspected PDF pages or issuer provenance. No new code, source deployment, database, launcher, reviewer, or cloud inference was performed.

**Next meaningful capability:** examine actual immutable PDF contents and annex page locators using an authorized local PDF viewer or documented text extraction; independently confirm legal instrument number/title/issuer, PDF page count and Perwal 12/2026 Lampiran II printed p520/PDF p561. Reconcile Perwal 13/15 and RKA/DPA/realisasi before any fiscal claim. Never infer legal authenticity from a SHA match alone.

`R105_CI=PASS` • `R105_OWNER_LOCAL_INTEGRITY=PASS` • `R105_SUBSTANTIVE_PDF_REVIEW=NOT_DONE` • `NUMERIC_COMPARISON=BLOCKED` • `PRODUCTION_ADMISSION=DENIED`.
