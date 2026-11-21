# PLANNING INTELLIGENCE — MASTER PRODUCT ROADMAP TERPADU

**ID:** PI-MR-20261108-V3 · **Keputusan pemilik:** 8 November 2026 (WIB); penegasan normative-root/amendment-first: 8 November 2026  
**Kedudukan:** **RUJUKAN KANONIS PENGEMBANGAN CABANG PILOT GITHUB** `pilot/pi-laravel-github-bridge-20261108`.  
**Perlindungan:** dokumen asli Library dan berkas Windows yang belum ditemukan **tidak diubah**; cabang default, sistem produksi, database pengguna, data QA historis dan lingkungan lokal terlindungi tetap memiliki gerbangnya sendiri.  
**North star:** satu PI Superior App yang mengorkestrasi lima Specialized Apps—Evidence, Performance, Regulatory, Fiscal, Evaluation—untuk menghasilkan rekomendasi perencanaan/penganggaran terikat sumber, dapat ditelusuri lintas jenjang, diuji pada data nyata lintas Pemda, ditelaah manusia, dan layak dikomersialisasi setelah bukti serta gerbang operasional memadai.

> **Aturan pembacaan:** ini roadmap *apa yang wajib dicapai*, bukan pernyataan semua fiturnya telah ada. Status `IMPLEMENTED`, `LOCAL_APPLIED`, `LIVE_ACCEPTED`, `PRODUCTION_ADMITTED` hanya boleh diberi berdasarkan bukti masing-masing. Label `PASS` QA historis bukan bukti otomatis kesiapan produk.

## 1. Konsolidasi otoritas dan perubahan mandat

| Sumber keputusan | Yang dipertahankan/diintegrasikan | Kedudukan setelah 8 Nov |
| --- | --- | --- |
| `02_PI_MASTER_ROADMAP_20261025.md` (`PI-MR-20261025-V1`) | North star, M0–M8, evidence → graph → coherence → reviewer → report, kualitas terukur dan jalur komersial | Baseline historis; urutan G6/Python-first tidak lagi mengikat ketika bertentangan dengan mandat terbaru |
| `PI_SUPERIOR_SPECIALIZED_EXECUTION_MAP_20261029.md` | Arsitektur 1+5, sembilan kontrak, MLCE lintas engine, gelombang W0–W5, lapisan C1–C3 | Peta produk dan ketergantungan aktif |
| `PI_MASTER_ROADMAP_REGULATORY_MANDATE_20261102.md` | `PI-MRM-REG-001`, `MULTIREGION-002`, `DELIVERY-003` | Wajib lintas aplikasi dan sebagai gerbang kesimpulan |
| `PI_MASTER_ROADMAP_HIERARCHICAL_TAXONOMY_MANDATE_20261102.md` | `DELIVERY-004`, R62, R63, `HIERARCHY-TAXONOMY-005`, R64 | Wajib untuk kemampuan baru, bukan klaim R62–R64 sudah tuntas di Laravel |
| Mandat pemilik 4–8 November dan kerja GitHub–WSL | Laravel-first, reuse OSS, data nyata, UI/UX yang siap pengguna, zero-spend AI, otomatisasi milestone berbatas, bridge source-only, R70H dan penanganan PDF 404 | Keputusan implementasi terbaru dan pembatas operasional |
| `docs/PI_ROADMAP_LIBRARY_RECONCILIATION_20261108.md` | Daftar sumber Library, riwayat kesenjangan roadmap lokal, status bridge | **Arsip rekonsiliasi sementara**, digantikan dokumen ini untuk arah cabang pilot |
| Penegasan pemilik 8 November tentang peraturan induk | UU, PP, Perpres, Permendagri, PMK, Peraturan Menteri PPN/Bappenas dan norma sektoral relevan, revisi/pencabutan/peralihan harus diikuti sebelum menilai RPJPD/RPJMD/RKPD/Renja/APBD | Gerbang wajib `NORMATIVE_ROOT_TRACE`; RPJPD/RPJMD bukan norma induk tertinggi; lihat §4.1A |

**Aturan konflik:** instruksi eksplisit pemilik yang lebih baru dan bukti source/runtime yang terverifikasi mengalahkan estimasi/prasyarat teknis lama; tidak menghapus sejarah. Perubahan mandat yang membawa risiko produksi, hak data, lisensi atau data pengguna tidak disamakan dengan izin menjalankan tindakan tersebut.

## 2. Sasaran hasil: satu alur kerja yang nyata

1. Analis memilih institusi/daerah, tahun dan versi dokumen dengan hak penggunaan jelas; sistem menerima PDF/DOCX/XLSX atau adapter data resmi *read-only*.
2. Evidence memperoleh sumber primer, checksum snapshot, lisensi/hak, lokasi halaman/pasal/tabel/sel, identitas ekstraksi dan tingkat kelengkapan. Ketika hilang atau keliru, `ABSTAIN`.
3. Regulatory **wajib terlebih dahulu** menemukan norma induk nasional/daerah yang relevan dengan urusan dan jenis dokumen—UU/PP/Perpres, Permendagri, PMK, Kementerian PPN/Bappenas dan sektoral sesuai kewenangan—beserta delegasi, perubahan/pencabutan/peralihan dan versi yang berlaku pada tanggal kasus. Baru kemudian menghubungkan ketentuan dengan RPJPD/RPJMD/RKPD dan turunannya. Konflik/ketidaksesuaian tetap **kandidat penelaahan**, bukan vonis.
4. Planning/Performance menormalisasi dokumen RPJPD/RPJMD/RKPD/Renstra/Renja dan pohon tujuan–sasaran–indikator–program–kegiatan–subkegiatan, dengan hubungan N dokumen/N tahun/lintas perangkat daerah. Indikator dan target hanya dibandingkan bila definisi operasional sepadan.
5. Fiscal mengaitkan kode/nomenklatur, pendanaan, pembatasan, KUA–PPAS/RKA/DPA/APBD/realisasi dan dasar hukum tahun berlaku. **Tidak** menghitung keselarasan angka ketika numeric gate tertutup.
6. Evaluation menilai realisasi, keluaran dan hasil secara terpisah; tidak mengarang atribusi dampak dari korelasi atau kemiripan teks.
7. Superior menampilkan satu *decision case* lintas lima aplikasi, anomali/prioritas dan bukti pro–kontra, draf executive brief AI atau deterministik dengan ketidakpastian, keputusan reviewer, riwayat, ekspor/reopen, serta dampak perubahan sumber.

**Definisi minimum hasil V1:** seorang reviewer menyelesaikan **satu kasus lintas dokumen resmi yang diizinkan**, dari sumber → fragmen regulasi → relasi jenjang → kandidat temuan → keputusan manusia → laporan yang dapat dibuka kembali. Bila suatu domain belum punya ground truth, alur menunjukkan *review/abstain*, bukan membuat klaim palsu.

## 3. Struktur produk dan pemisahan kontrak dengan implementasi

| Produk | Alur/hasil pengguna yang wajib tersedia | Pemilik kontrak utama |
| --- | --- | --- |
| **Evidence Specialized** | akuisisi sah, versi dan snapshot, pencarian sumber, kutipan/locator tepat, provenance, admission | `SDEA-01`, `SCF-00` |
| **Regulatory Specialized** | norma, hierarki/otoritas/delegasi, tanggal/wilayah, perubahan, indikasi harmonisasi, antrean review | `SCR-01`, `SCC-01`, `SCF-00` |
| **Performance Specialized** | pohon kinerja, indikator/definisi, baseline–target–realisasi terverifikasi, MLCE review | `SPC-01`, `SCP-01`, `SCC-01` |
| **Fiscal Specialized** | urusan–program–kegiatan–subkegiatan–akun–dana dan batas hukumnya; skenario terbatas | `SRA-01`, `SOT-01`, `SCR-01` |
| **Evaluation Specialized** | evaluasi keluaran, outcome, risiko/dampak tanpa klaim kausal otomatis | `SCP-01`, `SOT-01`, `SCC-01` |
| **PI Superior** | satu identitas kasus, penelusuran dan keputusan lintas kelima aplikasi, alternatif, review, audit dan laporan | `SCDG-01`, semua kontrak di atas |

**Sembilan kontrak bersama:** `SDEA-01` Data Rights & Evidence Admission; `SCF-00` Shared Foundation; `SPC-01` Planning Core; `SCP-01` Performance; `SCR-01` Regulatory; `SRA-01` Resource Allocation; `SOT-01` Operational Traceability; `SCC-01` Coherence Auditor; `SCDG-01` Decision Case & Ledger. Kontrak `COMMITTED_SUCCESSOR_AUTHORITY` tidak berarti modul siap pakai atau implementasi telah diberi *production admission*. **MLCE** adalah orkestrasi lintas kontrak, bukan mesin kesepuluh atau aplikasi specialized keenam.

## 4. Prinsip arsitektur dan mandat wajib lintas aplikasi

### 4.1 Regulatory Intelligence adalah fondasi normatif (REG-001)

- **Hierarchy + Authority Graph:** `Regulation`, `RegulationVersion`, `Provision`, `Authority`, `Jurisdiction`, `PlanningRequirement`, `PlanningArtifact`, `EvidenceSpan`, `ReviewDecision`; kedudukan peraturan, pembentuk, dasar kewenangan/delegasi, materi, pengecualian dan status waktu.
- Pasal 7 vs Pasal 8 UU 12/2011 serta perubahan harus diperiksa dari sumber resmi yang berlaku pada tanggal kasus; **jangan buat tangga linear palsu** untuk semua Peraturan Menteri/Perkada atau menyimpulkan kewenangan semata dari kedekatan teks.
- **Harmonisasi vertikal dan horizontal**: keluarkan `POTENTIAL_CONFLICT_REVIEW` hanya dengan pasal/provisi, relasi yang diuji, alasan, kontra-bukti, skor ketidakpastian dan status reviewer. `NO_FINDING` maupun `ABSTAIN` sah.
- **Change impact**: jika naskah dasar berubah, tandai ketentuan/aturan turunan dan artefak perencanaan/anggaran **yang memang memiliki hubungan terverifikasi**; perubahan yang belum terkait tidak diklaim terdampak.
- Keputusan legal, pembatalan/pencabutan, dan penetapan sah/tidak sah **bukan fungsi otomatis aplikasi**.

### 4.1A Norma induk lebih tinggi adalah prasyarat (NORMATIVE-ROOT-006; penegasan 8 Nov)

**Aturan kanonis:** **RPJPD dan RPJMD bukan puncak hierarki norma.** PI wajib memisahkan (A) **Regulatory Hierarchy & Authority Graph**: sumber hukum, kedudukan, pembentuk, delegasi, norma, perubahan, waktu, wilayah; dari (B) **Planning & Budget Artifact Graph**: RPJPN/RPJMN/RKP, RPJPD/RPJMD/RKPD/Renstra/Renja dan KUA–PPAS/RKA/APBD/DPA. Instrumen penetapan dokumen (Perda/Perkada) bisa berstatus peraturan, tetapi hubungan antardokumen rencana tidak dengan sendirinya membuktikan kepatuhan kepada aturan induknya.

**Alur wajib untuk setiap pertanyaan substantif:**

1. Identifikasi urusan, jenis pemeriksaan (tata cara perencanaan, indikator, program/subkegiatan, kewenangan, klasifikasi, fiskal/transfer, sektoral), daerah, OPD, periode/tahun fiskal, dan **tanggal evaluasi historis atau terkini**.
2. Temukan **semua norma induk yang benar-benar relevan**, dari konstitusi/UU ke PP/Perpres serta aturan menteri/lembaga sesuai mandat/delegasi (khususnya Kemendagri, Kemenkeu, Kementerian PPN/Bappenas, dan kementerian sektoral), Perda/Perkada serta pengecualian setempat. Pisahkan **Pasal 7 vs Pasal 8 UU 12/2011 beserta perubahannya**; jangan mengurutkan semua Permen/Perkada sebagai tangga linear, dan jangan menganggap setiap PMK/Permen berlaku untuk semua Pemda dan kasus.
3. Tarik **rantai versi** norma sampai pasal/ayat/huruf: aturan awal, perubahan parsial, perubahan kedua dan seterusnya, pencabutan/penggantian, aturan turunan, ketentuan peralihan, tanggal efektif, pembentuk, dasar kewenangan/delegasi, ruang lingkup materi dan yurisdiksi. Bedakan naskah konsolidasi tak resmi dari naskah/metadata resmi; *current rule* tidak otomatis berlaku untuk kasus masa lalu.
4. Buat relasi bertipe yang **dibuktikan**: `DELEGATES_TO`, `AMENDS`, `REVOKES`, `TRANSITIONAL_RULE`, `IMPLEMENTS`, `CONSTRAINS`, `REFERENCES_NORM`, `APPLIES_TO` pada artefak/OPD/kode/indikator/tahun yang tepat. Tidak cukup menemukan kemiripan kata atau rujukan judul saja.
5. Baru lakukan *crosswalk* nasional–provinsi–daerah dan antar-RPJPD/RPJMD/RKPD/Renstra/Renja/anggaran dengan norma induk sebagai **kendala normatif**. Perubahan hukum memicu **impact recalculation** hanya pada norma/aturan turunan/artefak yang memiliki bukti relasi, waktu dan lingkup relevan. Jangan menyimpulkan otomatis semua Pemda terdampak.

**Keluarga sumber awal untuk discovery, bukan daftar final, bukan jaminan semua ketentuan masih berlaku:** UU 25/2004 Sistem Perencanaan Pembangunan Nasional; UU 12/2011 sebagaimana diubah UU 15/2019 dan UU 13/2022; UU 59/2024 tentang RPJPN 2025–2045; Permendagri 86/2017 tentang tata cara perencanaan daerah; PP 12/2019 dan Permendagri 77/2020 tentang pengelolaan keuangan daerah; regulasi nomenklatur dan SIPD; **PMK tahun fiskal tertentu bila memang mengatur sumber dana/transfer yang dianalisis**; Peraturan Menteri PPN/Bappenas, aturan SPM dan peraturan sektoral hanya menurut materi yang relevan. Verifikasi perubahan/status/ketentuan peralihan **pada tanggal kasus** melalui JDIH dan naskah resmi, jangan hardcode daftar ini sebagai `ACTIVE_CONFIRMED`. Rujukan pembuka: https://peraturan.go.id/files/uu25-2004.pdf ; https://peraturan.go.id/files/uu13-2022.pdf ; https://peraturan.go.id/files/Salinan%2BUU%2BNomor%2B59%2BTahun%2B2024.pdf ; https://peraturan.go.id/files/bn1312-2017bt.pdf ; https://peraturan.go.id/files/bn1781-2020.pdf .

**Model data minimum:** `regulation_id/version_id`, `instrument_type`, `issuer/authority_id`, `delegation_basis_provision`, `amendment_chain`, `transitional_rule`, `effective_from/to`, `as_of_date`, `jurisdiction`, `material_scope`, `provision_locator`, `source_url/sha256`, `planning_requirement`, `planning_artifact/version`, `fiscal_year`, `evidence_status`, `review_status`. Tiap edge memiliki jenis, arah, pasal bukti dan waktu.

| Gerbang | Wajib dibuktikan | Saat gagal |
| --- | --- | --- |
| `NORMATIVE_ROOT_TRACE` | norma induk *materially applicable*, pasal, kewenangan dan delegasi | `ROOT_UNVERIFIED` / `ABSTAIN` |
| `REGULATORY_VERSION` | perubahan, pencabutan, transisi dan keberlakuan pada tanggal/daerah kasus | `AMENDMENT_CHAIN_INCOMPLETE` / `ABSTAIN` |
| `PLANNING_CONSTRAINT_CROSSWALK` | aturan berlaku terkait persyaratan rencana/indikator/program dengan bukti locus | `MAPPING_CANDIDATE` / `ABSTAIN` |
| `FISCAL_LEGAL_BASIS` | norma sumber dana, kewenangan anggaran dan PMK/aturan tahun terkait | `FISCAL_RULE_UNVERIFIED`; angka ditahan |
| `CHANGE_IMPACT` | perubahan provisi, turunan dan artefak yang sungguh terkait, periode dampak | `IMPACT_REVIEW_REQUIRED`, jangan vonis |

**Batas otomatisasi:** PI boleh menunjukkan fakta sumber, kandidat hubungan dan risiko; **tidak memutus sah/tidak sah, konflik hukum final atau rekomendasi keuangan definitif**. Bila norma induk atau rantai amandemen belum terbukti, antarmuka harus memperlihatkan `ABSTAIN`/blokir penilaian keselarasan, sementara fakta sumber tetap dapat dilihat untuk pengumpulan bukti dan reviewer berwenang.

### 4.1B Penelusuran regulasi dari sumber hukum resmi (OFFICIAL-DISCOVERY-007; mandat 8 Nov)

- Regulatory Specialized **wajib** memiliki pencarian web *on-demand* berbasis kata kunci, nomor, tahun dan jenis norma dari sumber hukum yang *allowlisted*, serta sumber JDIH instansi yang ditentukan pengguna **setelah** adapter dan legalitas akses divalidasi. Jenis sumber nasional awal: JDIH BPK, peraturan.go.id, JDIH Kemendagri, Kemenkeu, Bappenas dan JDIH sektoral sesuai kasus. Menyebut sumber dalam roadmap tidak berarti konektor itu telah tersedia.
- **Tahap R70O**: adapter HTTP Laravel JDIH BPK beralamat tetap, form dalam Regulatory UI, login/tenant/throttle, pencarian dari situs resmi dan kandidat tautan /Details/. Status `SOURCE_IMPLEMENTED` (pilot GitHub `0126d98`), `LOCAL_APPLIED=UNCONFIRMED`, `PHP_TEST=NOT_RUN`, `LIVE_PROVIDER_ACCEPTANCE=NOT_RUN`. Tidak mengaktifkan sumber lainnya atau mengklaim telah melaksanakan *full census*.
- **Tahap wajib berikutnya**: adapter resmi tambahan dengan pemeriksaan URL, HTML/API contract, batas permintaan, legalitas akses, sumber gagal/anti-bot, provenance snapshot PDF, *deduplication* nomor/jenis/tahun/pembentuk, serta pemetaan relasi `AMENDS`/`REVOKES`/`IMPLEMENTS`/`DELEGATES_TO` berikut pasal, transisi dan tanggal efektif. Jika parser/provider berubah, hasil harus `SOURCE_UNAVAILABLE` atau `ABSTAIN`—bukan diam-diam mengganti dengan sumber tidak resmi.
- **Jalur kelulusan**: (1) sensus revisi resmi untuk *case scope* dan tanggal evaluasi selesai, (2) bukti parser memperoleh temuan nyata dari provider di Laravel lokal, (3) reviewer dapat menavigasi metadata/PDF asli dan membedakan *search hit* dari norma efektif, (4) kasus dinilai hanya setelah NORMATIVE-ROOT-006 gates terpenuhi. Aktivasi bridge lokal menunggu (1), lalu tes terfokus (2) dan UI acceptance (3); scheduled bridge yang dinonaktifkan pemilik tetap tidak disentuh.
- `DISCOVERY_ONLY` ≠ `SOURCE_PDF_VERIFIED` ≠ `AMENDMENT_CHAIN_COMPLETE` ≠ `LEGAL_ALIGNMENT_APPROVED`. Pencarian tanpa temuan tidak membuktikan peraturan tidak ada. Seluruh keputusan hukum/angka dan produksi tetap di-*deny*.

### 4.1C Penelusuran otomatis sampai norma induk (AUTO-ANCESTRY-008; mandat pemilik 9 Nov 2026)

**Keputusan produk yang mengikat:** Regulatory Intelligence **tidak boleh bergantung pada pengguna mengetik daftar regulasi atau pemeriksa manusia menelusuri satu per satu**. Input pengguna maksimal konteks pemeriksaan (urusan, wilayah, tahun/tanggal kasus, dokumen). Sistem sendirilah yang menemukan regulasi relevan, menelusuri sumber resmi ke dasar kewenangan dan norma induk paling atas yang terbukti terkait, menyensus perubahan/pencabutan/transisi, mendeteksi duplikasi, memelihara graph, dan memperbarui hasil berkala. Form manual R70O adalah fallback, bukan jalur utama.

**Urutan kemampuan wajib yang berjalan otomatis:**

1. **Klasifikasi konteks:** ekstrak urusan, topik hukum, produk hukum, yurisdiksi dan tanggal kasus dari dokumen perencanaan/anggaran; temukan identitas awal tanpa user menyuplai nama aturan.
2. **Discovery multi-adapter:** query JDIH BPK, peraturan.go.id, JDIH kementerian/lembaga dan daerah yang dibolehkan; dedup jenis–nomor–tahun–pembentuk; bedakan SEARCH_HIT, OFFICIAL_METADATA, OFFICIAL_PDF_HASHED, SOURCE_UNAVAILABLE.
3. **Traversal induk rekursif berbatas:** baca konsiderans Mengingat/Dasar Hukum, pasal delegasi dan rujukan eksplisit; telusuri node baru sampai semua induk relevan memiliki sumber, konstitusi tercapai pada jalur relevan, atau batas keamanan memerlukan ROOT_UNRESOLVED. Gunakan queue, visited, max-depth/max-nodes/max-requests, frontier persist/retry, dan identitas serta SHA bukti. Kutipan dasar hukum saja **bukan bukti delegasi** atau kelengkapan semua norma.
4. **Sensus waktu dan versi:** cari AMENDS, REVOKES, REPLACES, TRANSITIONAL; verifikasi perubahan parsial/berantai dan masa berlaku per tanggal kasus. Belum Tersedia atau nol temuan **bukan** bukti tidak pernah diubah.
5. **Pemeliharaan tanpa operator:** scheduler + cache-miss/event dokumen baru, backoff/rate cap, deteksi perubahan hash/parser, monitoring dan antrean hanya untuk **pengecualian material**. Manusia tidak memverifikasi setiap regulasi, tetapi keputusan hukum final/keuangan tetap membutuhkan otorisasi berwenang.

**Increment R70R (GitHub pilot 9 Nov):** adapter otomatis membaca metadata resmi **dua seed** Permendagri 10/2025 dan 86/2017, mengekstrak dasar hukum menjadi graph *calon* induk, memakai endpoint Laravel berotentikasi, cache 12 jam, command dan deklarasi jadwal 03:30 jika scheduler aktif; UI memuat tanpa user mencari. **Belum selesai sampai induk:** URL induk selain dua seed belum di-resolve, belum ada BFS lintas semua jalur, belum ada sensus amendemen menyeluruh atau bukti scheduler hidup. CODE_COMMITTED; LOCAL_APPLY=UNCONFIRMED; FUNCTIONAL_TEST=NOT_RUN; LIVE_PROVIDER_ACCEPTANCE=NOT_RUN; PRODUCTION=DENIED. Berikutnya: resolver identitas UU/PP/Perpres/Permen dari sumber resmi, traversal berbatas dan sensus versi.

**Increment R70S (GitHub pilot 9 Nov, source only):** mesin menemukan kandidat metadata resmi bagi identitas induk UU/Perpres/Permendagri yang berasal dari graph R70R secara otomatis, tanpa user mengisi nomor atau kata kunci; pencarian dibatasi tiga identitas per eksekusi, exact-match jenis–nomor–tahun–slug, domain allowlist, SHA HTML hasil pencarian, retry jika provider gagal, dan status parsial. Command Laravel dan jadwal setiap enam jam tersedia **hanya bila scheduler Laravel diaktifkan**; tidak menyalakan Windows Task Scheduler yang telah dinonaktifkan pemilik. URL yang cocok hanya status OFFICIAL_CATALOG_IDENTITY_CANDIDATE, belum membaca norma induk/PDF, belum mensensus revisi, dan tidak mengubah izin ABSTAIN. CODE_COMMITTED; LOCAL_APPLY=UNCONFIRMED; PHP_TEST=NOT_RUN; LIVE_SOURCE=NOT_RUN; PRODUCTION=DENIED. Berikutnya: baca metadata induk terselesaikan, ekstrak dasar hukum pada setiap node, lanjutkan BFS dan sensus perubahan efektif secara berbatas.\n\n**Increment R70T (GitHub pilot 9 Nov, source only):** sistem mengambil halaman metadata resmi hasil pencocokan R70S untuk dua UU per batch, memeriksa identitas jenis–nomor–tahun dan domain sumber, membatasi ukuran respons, menyimpan SHA-256 HTML, lalu menangkap **rujukan konstitusi dari abstrak** dan **calon peraturan pengubah dari bagian status resmi** ke graph/daftar kandidat. Hal ini memisahkan *constitutional mention* dari *listed amendment candidate*: keduanya tidak boleh dianggap bukti final hubungan hierarki, keberlakuan historis, atau sensus amandemen lengkap. Command dan jadwal setiap enam jam tersedia hanya jika Laravel scheduler aktif. CODE_COMMITTED; LOCAL_APPLY=UNCONFIRMED; PHP_TEST=NOT_RUN; LIVE_SOURCE=NOT_RUN; PRODUCTION=DENIED. Milestone berikutnya: pemeriksaan PDF dan ketentuan substantif beserta tanggal perubahan, traversal rekursif UU/PP/Perpres/Permen lintas sumber resmi, serta penyelesaian pengecualian tanpa pemeriksaan manual per regulasi.\n\n**Koreksi R70T-V2 (9 Nov, GitHub pilot, source-only):** pemeriksaan ulang metadata JDIH BPK nyata menunjukkan bahwa pencarian regex pada seluruh teks bagian "Diubah dengan" berpotensi keliru memasukkan nomor UU yang hanya muncul dalam judul/penjelasan perubahan, sehingga hitungan awal 8 merupakan **kandidat kasar dan bukan sensus terpercaya**. Ekstraksi kini menerima hanya **entri UU yang merupakan hyperlink resmi langsung** di dalam daftar "Diubah dengan", dengan URL JDIH BPK yang cocok tepat jenis–nomor–tahun; kelompok "Dicabut sebagian dengan", "Mengubah" dan sekadar penyebutan dalam judul tidak digabung. Setiap kandidat memiliki link ke instrumen pengubah; cache R70T-V1 dibuang/dibaca ulang otomatis ketika command V2 dijalankan. STATUS=CODE_COMMITTED; LOCAL_APPLY=UNCONFIRMED; PHP_TEST=NOT_RUN; LIVE_RECOUNT=NOT_RUN; LEGAL=ABSTAIN. Perpu, pencabutan sebagian, uji materi, masa berlaku historis dan bukti pasal masih memerlukan slice berikutnya. Jangan promosikan AMENDMENT_CENSUS_COMPLETE.

**R70T-V3 (9 Nov, GitHub pilot, source-only):** pemilik mengonfirmasi V2 LOCAL_APPLY=PASS, PHP_TEST=3 PASS/40 ASSERTIONS, namun pembacaan asli READ=2 dan AMENDMENT_CANDIDATES=0. Bukan bukti tidak ada perubahan; ekstraksi berbasis tautan V2 terlalu ketat terhadap HTML JDIH BPK. V3 mempertahankan hanya label UU tertaut pada bagian "Diubah dengan", menerima dua bentuk link aman (slug tepat atau Details ID tanpa slug dengan kelas keyakinan lebih rendah), menolak host/path yang tidak cocok, menyertakan diagnostik saat hasil nol, dan otomatis membuang kandidat versi parser lama sebelum membaca ulang. STATUS=CODE_COMMITTED; V3_LOCAL_APPLY=UNCONFIRMED; V3_PHP_TEST=NOT_RUN; V3_LIVE_RECOUNT=NOT_RUN; LEGAL=ABSTAIN. Jangan menyamakan kandidat tertaut dengan perubahan/keberlakuan terverifikasi.

**R70T-V4 (9 Nov, GitHub pilot, source-only):** pemilik mengonfirmasi V3 LOCAL_APPLY=PASS, PHP_TEST=3 PASS/45 ASSERTIONS, tetapi sumber nyata tetap READ=2, AMENDMENT_CANDIDATES=0 dan diagnostik STATUS_SEEN=0, CHANGE_HEADING_SEEN=0. Berarti masalah ada pada pemetaan penanda bagian status di DOM, bukan pada ketiadaan perubahan. V4 mencocokkan posisi byte tautan dan heading pada teks gabungan node DOM (tahan terhadap penanda yang dipecah menjadi beberapa node), menyimpan kualitas tautan yang sudah dibatasi host/path, tetap menahan kesimpulan hukum; tes fiktif kini juga memecah heading menjadi beberapa span. STATUS=CODE_COMMITTED; V4_LOCAL_APPLY=UNCONFIRMED; V4_PHP_TEST=NOT_RUN; V4_LIVE_RECOUNT=NOT_RUN; LEGAL=ABSTAIN.

**R70T-V4 penerimaan sumber asli (9 Nov):** Pemilik melaporkan `PI_BRIDGE_APPLY=PASS` (3 berkas), PHPUnit 3 tes/45 assertions PASS, JDIH BPK READ=2/2, dan 6 entri kandidat UU pengubah yang tertaut. Kesalahan hitung V1 (8) dan V2/V3 (0) telah terkoreksi pada kasus ini. Ini PASS untuk linked-metadata candidate extraction, **bukan** verifikasi substansi norma, kelengkapan sensus versi, atau status keberlakuan historis. LEGAL=ABSTAIN, NUMERIC=BLOCKED, PRODUCTION=DENIED.

**Increment R70U (GitHub pilot, 9 Nov, source-only):** menambahkan satu command Laravel tanpa input regulasi per-user yang menjalankan maksimal dua putaran resolver identitas R70S (tiga pencarian per putaran) dan pembaca bukti R70T (dua halaman per putaran), menyimpan cache di antara tahap, tidak mengulang lookup yang sudah selesai, dan menjaga LEGAL=ABSTAIN. Jadwal Laravel kini mengarah ke command gabungan tiap enam jam, bukan dua jadwal terpisah; scheduler tetap **belum diaktifkan** oleh perubahan ini, dan Windows bridge scheduled task tetap tidak disentuh. CODE_COMMITTED; LOCAL_APPLY=UNCONFIRMED; TEST=NOT_RUN; LIVE=NOT_RUN. **Batas:** belum traversal rekursif seluruh bentuk regulasi, PDF/pasal, pencabutan parsial, uji materi, atau keberlakuan per tanggal.

**Penerimaan produk:** dari dokumen RKPD/APBD + tanggal kasus tanpa nama induk, sistem membentuk graph rujukan resmi sampai akar relevan, menampilkan revisi dan pencabutan per tanggal, refresh otomatis, serta *exception review queue*. Jika bukti satu jalur belum lengkap, tetap ROOT_UNRESOLVED / AMENDMENT_CHAIN_INCOMPLETE / ABSTAIN; tidak mengeluarkan keputusan hukum atau angka.

### 4.2 Multi-Pemda dan waktu adalah parameter inti (MULTIREGION-002)

- `jurisdiction_id` berbasis kode resmi tervalidasi, bukan nama kota; relasi nasional→provinsi→kabupaten/kota terpisah dari graf delegasi hukum. Sumber, perangkat daerah, versi nomenklatur, tahun, status regulasi dan `region_rulepack` dapat diganti.
- Hak akses dan identitas tenant ditolak secara *fail-closed* ketika tidak dapat diverifikasi. *Shadow* multi-Pemda bukan bukti isolasi tenant produksi.
- Perbandingan antardaerah/antartahun memerlukan definisi, satuan, metode, cakupan, periode, versi dan hak penggunaan yang sebanding; `NUMERIC_COMPARISON=BLOCKED` sampai gerbangnya lulus.
- Kekhususan DIY/Danais ditangani lewat `special_fund_policy_profile` yang diberi sumber, waktu dan wilayah; **bukan sinonim Dana Otsus** dan bukan default seluruh Pemda.

### 4.3 Satu taksonomi perjenjangan bertipe (HIERARCHY-TAXONOMY-005)

**Enam lapisan wajib berbeda tetapi berelasi:**

1. Regulasi/otoritas/delegasi/ketentuan/masa berlaku;
2. Jenjang dokumen RPJPD–RPJMD–RKPD–Renstra–Renja beserta nasional/provinsi yang relevan;
3. Tujuan, sasaran, indikator IKU/IKD/IKK/SPM/SAKIP/LPPD, numerator/denominator/formula/satuan, target awal/perubahan, realisasi;
4. Organisasi, urusan, perangkat daerah, unit/pelaksana dan riwayat perubahan nama;
5. Program–kegiatan–subkegiatan, sumber dana PAD/DAU/DAK/DBH/Danais/BKK, akun belanja, KUA–PPAS/APBD/DPA/realisasi, mandat pendanaan terversi;
6. Wilayah, periode rencana/tahun fiskal, identitas dokumen, versi awal/perubahan, tanggal penetapan/pengundangan/berlaku.

**Minimum objek:** `jurisdiction_id`, `fiscal_year/planning_period`, `document_id/version`, `taxonomy_type`, `official_code` bila nyata, `official_name`, `responsible_unit`, `effective_from/to`, `source_url`, `pdf_sha256`, `page_or_clause`, `review_status`. Relasi seperti `DERIVES_FROM`, `IMPLEMENTS`, `MEASURES`, `OWNED_BY`, `FUNDED_BY`, `REFERENCES_NORM`, `AMENDS`, `REVOKES` harus memiliki arah, bukti, versi/waktu dan status. Model tidak boleh mengarang kode atau relasi untuk mengisi kekosongan. `SOURCE_CONFIRMED` berbeda dari `MAPPING_CANDIDATE`, `REVIEW_REQUIRED`, `ABSTAIN`, `HUMAN_CONFIRMED`.

### 4.4 Hak data, ketertelusuran dan keputusan manusia

- Semua sumber resmi, hasil ekstraksi, laporan dan ringkasan AI memuat hak penggunaan, identitas versi/snapshot, SHA-256, waktu akses dan *evidence spans*. Tautan web **live** bukan otomatis berkas QA yang di-hash; UI harus membedakannya.
- Pisahkan `observed`, `documented intention`, `verified calculation`, `assumed relation`, `AI_DRAFT`, `NOT_ESTABLISHED`, `HUMAN_REVIEWED`.
- AI boleh menyortir, meringkas, memprioritaskan kandidat dan menyarankan opsi; **tidak** membuat putusan hukum/anggaran atau memperbarui hasil review tanpa keputusan berwenang.
- Temuan tanpa bukti harus `ABSTAIN`; arsitektur mencatat versi rule/model, tenant, run, reviewer dan riwayat ekspor/reopen.

## 5. Mandat teknik dan efisiensi penyampaian (DELIVERY-003/004, revisi 8 Nov)

1. **Results-first:** satu capability slice bermakna per eksekusi, menunjukkan tugas pengguna selesai atau *blocked* jujur; hentikan rangkaian tes/audit yang tidak mengubah keputusan. Satu regresi terfokus + satu alur fungsional inti lebih bernilai daripada angka tes semata.
2. **Laravel-first:** teruskan migrasi *fungsi aplikasi* ke Laravel, auth/tenant/session yang sudah ada, HTTP Client/Queue/Scheduler dan komponen matang; hentikan pengembangan specialized Python baru ketika dapat diadopsi dengan lebih baik/efisien pada Laravel. Modul Python lama boleh dipakai sementara untuk ingest/benchmark bila belum ada pengganti setara; ukur dan rencanakan penonaktifannya, jangan hapus sumber historis tanpa otorisasi.
3. **OSS-first:** time-box discovery dari GitHub/Laravel dan ekosistem sah, cek fit, lisensi distribusi komersial (terutama AGPL/GPL), maintenance, kompatibilitas, dependency, ukuran, data rights, risiko; instal/adaptasi pada kandidat terisolasi. Komponen open source yang matang lebih diutamakan dibanding tulis ulang.
4. **Real-data-first:** alur nyata *read-only* dari sumber pemerintah/riset/open data yang sah; catat source registry, Terms, checksum, halaman, freshness, versi dan batas scope; synthetic dipakai untuk edge case, bukan menggantikan penerimaan nyata.
5. **Zero-spend AI:** prefer opsi gratis dengan persyaratan penggunaan yang diperiksa (Gemini/Groq/OpenRouter free, jika tersedia) atau Ollama lokal opt-in; jangan mengaktifkan tagihan, mengunggah dokumen privat ke provider, mengunduh model multi-GB, atau menyatakan inference live berjalan tanpa runtime. Untuk data sensitif, jangan pakai cloud AI tanpa hak/otorisasi. Draf offline berprovenance diberi label jelas.
6. **Installable cumulative & bridge:** satu paket/komit konsisten dan integrasi aman dari source baseline, hash/allowlist/drift-check, backup dan rollback recoverable; jangan memaksa pemasangan berantai puluhan ZIP. Pada mekanisme **GitHub→WSL→Windows**, komit pilot menghasilkan kandidat source; Windows Task Scheduler 15 menit menyalin hanya source yang diizinkan; **tidak** otomatis menjalankan migration, Composer/npm, build/restart, `.env`, koneksi data, atau promosi produksi.
7. **UI/UX dan benchmarking kompetitor** menjadi kegiatan setiap domain, bukan kosmetik di akhir: telaah fitur/workflow produk sejenis, adaptasi pattern yang layak secara lisensi, navigasi 1+5, keterbacaan tabel dan sumber, desain responsif, aksesibilitas, respons error, sign-in, pekerjaan reviewer, executive brief dan ekspor. Jangan menyalin aset proprietary.
8. **AI executive decision brief:** untuk setiap `REVIEW_REQUIRED` yang mempunyai bukti, tampilkan *apa ditemukan → mengapa penting → bukti dan kontra-bukti → dampak potensial → prioritas/verifikasi → batas keyakinan dan keputusan manusia*. Provenance wajib pada setiap klaim.
9. **Fungsional bukan QA theater:** laporkan `FITUR`, `DATA_ASLI`, `FLOW=PASS|FAIL|BLOCKED|NOT_TESTED`, `BUKTI`, `LIMITASI`. Kegagalan jalur utama menahan klaim milestone selesai.

## 6. Bukti historis dan posisi aktual, **per 8 November 2026**

| Bukti/versi | Apa yang sah dikatakan | Apa yang belum sah |
| --- | --- | --- |
| G1–G5, G6, VS dan kontrak successor (Okt) | fondasi parsing/evidence/reviewer/graph memiliki bukti historis terpisah; kontrak domain terpetakan | jangan memindahkan status `PASS` lama ke Laravel atau menyebut semua kontrak `IMPLEMENTED` |
| R55 (1 Nov), dokumen PDF 184 halaman | 156 kandidat; visual internal 27/27; QA PASS | 129 kandidat tanpa referensi, 3 UNKNOWN; numeric BLOCKED dan produksi DENIED |
| R56 (2 Nov) shadow | 127 halaman, 69 tabel terdeteksi, 9.769 sel; eksekusi shadow selesai | hasil ekstraksi bukan kebenaran substansi/perbandingan angka |
| R57–R64 (2 Nov) | addenda mencatat percobaan hierarchy/regulation, sumber hukum resmi, R63 Semarang 4 PDF dan 9 fragmen, rancangan R64 taxonomy | bukan bukti seluruh paket telah masuk source Laravel atau lolos user E2E; perlu periksa masing-masing versi |
| R69E/R70C–R70H (8 Nov) | local Laravel auth `/api/auth/setup-status` HTTP 200 (dilaporkan pemilik); R70G model 7 dokumen/5 perubahan/47 SHA kutipan; R70H endpoint evidence, lint/route PASS; `c765595` UI di-*apply* Windows per log pemilik | acceptance browser login→kutipan→PDF dan penalaran hukum/angka **belum terkonfirmasi** |
| Perbaikan tautan PDF 404 | commit `4cb81779`: mengganti rute PDF lokal yang tidak terdaftar dengan URL JDIH dalam ledger yang di-*allowlist*; membedakan live web dengan snapshot QA | `LOCAL_APPLY=UNCONFIRMED` untuk commit perbaikan, PDF live tidak di-hash ulang |
| Bootstrap roadmap dan branch | commit `f371aba` mencatat empat sumber Library dan status Windows `ROADMAP=NEEDS_EXPLICIT_PATH_0_CANDIDATES`; branch pilot + draft PR #31 | dokumen Windows canonical belum ditemukan/diubah; commit GitHub bukan deployment |

**Gerbang saat roadmap ditetapkan:** `REVIEW_REQUIRED`, `LEGAL_HARMONIZATION=NOT_ASSESSED`, `SUBSTANTIVE_ALIGNMENT=NOT_ASSESSED`, `NUMERIC_COMPARISON=BLOCKED`, `PRODUCTION_ADMISSION=DENIED`. Pembukaan gerbang hanya melalui evidence, review dan izin yang sesuai.

## 7. Urutan master milestone terkini (outcome dan dependency)

Nama `M0–M8` dan `W0–W5` dipertahankan sebagai **peta dependensi**, sementara Rxx adalah sejarah incremental/QA yang tidak boleh dianggap otomatis setara milestone produk.

| Urutan | Milestone terintegrasi | Acceptance hasil manusia/produk | Status 8 Nov |
| --- | --- | --- | --- |
| **F0 / M0 / W0** | satu sumber roadmap, source GitHub ↔ Windows guarded bridge, identitas, source rights dan UI baseline | roadmap tunggal aktif untuk pilot, pull/apply tanpa drift dan sumber asli utuh | bridge source terbukti; roadmap dipadukan dalam dokumen ini; Windows canonical path belum diketahui |
| **F1 / W1** | Evidence: ingestion nyata, hash/locator, searchable provision, PDF page clickthrough, excerpt reviewer | satu naskah resmi → kutipan aslinya di layar, hash cocok, sumber dibuka tanpa 404, failure ditahan | R70H code ada; local acceptance/tautan live pending |
| **F2 / R57A–C/R57A2** | **Normative root first** UU/PP/Perpres/Permen relevan, Pasal 7/8, delegasi, versi/transisi, yurisdiksi, konflik kandidat dan perubahan | satu ketentuan induk nasional + dasar kewenangan + perubahan efektif + hubungan terbukti ke kewajiban dokumen/periode dan antrean review | foundation/shadow historis; gerbang `NORMATIVE_ROOT_TRACE` belum diakui lulus pada Laravel |
| **F3 / R64 / W2 / M2** | Planning taxonomy + Performance N dokumen + MLCE: versi, OPD, indikator, kode | satu program/subkegiatan dari dokumen resmi → pohon dan crosswalk bertipe; kandidat/abstain saat tak yakin | perlu real crosswalk user-visible terintegrasi |
| **F4 / W3** | Fiscal planning-budget bridge: KUA-PPAS/RKA/APBD/DPA, sumber dana, mandatory rulepack | satu hubungan regulasi→program→anggaran berprovenance; hitungan hanya pada kelas terverifikasi | evidence Semarang R63 historis, numeric BLOCKED |
| **F5 / W4 / M3** | Multi-dimensional coherence & Evaluation: target–aktual, keluaran/hasil, risiko, longitudinal | satu finding terpaut bukti, kontra-bukti, tidak memaksa hubungan kausal | belum ada acceptance produk penuh |
| **F6 / W5 / M5** | PI Superior orchestration, reviewer decision/ledger, draf AI brief, laporan dan reopen | satu case lintas 5 domain selesai sampai human decision + export/reopen | target belum terpenuhi |
| **F7 / M6** | real-document benchmark, UI acceptance desktop/mobile, keamanan, operasi & recovery | FP/FN/abstain/locator terukur pada korpus berizin, tenant boundaries dan restore | belum admission |
| **F8 / M7/C1–C3** | productization + go-to-market: demo, workspace, tenant, packaging, subscription | demo engine asli, secure RBAC, isolation, licensing, deploy/runbook, calon pengguna dan nilai terbukti | **tidak commercial-ready** |
| **F9 / M8** | full decision platform multi-Pemda: scenario, prioritas, pengalokasian, delivery, learn loop | kasus lintas perangkat/daerah/masa yang dapat diaudit, manusia memutuskan | horizon lanjut |

**Tidak perlu menunggu seluruh F2 selesai untuk membuat F3 UI/ingestion source-only**, asalkan hukum, angka dan klaim keselarasan tetap ditahan. Prioritas adalah vertical slice yang dapat dipakai, bukan menghabiskan seluruh nomor R secara berurutan.

### Pekerjaan setelah penetapan dokumen ini

- **NEXT-00 — Norma induk sebelum crosswalk R70L:** kumpulkan minimal satu provision resmi nasional yang mengikat proses rencana/anggaran berikut versi/delegasi/amendemen pada tahun kasus, baru tautkan ke program 2026 bila bukti cukup. R70L boleh menampilkan kode program faktual tanpa root, tetapi **tidak** menilai keselarasan; tampilkan `ROOT_UNVERIFIED` / `ABSTAIN`.
- **NEXT-01 — R70H browser acceptance/PDF correction:** pastikan commit `4cb81779` telah diaplikasikan oleh bridge, uji sesi sebenarnya pada port 8891, klik kutipan, verifikasi halaman PDF dan peringatan live-vs-hash; bila gagal, satu perbaikan terfokus. Jangan laporkan PASS live tanpa browser/sesi.
- **NEXT-02 — Evidence handoff yang siap reviewer:** tambah satu aksi nyata yang mempermudah telaah dan transfer bukti dari R70H (contoh sitasi lengkap yang bisa disalin dengan ID/halaman/hash/sumber, sambil mempertahankan ketidakpastian). Integrasikan pada kartu sumber yang benar-benar bekerja; tidak memerlukan DB/migration.
- **NEXT-03 — Regulatory → Planning crosswalk:** satu relasi berprovenance dari provision ke kategori dokumen/perangkat/program dari sumber resmi R63 atau sumber multi-Pemda lain dengan status `MAPPING_CANDIDATE` hingga diverifikasi; lanjutkan fiscal hanya setelah crosswalk benar.
- **NEXT-04 — Reviewer decision case:** satu antrean review persisten di domain Laravel dengan auth/tenant, decision history, export; perubahan DB **memerlukan rencana backup/migration dan otorisasi terpisah**.
- Sesudah itu lanjutkan Performance, Fiscal, Evaluation, Superior, UI acceptance dan komersialisasi berdasarkan evidensi, tidak dengan angka kemajuan spekulatif.

## 8. Pola eksekusi otomatis dan otoritas perubahan

1. **Autopilot ChatGPT** memeriksa HEAD dan PR #31, dokumen ini, hasil capability terakhir, memilih *satu* fitur source-only yang aman dan bernilai, melakukan implementasi + tes fokus, commit ke cabang pilot, memperbarui draft PR dan laporan. Tidak membuat milestone ganda bila fungsi sudah ada.
2. **GitHub pilot** menampung source dan roadmap; commit menggunakan hash agar audit mudah. Draft PR tetap draft sampai ada acceptance dan keputusan merge terpisah.
3. **Bridge Windows** `PI_LARAVEL_GITHUB_BRIDGE` (dilaporkan ENABLED tiap 15 menit) hanya menerapkan allowlist code dengan drift check dan backup. `UP_TO_DATE`/ `NO_LARAVEL_CHANGES` bukan bukti workflow hidup; `PI_BRIDGE_APPLY=PASS` hanya bukti source diterapkan.
4. **Tidak otomatis:** force push, merge default, reset/hapus data, menimpa original QA, migrasi database, Composer/npm, build, restart, ubah `.env`/SQLite/Postgres, akun, secrets, billing API, promosi produksi, ekspor data privat atau tes eksternal.
5. **Stop condition:** source/hash tidak sama, tenant berbeda, hak sumber meragukan, endpoint auth gagal, data korup, acceptance utama gagal, atau membutuhkan otorisasi di luar source pilot. Simpan backup, berhenti pada bagian berisiko dan laporkan satu blocker presisi.
6. Status per slice: `CODE_COMMITTED`, `LOCAL_APPLY`, `FUNCTIONAL_FLOW`, `REAL_DATA`, `SECURITY`, `LEGAL`, `NUMERIC`, `PRODUCTION`. Jangan mengisi unknown dengan PASS.

## 9. Kesiapan komersialisasi dan UX sebagai gerbang nyata

**C1 — Public Free Demo:** landing/showcase berbasis data sintetis tanpa login; kemampuan mesin sungguhan, tidak mengungkap user data, tidak memanggil API berbayar.  
**C2 — Account & Free Workspace:** institusi, user, tenant isolation, hak akses, rate/quota, audit, portabilitas data, sesi aman.  
**C3 — Specialist/Bundle/Superior/Institutional:** entitlements server-side, metering transparan, subscription/billing hanya setelah persetujuan, masing-masing domain menjaga mutu kebenaran yang sama; Superior membeli orkestrasi/decision support, bukan memonopoli aturan wajib.  
**Kelaikan produk:** UI responsive/aksesibel, navigasi 1+5 terpadu, walkthrough analis–reviewer, kinerja laptop wajar, source links berguna, tabel/graph terbaca, ekspor/reopen, dokumentasi, deploy/recover, privasi, legal/licensing, bukti pelanggan/kontrak/biaya support. Mockup tanpa workflow sumber tidak dihitung selesai.

**Pengukuran kemajuan:** jangan menjumlah jumlah commit atau tes menjadi persen siap jual. Skor berbobot F1–F8 dibedakan per *implementasi*, *keandalan evidence*, *penerimaan pengguna*, *operasi/keamanan*, *validasi pasar*. Angka 34% core dan ~25% full pada 24 Oktober adalah **estimasi historis yang tidak boleh dipublikasikan sebagai persentase terkini**. Taksir ulang sesudah checkpoint outcome dengan rubric dan bukti tertulis; jangan menyamakan `HTTP 200` dengan komersialisasi.

## 10. Quality gates substantif sebelum penggunaan resmi

| Gate | Bukti lulus | Bila tidak lulus |
| --- | --- | --- |
| Data rights/admission | sumber diizinkan, versi/tenant/hash/locator lengkap | tahan ingestion atau keluaran |
| Hierarchy/delegation/normative root | UU/PP/Perpres/Permen relevan, pasal, kewenangan/delegasi, perubahan/transisi, tanggal/daerah dan relasi ke artefak terbukti | `ROOT_UNVERIFIED` / `AMENDMENT_CHAIN_INCOMPLETE` / `ABSTAIN` |
| Harmonization | bukti hukum memadai, reviewer berwenang mengesahkan label | hanya `POTENTIAL_CONFLICT_REVIEW`, bukan putusan |
| Crosswalk taxonomy | kode/versi/OPD/periode/satuan terikat bukti resmi | `MAPPING_CANDIDATE` / `ABSTAIN` |
| Numeric | ground truth dan pemeriksaan denominator/versi/periode/formula | `NUMERIC_COMPARISON=BLOCKED` |
| Real end-to-end | source→reviewer→brief→decision→report/reopen lulus | `FUNCTIONAL_FLOW=BLOCKED` |
| UI/ops/security | desktop/mobile, role, tenancy, restore, observability, licensing | kandidat/staging saja |
| Commercial/production | izin tertulis, kondisi SLA/privacy/procurement dan bukti nilai nyata | `PRODUCTION_ADMISSION=DENIED` |

## 11. Artefak bukti dan template penyelesaian

Setiap penyelesaian sebaiknya memuat satu catatan singkat:

```text
PI_CAPABILITY=<ID + hasil pengguna>
GITHUB_COMMIT=<full SHA>; PR=<nomor>
LOCAL_APPLY=PASS|UNCONFIRMED|BLOCKED
DATA_ASLI=<identitas sumber, hak, hash/version>
FUNCTIONAL_FLOW=PASS|FAIL|BLOCKED|NOT_TESTED
EVIDENCE=<aksi/URL/teks/halaman/hash/status yang terukur>
COST=ZERO|UNKNOWN; OSS_REUSE=<module + version/license atau alasan tidak digunakan>
LIMITATIONS=<apa yang belum bisa disimpulkan>
LEGAL_HARMONIZATION=NOT_ASSESSED
NUMERIC_COMPARISON=BLOCKED
PRODUCTION_ADMISSION=DENIED
NEXT=<satu capability slice yang siap berikutnya>
```

Simpan riwayat keputusan `CD-YYYYMMDD-NN` bila ada perubahan prioritas bermakna, dengan alasan, mandat terdahulu yang diganti, kode/PR dan dampak. Perbarui roadmap hanya ketika target/otoritas berubah, bukan setiap smoke test.

## 12. Registri bukti sumber dokumen roadmap

- **S1:** Library `02_PI_MASTER_ROADMAP_20261025.md` — `libfile_71f3b0b25a988191a753e00f42c33ce6`.
- **S2:** Library `PI_SUPERIOR_SPECIALIZED_EXECUTION_MAP_20261029.md` — `libfile_746615dfb660819186fecff602cddbf7`.
- **S3:** Library `PI_MASTER_ROADMAP_REGULATORY_MANDATE_20261102.md` (versi 2) — `libfile_ec7a78a56a5c819199b1c19a92546d37`.
- **S4:** Library `PI_MASTER_ROADMAP_HIERARCHICAL_TAXONOMY_MANDATE_20261102.md` — `libfile_b0e9bc14e2588191907fe608bc3be017`.
- **S5:** `docs/PI_MILESTONE_EXECUTION_CONTROL_20261108.md`, `docs/PI_ROADMAP_LIBRARY_RECONCILIATION_20261108.md`, `docs/PI_R70H_VERIFIED_EVIDENCE_LOOKUP.md` pada cabang pilot, PR #31, dan log bridge/HTTP/PHP yang dikirim pemilik.

**Change decision `CD-20261108-01`:** Pemilik menginstruksikan penyatuan seluruh master roadmap dan mandat terbaru menjadi pegangan. Dokumen ini **menggantikan roadmap sementara untuk pengembangan pilot**; sumber historis tetap utuh. Ia **tidak menyatakan** path master asli Windows telah ditemukan, tidak menimpa dokumen Windows dan tidak menaikkan status legal/numeric/production. Setelah roadmap ini, eksekusi berlanjut ke NEXT-01/02 menurut bukti kesiapan.

**Change decision CD-20261109-02:** Pemilik mewajibkan traversal induk hukum otomatis tanpa pencarian manual; AUTO-ANCESTRY-008 adalah gerbang produk, bukan izin mengabaikan verifikasi sumber atau mempromosikan produksi.

## 13. R86 — Integrated Government Regulatory & Development Lifecycle Intelligence (18 November 2026)

**Change decision `CD-20261118-03`:** R86 menjadikan regulatory backbone sebagai kemampuan bersama PI Superior dan lima Specialized Apps tanpa mengganti kontrak R85. Registry awal menggunakan 38 entri handoff sebagai **katalog riset yang wajib diverifikasi**, bukan daftar aturan yang otomatis sah/berlaku. Implementasi berada pada branch kandidat `feature/r86-regulatory-backbone-lifecycle`, berbasis accepted baseline `62415afa23af4dbeb81e861e1d0bda219f23e5a0`, commit implementasi `b17684d0c036e3a2191ed45aaee8e24c995f641b`, dan draft PR [#69](https://github.com/fajarnurmanto-source/planning-intelligence/pull/69); PR belum di-merge dan tidak mengubah database.

Hasil kandidat R86 yang sudah tersedia:

- panel **Siklus Pemerintahan & Dasar Regulasi** terintegrasi di workspace PI Superior/R85; tidak ada aplikasi atau dashboard paralel;
- satu read model Laravel yang memakai kembali `DevelopmentCaseGraph`, empat graf, lima pertanyaan, autentikasi dan isolasi tenant R85;
- registry berversi dengan status sumber, tanggal efektif, tahun perencanaan/anggaran, provision locator, provenance dan fail-closed validation;
- graf dependensi 10 tahap: kewenangan, perencanaan, penganggaran, pelaksanaan/perbendaharaan, monitoring, evaluasi, APIP, BPK, tindak lanjut dan umpan balik ke perencanaan berikutnya;
- lima projection bersama untuk Evidence, Regulatory, Performance, Fiscal dan Evaluation Intelligence, tanpa engine duplikat;
- vertical slice `YOG-2026-PERSAMPAHAN`, termasuk gap/`ABSTAIN` untuk artefak monitoring, evaluasi, pengawasan dan audit yang belum tersedia;
- temporal projection membedakan pedoman RKPD/APBD 2026 dan 2027 serta transisi SPKN Peraturan BPK 1/2017 ke Peraturan BPK 1/2026 pada 10 April 2028;
- tautan kembali ke reviewer R84; registry/relation tidak membuat keputusan reviewer atau kesimpulan harmonisasi otomatis;
- ekspor JSON dan Markdown berlabel `NOT_APPROVED`.

Status verifikasi kandidat per 20 November 2026: perbaikan baseline terpisah pada draft PR #70 membuat acceptance gabungan `98/98` backend (`1116` assertions); frontend R84–R86 `38/38`; route, build dan lint `PASS`. Acceptance Edge terautentikasi mencakup registry 38 kandidat/10 tahap/lima projection, filter BPK 1/10, reviewer R84, sumber resmi, batas temporal 9/10 April 2028, JSON/Markdown nyata, desktop, tablet 768 px, ponsel 390 px, logout dan endpoint pasca-logout. Luapan provenance R85 yang ditemukan pada 390 px diperbaiki dan diuji ulang tanpa overflow. Draft PR #69 dan #70 tetap tidak di-merge.

Gerbang R86 tetap: `LEGAL_HARMONIZATION=NOT_ASSESSED`, `NUMERIC_COMPARISON=BLOCKED`, `REVIEW_DECISION=NO_ACTIVE_DECISION`, `PRODUCTION_ADMISSION=DENIED`. Acceptance R86 `PASS` dan merge-readiness `READY_FOR_HUMAN_APPROVAL`; NEXT adalah review manusia atas draft PR #70/#69, merge hanya setelah persetujuan eksplisit, lalu guarded Windows bridge sync. Verifikasi provision/temporal kandidat yang belum terpin tetap pekerjaan hukum/evidence berikutnya dan tidak mengubah gate.


## R88 — 21 November 2026
R87 merged through PR72 (ebbada1). R88 isolated implementation composes existing R85/R86/R87 engines in one protected crosswalk endpoint and user-triggered PI Superior panel. Operational definitions retain candidate status; no 2027 equivalence without evidence. See PI_R88_ARCHITECTURE_GAPS_20261121.md and PI_STATE_CAPSULE_R88_20261121.md. Backend106/106, frontend50/50; desktop/mobile replay PASS. Live authenticated E2E outstanding; overall R88 PARTIAL, production DENIED. No new reviewer or numerical/legal promotion.

## 14. PI-HYBRID-001 — Mandat Inti Regulatory × Semantic Reasoning (21 November 2026)

**Change decision CD-20261121-04 (persetujuan pemilik):** Semantic Regulatory Reasoning adalah **arsitektur inti PI**, bukan chatbot atau lapisan pelengkap. R88 wajib diperkuat dengan boundary deterministik dan bukti; R89 mengembangkan candidate-generation AI semantik berprovenance; R90 menggabungkannya ke alur reviewer dan lima Specialized Apps. Aturan deterministik menguasai legal/version/tenant/evidence gates; AI mengusulkan relasi makna saja dan tidak pernah mengesahkan relasi hukum, angka, atau keputusan reviewer. Confidence model, bukti dan validasi hukum adalah tiga dimensi berbeda.

Rujukan operasional dan acceptance: [PI-HYBRID-001](PI_HYBRID_REGULATORY_SEMANTIC_MANDATE_20261121.md). Mandat ini **menambah** norma induk, taxonomy, rights, human-review dan 1+5 yang sudah kanonis; tidak menggantikannya. R88 browser QA pada 21 November: crosswalk 2026/2027, export, R70A/R82/R84 read-only HTTP 200 pada runtime lokal; status legal/numeric/production tetap ditahan. Pengakuan semantik/model deployment **NOT_IMPLEMENTED sampai diverifikasi terpisah**. PR #74 tetap OPEN/NOT_MERGED dan tidak boleh auto-merge.

**Change decision CD-20261121-05 — R89 CLOUD-FIRST / BYOK:** Owner requires R89 Semantic Regulatory Reasoning to offer institution-configured hosted AI (DeepSeek, OpenAI, Gemini, Groq, OpenRouter) without local model hardware. Reuse existing encrypted per-tenant provider vault and administrative settings. Every external call is user-triggered, preceded by exact metadata preview with SHA-256 matching, affirmative privacy/provider-cost consent, fixed HTTPS endpoint, bounded provider JSON, and canonical PI legal/evidence gatekeeping. Ollama is no longer an R89 requirement. No automated legal/fiscal/reviewer decisions. See [Cloud BYOK mandate](PI_R89_CLOUD_BYOK_SEMANTIC_MANDATE_20261121.md), which overrides conflicting local-only R89 recommendations. Cloud calls may incur provider charges. CI success does not prove live provider acceptance.


**Change decision CD-20261122-06 — PI SINGLE RUNTIME & WINDOWS APP (MANDATORY):** Instruksi eksplisit owner 22 November 2026 mengikat satu source Laravel PI Superior + lima specialized, satu database/account/session authority, satu launcher Windows, dan satu alamat desktop utama http://127.0.0.1:8820. Port 8822 (R88) dan 8823 (R89) adalah acceptance QA sementara dan tidak boleh menjadi produk/launcher permanen. Pengguna harus dapat membuka PI dari ikon tanpa menghidupkan php artisan serve manual. Rekonsiliasi source/R89 stack, APP_KEY/DB main, preview hash dan reviewer gates harus diverifikasi sebelum cutover. Source-only launcher candidate pada scripts/pi-windows-app.ps1 dan instruksi canonical docs/PI_SINGLE_RUNTIME_MANDATE_20261122.md; GitHub CI bukan bukti sudah dipasang pada mesin pengguna. Dilarang migrasi/overwrite main atau auto-merge tanpa acceptance. PHP development server adalah solusi desktop QA sementara; managed service/webserver wajib dievaluasi untuk operational production readiness. Numeric dan legal tetap blocked, production DENIED.


**Correction CD-20261122-06A — REUSE EXISTING PI DESKTOP ICON, NOT RECREATE IT (MANDATORY):** Owner presented screenshot showing an existing Windows desktop shortcut named Planning Intelligence. The preceding CD-20261122-06 incorrectly presumed PI lacked an icon and chose localhost:8820 as desktop canonical without inspecting that existing shortcut. Repository inventory confirms historic 01_START.bat -> scripts/start.ps1 -> Docker Compose -> localhost:8080 plus G-series Python/DPAPI launchers, but **the target of the current desktop .lnk is NOT_YET_VERIFIED**. Therefore preserve the existing icon unchanged; remove duplicate shortcut installation in PR #78; audit shortcut target, arguments (locally, redact secrets in output), working directory, actual origin, ownership of process and source; then repair the existing bootstrap/managed runtime rather than replacing it. Port 8820 is only a candidate/local test endpoint, not confirmed production or desktop default. The read-only auditor scripts/pi-audit-existing-launcher.ps1 is the gate before integration. No duplicate icon/shortcut, no secondary server, no source/data/APP_KEY modifications. The earlier claim that PI had no launcher is superseded.
