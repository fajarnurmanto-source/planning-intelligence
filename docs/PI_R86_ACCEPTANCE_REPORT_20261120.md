# PI R86 — ACCEPTANCE & BASELINE STABILIZATION REPORT

Tanggal laporan: **20 November 2026 (Asia/Jakarta)**

Repository: `fajarnurmanto-source/planning-intelligence`

R86: draft PR [#69](https://github.com/fajarnurmanto-source/planning-intelligence/pull/69), branch `feature/r86-regulatory-backbone-lifecycle`

Baseline stabilization: draft PR [#70](https://github.com/fajarnurmanto-source/planning-intelligence/pull/70), branch `fix/r86-baseline-stabilization`

Target integrasi: `pilot/pi-laravel-github-bridge-20261108`

Dokumen ini mencatat bukti aktual. Status yang belum selesai tidak dinaikkan menjadi PASS. Kedua PR tetap draft dan tidak di-merge.

## 1. Ringkasan keputusan

- Empat kegagalan baseline telah diisolasi, diperbaiki pada PR #70 tanpa perubahan production code, dan acceptance gabungan menghasilkan **98/98 tests, 1116 assertions**.
- Frontend R84–R86 setelah penegasan label temporal menghasilkan **38/38 PASS**.
- Panel R86 dimuat pada sesi QA terautentikasi dan menampilkan kasus, 38 kandidat, 10 tahap, lima projection, gap/ABSTAIN, serta pemisahan APIP/BPK.
- Filter `external_audit` menghasilkan **1/10** tahap dan pengembalian ke semua tahap menghasilkan **10/10**.
- Navigasi same-origin menuju reviewer R84 berhasil dan tetap berada di sesi terautentikasi; ledger tidak membuat keputusan otomatis.
- Batas temporal 9/10 April 2028, dua unduhan nyata, sumber resmi, desktop/tablet/mobile, logout eksplisit, dan pencabutan akses endpoint telah diuji melalui Edge.
- Uji 390 px menemukan luapan horizontal pada kartu provenance R85; perbaikan `min-width`/wrapping diterapkan, dilindungi test, disinkronkan ke candidate runtime, dan uji ulang menghasilkan `scrollWidth = clientWidth = 375`.
- Acceptance R86 adalah **PASS** dan kandidat siap untuk persetujuan manusia. Kedua PR tetap draft, tidak di-merge, dan admission produksi tetap ditolak.

## 2. Matriks acceptance browser

| Skenario | Status | Langkah / expected | Actual dan bukti |
| --- | --- | --- | --- |
| Workspace PI Superior terautentikasi | **PASS** | Buka `/development-case-r85.html`; identitas QA dan panel R85/R86 terlihat | Identitas `fajar.nurmanto · institution admin` terlihat; panel R86 memuat tanpa pesan ABSTAIN/error |
| Kontrak kasus R86 | **PASS** | Kasus `YOG-2026-PERSAMPAHAN`, 38 kandidat, 10 tahap, lima projection | Runtime menampilkan 38 kandidat, 10/10 tahap, lima aplikasi spesialis, tiga sumber terpin, dan enam tahap tanpa bukti |
| Filter pemeriksaan eksternal/BPK | **PASS** | Pilih `external_audit`; hasil harus 1/10 dan tidak bocor ke kasus lain | Status berubah menjadi `1/10 tahap ditampilkan`; kembali ke semua tahap memulihkan 10/10 |
| Pemisahan APIP dan BPK | **PASS** | Internal oversight dan external audit harus menjadi tahap/aktor berbeda | Kartu dan registry mempertahankan `internal_oversight` dan `external_audit` sebagai dua tahap |
| Lima Specialized Apps | **PASS** | Evidence, Regulatory, Performance, Fiscal, Evaluation memakai registry bersama | Kelima projection tampil dengan jumlah tahap dari registry yang sama; tidak ada engine/UI paralel |
| Gap dan ABSTAIN | **PASS** | Dokumen monitoring/evaluasi/APIP/BPK yang tidak ada tidak boleh direka | Tahap tanpa artefak tetap `EVIDENCE_NOT_AVAILABLE`; brief menampilkan ABSTAIN/no decision |
| Endpoint tanpa sesi | **PASS** | GET endpoint R86 tanpa sesi harus 401 | HTTP lokal dan feature test menghasilkan 401 |
| Tenant lain | **PASS** | Tenant selain Kota Yogyakarta harus ditolak | Feature test tenant isolation menghasilkan 403/fail-closed |
| Reviewer R84 | **PASS** | Buka tautan relative same-origin dan pertahankan auth/reviewer gate | `/regulatory-trace-r70a.html#r84-review-workspace` terbuka; reviewer menampilkan tujuh kandidat, checkbox pemeriksaan PDF, tombol ledger tetap disabled tanpa syarat, dan identitas QA |
| Tautan sumber resmi eksternal | **PASS** | Klik minimal satu URL pada allowlist dan verifikasi tujuan | Membuka PDF resmi Peraturan BPK 1/2026 di `https://www.peraturan.go.id/files/peraturan-bpk-no-1-tahun-2026.pdf` |
| Batas temporal 9/10 April 2028 melalui UI | **PASS** | Ubah `as_of` ke 2028-04-09 lalu 2028-04-10 dan cocokkan status API/UI | 9 April: BPK 1/2017 current dan BPK 1/2026 not-yet-effective; 10 April: 2017 superseded dan 2026 current |
| Label temporal tidak menyesatkan | **PASS** | `CURRENTLY_APPLICABLE` harus dijelaskan sebagai metadata, bukan sertifikasi hukum | Kode kandidat memakai `BERLAKU SECARA TEMPORAL (METADATA)` dan peringatan eksplisit; frontend test PASS. Candidate runtime lama tetap diperlakukan sebagai uji fungsi temporal, bukan legal certification |
| Ekspor JSON nyata | **PASS** | Klik, unduh, parse, cocokkan filter/tanggal/gate | `PI_R86_YOG_2026_REGULATORY_LIFECYCLE_NOT_APPROVED.json` terunduh dan terurai: tanggal 2028-04-10, tahap BPK 1/10, katalog 38, evidence 4, gap 2, seluruh gate utuh |
| Ekspor Markdown nyata | **PASS** | Klik, buka, cocokkan isi dan label NOT_APPROVED | `PI_R86_YOG_2026_EXECUTIVE_REVIEW_NOT_APPROVED.md` terunduh dan terurai: kasus/tanggal/tahap/gap BPK benar, enam heading, seluruh gate utuh |
| Desktop visual sekitar 1440 px | **PASS** | Panel, controls, cards, source dan warning terbaca tanpa overlap | Runtime Edge 1366 px dirender dan dapat dioperasikan; panel/kartu/status terbaca |
| Tablet sekitar 768 px | **PASS** | Render dan operasikan filter/tanggal/ekspor | `innerWidth=768`, `scrollWidth=clientWidth=753`; enam kontrol tersusun dua kolom, terbaca dan operabel |
| Mobile sekitar 390 px | **PASS** | Render dan operasikan filter/tanggal/ekspor tanpa overflow | Setelah perbaikan provenance, `innerWidth=390`, `scrollWidth=clientWidth=375`; semua kontrol satu kolom, tanggal/tahap dapat diubah dan status 1/10 tampil |
| Logout eksplisit | **PASS** | Klik Keluar lalu pastikan akses endpoint dicabut | Tombol Keluar mengembalikan halaman login; navigasi langsung ke endpoint menghasilkan `{"message":"Unauthenticated."}` |

## 3. Temporal boundary dan batas klaim

Feature test R86 memverifikasi:

- `as_of=2028-04-09`: Peraturan BPK 1/2017 = `CURRENTLY_APPLICABLE`; Peraturan BPK 1/2026 = `NOT_YET_EFFECTIVE`;
- `as_of=2028-04-10`: Peraturan BPK 1/2017 = `REPEALED_OR_SUPERSEDED`; Peraturan BPK 1/2026 = `CURRENTLY_APPLICABLE`;
- pedoman RKPD 2027 dan APBD 2027 tetap `OUT_OF_SCOPE_CASE_YEAR` untuk kasus 2026.

Label API dipertahankan agar kontrak kompatibel. Presentasi UI mengubah label manusia menjadi status temporal berbasis metadata dan menambahkan peringatan bahwa hasil tersebut bukan sertifikasi penerapan hukum, harmonisasi, atau keputusan reviewer.

## 4. Baseline stabilization report

| Kegagalan awal | Root cause | Perubahan PR #70 | Sebelum | Sesudah |
| --- | --- | --- | --- | --- |
| AI provider status/vault | Tes read-only membaca vault runtime nyata; ciphertext memakai APP_KEY berbeda dari test | `Storage::fake('local')` mengisolasi tes status seperti suite settings | HTTP 500 `PI_AI_VAULT_UNAVAILABLE` | PASS |
| `ExampleTest` | Route root memang redirect ke `/index.html`; tes bawaan mengharapkan 200 | Verifikasi redirect, file institusi, dan identitas Planning Intelligence | expected 200, actual 302 | PASS |
| Cache-Control normative root | Symfony menormalkan urutan direktif menjadi `no-store, private`; urutan tidak semantik | Bandingkan himpunan tepat `no-store` + `private` | mismatch string | PASS |
| Cache-Control discovery | Penyebab sama pada endpoint discovery | Normalisasi semantik yang tetap mewajibkan dua directive keamanan | mismatch string | PASS |

Bukti PR #70 pada baseline R85:

- focused groups: **10/10 PASS, 101 assertions**;
- full backend: **93/93 PASS, 1056 assertions**;
- frontend: **33/33 PASS**;
- route, Vite production build, dan Pint: **PASS**;
- tidak ada production code, middleware, enkripsi, tenant isolation, atau cache policy yang diubah.

Bukti acceptance gabungan R86 + PR #70 pada worktree terisolasi:

- full backend: **98/98 PASS, 1116 assertions**;
- frontend sebelum penegasan label temporal: **37/37 PASS**;
- frontend setelah penegasan label temporal dan perlindungan overflow mobile pada PR #69: **38/38 PASS**;
- route, build, dan Pint: **PASS**;
- tidak ada merge ke branch integrasi.

## 5. GitHub dan gate

- PR #69: **OPEN, DRAFT, MERGEABLE, NOT MERGED**.
- PR #70: **OPEN, DRAFT, MERGEABLE, NOT MERGED**.
- PR #69 pada acceptance code HEAD `63341ce24218ecf537e31693717ea75338bd9eb8`: frontend lokal **38/38 PASS**; GitHub Actions dicatat pada HEAD final PR setelah commit laporan.
- PR #70 HEAD `127bd7e85e63f90d7bc4db9232985094e11b7c91`: tidak ada workflow run yang dipicu untuk perubahan test-only pada saat diperiksa.
- PR #69 HEAD terbaru harus menunggu hasil CI baru sebelum status merge-readiness dapat dinaikkan.

```text
LEGAL_HARMONIZATION=NOT_ASSESSED
NUMERIC_COMPARISON=BLOCKED
REVIEW_DECISION=NO_ACTIVE_DECISION
PRODUCTION_ADMISSION=DENIED
CATALOGUE_ADMISSION=CANDIDATE_CATALOGUE_NOT_LEGAL_VALIDATION
```

## 6. Sinkronisasi runtime Windows setelah persetujuan merge

Instruksi ini **baru dijalankan setelah** pemilik menyetujui merge PR #70 dan PR #69:

1. Catat SHA merge yang disetujui dan pastikan kedua PR tidak berubah sejak review.
2. Jalankan drift check bridge terhadap target integrasi; hentikan bila file runtime berbeda dari baseline/backup yang diketahui.
3. Pertahankan backup `D:\Research\PlanningIntelligence\PI_GITHUB_BRIDGE_BACKUPS\R86_CANDIDATE_20261118`.
4. Terapkan hanya source allowlist dari SHA merge; jangan menyalin `.env`, database, vendor, node_modules, secret, atau data pengguna.
5. Jalankan Composer/npm/build dan migration hanya bila diff merge memang memerlukannya. R86 dan PR #70 saat ini tidak menambah migration atau dependency.
6. Jalankan full backend, frontend, route, build, auth/tenant smoke, temporal boundary, dan dua ekspor pada runtime hasil sinkronisasi.
7. Ubah marker bridge hanya setelah apply dan smoke test berhasil; bila gagal, pulihkan source dari backup dan biarkan marker tidak berubah.

## 7. Status saat laporan dibuat

```text
PI_MILESTONE=R86_ACCEPTANCE_BASELINE_STABILIZATION
R86_PR=69
R86_HEAD=63341ce24218ecf537e31693717ea75338bd9eb8
R86_BROWSER_DESKTOP=PASS
R86_BROWSER_MOBILE=PASS
R84_NAVIGATION=PASS
R86_JSON_EXPORT=PASS
R86_MARKDOWN_EXPORT=PASS
R86_TEMPORAL_BOUNDARY=PASS
BACKEND_FULL_SUITE=98/98
FRONTEND_FULL_SUITE=38/38
GITHUB_CI=8/8
BASELINE_FAILURES_REMAINING=0
BASELINE_FIX_PR=70
R86_ACCEPTANCE=PASS
MERGE_READINESS=READY_FOR_HUMAN_APPROVAL
MERGE_PERFORMED=NO
PRODUCTION_ADMISSION=DENIED
LOCAL_BRIDGE_MARKER=UNCHANGED
NEXT_ACTION=Human owner reviews draft PR #70 and #69; merge only after explicit approval, then guarded Windows bridge sync.
```

