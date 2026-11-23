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
