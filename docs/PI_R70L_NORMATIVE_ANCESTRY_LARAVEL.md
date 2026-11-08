# PI R70L — Norma Induk Nasional → RKPD 2026 (Implementasi Kandidat Laravel)

**Tanggal:** 8 November 2026 (WIB) · **Master roadmap:** PI-MR-20261108-V3 §4.1A (`NORMATIVE-ROOT-006`)  
**Milestone:** `R70L` · **Status:** `CODE_COMMITTED_SOURCE_ONLY`, `LOCAL_APPLY=UNCONFIRMED`, `FUNCTIONAL_UI_FAKE_DOM=PASS`, `PHP_TEST=NOT_RUN`, `LIVE_ACCEPTANCE=NOT_RUN`.

## Hasil produk

Satu endpoint tenant-authenticated read-only: `GET /api/qa-regulatory-r70l/normative-root`, disajikan pada layar Regulatory Laravel sebagai panel **04A — National Normative Ancestry**.

Pengguna memperoleh:
- 2 rujukan peraturan **resmi** dengan tautan JDIH BPK dan PDF peraturan.go.id.
- 6 ketentuan bertipe dan *locator* pasal/pasal ayat/konsiderans; ringkasan bukan kutipan PDF yang sudah di-hash.
- Bukti **rujukan delegasi tekstual**: Permendagri 86/2017 Pasal 76, lalu konsiderans *Menimbang huruf b* Permendagri 10/2025 secara eksplisit merujuk Pasal 76 itu.
- Penghubung relevansi **RKPD Kota Yogyakarta 2026**: Permendagri 10/2025 Pasal 2(3), Pasal 3(5) dan Pasal 12(2). Pasal 2(6) khusus **Provinsi DIY**, tidak otomatis menjadi pengecualian Kota Yogyakarta.
- Pembedaan **normative source reference confirmed** dan **substantive alignment not assessed**; ketidaklengkapan sensus perubahan menjadikan hasil akhir `ABSTAIN`, bukan penilaian legal/angka.

### Evidence locator yang dirujuk

| Dokumen sumber primer | Locator | Fakta terikat sumber |
| --- | --- | --- |
| Permendagri 86/2017, BN 2017 No. 1312 | Pasal 76, cetak -40- | Pedoman penyusunan RKPD ditetapkan dengan Peraturan Menteri |
| Permendagri 10/2025, BN 2025 No. 435 | Menimbang b | Menyatakan pelaksanaan Pasal 76 Permendagri 86/2017 |
| Permendagri 10/2025 | Pasal 2(3), cetak -4- | RKPD 2026 disusun simultan/terkoordinasi dengan RPJMD 2025–2029 |
| Permendagri 10/2025 | Pasal 3(5), cetak -5- | RKPD kabupaten/kota 2026 berpedoman pada RKP, PSN dan RKPD provinsi tahun 2026 |
| Permendagri 10/2025 | Pasal 2(6), cetak -5- | Ketentuan khusus secara eksplisit menyebut Provinsi DIY |
| Permendagri 10/2025 | Pasal 12(2) a–c, cetak -8- | RKPD kabupaten/kota 2026 menjadi pedoman KUA–PPAS, bahan APBD dan evaluasi |

**Sumber primer:**
- Permendagri 86/2017: https://peraturan.go.id/files/bn1312-2017bt.pdf
- Permendagri 10/2025: https://peraturan.go.id/files/permendagri-no-10-tahun-2025.pdf
- Metadata 10/2025: https://peraturan.bpk.go.id/Details/321470/permendagri-no-10-tahun-2025
- RKPD Yogyakarta 2026 Perwali 34/2025: https://jdih.jogjakota.go.id/asset/naskah/2025pw3405034.pdf

`Source confirmed` hanya berarti identitas ketentuan dan rujukan **ditemukan dalam teks sumber resmi**; bukan kepastian tidak ada amandemen/aturan baru. Sumber PDF daring belum ditangkap sebagai snapshot dengan SHA-256, dan sensus perubahan/pencabutan/ketentuan peralihan **belum lengkap**.

## File berubah

- `laravel/resources/pi/normative-root-r70l.json`
- `laravel/app/Http/Controllers/Api/NormativeRootController.php`
- `laravel/config/pi.php` dan `laravel/routes/web.php`
- `laravel/public/normative-root-r70l.js`
- `laravel/public/regulatory-laravel-r70g.html`
- `laravel/public/planning-lineage-r70j.css`
- `laravel/tests/Feature/NormativeRootTest.php`

Tidak ada perubahan database/migrasi, dependency, setup, secrets, deployment atau approval.

## Focused check

- **UI functional fake-DOM smoke: PASS**: 2 dokumen, 6 ketentuan, delegasi, konteks pengecualian DIY dan status ABSTAIN tampil pada respons valid.
- **Fail-closed UI smoke: PASS**: pemalsuan gerbang amendemen ditolak; HTTP 503 menahan panel; 401 mengarahkan ke login.
- **PHP feature tests: COMMITTED_NOT_RUN**: pengujian auth 401, tenant 403, respons normal, versi belum terverifikasi, pemalsuan URL/ketentuan/klaim pengecualian DIY. PHP CLI tidak tersedia di lingkungan cloud ini.
- **LOCAL_APPLY dan authenticated browser: UNCONFIRMED**. Repo GitHub bukan bukti bridge telah menyalin source atau halaman diterima pada Windows port 8891.

## Gerbang

`NORMATIVE_ROOT_TRACE=PARTIAL_PRIMARY_PROVISIONS_FOUND`  
`REGULATORY_VERSION=AMENDMENT_CHAIN_INCOMPLETE`  
`PLANNING_CONSTRAINT_CROSSWALK=MAPPING_CANDIDATE`  
`LEGAL_HARMONIZATION=NOT_ASSESSED`  
`SUBSTANTIVE_ALIGNMENT=NOT_ASSESSED`  
`NUMERIC_COMPARISON=BLOCKED`  
`REVIEW_REQUIRED` · `PRODUCTION_ADMISSION=DENIED`

## Langkah berikutnya

1. Verifikasi tugas bridge Windows menerapkan seluruh file allowlisted; `php -l`, `php artisan route:list`, `php artisan test --filter=NormativeRootTest` dan satu sesi login→Regulatory→R70L dengan dua link PDF.
2. Sensus perubahan/pencabutan dan ketentuan peralihan bagi kedua Permendagri hingga *as-of date* dengan bukti metadata/ketentuan resmi. Periksa juga sumber UU 25/2004, UU 59/2024, Perpres RPJMN dan RKP 2026 yang relevan; jangan menganggap daftar induk lengkap.
3. Tambahkan satu **baris 2026 resmi berkode** dari Renja/DPA/RKPD, OPD, tahun, versi, halaman/tabel dan sumber tervalidasi. Relasi pasal→program tetap `ABSTAIN` sampai spesifikasi dan *source snapshot* diverifikasi.
