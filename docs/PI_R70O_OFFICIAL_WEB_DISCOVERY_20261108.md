# PI R70O — Penelusuran regulasi resmi (source-only pilot)

**Status 8 November 2026:** Implementasi sumber di GitHub pilot; **belum diinstal pada Laravel Windows**, PHP feature test **belum dijalankan**. Tidak ada restart, migrasi database, Composer/npm, merge atau akses produksi.

## Kapabilitas
- Form **Cari peraturan resmi dari internet** di Regulatory Intelligence Laravel, sumber awal JDIH BPK.
- Endpoint terscope sesi dan tenant: `GET /api/qa-regulatory-r70o/discover?q=...&source=jdih_bpk`, throttle 10/menit.
- Laravel HTTP hanya memanggil alamat tetap `https://peraturan.bpk.go.id/Search`; input tidak bisa memilih URL atau host. Batas koneksi 3 detik dan respons 8 detik, tanpa redirect, konten HTML dibatasi 5 MiB.
- Ekstraksi kandidat dari tautan resmi `/Details/ID/slug`, deduplikasi, filter relevansi kata kunci, maksimum 15. Judul berkaitan dengan perubahan/pencabutan mendapat flag **kandidat** saja.
- Kesalahan provider menghasilkan HTTP 503 `OFFICIAL_SOURCE_UNAVAILABLE`, bukan hasil simulasi. Hasil kosong **bukan** pernyataan tidak ada peraturan.
- Kontrak respons membekukan `amendment_census=NOT_EXHAUSTIVELY_VERIFIED`, `legal_status=NOT_VERIFIED`, `normative_alignment=ABSTAIN`, `numeric_comparison=BLOCKED`, `production_admission=DENIED`.
- Sumber tambahan dapat dikembangkan menjadi adapter yang divalidasi satu per satu (JDIH Kemenkeu/peraturan.go.id); pada R70O sumber tersebut **belum diaktifkan** dan tidak boleh dinyatakan sudah tertelusuri.

## Catatan penilaian
Kode **mampu mengirim permintaan pencarian sungguhan** ketika Laravel lokal memiliki akses ke JDIH BPK. Struktur HTML, kueri dan pemblokiran anti-bot dari penyedia belum teruji secara langsung di lingkungan ini. Karena itu `LIVE_PROVIDER_ACCEPTANCE=NOT_RUN` hingga operator memverifikasi respon situs di Windows. Fitur ini belum mengumpulkan seluruh revisi dan belum boleh mempengaruhi status hukum R70L/R70N.

## Perintah pengujian setelah sensus legal sesuai mandat pengguna
1. Review source diff dan pastikan daftar aturan induk/revisi dari situs resmi dilengkapi hingga audit sumber dinyatakan layak.
2. Secara **manual** jalankan bridge satu kali jika telah memenuhi prasyarat; jangan mengaktifkan Scheduled Task otomatis yang sebelumnya dinonaktifkan.
3. Di Laravel lokal: `php artisan test --filter=RegulatoryDiscoveryTest`.
4. Buka `/regulatory-laravel-r70g.html` dengan sesi tenant sah; cari "Permendagri 86 Tahun 2017", periksa URL detail resmi, hasil kosong/gagal akses, dan status **ABSTAIN**. Jangan membuka gerbang produksi atau nominal anggaran.

## Landasan publik
- JDIH BPK: https://peraturan.bpk.go.id/Search
- Dokumen petunjuk pencarian JDIH Kemenkeu (sumber pembanding calon adapter): https://jdih.kemenkeu.go.id/bantuan

