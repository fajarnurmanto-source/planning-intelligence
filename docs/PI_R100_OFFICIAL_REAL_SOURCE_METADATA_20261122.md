# PI R100 — Sumber Data Nyata: Katalog Metadata JDIH Kota Yogyakarta

PIT: 22 November 2026. Branch PR #78 DRAFT. Original Laravel Windows 8820 NOT modified in this milestone.

## Implemented

The PI Superior Development Case page now includes a local JDIH official-source metadata index. The source file is laravel/resources/pi/official-source-metadata-r100.json with 18 indexed official record identities, each with document title, legal instrument, number/year, period, group, JDIH URL and locator, and strictly unverified document-byte status.

The read-only Laravel API GET /api/qa-superior-r100/official-sources requires the existing authenticated tenant session. A PHP source validation service rejects duplicate records, untrusted URLs, numerical amounts, invented PDF hashes, legal-validation claims, and malformed registry data with fail-closed HTTP 503. The browser displays safe JDIH links, filters, counts, review gates and evidence gaps without fetching external documents. The normal source response runs locally and never calls a paid AI provider or writes a reviewer decision.

The 18 source records comprise nine planning documents (RPJPD 2025–2045, RPJMD 2025–2029, Renstra PD 2025–2029, RKPD/Renja PD 2026, revisions to both 2026, RKPD/Renja PD 2027), one waste sector masterplan 2022–2031, seven fiscal instruments (APBD 2026, Penjabaran APBD 2026 and five amendment instruments), and one 2025 fiscal accountability instrument. Official indexed JDIH links and document identities are in the JSON manifest.

## Verification boundary

Every source is OFFICIAL_JDIH_INDEXED metadata; each is NOT_ACQUIRED_NOT_HASH_PINNED, with document_sha256 null, numeric_amount null, case_program_equivalence NOT_VERIFIED and legal_validity NOT_ASSESSED. JDIH website and naskah PDF endpoints returned "Sedang proses maintenance" when revisited during this work. Thus 18 metadata links must never be described as 18 verified PDF files, and no 2026 subactivity .0004 financial figure or 2027 code equivalence has been admitted.

The five specialized-readiness views remain candidate-only: Evidence requires lawful primary file bytes and SHA, Regulatory provision/amendment/legal review, Performance indicator formulas and target versions, Fiscal RKA/DPA/realization reconciliation, and Evaluation monitoring, APIP/BPK and follow-up evidence.

## Tested on a disposable GitHub origin

At tested source commit 6c5c12c812587ce821069af99adc873dc8d37f78, PHP Laravel R88/R89/R100 tests: 37 PASS / 496 assertions. JS frontend: 61 PASS. R93 Laravel integrated auth: 3 PASS / 56 assertions. Real HTTP authenticated R100 catalogue read PASS at disposable localhost19893, and real Chrome R100 source cards, JDIH link safety, group filtering, expand and viewport390 CSS PASS, with cloud disabled.

The original user Windows app was not touched.

## Cutover pending

Source-only installer scripts/pi-r100-official-source-cutover.ps1 is default READ_ONLY and allows exactly eight code/metadata files: existing config/pi.php, routes/web.php, development-case-r85.html and five new R100 service/controller/registry/JS/CSS sources. It protects original SQLite, .env, APP_KEY, original-only PDFs, R88 R98 registries, R95 CSS and the existing desktop launcher; requires the accepted R94 baseline, clean source pin, SHA backup and rollback and HTTP route401/public asset SHA acceptance.

The initial Windows installer CI fixture stopped because a shallow GitHub Actions checkout lacked the accepted R94 ancestor. Workflow fetch-depth was changed to 0. The later GitHub Actions jobs failed before any step or retrievable log, so latest Windows cutover CI is NOT_GREEN and the installer is NOT approved for execution on original 8820. Do not run the new installer until the Windows fixture and rollback checks actually PASS.

## Follow-up data ingestion

Once official JDIH naskah become available, acquire primary bytes into isolated staging, record lawful rights, timestamp, size, SHA256, version and exact locators, then seek reviewer confirmation. Reconcile RKPD/Renja/APBD versions and the specific .0004 subactivity budget/indicator only against pinned annex cells, RKA, DPA and realization. Do not infer amounts from index records or claim 2027 equivalence just because a 2027 plan exists.

R100_SOURCE_CODE=IMPLEMENTED
R100_LARAVEL_HTTP_AND_CHROME_CI=PASS
R100_WINDOWS_CUTOVER_CI=BLOCKED_NOT_GREEN
R100_OWNER_8820_INSTALLATION=NOT_RUN
R100_NEW_SOURCE_BYTES_AND_NUMERIC_ADMISSION=ZERO
PRODUCTION_ADMISSION=DENIED
