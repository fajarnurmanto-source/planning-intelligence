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
- Panel R86 pernah dimuat pada sesi QA terautentikasi dan menampilkan kasus, 38 kandidat, 10 tahap, lima projection, gap/ABSTAIN, serta pemisahan APIP/BPK.
- Filter `external_audit` menghasilkan **1/10** tahap dan pengembalian ke semua tahap menghasilkan **10/10**.
- Navigasi same-origin menuju reviewer R84 berhasil dan tetap berada di sesi terautentikasi; ledger tidak membuat keputusan otomatis.
- Sesi browser QA kemudian kedaluwarsa sebelum uji tanggal melalui UI, unduhan nyata, sumber eksternal, viewport tablet/mobile, dan logout eksplisit selesai.
- Karena acceptance browser belum lengkap dan CI untuk HEAD terbaru belum tersedia, status saat laporan ini dibuat adalah **BLOCKED**, bukan merge-ready.

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
| Tautan sumber resmi eksternal | **NOT_RUN** | Klik minimal satu URL pada allowlist dan verifikasi tujuan | URL resmi/allowlist terlihat, tetapi klik eksternal belum selesai sebelum sesi kedaluwarsa |
| Batas temporal 9/10 April 2028 melalui UI | **BLOCKED** | Ubah `as_of` ke 2028-04-09 lalu 2028-04-10 dan cocokkan status API/UI | Kontrak backend PASS; input UI belum selesai karena sesi beralih ke halaman login |
| Label temporal tidak menyesatkan | **PASS** | `CURRENTLY_APPLICABLE` harus dijelaskan sebagai metadata, bukan sertifikasi hukum | UI sekarang memakai `BERLAKU SECARA TEMPORAL (METADATA)` dan peringatan eksplisit; frontend test PASS |
| Ekspor JSON nyata | **BLOCKED** | Klik, unduh, parse, cocokkan filter/tanggal/gate | Belum dapat dilakukan setelah sesi QA kedaluwarsa |
| Ekspor Markdown nyata | **BLOCKED** | Klik, buka, cocokkan isi dan label NOT_APPROVED | Belum dapat dilakukan setelah sesi QA kedaluwarsa |
| Desktop visual sekitar 1440 px | **PASS** | Panel, controls, cards, source dan warning terbaca tanpa overlap | Runtime Edge 1366 px dirender dan dapat dioperasikan; panel/kartu/status terbaca |
| Tablet sekitar 768 px | **BLOCKED** | Render dan operasikan filter/tanggal/ekspor | Belum dijalankan setelah sesi QA kedaluwarsa |
| Mobile sekitar 390 px | **BLOCKED** | Render dan operasikan filter/tanggal/ekspor tanpa overflow | Belum dijalankan setelah sesi QA kedaluwarsa |
| Logout eksplisit | **NOT_RUN** | Klik Keluar lalu pastikan akses endpoint dicabut | Sesi berakhir/kedaluwarsa, tetapi bukan bukti logout eksplisit |

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
- frontend setelah penegasan label temporal pada PR #69: **38/38 PASS**;
- route, build, dan Pint: **PASS**;
- tidak ada merge ke branch integrasi.

## 5. GitHub dan gate

- PR #69: **OPEN, DRAFT, MERGEABLE, NOT MERGED**.
- PR #70: **OPEN, DRAFT, MERGEABLE, NOT MERGED**.
- PR #69 pada HEAD lama `0cce5926ec79137a33b131517be187ff4850b25a`: GitHub Actions **8/8 PASS**.
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
R86_HEAD=63170181eea5abd146f45fe6915b053795f2790f
R86_BROWSER_DESKTOP=BLOCKED
R86_BROWSER_MOBILE=BLOCKED
R84_NAVIGATION=PASS
R86_JSON_EXPORT=BLOCKED
R86_MARKDOWN_EXPORT=BLOCKED
R86_TEMPORAL_BOUNDARY=BLOCKED
BACKEND_FULL_SUITE=98/98
FRONTEND_FULL_SUITE=38/38
GITHUB_CI=0/0
BASELINE_FAILURES_REMAINING=0
BASELINE_FIX_PR=70
R86_ACCEPTANCE=BLOCKED
MERGE_READINESS=NOT_READY
MERGE_PERFORMED=NO
PRODUCTION_ADMISSION=DENIED
LOCAL_BRIDGE_MARKER=UNCHANGED
NEXT_ACTION=Login manual QA, lalu selesaikan temporal UI, sumber resmi, ekspor nyata, tablet/mobile, logout, dan CI HEAD terbaru.
```

