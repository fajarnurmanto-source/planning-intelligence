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


## Nov 23 update — Windows code-only fixture acceptance GREEN
GitHub CI rerun on tested source `6f113b4d459466b66c7c646b66e9e966ab10a5fa` returned Windows `audit-safety` SUCCESS (run 45039570663, job 137712366481): PowerShell5.1 parse PASS; actual eight-file add/replacement fixture and SHA parity PASS; original DB/env/R88 protected sentinel hashes PASS; second application no-op PASS; unknown original code drift fail-closed PASS; injected first-swap failure rollback PASS. Independent R88 crosswalk CI run 45039570643 also SUCCESS. Earlier runner failed before steps; retry resolved. Source-only R100 is now eligible for ORIGINAL owner Windows preflight, not automatically deployed. Use exact pinned QA commit and require `PI_R100_READ_ONLY_PREFLIGHT=PASS`, 8 allowlist, expected 8 change lines and zero drift; only then explicit `-Apply` with backup and HTTP 401+asset SHA guard. Never modify original database, user, launcher, env or evidence. JDIH live PDF still maintenance, so 18 indexed source metadata only and no newly pinned original PDF bytes.


## R101 — Original Windows HTML drift guard corrected without bypass
Owner local R100 first attempt with clean source commit 6f113b4d459466b66c7c646b66e9e966ab10a5fa correctly stopped BEFORE applying changes due `PI_R100_ORIGINAL_CODE_DRIFT_HOLD=public/development-case-r85.html`. The old caller wrapper then triggered `Compare-Object` null DifferenceObject; this second error was purely in the caller. Root cause of likely false drift: PowerShell 5.1 native `git show` output was code-page-decoded, unlike UTF-8 bytes of original R94-deployed HTML, so identical non-ASCII text could compare unequal. Fixed candidate script now reads Git baseline raw byte stream and strictly decodes UTF-8; preserves strict HOLD on real content divergence and emits only safe normalized SHA/first-line diagnostic. Windows CI fixture revised to use byte-identical baseline raw UTF8 and U+00B7 codepoint check, with corrected test. New owner wrapper `scripts/pi-r101-r100-safe-owner-rollout.ps1` requires exactly 8 manifest lines, three verified text baselines and explicit -Apply. Windows audit workflow and three other workflows GREEN at pin `6882f16e76686a7d7c130f706a9b1faa358d6c86`; Windows confirms real eight-file fixture Apply + rollback, wrapper accepted UTF8, and rejected deliberately edited HTML before writes. NO original Windows R100 source code was installed from earlier failed preflight. Future original protected run may proceed only through R101 wrapper and must not bypass a genuine drift. Original database, accounts, APP_KEY, 8820 PHP and shortcut remain unmodified by source development.


## CD-20261123-08D — Owner original Windows R100 8-file release verified
Owner R101 protected -Apply at pin `6882f16e76686a7d7c130f706a9b1faa358d6c86` succeeded on original PI Superior Windows 8820. Exact 8 changes authorized and applied: REPLACE config/pi.php, routes/web.php, public/development-case-r85.html; ADD OfficialSourceMetadataController.php, OfficialSourceMetadataCatalogue.php, official-source-metadata-r100.json, official-sources-r100.css and official-sources-r100.js. Preflight confirms 3 UTF-8 original accepted baselines, 18 official metadata records, zero verified new PDF byte hashes, zero accepted 2026 subactivity numbers. Original live HTTP anonymous R100 auth barrier 401 and public R100 HTML/JS/CSS SHA validated, PID5704 retained through update, original SQLite/.env/APP_KEY/R88/launcher unchanged, full backup at `D:\Research\PlanningIntelligence\PI_UNIFIED_LAUNCHER_R74_20261111\backups\R100_OFFICIAL_SOURCES_20261123-204651-824-e1be26ad`. Final `PI_R101_R100_OFFICIAL_SOURCES=SUCCESS`. This upgrades earlier staging/NOT_RUN status: original code cutover is now COMPLETE, but still requires actual signed-in Edge R100 card render/filter to declare browser UAT PASS. 18 metadata are NOT 18 acquired PDF/source bytes, 2026 .0004 numeric evidence and 2027 equivalence still unverified; production admission still denied.


## CD-20261123-08E — Owner Edge source catalogue and fiscal filtering PASS
Owner Windows screenshots for original localhost8820 plus connected tab content now confirm R100 UI renders 18 official JDIH indexed records with 0 new verified SHA-256 primary source bytes and 0 fiscal subactivity figures admitted. On switching the category to `Penganggaran`, 7 records are visible including APBD 2026 Perda 11/2025, base Penjabaran APBD Perwal 74/2025 and five 2026 amendment versions; the official JDIH links and `BERKAS BELUM DIPIN HASH` provenance badges are present, and the evidence gaps remain displayed. This establishes original authenticated browser catalogue/filter acceptance, in addition to owner earlier R101 protected 8-file source cutover SHA/HTTP PASS. It does NOT establish that the JDIH PDFs or appendices are available, legally in effect, signed/hash pinned, or that `.0004` 2026 APBD amount and actual realization are known. Next step is isolate lawful primary-file acquisition and record exact byte hashes and source pages with versioned amendment lineage; no automatic approval and no direct mutation of original DB. Current R100_ORIGINAL_BROWSER_CATALOGUE_AND_FISCAL_FILTER=PASS; R100_NEW_PRIMARY_BYTES=0; R100_REVIEWED_NUMERIC_AMOUNTS=0; full commercialization gate DENIED.
