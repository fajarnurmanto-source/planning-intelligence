# PI R70M — Temporal Regulatory Version Resolver

**Tanggal:** 8 November 2026 (WIB) · **Canonical roadmap:** PI-MR-20261108-V3, NORMATIVE-ROOT-006  
**Delivery:** source-only on GitHub pilot; no data mutation or approval to production.

## Perubahan produk yang nyata

Aplikasi Laravel sekarang memiliki `App\\Services\\Pi\\Regulatory\\NormativeVersionTimeline`, mesin sederhana yang menafsirkan *as-of date* untuk hubungan `AMENDS`, `REVOKES`, `REPLACES`, dan `TRANSITIONAL` **hanya jika** suatu peristiwa memiliki pengenal, target, tanggal efektif, **provision scope**, locator sumber resmi dan SHA-256 bukti yang sudah ditandai diterima.

Integrasi pada `GET /api/qa-regulatory-r70l/normative-root` menambahkan `version_timeline` yang benar-benar dihitung oleh server (bukan status versi yang sekadar diketik pada UI). Panel Regulatory R70L menunjukkan tanggal kasus, status tiap instrumen, jumlah peristiwa yang dimasukkan, dan dengan tegas menyatakan **0 event di registri tidak sama dengan 0 perubahan peraturan di dunia nyata**.

### Kasus resmi yang sekarang ditampilkan

- **Tanggal kasus:** 8 Nov 2026.
- Permendagri 86/2017: `START_DATE_UNVERIFIED` dalam registri R70L (tanggal awal yang tepat belum dipatok dalam snapshot ini), sensus perubahannya belum lengkap.
- Permendagri 10/2025: mulai berlaku menurut metadata resmi 23 Juni 2025, tetapi sensus amendemen/pencabutannya belum lengkap.
- `version_events=[]` secara eksplisit: **belum ada peristiwa perubahan yang diadmit dan dibuktikan**. BUKAN klaim kedua peraturan tidak pernah diubah.
- Kalkulasi menghasilkan `AMENDMENT_CHAIN_INCOMPLETE`, tetap `MAPPING_CANDIDATE`, `ABSTAIN`, `LEGAL_HARMONIZATION=NOT_ASSESSED` dan `NUMERIC_COMPARISON=BLOCKED`.
- Source documentary chain R70L tetap Permendagri 86/2017 Pasal 76 → rujukan delegasi *Menimbang b* Permendagri 10/2025 → Pasal 2(3)/3(5)/12(2) untuk RKPD 2026, dengan pengecualian provinsi DIY Pasal 2(6).

Sumber primer awal:
- https://peraturan.go.id/files/bn1312-2017bt.pdf
- https://peraturan.go.id/files/permendagri-no-10-tahun-2025.pdf
- https://peraturan.bpk.go.id/Details/321470/permendagri-no-10-tahun-2025

## Aturan perhitungan

1. Tahun/tanggal kasus dengan format kalender ISO valid menentukan apakah aturan atau peristiwa perubahan **sudah efektif** atau masih **di masa depan**.
2. Tipe peristiwa dibatasi pada `AMENDS|REVOKES|REPLACES|TRANSITIONAL`. Target peraturan dan pasal terdampak wajib spesifik, tidak boleh menganggap pencabutan satu pasal mencabut semua isi.
3. Keadaan `OFFICIAL_CENSUS_VERIFIED_COMPLETE` hanya boleh dipasok oleh tahap admission yang benar-benar membuktikan kelengkapan metadata dan revisi dari JDIH resmi. Mesin timeline **bukan** pengumpul JDIH dan tidak dengan sendirinya membuktikan klaim kelengkapan tersebut.
4. Peristiwa yang belum punya sumber resmi berprovenance, URL allowlisted, locator, SHA-256, atau tanggal yang valid **ditolak**. Nilai hash dalam data bukan bukti adanya snapshot apabila berkas belum ditangkap dan dibandingkan dengan hash tersebut.
5. Semua keluaran yang lengkap pun tetap `LEGAL_CONCLUSION=NOT_ASSESSED` dan `REVIEW_REQUIRED` sampai ada penelaahan manusia berwenang.
6. Endpoint pilot saat ini hanya mengizinkan daftar peristiwa kosong karena revisi R70L belum diverifikasi; pemalsuan daftar event ditolak `503`. Contoh event yang dipakai tes PHP adalah **sintetis**, bukan tuduhan adanya revisi tertentu.

## Verifikasi

- UI fake-DOM functional smoke **PASS**: keadaan 2 instrumen dan peringatan *zero event ≠ zero change* tampil; forged version status ditolak; 503 menahan panel; 401 mengarahkan ke login.
- Unit test sumber yang disiapkan (belum dieksekusi dalam lingkungan cloud tanpa PHP CLI): `NormativeVersionTimelineTest` mencakup amendemen di masa depan vs efektif pada tanggal kasus, pencabutan terikat pasal, dokumen tanpa tanggal awal dan sensor sumber palsu.
- Feature test `NormativeRootTest` menegaskan kalkulasi `AMENDMENT_CHAIN_INCOMPLETE` dan registri yang disisipi event tak terverifikasi ditolak.
- `LOCAL_APPLY=UNCONFIRMED`, `PHP_TEST=NOT_RUN`, `LIVE_UI_ACCEPTANCE=NOT_RUN`. Tidak ada migrasi database/dependency/restart/secret/production.

## Langkah penerimaan di laptop

Setelah bridge source-only telah menerapkan revisi pilot ini, dari root Laravel lokal jalankan `php artisan test --filter='NormativeVersionTimelineTest|NormativeRootTest'`, `php artisan route:list`, lalu satu sesi authenticated Regulatory → R70L. Pastikan versi kedua regulasi tetap `AMENDMENT_CHAIN_INCOMPLETE`.

## Capability berikutnya — R70N

Lakukan **amendment/repeal census yang sungguh sumber-terverifikasi** pada kedua aturan dari JDIH, termasuk versi historis dan ketentuan peralihan; hubungkan `regulation_version_id` sampai norma pasal. Secara paralel bisa mencari satu baris berkode dari dokumen perencanaan/anggaran resmi TA2026; **jangan menyatakan alignment** sebelum keduanya diterima. Untuk komersialisasi perlu layanan pencarian amandemen multi-Pemda dan admission snapshot otomatis, bukan hanya mesin kalkulasi ini.
