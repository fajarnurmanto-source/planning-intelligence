"""Frozen, human-authored synthetic Indonesian topical relevance benchmark.

Labels assess TOPICAL RELEVANCE only; no entailment, factuality or impact.
No threshold tuning on holdout labels. No network or model download.
"""
from __future__ import annotations
from collections import Counter
from math import isfinite
from .engine import cosine, DEFAULT_THRESHOLD, EmbeddingUnavailable

# Items intentionally vary terminology and include difficult topical negatives.
# Labels are author judgments for synthetic evaluation, NOT expert-reviewed gold.
_PAIRS_GROUPED=(
 ('Digitalisasi pengaduan warga memperpendek penanganan laporan.', 'Pelayanan pengaduan daring mempercepat tindak lanjut aspirasi.',1),
 ('Pembenahan jaringan air bersih meningkatkan cakupan rumah tangga terlayani.', 'Perluasan sambungan perpipaan menambah penduduk penerima air layak.',1),
 ('Pelatihan pedagang pasar meningkatkan pemahaman transaksi digital.', 'Pendampingan UMKM pasar membantu kemampuan pembayaran nontunai.',1),
 ('Perbaikan drainase mengurangi genangan saat hujan lebat.', 'Normalisasi saluran lingkungan menekan risiko banjir lokal.',1),
 ('Kampanye pilah sampah meningkatkan praktik pengurangan residu.', 'Edukasi pemilahan rumah tangga mendorong penurunan sampah campuran.',1),
 ('Pemeliharaan lampu jalan memperbaiki penerangan kawasan.', 'Penggantian armatur PJU menambah ruas jalan yang terang.',1),
 ('Peningkatan kapasitas puskesmas memperluas akses layanan primer.', 'Penambahan jam pelayanan klinik wilayah memperbesar jangkauan pasien.',1),
 ('Fasilitasi perpustakaan meningkatkan literasi anak.', 'Akses buku bacaan sekolah memperkuat budaya membaca murid.',1),
 ('Pembinaan relawan tanggap bencana mempercepat kesiagaan.', 'Simulasi evakuasi meningkatkan kesiapan komunitas menghadapi kejadian darurat.',1),
 ('Penataan jalur pejalan kaki memudahkan mobilitas tanpa kendaraan.', 'Pelebaran trotoar memberi akses berjalan yang lebih aman.',1),
 ('Sistem antrean elektronik mengurangi lama menunggu layanan publik.', 'Nomor antrean daring menekan waktu tunggu pemohon administrasi.',1),
 ('Revitalisasi ruang terbuka meningkatkan kesempatan aktivitas warga.', 'Pembenahan taman kota menambah fasilitas rekreasi publik.',1),
 ('Penyuluhan hemat energi mengurangi konsumsi listrik bangunan.', 'Edukasi efisiensi penggunaan energi menekan pemborosan daya.',1),
 ('Pemetaan risiko kebakaran memperbaiki kesiapan pencegahan.', 'Penyusunan peta rawan api mengarahkan mitigasi insiden.',1),
 ('Peningkatan akses internet sekolah mendukung pembelajaran berbasis digital.', 'Konektivitas kelas memperluas kesempatan penggunaan materi daring.',1),
 ('Peningkatan kebersihan pasar mengurangi keluhan sampah.', 'Pengelolaan limbah kios memperbaiki kondisi sanitasi pasar.',1),
 ('Kegiatan penghijauan meningkatkan tutupan vegetasi perkotaan.', 'Penanaman pohon jalan menambah kanopi hijau kawasan.',1),
 ('Pemantauan kualitas udara menyediakan data polutan harian.', 'Sensor polusi merekam konsentrasi partikulat secara berkala.',1),
 ('Pelayanan administrasi kependudukan bergerak memperluas akses dokumen.', 'Layanan jemput bola memudahkan penerbitan identitas warga.',1),
 ('Optimalisasi angkutan umum mengurangi ketergantungan mobil pribadi.', 'Perbaikan layanan bus mendorong peralihan moda perjalanan.',1),
 ('Pembenahan jaringan air bersih meningkatkan cakupan rumah tangga terlayani.', 'Pelatihan manajemen pasar memperbaiki pencatatan stok dagangan.',0),
 ('Pemeliharaan lampu jalan memperbaiki penerangan kawasan.', 'Perluasan akses posyandu menambah kunjungan balita.',0),
 ('Perbaikan drainase mengurangi genangan saat hujan lebat.', 'Pembinaan pelaku wisata meningkatkan kemampuan pemanduan budaya.',0),
 ('Pengembangan perpustakaan meningkatkan literasi anak.', 'Pemeliharaan jembatan memperlancar angkutan barang.',0),
 ('Penataan trotoar meningkatkan keamanan pejalan kaki.', 'Pelatihan operator aplikasi layanan meningkatkan kompetensi pegawai.',0),
 ('Sistem antrean elektronik mengurangi lama menunggu layanan publik.', 'Pemasangan panel surya menurunkan konsumsi listrik gedung.',0),
 ('Peningkatan kebersihan pasar mengurangi keluhan sampah.', 'Perbaikan saluran irigasi menunjang panen pertanian.',0),
 ('Pembinaan relawan tanggap bencana mempercepat kesiagaan.', 'Peremajaan lampu jalan meningkatkan keterlihatan rambu.',0),
 ('Penghijauan kota meningkatkan tutupan vegetasi.', 'Revitalisasi arsip sejarah mempermudah akses dokumen lama.',0),
 ('Pemantauan kualitas udara menyediakan data polutan harian.', 'Layanan konseling keluarga memperkuat pola asuh.',0),
 ('Peningkatan akses internet sekolah mendukung pembelajaran digital.', 'Pelebaran jalan mempercepat arus transportasi.',0),
 ('Layanan administrasi bergerak memperluas akses dokumen warga.', 'Pemasaran hasil tani menaikkan harga jual petani.',0),
 ('Optimalisasi angkutan umum mengurangi kendaraan pribadi.', 'Perbaikan jaringan komputer pusat data meningkatkan pencadangan.',0),
 ('Pelatihan pedagang pasar meningkatkan kemampuan transaksi digital.', 'Penataan pedagang kaki lima memperbaiki sirkulasi pejalan kaki.',0),
 ('Edukasi pemilahan sampah mengurangi residu.', 'Pembelian truk sampah menambah frekuensi pengangkutan.',0),
 ('Simulasi kebakaran memperkuat kesiapan relawan.', 'Pemasangan alat pemadam dapat menekan dampak api.',0),
 ('Penambahan tempat tidur klinik memperluas kapasitas layanan kesehatan.', 'Pengadaan mobil ambulans mempercepat evakuasi pasien.',0),
 ('Penerangan jalan yang membaik meningkatkan keamanan ruas.', 'Patroli keamanan malam meningkatkan kepatuhan lalu lintas.',0),
 ('Normalisasi saluran mengurangi banjir permukiman.', 'Penanganan longsor mencegah amblesnya lereng.',0),
 ('Pembenahan taman kota membuka ruang rekreasi.', 'Pengadaan alat kebugaran taman menyediakan fasilitas olahraga.',0),
)

# Stratified frozen split: 10 positive + 10 negative in each 20-example partition.
PAIRS=tuple(case for duo in zip(_PAIRS_GROUPED[:20],_PAIRS_GROUPED[20:]) for case in duo)


def evaluate_benchmark(embedder=None,threshold=DEFAULT_THRESHOLD):
    if embedder is None:
        return {'status':'NOT_RUN_NO_LOCAL_MODEL','dataset_size':len(PAIRS),
                'split':'FROZEN_20_20','model_id':None,
                'metric_scope':'TOPICAL_RELEVANCE_ONLY'}
    if not 0.5<=threshold<=0.95:raise ValueError('G6_BENCH_THRESHOLD_OUT_OF_RANGE')
    hits=[]
    for left,right,label in PAIRS:
        vec=embedder.embed([left,right])
        if not isinstance(vec,(list,tuple)) or len(vec)!=2:
            raise ValueError('G6_BENCH_BAD_VECTOR')
        score=cosine(vec[0],vec[1]);hits.append((label,score>=threshold,score))
    def metrics(rows):
        tp=sum(label and predicted for label,predicted,_ in rows)
        fp=sum(not label and predicted for label,predicted,_ in rows)
        fn=sum(label and not predicted for label,predicted,_ in rows)
        tn=len(rows)-tp-fp-fn
        precision=tp/(tp+fp) if tp+fp else None
        recall=tp/(tp+fn) if tp+fn else None
        f1=(2*precision*recall/(precision+recall)
            if precision is not None and recall is not None and precision+recall else None)
        return dict(n=len(rows),tp=tp,fp=fp,fn=fn,tn=tn,
                    precision=precision,recall=recall,f1=f1,
                    specificity=tn/(tn+fp) if tn+fp else None)
    return {'status':'COMPLETED_SYNTHETIC_TOPICAL_RELEVANCE',
            'model_id':getattr(embedder,'identity','UNNAMED'),
            'threshold':threshold,'dataset_size':len(PAIRS),
            'development':metrics(hits[:20]),'holdout':metrics(hits[20:]),
            'metric_scope':'TOPICAL_RELEVANCE_ONLY',
            'limitations':'Author-labeled synthetic corpus; score does not measure causal entailment or policy coherence.'}
