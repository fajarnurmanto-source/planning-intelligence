# PI R103 — Salinan BPK Penjabaran APBD 2026 dan kandidat bukti subkegiatan `.0004`

**PIT:** 24 November 2026 (WIB). **Target:** PR #78 DRAFT, Laravel-only, fail-closed, tanpa deployment ke PI Windows 8820.

## 1. Identitas sumber resmi yang diverifikasi

| Sumber BPK | Naskah | Status bukti |
|---|---|---|
| https://peraturan.bpk.go.id/Details/346297/perwali-kota-yogyakarta-no-74-tahun-2025 | Perwali 74/2025 — Penjabaran APBD 2026; BPK menyebut naskah 22 halaman dan lampiran 1.133 halaman | BPK metadata and attached PDF URL confirmed; server responds ~17 MB but full original bytes could not yet be acquired and hashed in our runtime |
| https://peraturan.bpk.go.id/Details/350352/perwali-kota-yogyakarta-no-12-tahun-2026 | Perwali 12/2026 — Perubahan Ketiga Penjabaran APBD 2026, berlaku 10 April 2026 | BPK PDF parsed and rendered via verified browser source, 1.596 PDF pages; full local file SHA still not pinned |

**Exact BPK PDF candidates:**
- Base: https://peraturan.bpk.go.id/Download/409706/Perwal%20Jogja%20Nomor%2074%20Tahun%202025.pdf
- Change3: https://peraturan.bpk.go.id/Download/414066/perwalkot%20No.12%20thn%202026.pdf

JDIH BPK metadata states its republication copies originate from the governmental issuing organization, and directs readers to confirm with that organization if contents differ. Therefore provenance `BPK_OFFICIAL_REPUBLICATION` MUST NOT be silently converted into `JDIH_KOTA_YOGYAKARTA_ORIGIN_BYTES_VERIFIED`.

## 2. Source-observed Lampiran II, 2026 DLH `.0004`

The **Perwali 12/2026 PDF, zero-based page index 560; one-based PDF page 561; printed Lampiran II page 520** directly displays:
- Program 2.11.11 `Program Pengelolaan Persampahan`
- Kegiatan 2.11.11.2.01 `Pengelolaan Sampah`
- Subkegiatan 2.11.11.2.01.0004 `Peningkatan Peran Serta Masyarakat dalam Pengelolaan Persampahan`
- Output indicator: number of community groups, people or other actors actively involved in community-based waste management

| Rincian belanja terlihat | Sebelum (Rp) | Sesudah (Rp) | Selisih (Rp) |
|---|---:|---:|---:|
| Kertas dan cover | 18.000.000 | 18.000.000 | 0 |
| Bahan cetak | 48.432.250 | 48.432.250 | 0 |
| Makanan dan minuman rapat | 27.060.000 | 27.060.000 | 0 |
| Jasa PPPK paruh waktu tenaga teknis | 48.324.000 | 48.324.000 | 0 |
| Jasa konsultansi manajemen | 75.000.000 | 75.000.000 | 0 |
| Jasa pihak ketiga/pihak lain | 803.550.000 | 803.550.000 | 0 |
| **Derived sum, six LEAF rows (NOT printed total)** | **1.020.366.250** | **1.020.366.250** | **0** |

Full account numbers fragmented across PDF text lines are not automatically reconstructed; verification of their concatenation is deliberately still required. The six values are supported by the rendered PDF table, not by an unverified LLM guess.

**Scope limitation:** Article I Perwali 12/2026 lists the specifically amended Appendix II organizational detail for Sekretariat Daerah (Bagian Kesejahteraan Rakyat) and BPKAD. The DLH table reproduced in the compiled attachment does **not** prove Perwali 12 amended DLH expenditure; the displayed DLH before/after amounts are identical. Perwali 13 and 15/2026 (subsequent changes) must be examined before claiming the latest official fiscal position. Neither these totals nor the 2026 cash realization is approved for live PI Fiscal calculations.

Official sources: https://peraturan.bpk.go.id/Download/414066/perwalkot%20No.12%20thn%202026.pdf#page=561 and https://peraturan.bpk.go.id/Details/350352/perwali-kota-yogyakarta-no-12-tahun-2026

## 3. Executed source implementation

- New `laravel/resources/pi/official-bpk-pdf-mirrors-r103.json`: exactly two source IDs, exact BPK detail/pdf/read URLs, unacquired SHA remains null, JDIH origin distinguished from BPK official republication.
- Updated `PrimaryPdfCandidateIntake`: offline source ID is still tied to R100; exact BPK pair allowed, but swapping 74/2025 and 12/2026 URLs, arbitrary BPK downloads, malicious hosts, query params and unreviewed IDs are rejected. Extra receipt fields preserve mirror role, detail URL and lack of direct retrievability proof. Existing JDIH intake remains supported.
- New `laravel/resources/pi/bpk-perwal12-dlh-0004-fiscal-candidate-r103.json`: six page-located values, computed sum and status `REVIEW_REQUIRED`, no source-byte digest, no fiscal admission.
- New `scripts/pi-r103-bpk-fetch.php`: default NO NETWORK and NO WRITE, explicit `--fetch` enables bounded direct HTTPS curl to ONE of the two exact official URLs, rejects redirects, HTML, malformed/oversize PDFs, copies to caller-provided quarantine OUTSIDE Laravel code and writes immutable SHA filename and HTTP receipt. It does NOT update original PI or make fiscal decisions.
- `scripts/pi-r102-stage-primary-pdf.php` now uses its standalone offline service without requiring Composer installation in a clean QA worktree; all existing R102 gates retained.
- Tests `R103BpkMirrorAndFiscalEvidenceTest.php` check exact allowlist, unsafe/cross-swapped links rejection, six-row sum and no legal/numeric promotions.

## 4. Approval and deployment gates

At the time of drafting, standalone code was committed and GitHub CI was started. CI attempts to retrieve BPK files on an ephemeral Actions runner; success or failure must be read from its logs before claiming SHA. The environment executing this assistant's Python and container utilities cannot resolve `peraturan.bpk.go.id`, and the available PDF browser gives extracted text/images, not full locally hashable bytes. Therefore the exact BPK whole-file SHA **must remain UNKNOWN unless the explicit downloader succeeds**. Runner temporary bytes are not persisted to owner Windows; owner must separately obtain and stage the files from a source that allows complete download.

Original Windows 8820 database, .env, account, APP_KEY, evidence uploads, existing PDF assets, protected R88–R100 runtime and desktop shortcut are unchanged. No new route or migration added, and no reviewer decision was created.

R103_BPK_OFFICIAL_MIRROR_IDENTITIES=VERIFIED_METADATA
R103_DLH_0004_SIX_VISIBLE_ROWS=PAGE_LOCATED_CANDIDATE
R103_SIX_ROW_BEFORE_AND_AFTER_DERIVED_SUM=1020366250_IDR
R103_LATEST_AMENDMENT_AND_ACTUAL_REALIZATION=NOT_VERIFIED
R103_OWNER_8820_DEPLOYMENT=NOT_RUN
PRODUCTION_ADMISSION=DENIED


## 5. CI acceptance and actual public-HTTP attempt — 24 Nov 2026

GitHub source-tested code commit `2b49be7efebb854f9da0ea90d0b7dda55b9f2684`, R88 CI run **45247893946**, job **137949400312**, plus R73/R74/Windows launcher workflows all **SUCCESS**:

- **46 PHPUnit tests / 603 assertions PASS**, including R102 and R103 source allowlist and page-located six-row fiscal candidate.
- Existing 61 JS frontend tests, R93 real HTTP and Chrome 390px regressions continue to PASS.
- `PI_R103_CI_BPK_OFFICIAL_REPUBLICATION_OFFLINE_GUARD=PASS` and `PI_R103_CI_NETWORK_FETCH_OPT_IN_AND_READ_ONLY_DEFAULT=PASS`.
- The new explicit HTTPS fetcher attempted BOTH canonical BPK Download URLs in an isolated temporary GitHub runner. **Both responded HTTP 403**, yielding `PI_R103_BPK_DIRECT_ACQUISITION_YOG-APBD-DETAIL-2026=HOLD_NOT_RETRIEVED`, `PI_R103_BPK_DIRECT_ACQUISITION_YOG-APBD-DETAIL-CHANGE3-2026=HOLD_NOT_RETRIEVED` and `PI_R103_HTTPS_FETCH_HOLD_STATUS_403` for each. A direct Python requests attempt in this assistant container failed earlier at DNS resolution. No genuine PDF bytes/hash were admitted in R103.
- Browser-readable BPK PDF viewer still supports the quoted source page and table candidate (Perwali 12/2026 PDF page 561), but does not provide a source file for a lawful byte-level hash at this runtime.

**Next actionable external acquisition:** an operator may open the official BPK detail/Read links in Edge, save BOTH full PDFs through the browser if its download flow works, then run `scripts/pi-r102-stage-primary-pdf.php` on each local file using the exact BPK URL IDs and a separate existing quarantine outside Laravel. Staging receipts must preserve `source_repository=BPK_OFFICIAL_REPUBLICATION`, `source_file_url_retrieval_confirmed=false` for offline user-supplied copies and `reviewer_approval=NOT_REQUESTED`. Any HTML/403 page saved as `.pdf` will be rejected. Avoid uploading into protected original 8820/public or changing DB/accounts.

R103_GITHUB_CI=PASS_46_TESTS_603_ASSERTIONS
R103_GITHUB_DIRECT_BPK_FETCH_74_2025=HOLD_HTTP403
R103_GITHUB_DIRECT_BPK_FETCH_12_2026=HOLD_HTTP403
R103_PDF_HASHES_OF_FULL_BPK_FILES=NOT_AVAILABLE
R103_FISCAL_PAGE_561_CANDIDATE=LOCATED_NOT_ADMITTED
R103_OWNER_8820=UNCHANGED


## R104 owner local BPK PDF acquisition — exact two-file SHA and quarantine
User has downloaded both BPK PDF files into a local folder written as `?:\Download` with exact names:

- `Perwal Jogja Nomor 74 Tahun 2025.pdf` maps ONLY to source ID `YOG-APBD-DETAIL-2026` and BPK Download/409706 exact URL.
- `perwalkot No.12 thn 2026.pdf` maps ONLY to source ID `YOG-APBD-DETAIL-CHANGE3-2026` and BPK Download/414066 exact URL.

A new Windows PowerShell 5.1 script `scripts/pi-r104-owner-bpk-two-pdf-intake.ps1` has been authored for a pinned clean QA worktree. It finds a **single unambiguous exact-name pair** by scanning only local drive-root `Download`/`Downloads` and the user's Downloads; accepts optional explicit `-DownloadDirectory` if needed. Dry-run mode calculates raw PDF byte count and SHA-256 after checking file magic, PDF EOF, symlink and size; neither file is edited. Explicit `-Archive` checks the same hashes again before invoking offline R102 with exact BPK URLs and recording immutable quarantine file+receipt under `%LOCALAPPDATA%\PlanningIntelligence\R104_BPK_PDF_QUARANTINE`, outside the user PI Laravel installation and web root. A successful SHA/archive receipt DOES NOT yet confirm legal identity/signature, originating JDIH content equivalence, completeness of appendix, current APBD revision, RKA/DPA or account reconciliation, or reviewer approval.

CI source pin `9a551a920e33b572cf8e00ad994c0ecf487cdfbb`: Windows `PI existing unified launcher audit safety` run 45250402968 SUCCESS, step `R104 owner two downloaded BPK files SHA-only preflight, ambiguous and HTML rejection` PASS with synthetic files of the exact two names; `PI R88 crosswalk guards` 45250403024, R73/R74 workflows also SUCCESS. **No actual owner PDF bytes or SHA have yet been observed in this conversation.** Do not claim local archive or full document verification until owner returns `PI_R104_...` logs; then review R102 receipts, PDF signature/size and version, and read relevant appendices before fiscal admission.
