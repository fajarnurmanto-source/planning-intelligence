# PI R70N — Official Regulation Amendment Census (bounded observation)

**Tanggal observasi:** 8 November 2026 (WIB). **Roadmap:** PI-MR-20261108-V3 / NORMATIVE-ROOT-006. **Status:** SOURCE-ONLY PARTIAL CENSUS; LEGAL_ALIGNMENT=ABSTAIN; LOCAL_APPLY=NOT_STARTED.

## Keputusan dan ruang lingkup

Sensus metadata awal ini hanya mencakup dua instrumen yang sudah disertakan pada read model R70L/R70M, **bukan semua UU/PP/Perpres/Permen/PMK beserta perubahannya**. Ketiadaan daftar perubahan di suatu portal JDIH **tidak menjadi bukti tidak ada perubahan**. Tidak ada daftar revisi fiktif, tidak ada pengesahan hukum otomatis. Tahap pemasangan/pengujian lokal ditunda sampai sensus wajib telah memadai sesuai perintah pemilik.

## Observasi sumber primer pemerintah

| Instrumen | Sumber resmi | Fakta yang dapat diikat | Yang belum dapat dibuktikan |
| --- | --- | --- | --- |
| Permendagri 86/2017 | [JDIH BPK / Details/311927](https://peraturan.bpk.go.id/Details/311927/permendagri-no-86-tahun-2017) | Status `Berlaku`, ditetapkan 18-09-2017, diundangkan dan berlaku **25-09-2017**. Bagian *Status Peraturan* mencatat **mencabut Permendagri 54/2010**. | Rekaman metadata yang diperiksa tidak menampilkan perubahan langsung terhadap 86/2017; ini bukan sertifikasi kelengkapan seluruh amendemen atau putusan pengujian. |
| Permendagri 10/2025 | [JDIH BPK / Details/321470](https://peraturan.bpk.go.id/Details/321470/permendagri-no-10-tahun-2025) | Status `Berlaku`, ditetapkan/diundangkan/berlaku **23-06-2025**. | Bagian *Status Peraturan* bertuliskan **Belum Tersedia**: tidak boleh diterjemahkan menjadi `tidak ada revisi`. |
| Pendahulu 54/2010 | [Naskah resmi Berita Negara 2010 Nomor 517](https://peraturan.go.id/files/bn517-2010.pdf) dan [metadata 86/2017](https://peraturan.bpk.go.id/Details/311927/permendagri-no-86-tahun-2017) | Naskah aturan lama teridentifikasi, kemudian **dicabut oleh 86/2017**. | Ini hubungan `REVOKES_PREDECESSOR`, **bukan** event amendemen atas 86/2017. Harus dipisahkan dari rantai versi instrumen yang sama. |

Sumber teks utama 86/2017: https://peraturan.go.id/files/bn1312-2017bt.pdf . Sumber teks utama 10/2025: https://peraturan.go.id/files/permendagri-no-10-tahun-2025.pdf . Metadata langsung belum mempunyai checksum snapshot byte sumber yang dihitung dan dicocokkan.

## Cakupan normative-root yang masih harus disensus sebelum admisi

Batang tubuh *Mengingat* Permendagri 10/2025 mengidentifikasi referensi seperti UU 39/2008 (perubahan UU 61/2024), UU 23/2014 (perubahan yang disebut pada tanggal penyusunan naskah), UU 59/2024, Perpres 149/2024, Perpres 12/2025, Permendagri 86/2017, 90/2019, dan 9/2025. Ini **antrian penelusuran berbasis sumber**, bukan pernyataan semua pasal dan setiap instrumen otomatis berlaku bagi semua jenis kasus/OPD/tahun.

Untuk keputusan perencanaan maupun anggaran perlu menambah hanya rujukan yang **materinya memang relevan**: UU 25/2004, UU 12/2011 beserta perubahan dan Pasal 7/8, PP dan aturan pelaksana, PMK tertentu menurut jenis dana dan tahun fiskal, peraturan Kementerian PPN/Bappenas serta peraturan sektoral. Masing-masing wajib memiliki matriks bentuk, pembentuk, kewenangan/delegasi, pasal, objek, yurisdiksi, tanggal berlaku, versi, penggantian/pencabutan, ketentuan peralihan, dan bukti naskah resmi; jika belum, `ROOT_UNVERIFIED` atau `AMENDMENT_CHAIN_INCOMPLETE`.

## Perubahan source-only pada R70N

1. Dataset `laravel/resources/pi/normative-root-r70l.json`: memperbaiki tanggal efektif Permendagri 86/2017 ke **2017-09-25**, menambah `census_snapshot` yang memuat dua observasi resmi dan relasi pencabutan pendahulu.
2. `NormativeRootController`: validasi keras setiap fakta sensus yang diterima, menolak perubahan status kelengkapan/penghilangan caveat dan mengekspos census ke API yang sudah auth+tenant.
3. Panel `normative-root-r70l.js`: menampilkan sensus parsial dan tautan historis 54/2010; menahan respons yang memalsukan `OFFICIAL_CENSUS_VERIFIED_COMPLETE`.
4. `NormativeRootTest`: memeriksa tanggal, relasi resmi, blokir klaim completeness palsu. Pengujian PHP **ditulis tetapi belum dieksekusi** dalam cloud tanpa PHP CLI.
5. Mocked functional UI smoke: **PASS** (tanggal 86/2017, histori repeal, caveat, ABSTAIN; forged-complete and HTTP503 fail-closed).

Tidak dilakukan migrasi database, instal paket, build ulang, restart, unggah PDF, pengeluaran API, perubahan default branch maupun produksi.

## Blocker kelengkapan dan urutan pelaksanaan

1. **Selesaikan sensus hubungan resmi dan seluruh perubahan relevan**, termasuk JDIH pembentuk, katalog nasional, naskah perubah/pencabut, ketentuan peralihan, pengecualian, keputusan pengujian yang relevan, tanggal efektif dan cakupan pasal. Kesimpulan `no known amendment` adalah hasil pencarian, **bukan** `complete amendment census`.
2. Ambil snapshot byte PDF resmi yang sah di lingkungan yang mempunyai akses, hitung SHA-256, identifikasi pasal/halaman terkait dan bukti katalog yang direkonsiliasi. Jangan mengarang hash.
3. Baru sesudah sensus siap-admit, terapkan branch pilot melalui bridge source-only di laptop; jalankan PHPUnit/route checks dan satu sesi UI autentik pada localhost:8891 dengan rekaman status, tanpa membuka gerbang produksi.

**Gerbang saat ini:** `REVIEW_REQUIRED`, `LEGAL_HARMONIZATION=NOT_ASSESSED`, `SUBSTANTIVE_ALIGNMENT=NOT_ASSESSED`, `NUMERIC_COMPARISON=BLOCKED`, `PRODUCTION_ADMISSION=DENIED`.
