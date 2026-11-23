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
