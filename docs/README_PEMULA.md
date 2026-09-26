# Planning & Decision Intelligence V0.0 — Panduan Pemula Windows 11

## Tujuan tahap ini
V0.0 belum memakai AI. Tujuan kita hanya membuktikan bahwa aplikasi lokal, database, penyimpanan dokumen, audit ledger, backup, dan restart bekerja dengan benar.

## Konfigurasi laptop yang ditargetkan
- Windows 11
- RAM 16 GB
- CPU Intel Core i5 kelas 10th Gen atau setara
- Proyek dan seluruh data ditempatkan di **Drive D** yang masih lega.
- GPU tidak dipakai pada V0.0.

## Sebelum mulai
1. Pastikan virtualisasi aktif: `Ctrl+Shift+Esc` → Performance → CPU → lihat `Virtualization: Enabled`.
2. Install **Docker Desktop** dan gunakan WSL 2 bila diminta.
3. Install **Visual Studio Code** (untuk melihat file; tidak wajib untuk menjalankan aplikasi).
4. Install **Git for Windows** (untuk riwayat source code; sangat disarankan).
5. Restart Windows setelah instalasi Docker bila diminta.

## Lokasi folder yang disarankan
Ekstrak package ini menjadi:

`D:\PlanningIntelligence\planning-intelligence-v0.0\`

Jangan menaruh project di Desktop/Downloads untuk pemakaian jangka panjang.

## Menjalankan untuk pertama kali
1. Pastikan Docker Desktop sudah terbuka dan menunjukkan Engine running.
2. Klik dua kali `00_SETUP.bat`.
3. Klik dua kali `01_START.bat`.
4. Tunggu build pertama. Bisa beberapa menit karena Docker mengunduh image.
5. Browser otomatis membuka `http://localhost:8080`.

## Uji penerimaan V0.0
Lakukan urutan berikut:
1. Projects → buat `Kota Uji`, periode `RPJMD 2025–2029`.
2. Documents → upload satu PDF/DOCX/XLSX kecil. Pastikan muncul SHA-256.
3. Regulations → masukkan satu regulasi dan, bila ada, upload PDF-nya.
4. Audits → buat `Audit Fondasi Pertama`.
5. Activity → pastikan PROJECT_CREATED, DOCUMENT_UPLOADED, REGULATION_REGISTERED, AUDIT_CREATED tercatat.
6. Klik `03_BACKUP.bat`. Pastikan file ZIP dan SHA256 muncul di folder `backups`.
7. Klik `02_STOP.bat`, lalu `01_START.bat` kembali. Pastikan data masih ada.

Jika semua langkah berhasil, Foundation V0.0 dinyatakan **PASS**.

## Tombol/skrip
- `00_SETUP.bat` — menyiapkan folder dan `.env`.
- `01_START.bat` — build + menyalakan aplikasi + membuka browser.
- `02_STOP.bat` — menghentikan container tanpa menghapus data.
- `03_BACKUP.bat` — database dump + dokumen + konfigurasi + checksum.
- `04_RESTORE.bat` — restore manual dari ZIP; gunakan hanya ketika diuji bersama.
- `05_STATUS.bat` — melihat service yang sedang hidup.

## Jangan lakukan dulu
- Jangan mengubah `.env` kecuali diarahkan.
- Jangan menghapus folder `data/postgres`.
- Jangan mengaktifkan OpenAI API/local model dulu.
- Jangan menginput seluruh dokumen Pemda; cukup golden dataset kecil.

## Bila terjadi error
Ambil screenshot penuh jendela error dan kirimkan. Jangan mencoba menghapus database atau install ulang tanpa mengetahui penyebabnya.
