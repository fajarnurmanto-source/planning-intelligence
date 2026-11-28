# PI R102 — Akuisisi Primer Offline, SHA-256, dan Resi Karantina

**PIT:** 23 November 2026. **Status:** source PR #78 DRAFT, original PI Windows 8820 untouched.

## Official PDF availability

R100 has 18 official indexed JDIH document records (7 fiscal), but not actual fetched PDF bytes. Examples:
- Perwal Kota Yogyakarta 74/2025, Penjabaran APBD 2026: https://jdih.jogjakota.go.id/home/produk_hukum/detail/3828
- Official PDF URL recorded in JDIH: https://jdih.jogjakota.go.id/asset/naskah/2025pw3405074.pdf
- Perwal Kota Yogyakarta 12/2026 (Third Amendment): https://jdih.jogjakota.go.id/asset/naskah/2026pw3405012.pdf

Direct PDF access on 23 Nov 2026 returned HTML "Sedang proses maintenance". Search-engine cached PDF text is NOT acquisition of original document bytes; source PDF SHA values and .0004 expenditure amounts must stay UNKNOWN.

## Implemented workflow

- Laravel service: laravel/app/Services/Pi/Development/PrimaryPdfCandidateIntake.php. Pure offline validation: source ID must be in reviewed R100 index, claimed file URL must be HTTPS JDIH under asset/naskah, timestamp ISO8601, size bound, PDF header and tail, SHA256 of local bytes.
- Local CLI: scripts/pi-r102-stage-primary-pdf.php. Default READ-ONLY dry-run; only an explicit --commit copies file and immutable JSON receipt under an isolated existing quarantine directory OUTSIDE original Laravel. Existing sources and original R100 metadata remain untouched.
- Receipt fields: source ID, known indexed JDIH record, claimed PDF URL, timestamp, original R100 registry SHA256, local-byte SHA256 and size, unconfirmed URL retrieval, identity REVIEW_REQUIRED, PDF signatures/legal validity NOT_AUTHENTICATED, reviewer NOT_REQUESTED, financial numbers admitted ZERO, production DENIED.
- PDF magic/footer only proves a byte-level candidate, not legal authenticity, digital signature, appendix completeness, or a valid table entry. No network requests, Laravel boot/migration, SQLite, password, APP_KEY, paid cloud AI, or reviewer writes.

## Example when a lawful original PDF is actually available locally

Use a clean pinned QA repo with composer dependencies installed and a separate existing quarantine folder. Replace the sample capture time with the ACTUAL acquisition time. No automatic download occurs.

~~~powershell
$qa = 'C:\Users\fajar\.codex\worktrees\pi-r74-autostart\PI_GITHUB_BRIDGE_20261108'
$quarantine = 'D:\Research\PlanningIntelligence\PI_EVIDENCE_QUARANTINE_R102'
php "$qa\scripts\pi-r102-stage-primary-pdf.php" --id=YOG-APBD-DETAIL-2026 --file="D:\Downloads\2025pw3405074.pdf" --url=https://jdih.jogjakota.go.id/asset/naskah/2025pw3405074.pdf --captured-at=2026-11-23T21:00:00+07:00 --staging-root="$quarantine"
~~~

A successful dry-run creates NO files. Only after human verification of the lawful download should the same command be rerun with --commit; this saves a CANDIDATE, never grants production admission or reviewer approval.

## CI results

GitHub PR run 45191240266, job 137749843813 at source 5e226049e8510004a15973dd398b3036ec3c6450: R88/R89/R100/R102 PHPUnit 42 PASS / 549 assertions, Node frontend 61 PASS / 0 fail, R93 auth 3 PASS / 56 assertions, real HTTP R100 PASS, Chrome 390px PASS. CLI actual test executed read-only dry-run, explicit quarantine SHA+receipt, rejection of maintenance HTML. Label PI_R102_CI_OFFLINE_PDF_QUARANTINE_AND_HTML_REJECTION=PASS. The CI PDF fixture is SYNTHETIC and is NOT a document of Kota Yogyakarta. Windows launcher safety, Laravel R73 and R74 CI workflows also SUCCESS on this commit.

## Next admission gates

1. Lawfully acquire original PDF bytes once JDIH becomes available, record source URL, acquisition timestamp and rights.
2. Record local SHA256, exact file size, version and receipt with R102; quarantine only until reviewer confirms identity/provenance, appendix location and legal scope.
3. For fiscal evidence, inspect Lampiran II per amendment version, RKA/DPA and realization, never impute subactivity .0004 amounts or assume 2026 and 2027 code equivalence.
4. Only then create a reviewed read model feeding Evidence, Regulatory, Performance, Fiscal and Evaluation separately. Do not mutate source R100 counts or approvals from candidate status.

R102_OFFLINE_BYTE_CANDIDATE_STAGING=IMPLEMENTED_CI_PASS
R102_ACTUAL_OFFICIAL_PRIMARY_PDF_BYTES=NOT_ACQUIRED
R102_REVIEWED_FISCAL_NUMBERS=ZERO
R102_OWNER_WINDOWS_8820=NOT_MODIFIED
PRODUCTION_ADMISSION=DENIED
