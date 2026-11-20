# PI STATE CAPSULE — R86 REGULATORY BACKBONE

Tanggal keadaan: **20 November 2026 (Asia/Jakarta)**

Branch kandidat: `feature/r86-regulatory-backbone-lifecycle`

Starting commit: `62415afa23af4dbeb81e861e1d0bda219f23e5a0`

Commit implementasi: `b17684d0c036e3a2191ed45aaee8e24c995f641b`

Acceptance code head: `63341ce24218ecf537e31693717ea75338bd9eb8`

Pull request: [#69](https://github.com/fajarnurmanto-source/planning-intelligence/pull/69) — **DRAFT, TIDAK DI-MERGE**

Acceptance report: [PI_R86_ACCEPTANCE_REPORT_20261120.md](PI_R86_ACCEPTANCE_REPORT_20261120.md)

Target integrasi: `pilot/pi-laravel-github-bridge-20261108`

Status merge: **TIDAK DI-MERGE**

## 1. Hasil yang diimplementasikan

R86 ditanam langsung ke workspace `development-case-r85.html`. `DevelopmentCaseGraph` R85 tetap menjadi host case dan kontrak API R85 tidak diubah. Endpoint baru yang terautentikasi dan tenant-scoped adalah:

`GET /api/qa-superior-r86/regulatory-lifecycle`

Read model menggabungkan:

- katalog awal 38 regulasi dari handoff, hash-pinned sebagai kandidat riset;
- 18 versi regulasi prioritas dan provision locator yang dapat diperiksa;
- 10 tahap siklus pemerintahan dalam graf dependensi, bukan timeline tunggal;
- projection bersama untuk Evidence, Regulatory, Performance, Fiscal dan Evaluation Intelligence;
- satu kasus nyata `YOG-2026-PERSAMPAHAN` dengan bukti dan gap eksplisit;
- temporal status berdasarkan `as_of`, tahun perencanaan dan tahun anggaran;
- pemisahan APIP/internal oversight dari BPK/external audit;
- ekspor JSON/Markdown `NOT_APPROVED` dan tautan ke reviewer R84.

Tidak ada migration, perubahan database, dependency baru, service berbayar, reviewer kedua, atau keputusan reviewer fiktif.

## 2. Bukti sumber dan temporal

| Item | Bukti yang digunakan | Status R86 |
| --- | --- | --- |
| RKPD 2026 | Permendagri 10/2025, sumber resmi BPK, berlaku 23 Juni 2025 | tahun perencanaan 2026; source located |
| RKPD 2027 | Permendagri 14/2026, sumber resmi BPK, berlaku 11 Juni 2026 | tahun perencanaan 2027; tidak dipakai sebagai dasar RKPD 2026 |
| APBD 2026 | Permendagri 14/2025, sumber resmi BPK | tahun anggaran 2026; provision locator masih review-required |
| APBD 2027 | Permendagri 17/2026 dari matriks handoff | metadata candidate; effective date belum diterima sebagai fakta |
| SPKN lama | Peraturan BPK 1/2017 | berlaku untuk `as_of` sebelum 10 April 2028 |
| SPKN baru | Peraturan BPK 1/2026, Pasal 8 dan Pasal 9 | PDF resmi 53 halaman diperiksa; efektif 10 April 2028 dan mencabut 1/2017 saat mulai berlaku |

PDF resmi Peraturan BPK 1/2026 dipin dengan SHA-256:

`8D585DA7E9664B64CED5358ECA8D1FBE935F4A6D6680CF5F7C83AA11366C3F8D`

Katalog 38 regulasi dipin dengan SHA-256:

`BB90F07F6387589EBAC3960B899709C4758D76B6707CB72E542480FD1BA17C5B`

## 3. Hasil pengujian

| Area | Hasil | Bukti ringkas |
| --- | --- | --- |
| R86 backend | **PASS** | 5 tests, 60 assertions |
| R84–R86 + auth + tenant + lineage | **PASS** | 17 tests, 171 assertions |
| Frontend seluruh suite | **PASS** | 38/38 tests |
| Route | **PASS** | endpoint R86 memiliki middleware `web`, `auth`, `pi.tenant` |
| Production asset build | **PASS** | Vite 8.3.8 build selesai |
| HTTP lokal tanpa sesi | **PASS** | workspace `200`; endpoint R86 `401` |
| Suite backend penuh + stabilisasi | **PASS pada worktree gabungan** | 98/98 PASS, 1116 assertions; perbaikan baseline terpisah di draft PR #70 |
| Frontend setelah label temporal | **PASS** | 38/38 tests |
| Browser desktop | **PASS** | sesi authenticated; registry/filter/reviewer/source/temporal/logout dan tidak ada console error |
| Browser tablet/mobile | **PASS** | 768 px tanpa overflow; 390 px menemukan lalu memverifikasi perbaikan provenance, kontrol R86 operabel |
| Download ekspor nyata | **PASS** | JSON dan Markdown `NOT_APPROVED` terunduh, dibuka, dan dicocokkan dengan filter/tanggal/gate |

Empat kegagalan suite penuh tidak berasal dari file R86. Perbaikannya berada pada draft PR #70 yang terpisah dan tidak mengubah production code. Acceptance gabungan R86 + PR #70 serta acceptance browser hijau. Kandidat siap untuk persetujuan manusia, tetapi tetap bukan production-ready: PR belum di-merge, marker bridge tidak berubah, dan `PRODUCTION_ADMISSION=DENIED`.

## 4. Runtime Windows

Kandidat disalin secara terukur ke runtime lokal untuk qualification tanpa mengubah marker bridge R85. Tiga file baseline yang ditimpa sementara memiliki backup di:

`D:\Research\PlanningIntelligence\PI_GITHUB_BRIDGE_BACKUPS\R86_CANDIDATE_20261118`

Runtime kandidat sudah melayani workspace melalui HTTP lokal. Ini adalah **candidate staging**, bukan bukti merge atau deployment produksi. Setelah PR diterima dan di-merge, bridge harus melakukan drift check terhadap target integrasi sebelum apply resmi. Bila PR ditolak, pulihkan tiga file baseline dari backup dan hapus hanya file R86 yang tercatat pada diff PR; jangan mengubah database atau marker bridge.

## 5. Gate dan batas klaim

```text
LEGAL_HARMONIZATION=NOT_ASSESSED
NUMERIC_COMPARISON=BLOCKED
REVIEW_DECISION=NO_ACTIVE_DECISION
PRODUCTION_ADMISSION=DENIED
CATALOGUE_ADMISSION=CANDIDATE_CATALOGUE_NOT_LEGAL_VALIDATION
```

Tidak adanya dokumen hasil monitoring, evaluasi, APIP, pemeriksaan BPK atau tindak lanjut untuk kasus harus tetap menghasilkan evidence gap/`ABSTAIN`, bukan temuan yang direka.

## 6. Posisi lanjut manual

1. Pemilik meninjau diff, bukti CI, draft PR #70 dan draft PR #69; jangan auto-merge.
2. Merge hanya setelah persetujuan eksplisit, idealnya PR #70 lebih dahulu lalu PR #69, atau sesuai keputusan integrator.
3. Setelah merge, lakukan drift check dan guarded Windows bridge sync dengan allowlist source; jangan salin `.env`, database, vendor, secret, atau data pengguna.
4. Ulangi smoke auth/tenant, temporal, ekspor dan responsive pada runtime hasil sinkronisasi; marker bridge hanya berubah setelah seluruh smoke PASS.
5. Verifikasi provision/effective date kandidat yang belum hash-pinned, khususnya APBD 2026/2027, sebelum promosi status hukum.

