# PI R70K — source-located Planning node with truthful Regulatory ABSTAIN

**Roadmap:** PI-MR-20261108-V2. **Scope:** one source-only capability slice, pilot branch and draft PR #31. **Windows local apply and Laravel acceptance:** UNCONFIRMED.

## User-visible result

An added panel on the existing authenticated Regulatory Laravel screen shows a real planning-document node and verifies the separate R70F regulatory source model (7 official documents, 5 amendments, 10 Lampiran I/II target rows). It reports ABSTAIN, because a specific, source-proven same-year official program code and amendment-to-program mapping is still missing. An absence of verified mapping must not become a claim of agreement or discrepancy.

Public source: Peraturan Wali Kota Yogyakarta Nomor 34 Tahun 2025, RKPD 2026, official JDIH PDF https://jdih.jogjakota.go.id/asset/naskah/2025pw3405034.pdf and JDIH metadata https://jdih.jogjakota.go.id/home/produk_hukum/detail/3758 .

Evidence printed page **II-77**, Lampiran BAB II, C. Pekerjaan Umum dan Penataan Ruang, **Tabel 2.66** and adjoining prose:

- Dinas Pekerjaan Umum, Perumahan, dan Kawasan Permukiman is described as the institution responsible for the composite Indeks prasarana umum wilayah.
- Prose lists program penyelenggaraan jalan among multiple weighted components of that index; its official program code is **not present in the reviewed fragment**.
- Tabel 2.66 reports Indeks prasarana umum wilayah, unit angka indeks, value **92,16 for historical year 2024**. This is not a 2026 target. No indicator calculation or numeric comparison is conducted.
- The visible printed page locator II-77 is not represented as a physical PDF page number; PDF snapshot SHA256 remains null/NOT_CAPTURED_NO_HASH. Source excerpt from public web text is located, not admitted as hash-pinned PDF evidence.
- Program-to-regulation relation to the 10 amendment targets is null and ABSTAIN, with exact reason NO_VERIFIED_2026_PROGRAM_CODE_OR_ROW_LEVEL_REGULATORY_MATCH.

## Files

- laravel/resources/pi/official-planning-node-r70k.json — one real-source, explicit abstention registry.
- laravel/config/pi.php — server-side registry file path.
- laravel/app/Http/Controllers/Api/PlanningProgramCandidateController.php — source- and field-pinned, fail-closed read-only API.
- laravel/routes/web.php — GET /api/qa-planning-r70k/program-candidate inside auth + pi.tenant middleware.
- laravel/tests/Feature/PlanningProgramCandidateTest.php — focused 401/403, real value, null code, null target, 503 forged-code/target cases.
- laravel/public/planning-program-r70k.js — authenticated R70K + R70F validation and visible ABSTAIN.
- laravel/public/planning-lineage-r70j.css, laravel/public/regulatory-laravel-r70g.html — integrated accessible panel.

## Proof and limitations

- JavaScript parse + deterministic mock user flow PASS (one node, 10 R70F targets, explicit ABSTAIN).
- Forged official program code and wrong R70F target count fail closed in the visible panel.
- Focused Laravel tests committed but **not run**; PHP CLI unavailable in agent environment. No live session observed at owner's localhost 8891.
- No database, .env, credentials, packages, migrations, builds, restarts, billing or production changes.
- Status remains REVIEW_REQUIRED, LEGAL_HARMONIZATION=NOT_ASSESSED, SUBSTANTIVE_ALIGNMENT=NOT_ASSESSED, NUMERIC_COMPARISON=BLOCKED, PRODUCTION_ADMISSION=DENIED.

## Single owner acceptance

Once Windows GitHub bridge has safely applied source commit:

    php -l app\Http\Controllers\Api\PlanningProgramCandidateController.php
    php -l routes\web.php
    php artisan route:list --path=qa-planning-r70k
    php artisan test --filter=PlanningProgramCandidateTest

Open authenticated http://127.0.0.1:8891/regulatory-laravel-r70g.html and verify R70K shows source II-77, Tabel 2.66, year 2024 / 92,16 as raw historical data, no official program code, R70F ten targets, ABSTAIN and no 2026 numeric comparison. The R70H PDF 404 fix and R70I/R70J local features also still await owner's browser acceptance.

**Next capability:** acquire one actual 2026 program/subactivity row (official code + unit + document/version + source PDF page/table/row) from a real authorized RKPD/Renja/APBD version. Only then attempt exact-code regulatory candidate mapping; preserve ABSTAIN when evidence is incomplete.
