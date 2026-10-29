# PI-PERF-TAX-01 — Rumusan perluasan cakupan IKU, IKD, IKK dan kinerja Perangkat Daerah

Tanggal rumusan: 29 Oktober 2026. Status: **PROPOSED_CONTRACT_NOT_IMPLEMENTED**.

Dokumen ini merupakan koreksi cakupan atas `PI_PERFORMANCE_TAXONOMY_V1.md` pada PR #30. Jangan menyatakan kolom atau perhitungan yang belum diimplementasikan sebagai fitur PI. Produksi tetap **DENIED** dan status keberlakuan regulasi diperiksa terpisah oleh manusia.

## 1. Hasil audit kontrak V1

V1 memiliki `structural_kinds`, `indicator_levels`, `indicator_natures`, `directions`, `measure_roles`, bentuk angka, alias eksplisit, dan sebagian provenance. V1 **belum** menyimpan klasifikasi administratif IKU/IKD/IKK, urusan/bidang urusan/suburusan, perangkat daerah dan unit penanggung jawab, kode nomenklatur dan versinya, instrumen/formula/data pembentuk angka, target bertahap, hubungan perencanaan-penganggaran-pelaporan, maupun keputusan reviewer yang persisten.

## 2. Klasifikasi indikator tidak eksklusif

Simpan empat dimensi terpisah. Satu indikator dapat memiliki beberapa peran administratif pada dokumen atau tingkat organisasi berbeda, dengan provenance setiap penetapannya.

| Dimensi | Nilai minimum | Catatan |
|---|---|---|
| `designation` | `IKU_PEMDA`, `IKU_PD`, `IKD`, `IKK_URUSAN`, `INDIKATOR_TUJUAN`, `INDIKATOR_SASARAN`, `INDIKATOR_PROGRAM`, `INDIKATOR_KEGIATAN`, `INDIKATOR_SUBKEGIATAN`, `OTHER`, `UNRESOLVED` | Boleh banyak label, tetapi masing-masing memerlukan dokumen penetapan dan keberlakuan. |
| `performance_level` | `MAKRO_DAERAH`, `PEMDA_STRATEGIS`, `PD_STRATEGIS`, `PROGRAM`, `KEGIATAN`, `SUBKEGIATAN`, `LAYANAN`, `URUSAN` | Bukan sinonim designation. |
| `performance_nature` | `IMPACT`, `OUTCOME`, `OUTPUT`, `PROCESS`, `SERVICE_QUALITY`, `UNDETERMINED` | Label outcome/output harus dibuktikan dari definisi, bukan hanya nama. |
| `reporting_framework` | `RPJMD_RKPD`, `RENSTRA_RENJA`, `SAKIP`, `LPPD_EPPD`, `BUDGET_EXECUTION`, `OTHER` | Tidak semua indikator LPPD menjadi IKU atau sebaliknya. |

`IKK` di sini berarti **Indikator Kinerja Kunci** dalam konteks LPPD/EPPD dan penyelenggaraan urusan pemerintahan. Jika dokumen memakai `IKK` untuk *Indikator Kinerja Kegiatan* atau singkatan lain, simpan sebagai alias bertanda ruang lingkup, jangan otomatis dipetakan ke `IKK_URUSAN`.

**Definisi domain:** `IKU` untuk indikator kinerja utama tujuan/sasaran strategis Pemda maupun perangkat daerah; `IKD` untuk indikator kinerja daerah yang dapat mencakup indikator makro dan indikator kinerja kunci; `IKK_URUSAN` untuk indikator kunci keberhasilan urusan pemerintahan. Peran ini perlu dibuktikan dari RPJMD/Renstra/keputusan IKU/LPPD resmi, bukan disimpulkan melalui nama indikator.

## 3. Objek domain yang harus ditambahkan

### A. `IndicatorDefinition`

`indicator_id`, `canonical_name`, `aliases_with_source`, `designation_assignments[]`, `performance_level`, `nature`, `direction`, `unit`, `operational_definition`, `formula_version_id`, `measurement_instrument_id`, `jurisdiction_id`, `urusan_code`, `bidang_urusan_code`, `suburusan_code`, `owner_pd_id`, `responsible_unit_id`, `effective_from`, `effective_to`, `definition_source_id`, `review_status`.

### B. `PerformanceMeasurementInstrument`

`instrument_id`, `indicator_id`, `operational_definition`, `numerator_definition`, `denominator_definition`, `formula_expression`, `formula_method`, `data_source`, `data_custodian`, `collection_method`, `collection_frequency`, `measurement_period`, `cutoff_date`, `aggregation_method`, `disaggregation_dimensions`, `validation_rule`, `quality_flags`, `evidence_requirements`, `effective_version`.

Instrumentasi diperlukan agar dua angka hanya dibandingkan jika **rumus, satuan, rentang cakupan, dan periode** setara. Simpan formula sebagai deklarasi terversi; eksekusi kalkulasi dilakukan oleh interpreter aman/terbatas, bukan eval atas string pengguna.

### C. `PerformancePlanTarget`

`indicator_id`, `document_version_id`, `planning_year`, `planning_period`, `target_stage` (`INITIAL`, `REVISED`, `TRANSITION`), `target_shape` (`SCALAR`, `RANGE`, `QUALITATIVE`), `lower_bound`, `upper_bound`, `unit`, `direction`, `baseline`, `source_table_cell_id`, `header_cell_id`, `provenance`, `review_status`.

### D. `PerformanceActualAndAchievement`

`indicator_id`, `measurement_period`, `actual_value`, `data_source`, `recorded_at`, `quality_status`, `calculation_method_id`, `achievement_value`, `achievement_unit`, `achievement_rule_version`, `numerator_evidence`, `denominator_evidence`, `review_status`.

**Invariant:** `target`, `realisasi`, `capaian`, dan `predikat` adalah objek/atribut berbeda. `capaian = realisasi / target × 100%` tidak boleh dipaksakan untuk semua indikator; arah keberhasilan, rentang, dan formula spesifik berpengaruh.

### E. `PlanningIntervention`

`node_id`, `node_kind`, `nomenclature_code`, `nomenclature_version`, `title`, `document_role`, `planning_year`, `owner_pd_id`, `responsible_unit_id`, `target_group`, `location_scope`, `budget_plan`, `budget_revision`, `realization_budget`, `source_document_version_id`.

Hubungan tujuan–sasaran–program–kegiatan–subkegiatan harus berupa *evidence-backed edges*, bukan keturunan otomatis dari urutan tabel.

## 4. Rantai lintas dokumen dan level perangkat daerah

- Jalur strategis: `RPJMD` tujuan/sasaran/IKU/IKD → `Renstra PD` tujuan/sasaran/IKU PD → program dan indikator program.
- Jalur tahunan: `RKPD` prioritas/sasaran/target tahunan ↔ `Renja PD` program, kegiatan, subkegiatan, indikator, target dan lokasi/kelompok sasaran.
- Jalur penganggaran: Renja/RKPD ↔ RKA/DPA (pagu, keluaran, realisasi) dengan kode nomenklatur yang berlaku pada versi dokumen.
- Jalur akuntabilitas: target PK/Rencana Aksi → data ukur periodik → LKjIP/SAKIP; indikator IKK dan kinerja makro → LPPD/EPPD melalui kerangka pelaporan berbeda.
- Jalur perubahan: RKPD reguler ↔ RKPD Perubahan; Renja reguler ↔ Perubahan; target awal ↔ target revisi. Jangan timpa nilai sebelumnya.

Relasi yang diusulkan: `DEFINED_BY`, `MEASURES`, `SUPPORTS_OBJECTIVE`, `IMPLEMENTS_PROGRAM`, `HAS_TARGET`, `HAS_REALIZATION`, `REPORTED_IN`, `ACCOUNTABLE_TO_PD`, `ALIGNED_WITH`, `REVISED_BY`; semuanya membutuhkan SourceUnit locator, dokumen/versi, dan status keputusan manusia. Relasi bukan klaim kausal otomatis.

## 5. Aturan perbandingan RPJMD–RKPD/Renstra–Renja

Sebelum `MATCH` / `DIFFERENCE`, periksa berurutan:

1. Identitas indikator dan pengesahan alias eksplisit.
2. Penetapan kategori IKU/IKD/IKK dan level Pemda/PD/program/kegiatan, sesuai sumber.
3. Urusan, perangkat daerah, kewenangan, dan kode nomenklatur beserta versi.
4. Formula/definisi operasional, satuan, arah keberhasilan, cakupan dan periode.
5. Role nilai (`TARGET`, `REALIZATION`, `ACHIEVEMENT`, `BASELINE`) serta header tahun/tabel/baris/kolom.
6. Versi target (`INITIAL`, `REVISED`, `TRANSITION`) dan asal dokumen.
7. Relasi perencanaan-kinerja lintas dokumen yang didukung bukti.
8. Review manusia dengan hasil `CONFIRMED_MATCH`, `CONFIRMED_DIFFERENCE`, `UNKNOWN`, atau `NOT_COMPARABLE` dan alasan.

Jika salah satu prasyarat belum terbukti, hasil sementara adalah `UNKNOWN` atau `NOT_COMPARABLE`; jangan mengubah pencarian teks menjadi verdict.

## 6. Contoh dari handoff 14 indikator

- **Indeks Keterpaduan Pembangunan** vs **Indeks Keterpaduan Pembangunan Infrastruktur**: nama mirip belum membuktikan satu definisi, alias, satuan dan formula; status `INDICATOR_IDENTITY_UNRESOLVED`.
- **Crime Rate**: teks `159–173 130,84 168 81,45` belum memisahkan target/realisasi/capaian; status `MEASUREMENT_ROLE_UNRESOLVED`.
- **Indeks Keberdayaan Masyarakat**: cuplikan memiliki `Target 2026`, `Realisasi TW II`, dan beberapa nilai sekaligus; perlu geometri sel/baris sebelum penetapan target.

## 7. Implementasi bertahap

P0.1 (kontrak ini): perluasan registry kategori/PD/urusan/instrumen dan model nilai terversi. **BELUM IMPLEMENTED**.

P1: rekontruksi PDF table geometry, header yang berulang, merged cells, tahun, dan role nilai berprovenance.

P2: registry indikator dan nomenklatur resmi versi spesifik per dokumen/daerah; pemetaan IKU/IKD/IKK/indikator program yang dapat direview.

P3: hubungkan target tahunan dan realisasi dengan program/kegiatan/subkegiatan dan instrumen ukur; perbandingan 14 indikator asli dan laporan reviewer.

## 8. Referensi dan catatan keberlakuan

- Inmendagri 2/2025 (Pedoman Penyusunan RPJMD dan Renstra PD 2025–2029), dokumen resmi pemerintah daerah: https://ppid.kukarkab.go.id/detailip/5651
- PermenPANRB PER/09/M.PAN/5/2007 (IKU): https://peraturan.bpk.go.id/Details/133024/permen-pan-rb-no-per09mpan52007-tahun-2007
- Perpres 29/2014 (SAKIP): https://jdih.menpan.go.id/dokumen-hukum/peraturan-presiden-nomor-29-tahun-2014-tentang-sistem-akuntabilitas-kinerja-instansi-pemerintah-1577
- PermenPANRB 53/2014 (PK dan laporan kinerja): https://jdih.menpan.go.id/dokumen-hukum/peraturan-menteri-pendayagunaan-aparatur-negara-dan-reformasi-birokrasi-nomor-53-tahun-2014-tentang-444
- Permendagri 19/2024 (LPPD/EPPD): https://peraturan.bpk.go.id/Details/321490/permendagri-no-19-tahun-2024

**Penting:** Permendagri 19/2024 mencabut Permendagri 18/2020. Jangan memakai 18/2020 sebagai dasar hukum aktif untuk pelaporan/evaluasi 2026. Regulatory taxonomy/domain B tetap terpisah dan tidak otomatis memutuskan keberlakuan hanya dari referensi ini.
