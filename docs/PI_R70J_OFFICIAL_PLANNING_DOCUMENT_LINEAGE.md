# PI R70J — RPJMD ↔ RKPD 2026 official document lineage (pilot)

**Status:** GitHub source committed; authenticated Windows-local acceptance UNCONFIRMED. One capability slice, not a legal, program-code or numeric conclusion.

## User-visible outcome

The existing Laravel Regulatory QA page gains panel **03 · OFFICIAL PLANNING DOCUMENT LINEAGE · R70J** showing two public official planning documents, their locators and URLs, two candidate relations, and a chronology/version warning. API: GET /api/qa-planning-r70j/lineage-candidates, under existing auth + pi.tenant.

Bundled official-metadata registry validated by strict source/type/date/URL/status constraints; source_registry_sha256 hashes REGISTRY BYTES ONLY, NOT PDF. PDF hashes are null and snapshot status is NOT_CAPTURED_NO_HASH. No copies of PDF are republished.

## Public, official sources observed 8 November 2026

1. Kota Yogyakarta RPJMD 2025–2029: Perda 2/2025 final 15 August 2025. JDIH https://jdih.jogjakota.go.id/home/produk_hukum/detail/3774 ; official BPK PDF https://peraturan.bpk.go.id/Download/400721/Perda%20Kota%20Jogja%20Nomor%202%20Tahun%202025.pdf . Pasal 2 ayat (4) huruf a names RPJMD as guide for RKPD.
2. Kota Yogyakarta RKPD 2026: Perwali 34/2025, dated 7 July 2025. JDIH https://jdih.jogjakota.go.id/home/produk_hukum/detail/3758 ; official PDF https://jdih.jogjakota.go.id/asset/naskah/2025pw3405034.pdf . Annex BAB I, subsection 1.3, printed page I-3, refers to a draft RPJMD Perda 2025–2029.

The RKPD regulation predates the final RPJMD regulation. This flags VERSION_REVIEW_REQUIRED_NOT_VIOLATION: chronology alone does not establish disharmony, illegality, misalignment or invalidity. A subsequent RKPD amendment requires separate version handling.

## Fixed gates

- MAPPING_CANDIDATE; REVIEW_REQUIRED; VERSION_REVIEW_REQUIRED_NOT_VIOLATION.
- LEGAL_HARMONIZATION=NOT_ASSESSED; SUBSTANTIVE_ALIGNMENT=NOT_ASSESSED.
- NUMERIC_COMPARISON=BLOCKED; PRODUCTION_ADMISSION=DENIED.
- No fabricated OPD/program/activity/subactivity codes, PDF hashes, budget conclusions.
- Unofficial URL, forged PDF hash, duplicate/wrong document, or invalid guards reject the entire read model. JS clears the panel and surfaces an error on inconsistent response.

## Functional checks / limitations

- JS syntax parse and mocked valid user flow: PASS (two source cards and two relations).
- JS forged source-integrity and forged PDF-hash handling: FAIL_CLOSED PASS.
- Laravel Feature test committed: 401 anonymous, 403 other tenant, metadata/chronology and 503 for tampered sources. Not executed against owner's Windows runtime. PHP CLI is unavailable in agent runtime.
- GitHub source-only; no local applied status, database migration, Composer/npm, build, restart, env edit or default-branch merge claimed.

## Single owner's local acceptance pass

After Windows scheduled source bridge applies the pilot revision, in Laravel root run:

    php -l app\Http\Controllers\Api\PlanningLineageController.php
    php -l routes\web.php
    php artisan route:list --path=qa-planning-r70j
    php artisan test --filter=PlanningLineageTest

Open http://127.0.0.1:8891/regulatory-laravel-r70g.html after login; check panel R70J renders two official links, 7 Jul / 15 Aug dates, warning BUKAN TEMUAN PELANGGARAN and NOT_CAPTURED_NO_HASH. Stop and report exact error if not.

**Next capability:** evidence-anchored typed program/performance node from an official RPJMD/RKPD page/table to one regulatory candidate with MAPPING_CANDIDATE/ABSTAIN; require exact unit/code/period/page source and PDF snapshot integrity before advancing its evidence tier.
