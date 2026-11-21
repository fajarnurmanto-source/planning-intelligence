# PI R70O — Penelusuran regulasi resmi (source-only pilot)

**Status 8 November 2026 (pembaruan):** R70O terpasang pada Laravel Windows dengan `PI_BRIDGE_APPLY=PASS`, commit `2b1c093eaf6327f9e8031eb2822f4700d9600008`, 8 berkas, backup `PI_GITHUB_BRIDGE_BACKUPS/20261108_210419`. Edge lokal membuktikan formulir tampil dan pencarian publik ke JDIH BPK menghasilkan 11 kandidat, tetapi mayoritas salah jenis dokumen. **R70P penyaringan identitas presisi sudah dikembangkan di GitHub pilot dan BELUM diinstal/diuji di Laravel Windows.** PHP feature test **belum dijalankan**. Tidak ada restart, migrasi database, Composer/npm, merge atau akses produksi.

## Kapabilitas
- Form **Cari peraturan resmi dari internet** di Regulatory Intelligence Laravel, sumber awal JDIH BPK.
- Endpoint terscope sesi dan tenant: `GET /api/qa-regulatory-r70o/discover?q=...&source=jdih_bpk`, throttle 10/menit.
- Laravel HTTP hanya memanggil alamat tetap `https://peraturan.bpk.go.id/Search`; input tidak bisa memilih URL atau host. Batas koneksi 3 detik dan respons 8 detik, tanpa redirect, konten HTML dibatasi 5 MiB.
- Ekstraksi kandidat dari tautan resmi `/Details/ID/slug`, deduplikasi, filter relevansi kata kunci, maksimum 15. Judul berkaitan dengan perubahan/pencabutan mendapat flag **kandidat** saja.
- Kesalahan provider menghasilkan HTTP 503 `OFFICIAL_SOURCE_UNAVAILABLE`, bukan hasil simulasi. Hasil kosong **bukan** pernyataan tidak ada peraturan.
- Kontrak respons membekukan `amendment_census=NOT_EXHAUSTIVELY_VERIFIED`, `legal_status=NOT_VERIFIED`, `normative_alignment=ABSTAIN`, `numeric_comparison=BLOCKED`, `production_admission=DENIED`.
- Sumber tambahan dapat dikembangkan menjadi adapter yang divalidasi satu per satu (JDIH Kemenkeu/peraturan.go.id); pada R70O sumber tersebut **belum diaktifkan** dan tidak boleh dinyatakan sudah tertelusuri.

## Penerimaan eksplorasi provider (8 Nov, Edge lokal)

- Browser Edge yang tersambung berhasil membuka situs JDIH BPK secara langsung dan mengirim formulir pencarian **Permendagri 86 Tahun 2017**. Halaman resmi mengembalikan jalur `/Search?keywords=...&tentang=&nomor=` dan tajuk **PENCARIAN PERATURAN**. Parameter lama `search=` justru menampilkan **PERATURAN TERBARU** (tanpa pencarian); bug tersebut diperbaiki dalam R70O dan ditambahkan pengaman fail-closed.
- Pencarian bawaan BPK bersifat luas hingga isi berkas; contoh di atas menampilkan sangat banyak hasil. Oleh karena itu R70O belum menyatakan sensus lengkap, belum melakukan penjelajahan seluruh halaman, dan mungkin menghasilkan nol kandidat pada halaman pertama jika instrumen spesifik tidak termasuk hasil awal.
- Edge lokal berhasil mengakses aplikasi Laravel `127.0.0.1:8891/regulatory-laravel-r70g.html`, form R70O dan sesi tenant sah. Permintaan sungguhan `Permendagri 86 Tahun 2017` ke endpoint Laravel menghasilkan **11 kandidat** dan link resmi (LIVE_PROVIDER_ACCEPTANCE=PASS untuk konektivitas/kontrak sumber; **RETRIEVAL_PRECISION=FAIL** untuk identitas dokumen). PHP feature test belum dijalankan.

## Catatan penilaian
Kode **telah mengirim permintaan pencarian sungguhan** dari Laravel lokal ke JDIH BPK. Kontrak tautan resmi dan pengaman penilaian hukum terlihat aktif. Tetapi pencarian kata kunci bawaan menampilkan hasil lintas jenis peraturan; pencarian R70O **tidak membuktikan** identitas peraturan yang dicari, apalagi revisinya. Fitur ini belum mengumpulkan seluruh revisi dan belum boleh mempengaruhi status hukum R70L/R70N.

## Perintah pengujian setelah sensus legal sesuai mandat pengguna
1. Review source diff dan pastikan daftar aturan induk/revisi dari situs resmi dilengkapi hingga audit sumber dinyatakan layak.
2. Secara **manual** jalankan bridge satu kali jika telah memenuhi prasyarat; jangan mengaktifkan Scheduled Task otomatis yang sebelumnya dinonaktifkan.
3. Di Laravel lokal: `php artisan test --filter=RegulatoryDiscoveryTest`.
4. Buka `/regulatory-laravel-r70g.html` dengan sesi tenant sah; cari "Permendagri 86 Tahun 2017", periksa URL detail resmi, hasil kosong/gagal akses, dan status **ABSTAIN**. Jangan membuka gerbang produksi atau nominal anggaran.

## Landasan publik
- JDIH BPK: https://peraturan.bpk.go.id/Search
- Dokumen petunjuk pencarian JDIH Kemenkeu (sumber pembanding calon adapter): https://jdih.kemenkeu.go.id/bantuan


## R70P — Penyaringan identitas katalog resmi (belum terinstal lokal)

- **Temuan E2E R70O:** kata kunci `Permendagri 86 Tahun 2017` menghasilkan 11 kartu kandidat, termasuk banyak Perbup 86/2017 dan Perpres 86/2017. Penyebab: pencocokan bebas hanya membutuhkan dua token (86, 2017), bukan jenis peraturan.
- **Pembuktian langsung melalui formulir resmi JDIH BPK:** Advanced Search dengan `keywords=`, `tentang=`, `nomor=86`, `tahun=2017`, `jenis=40` (opsi **Peraturan Menteri Dalam Negeri**) menghasilkan tepat **1** katalog: [Permendagri 86/2017](https://peraturan.bpk.go.id/Details/311927/permendagri-no-86-tahun-2017).
- Adapter Laravel kini mengenali input eksak `Permendagri 86 Tahun 2017`, `Permendagri No. 86 Tahun 2017`, atau `Peraturan Menteri Dalam Negeri Nomor 86 Tahun 2017`, lalu mengirim parameter Advanced Search resmi tanpa URL arbitrer.
- Semua hasil pada mode terstruktur harus memiliki slug detail resmi `permendagri-no-86-tahun-2017`. Jika tidak ada slug yang cocok, hasil kosong dengan petunjuk mencari langsung di JDIH; hasil kosong **bukan** bukti dokumen tak ada.
- Mode `KEYWORD_BROAD` dipertahankan untuk pertanyaan umum. Mode `STRUCTURED_IDENTITY` hanya menerapkan batas identitas katalog; `LEGAL_STATUS=NOT_VERIFIED`, `AMENDMENT_CHAIN_INCOMPLETE`, `ABSTAIN`, `NUMERIC_COMPARISON=BLOCKED`, `PRODUCTION_ADMISSION=DENIED` tidak berubah.
- Laravel feature tests diperluas untuk menolak Perbup yang nomornya sama dan mengonfirmasi parameter jenis/tahun/nomor, serta tetap menjalankan kata kunci luas. **PHP TEST=NOT_RUN; R70P LOCAL_APPLY=NOT_RUN; LIVE_R70P_ACCEPTANCE=NOT_RUN.**
- Sumber resmi: https://peraturan.bpk.go.id/Search (Advanced Search); tanggal akses 8 Nov 2026. Ini belum mencakup sensus revisi/ketentuan peralihan secara menyeluruh.

**Langkah berikut:** source-only bridge manual, `php artisan test --filter=RegulatoryDiscoveryTest` di lingkungan lokal, dan browser E2E permintaan eksak setelah pemasangan. Task bridge Windows terjadwal tetap nonaktif.
