# PI-SINGLE-RUNTIME-001 — Mandat satu aplikasi Windows (22 November 2026)

**Owner instruction:** PI adalah satu aplikasi yang dapat dibuka layaknya aplikasi biasa: satu instalasi Laravel, satu alamat utama, satu identity/session/data authority, dan satu launcher. Dilarang menambah port/server per revisi R-series atau mewajibkan pengguna mengetik PowerShell / artisan serve setiap kali membuka aplikasi.

**Status:** MANDATORY / product-runtime engineering. Mandat bukan klaim main sudah terinstal.

## 1. Rekonsiliasi bukti

Output operator 22 Nov: R88 localhost:8822 HTTP 200, PHP Herd Lite PID 19684, HEAD 65d7a76b88fc, SESSION_COOKIE pi_r88_qa_session; R89 localhost:8823 HTTP 200, PHP Herd Lite PID 10760, HEAD f5edfb951cdd, SESSION_COOKIE tanpa konfigurasi eksplisit. Main 8820 NOT_RUNNING. Dua app tersebut Laravel 13.40.0 SQLite, dengan source kerja terpisah. R89 QA installer PASS 23/23 tests, 198 assertions; cloud provider live NOT_RUN. Browser R89 menampilkan PI Superior, R85–R89.

State Capsule rekonsiliasi 18 November mencatat 8820 sebagai endpoint historis R84 ketika saat itu HTTP 200. Saat ini 8820 belum berjalan. Dokumentasi R88–89 menetapkan 8822–8823 sebagai QA; bukan alamat produk.

## 2. Kontrak wajib

1. **ONE_PRODUCT:** PI Superior + Evidence/Regulatory/Performance/Fiscal/Evaluation Specialized berada dalam satu paket aplikasi Laravel. R88 dan R89 adalah modul, bukan instalasi produk baru.
2. **ONE_ORIGIN:** satu alamat runtime desktop utama http://127.0.0.1:8820. Port QA hanya sementara untuk acceptance; tidak dijadikan launcher produk.
3. **ONE_RELEASE:** sumber terintegrasi berasal dari R89 Cloud BYOK stacked on R88/R88.1/R89 drafts. PR #74–#77 tidak otomatis merged atau menggantikan main.
4. **ONE_IDENTITY:** pertahankan database akun dan tenant yang benar, APP_KEY main, serta SESSION_COOKIE eksplisit dan stabil; cookies host 127.0.0.1 tidak dipisahkan per port. Vault provider yang terenkripsi tidak boleh dipindah ke kunci aplikasi baru tanpa prosedur.
5. **PRESERVE_DATA:** data utama, source PDF/evidence, backup, env/secret, ledger, storage, reviewer decisions tidak boleh direset, ditimpa, atau dimigrasi diam-diam. DB QA clone bukan database main. Cutover hanya dengan backup/drift/rollback dan acceptance.
6. **SINGLE_RUNNER:** shortcut Windows membuka satu jendela aplikasi, menjalankan server loopback bila perlu, memeriksa pemilik port, menolak foreign listener, dan tidak memerlukan langkah manual menyalakan PHP. Pemasangan shortcut opt-in setelah source release benar.
7. **PRODUCTION_GATE:** PHP built-in server dipakai hanya untuk candidate QA desktop lokal loopback; produk produksi/komersial memerlukan managed PHP web server, TLS, backup/recovery, lifecycle supervision, restart/logout acceptance.
8. **CLOUD_BYOK:** provider DeepSeek/OpenAI/Gemini/Groq/OpenRouter menggunakan API cloud BYOK tenant-isolated encrypted. Tidak perlu GPU lokal, egress harus user-triggered setelah preview SHA-256 serta approval privacy/biaya. AI bukan legal authority atau reviewer.

## 3. Implementasi kandidat

- scripts/pi-windows-app.ps1: launcher PowerShell 5.1 Open/Start/Status/Stop/InstallShortcut; memulai hanya satu server pada 127.0.0.1:8820, port owner check, health GET setup-status dan index, mutex, shortcut Desktop + Start Menu tanpa overwrite, Edge app mode / default browser fallback.
- .github/workflows/pi-single-runtime-win.yml: CI Windows PS5.1 syntax, siklus launcher fixture start/reuse/stop, no-mutation guards. Fixture bukan akun atau DB main.
- Tidak ada perubahan terhadap .env, APP_KEY, DB, migrations, server R88/R89 yang berjalan, PID, atau branch main lokal.
- Batas hukum, numeric, reviewer dan production tetap ABSTAIN / BLOCKED / DENIED.

## 4. Pelaksanaan bertahap

A. Source CI: GitHub Draft PR stacked #77, no merge.
B. Read-only main preflight: cek source HEAD/status, backup & hashes, akun/tenant, database dan APP_KEY main, sesi, bukti, R88/R89 runtime. Dilarang membuka secret.
C. Release staging: kandidat satu Laravel, diuji pada salinan database main bukan clone QA R89. Acceptance live login, R85–R89, cloud preview, review, ekspor, responsive 1440/390, evidence/tenant guards.
D. Guarded cutover: hanya setelah source accepted, pemilik mengizinkan staging/cutover; pertahankan DB, APP_KEY, storage. Sesuaikan APP_URL dan cookie dalam rencana konfigurasi yang direview. Jalankan 8820 hanya setelah preflight, verifikasi landing/login/health. Stop 8822/8823 hanya setelah approval.
E. UX: pasang ikon Planning Intelligence pada Desktop + Start Menu, uji cold boot/logon, reopen, recovery, state persistence dan dokumentasi final; managed service runtime bila dibutuhkan. Jangan menambah port.

## 5. Status kickoff

PI_SINGLE_RUNTIME_MANDATE=ACTIVE
PI_RELEASE_SOURCE=R89_STACKED_CANDIDATE
PI_ONE_CLICK_LAUNCHER=SOURCE_CANDIDATE
PI_8820_MAIN_RUNTIME=NOT_RUNNING_AS_LAST_OBSERVED
PI_8822_QA=RUNNING_AS_LAST_OBSERVED
PI_8823_QA=RUNNING_AS_LAST_OBSERVED
PI_MAIN_DB=UNTOUCHED
PI_MAIN_APP_KEY=UNTOUCHED
PI_WINDOWS_INSTALL=NOT_RUN
PI_RESTART_ACCEPTANCE=NOT_RUN
PI_R89_LIVE_PROVIDER=NOT_RUN
PI_MERGE=NOT_APPROVED
PRODUCTION_ADMISSION=DENIED

Never claim production installation or Windows restart PASS without observed owner-machine evidence.
