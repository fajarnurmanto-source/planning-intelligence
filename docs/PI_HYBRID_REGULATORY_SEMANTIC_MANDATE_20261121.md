# PI-HYBRID-001 — Mandat Inti Regulatory Engine × Semantic Regulatory Reasoning

**Keputusan pemilik:** 21 November 2026 (WIB) · **Status:** MANDATORY_PRODUCT_ARCHITECTURE · **Sumber kanonis:** `docs/PI_MASTER_ROADMAP_CANONICAL_20261108.md` · **Basis:** R88 HEAD `65d7a76b88fc59819bb809188d47e14e097dda3d` · **Perubahan di cabang terisolasi:** tidak mengubah PR #74, database, kredensial, marker bridge atau produksi.

## 1. Mandat produk yang mengikat

Planning Intelligence bukan chatbot pencarian peraturan. PI adalah **regulatory-and-governance reasoning engine** yang memadukan (A) aturan deterministik dan hubungan norma yang bersumber, (B) analisis semantik AI untuk menemukan dan menjelaskan *kandidat* makna/relasi, dan (C) tata kelola bukti dan keputusan reviewer yang dapat diaudit. Komposisi dilakukan dalam engine/kontrak Laravel, dibaca oleh PI Superior dan lima Specialized Apps; **jangan** membangun graph, ledger reviewer, nomenklatur, ataupun aplikasi specialized baru yang menduplikasi R81–R88.

Setiap pertanyaan dimulai dari urusan/kompetensi pemerintahan, yurisdiksi, jenis ketentuan, hierarki/delegasi, identitas sumber, tanggal kasus, perubahan/pencabutan/transisi, lalu baru pembandingan istilah antarregulasi dan artefak RPJMD/RKPD/Renstra/Renja/APBD/DPA. Hubungan antarartefak bukan hubungan hukum otomatis.

## 2. Batas tanggung jawab tiga engine

| Lapisan | Dapat melakukan | Tidak boleh melakukan |
| --- | --- | --- |
| Canonical Evidence | memperoleh bukti sah; memeriksa hash, fragmen, locator, versi, hak, PIT, provenance dan keterlihatan sumber | menganggap search-hit/link katalog sebagai bunyi ketentuan atau teks primer terverifikasi |
| Regulatory & Governance | memeriksa tahun, kewenangan, jenjang, delegasi, amendemen/transisi, urutan proses, syarat pengakuan hubungan, dan aturan fail-closed | mengesahkan kewenangan/ketentuan hanya melalui kesamaan kata |
| Semantic Regulatory Reasoning | mengusulkan `EQUIVALENT`, `BROADER`, `NARROWER`, `RELATED`, `CONFLICT`, `INSUFFICIENT`; mengekstrak definisi operasional, unit, aspek, pengecualian, perbedaan cakupan, dan rujukan passage | mempromosikan kandidat menjadi fakta hukum, menciptakan sumber, menentukan hubungan hukum final, menghitung keselarasan angka ketika gate tertutup |
| Crosswalk & Review Governance | menguji kandidat terhadap anchor bukti, identitas tenant, tahun, graph, provenance, temporal scope, status reviewer; menerbitkan ABSTAIN, kandidat, reason code, audit envelope | mengubah hasil R84, memberi otorisasi belanja, atau membuka `PRODUCTION_ADMISSION` otomatis |

**Pemisahan wajib:** `semantic_confidence` (penilaian model, bukan probabilitas), `evidence_confidence` (bukti), dan `legal_validation_status` (status keputusan hukum) tidak boleh digabung menjadi satu skor.

## 3. Kontrak semantik internal PI

- Input: `tenant_id`, `case_id`, `official_code`, `case_year`, `as_of`, sumber/ketentuan berversi, source-pins/locator, graph edge IDs, informasi hak data.
- Kandidat keluaran internal: jenis relasi semantik, arah source→target, passage/edge IDs yang sudah ada, *natural-language explanation* dibatasi panjang, identitas model/version, label confidence non-probabilistik, kontra-bukti/ketidakpastian.
- Validator Laravel wajib menolak unknown node/edge, konteks tahun salah, relasi tanpa bukti atau tanpa jalur graph, identitas provider tak sah, confidence gabungan, dan 2027 yang belum punya versi resmi.
- Status default tanpa provider: `ABSTAIN_PROVIDER_NOT_CONFIGURED`; tanpa kasus berversi: `ABSTAIN_NO_VERSIONED_CASE`. Kandidat lolos pemeriksaan bentuk tetap `CANDIDATE_REVIEW_REQUIRED` dan `equivalence_asserted=false`; **bukan** legal determination.
- Semua input AI diperlakukan tidak tepercaya; tidak melakukan ekstraksi dari teks prompt di browser, tidak menerima keputusan reviewer melalui output AI, dan tidak mengirim dokumen tenant ke API eksternal secara default.
- Penyedia dapat ditukar (Gemini, ChatGPT, alternatif lokal) melalui adapter/kontrak yang sama, **opt-in** setelah legalitas pengolahan data, biaya, provider terms, model/version pin, retry, privacy dan benchmarking disetujui. Default: tanpa pemanggilan AI, biaya nol.

## 4. Urutan delivery wajib dan acceptance

### R88.1 — Hybrid boundary & runtime stabilization (implementasi awal)
- Pertahankan status R88 2026 dan 2027, `ABSTAIN_MISSING_COMPARISON_VERSION`, semua gate legal/numeric/production, source-only/tenant-auth endpoint, R84 existing reviewer.
- Tambahkan **semantic boundary di read model R88** dengan default abstention; validator deterministic bagi kandidat masa depan; output JSON bersumber tanpa chatbot terpisah.
- Regression: 2026 dan 2027, tenant/guest, invalid source, unknown edges, drift konteks, unsupported relation, tidak ada promosi legal, tidak ada provider call, ekspor tetap lossless.
- QA reproducible: konfigurasi sumber R70C/R70D dan R81/R82 dengan *read-only canonical paths*, bootstrap drift-checked, bukan hardcoded worktree bersama. Bukti local authenticated HTTP 200 pada R70A/R82/R84/R88 harus dicatat terpisah dari produksi.

### R89 — Semantic Regulatory Reasoning
- Normalisasi identifier/passage evidence dan *semantic candidate generation* berbasis konteks norma; supervised provider adapter Laravel, opt-in/dengan redaksi data jika perlu, batas tokens/biaya/timeout, tidak ada auto approval.
- Penilaian kontradiksi/relasi/entailment sebagai **hipotesis**, pelabelan perbedaan konteks, definisi indikator, hierarki lex specialis/delegasi hanya jika didukung teks ketentuan.
- Dataset berizin dengan label ahli: negative-hard cases, negasi, definisi berbeda, perubahan tahun, cross-tenant injection, model hallucination, source drift. Laporkan precision/recall/false admission/abstain dan calibration per jenis relasi, bukan mengiklankan accuracy sebelum uji.

### R90 — Hybrid crosswalk, quality gates & rollout
- Gabungkan kandidat yang sudah diperiksa ke PI Superior + 5 Specialized Apps sebagai *review queue*, bukan penambahan engine/ledger yang duplikatif.
- Peringatan perubahan aturan/versi dan *impact* terbatas hanya pada node/edge dengan bukti relevan; *do not infer* impact seluruh Pemda.
- Lulus kasus persampahan Yogya 2026 nyata dan satu kasus berbeda/daerah lain berhak-guna; 2027 tetap ABSTAIN sampai official nomenclature + indikator versi 2027 tersedia; lakukan UI desktop/mobile, auth/tenant, privacy, audit, zero-spend dan acceptance reviewer.

## 5. Gerbang yang tidak boleh dibuka oleh model

```text
LEGAL_HARMONIZATION=NOT_ASSESSED
NUMERIC_COMPARISON=BLOCKED
REVIEW_DECISION=NO_ACTIVE_DECISION
RELATION_PROMOTION=NOT_PERFORMED
PRODUCTION_ADMISSION=DENIED
2027_EQUIVALENCE=ABSTAIN_UNTIL_SOURCE_VERIFIED
```

Tidak ada auto-merge PR #74. Jalur pekerjaan ini berupa **stacked draft PR** terhadap branch R88; setelah R88 diterima baru rebase/review secara sadar. Perubahan roadmap mendefinisikan kewajiban produk, **bukan klaim semua kemampuan sudah terimplementasi**.
