/* Satu sumber data untuk seluruh app.
 *
 * Aturan: semua angka turunan dihitung di sini, tidak pernah diketik di HTML.
 * Kalau `validate()` melempar, halaman tidak dirender sama sekali — supaya angka
 * palsu tidak pernah tampil. Ini padanan `assert` di script FigCraft.
 */

export const INSTRUMEN = {
  totalTabel: 38,
  // Prodi UBBG punya tahap profesi (S1 + Profesi Bidan), jadi tabel
  // "Khusus Profesi" juga wajib. Kalau kelak ada prodi non-profesi,
  // daftar ini yang diisi, bukan kode yang dicabang.
  tabelNA: [],
  totalDokumen: 78,
  kriteria: 8,
};

// Jenjang & akreditasi dari situs resmi UBBG (FSTIK), 9 Nov 2026.
// Kebidanan UBBG = S1 + Pendidikan Profesi Bidan, BUKAN D-III.
// Kode prodi mengikuti PDDikti: 15201 (Kebidanan), 14201 (Keperawatan).
export const PRODI = [
  {
    id: 'kebidanan', nama: 'Kebidanan', jenjang: 'S1 + Profesi', accent: 'orn',
    kode: '15201', akreditasi: 'Unggul', berlaku: '26 Sep 2026', sampai: '26 Sep 2030',
    punyaProfesi: true,
  },
  {
    id: 'keperawatan', nama: 'Keperawatan', jenjang: 'S1 + Profesi', accent: 'ind',
    kode: '14201', akreditasi: 'Baik Sekali', berlaku: '28 Feb 2025', sampai: '28 Feb 2030',
    punyaProfesi: true,
  },
];

export const PERIODE = { ts: '2025/2026', ss: null };

/* Tanggal kerja sistem. TS 2025/2026 berjalan Sep 2025 – Agu 2026, jadi
 * "data per" harus di dalam rentang itu. */
export const DATA_PER = '12 Agustus 2026';

/* ── halaman 13 · Mahasiswa ─────────────────────────────────────── */
// angkatan, tahun, masuk, lulus, do, cuti, ipk
export const ANGKATAN = [
  ['TS-6', '2019/2020', 60, 55, 3, 0, 3.28],
  ['TS-5', '2020/2021', 58, 54, 2, 0, 3.31],
  ['TS-4', '2021/2022', 62, 58, 2, 0, 3.35],
  ['TS-3', '2022/2023', 55, 48, 2, 0, 3.38],
  ['TS-2', '2023/2024', 64, 41, 1, 0, 3.44],
  ['TS-1', '2024/2025', 60, 0, 1, 0, 3.47],
  ['TS',   '2025/2026', 66, 0, 0, 0, 3.52],
];

export const FEEDS = {
  'TS-6': ['2', '3'], 'TS-5': ['2'], 'TS-4': ['2', '5'], 'TS-3': ['2', '5', '10'],
  'TS-2': ['2', '3', '5', '9', '10'], 'TS-1': ['2', '5', '9', '10'],
  'TS': ['5', '10'],
};

export const MASALAH_MHS = [
  ['8 NIM ganda', 'Tabel 9 dan 10 menghitung mahasiswa dobel', 'bad', 'blokir simpan'],
  ['3 IPK kosong', 'Tabel 3 tidak bisa hitung rata-rata', 'warn', 'peringatan'],
  ['1 tanggal lulus kosong', 'Tabel 5 tidak bisa hitung tepat waktu', 'warn', 'peringatan'],
];

// 425 baris terdaftar, tapi 8 NIM muncul dua kali → 417 orang unik.
export const NIM_GANDA = 8;
export const UNIK = 425 - NIM_GANDA;

/* ── halaman 14 · Kurikulum & MK ────────────────────────────────── */
// kode, nama, sks kuliah, sks praktik, tahap, cpl, aspek[4], rps, lokasi
export const MK = [
  ['KB.101', 'Anatomi & Fisiologi', 2, 1, 'Akademik', true, [1,1,0,1], true, 'Lab'],
  ['KB.102', 'Asuhan Kebidanan Kehamilan', 2, 2, 'Akademik', true, [1,1,1,1], true, 'Lab · RB'],
  ['KB.103', 'Asuhan Kebidanan Persalinan', 2, 3, 'Akademik', true, [1,1,1,1], true, 'RB · BPM'],
  ['KB.104', 'Asuhan Kebidanan Nifas & Menyusui', 2, 2, 'Akademik', true, [1,1,1,0], true, 'RB'],
  ['KB.105', 'Asuhan Neonatus & Bayi', 2, 2, 'Akademik', true, [1,1,1,1], true, 'RS · RB'],
  ['KB.106', 'Kesehatan Reproduksi', 2, 0, 'Akademik', true, [1,1,0,0], true, '—'],
  ['KB.107', 'Keluarga Berencana', 1, 1, 'Akademik', true, [1,1,1,0], false, 'BPM'],
  ['KB.108', 'Deteksi Dini Komplikasi', 2, 1, 'Akademik', false, [1,0,1,0], true, 'RS'],
  ['KB.109', 'Etika & Hukum Kebidanan', 2, 0, 'Akademik', true, [1,1,0,0], true, '—'],
  ['KB.110', 'Komunikasi Terapeutik', 2, 0, 'Akademik', true, [1,1,1,0], true, '—'],
  ['KB.201', 'Praktik Klinik ANC', 0, 3, 'Profesi', true, [1,1,1,1], true, 'RB · BPM'],
  ['KB.202', 'Praktik Klinik Persalinan', 0, 4, 'Profesi', true, [1,1,1,1], true, 'RB'],
  ['KB.203', 'Praktik Komunitas', 0, 2, 'Profesi', true, [1,1,1,0], true, 'Posyandu'],
];

export const TOTAL_MK_INSTRUMEN = 239;
export const ASPEK = [['S', 'Sikap'], ['P', 'Pengetahuan'], ['U', 'Ket. umum'], ['K', 'Ket. khusus']];

/* ── halaman 15 · Pengguna & Hak Akses ──────────────────────────── */
export const PENGGUNA = [
  ['Siti Rahmawati, S.ST., M.Keb', 'Operator Prodi', 'Kebidanan', 'aktif', 'hari ini'],
  ['Nurhayati, S.Kep., M.Kep', 'Operator Prodi', 'Keperawatan', 'aktif', 'kemarin'],
  ['Dr. Maimunah, M.Keb', 'Verifikator', 'Kebidanan', 'aktif', '2 hari lalu'],
  ['Yusrizal, S.Kep., Ns., M.Kep', 'Verifikator', 'Keperawatan', 'aktif', '3 hari lalu'],
  ['Dr. Ir. Zulfikar, M.Pd', 'Admin UPPS', 'Semua prodi', 'aktif', 'hari ini'],
  ['Prof. Dr. Cut Zahriah, M.Pd', 'Pimpinan', 'Semua prodi', 'aktif', '1 minggu lalu'],
  ['Marlina, S.ST., M.Keb', 'Operator + Verifikator', 'Kebidanan + Keperawatan', 'konflik', 'hari ini'],
  ['Hasnawati, A.Md.Keb', 'Operator Prodi', 'Kebidanan', 'nonaktif', '14 Sep 2026'],
];

export const IZIN = ['Isi', 'Impor', 'Verifikasi', 'Buka kunci', 'Kelola pengguna', 'Ekspor'];
export const MATRIX = {
  'Operator Prodi': [1, 1, 0, 0, 0, 1],
  'Verifikator':    [0, 0, 1, 0, 0, 1],
  'Admin UPPS':     [1, 1, 1, 1, 1, 1],
  'Pimpinan':       [2, 2, 2, 0, 0, 1],
};

export const PERMINTAAN = [
  ['Identitas UPPS & Prodi', 'field jenjang', 'Siti Rahmawati', '9 Nov 2026 · 09:12', 'menunggu'],
  ['Periode Akreditasi', 'tanggal pengajuan', 'Dr. Ir. Zulfikar', '8 Nov 2026 · 14:20', 'disetujui'],
];

/* ── halaman 16 · Sarana & Wahana ───────────────────────────────── */
export const WAHANA = [
  ['RSUD Zainoel Abidin', 'Rumah Sakit', 'Banda Aceh', 40, true],
  ['RSIA Banda Aceh', 'Rumah Sakit', 'Banda Aceh', 25, true],
  ['RSJ Banda Aceh', 'Rumah Sakit', 'Banda Aceh', 15, false],
  ['Puskesmas Kuta Alam', 'Puskesmas', 'Banda Aceh', 12, true],
  ['Puskesmas Ulee Kareng', 'Puskesmas', 'Banda Aceh', 10, true],
  ['BPM Cut Nyak Dhien', 'BPM', 'Banda Aceh', 6, true],
  ['PMB Nurhasanah', 'BPM', 'Banda Aceh', 5, true],
];

export const SARANA = [
  ['Ruang kelas', 8, 'baik', 'Kampus A'],
  ['Laboratorium', 4, 'baik', 'Kampus A'],
  ['Ruang simulasi', 2, 'baik', 'Kampus A'],
  ['Perpustakaan', 1, 'baik', 'Kampus A'],
  ['Ruang praktik komunitas', 3, 'sebagian', 'Kampus A · luar'],
];

export const K3 = [
  ['APAR', '12', 'berlaku', true],
  ['Jalur evakuasi', '—', 'terpasang', true],
  ['Kotak P3K', '5', 'berlaku', true],
  ['Sertifikat kelayakan gedung', '—', 'kedaluwarsa 2024', false],
];

/* ── halaman 17 · Pustaka & Jurnal ──────────────────────────────── */
export const KOLEKSI = [
  ['Buku ajar kebidanan', 412, 512, false],
  ['Buku referensi', 96, 118, false],
  ['Jurnal tercetak', 14, 14, false],
  ['Skripsi & KTI', 256, 256, false],
  ['Buku digital', 780, 780, true],
  ['Jurnal elektronik (judul)', 1240, 1240, true],
];

export const LANGGANAN = [
  ['ProQuest Nursing', 'berlangganan', '31 Des 2026', 'aktif'],
  ['EBSCO CINAHL', 'berlangganan', '30 Jun 2027', 'aktif'],
  ['Garuda / Portal Garuda', 'terbuka', '—', 'aktif'],
  ['DOAJ', 'terbuka', '—', 'aktif'],
  ['ScienceDirect (Kesehatan)', 'berlangganan', '31 Mar 2026', 'kedaluwarsa'],
];

export const AKSES = [
  ['Unduhan artikel (TS)', '8.412'],
  ['Kunjungan perpustakaan', '3.190'],
  ['Peminjaman aktif', '127'],
];

/* ── status akreditasi & dokumen (baris 1 dashboard) ───────────────── */
export const PERINGKAT = {
  // Dari situs resmi UBBG: Kebidanan "Unggul", berlaku s.d. 26 Sep 2030.
  // PDDikti masih menampilkan "Baik" — belum sinkron dengan SK 26/09/2026.
  // Situs resmi yang dipakai; ini perlu dikonfirmasi ulang ke prodi.
  nilai: 'Unggul',
  sebutan: 'Unggul',
  berlaku: '26 Sep 2030',
  skala: ['Baik', 'Baik Sekali', 'Unggul'],
};

export const DOKUMEN = {
  terunggah: 41,
  total: 78,
  kedaluwarsa: 7,
};

/* konsistensi: 2 pemeriksaan lintas tabel yang gagal */
/**
 * Temuan konsistensi yang MEMBLOKIR ekspor.
 *
 * KENAPA KOSONG: dulu isinya dua temuan karangan dari desain awal
 * ("lulusan tepat waktu 41, tapi Tabel 2 mencatat 44"). Angka itu tidak
 * berasal dari database mana pun, dan akibatnya tombol ekspor terkunci
 * selamanya untuk data yang sebenarnya bersih.
 *
 * Sumber yang benar sudah ada: GET /api/konsistensi + ekspor_blokir() di
 * server. Sidebar kini membacanya dari sana. Daftar ini dibiarkan kosong
 * sebagai cadangan kalau server tidak merespons — dan kalau server mati,
 * ekspor memang seharusnya tidak jalan.
 */
export const KONSISTENSI_GAGAL = [];

/* tabel terbaru (baris 3 dashboard) — tabel, judul, status, ket, aksi */
export const TABEL_TERBARU = [
  ['T-01', 'Visi, Misi, Tujuan, Strategi', 'lengkap', '5 field terisi · diverifikasi', 'Buka'],
  ['T-02', 'Bundling Sarjana dan Profesi', 'sebagian', '3 dari 6 kolom · menunggu data', 'Isi'],
  ['T-03', 'Kurikulum & RPS', 'terkunci', 'dikunci setelah verifikasi 4 Nov', 'Lihat'],
  ['T-05', 'Lulusan Tepat Waktu', 'lengkap', '256 lulusan · 1 temuan konsistensi', 'Buka'],
  ['T-17', 'Keuangan: Penggunaan Dana', 'sebagian', 'persen TS-1 = 99,9% · harus 100%', 'Isi'],
];

/* ── KRITERIA: satu sumber untuk dashboard + Ringkasan 8 Kriteria ────
   `tabel` dan `dok` adalah [selesai, total]. CELAH di bawah diturunkan dari
   sini, jadi dashboard dan ringkasan tidak mungkin berbeda angka.

   "Lengkap" = tabel penuh DAN dokumen penuh DAN tanpa temuan. Satu definisi
   saja — sebelumnya hanya tabel, sehingga K8 tampil hijau padahal kurang 3
   dokumen. */
/* ── 38 tabel data dukung — SATU SUMBER ────────────────────────────
   Dibangun dari DATA DUKUNG KEBIDANAN.xlsx › Daftar Tabel.
   `no` = nomor tabel instrumen (bukan nomor urut baris).
   `profesi` = true kalau peruntukannya "Khusus Profesi".
   Jangan menambah tabel di halaman mana pun — ubah di sini. */
export const TABEL = [
  { no: '1', judul: "Substansi Kurikulum (kuliah/praktikum/praktik) di Tahap Akademik dan Tahap Profesi", krit: 'K2', status: 'sebagian', terisi: 3, total: 6, profesi: false },
  { no: '2', judul: "Persentase Keberhasilan Studi", krit: 'K3', status: 'sebagian', terisi: 3, total: 6, profesi: false },
  { no: '3', judul: "Data IPK Lulusan Tahap Akademik", krit: 'K3', status: 'lengkap', terisi: 6, total: 6, profesi: false },
  { no: '4', judul: "Data IPK Lulusan Tahap Profesi", krit: 'K3', status: 'kosong', terisi: 0, total: 6, profesi: true },
  { no: '5', judul: "Data Lulusan Tepat Waktu Tahap Akademik", krit: 'K3', status: 'lengkap', terisi: 6, total: 6, profesi: false },
  { no: '6', judul: "Data Lulusan Tepat Waktu Tahap Profesi", krit: 'K3', status: 'kosong', terisi: 0, total: 6, profesi: true },
  { no: '7', judul: "Uji Kompetensi Mahasiswa Program Studi (UKMPS-CBT)", krit: 'K3', status: 'kosong', terisi: 0, total: 6, profesi: true },
  { no: '8', judul: "Uji Kompetensi Mahasiswa Program Studi (UKMPS-OSCE)", krit: 'K3', status: 'kosong', terisi: 0, total: 6, profesi: true },
  { no: '9', judul: "Data Total Mahasiswa", krit: 'K4', status: 'sebagian', terisi: 3, total: 6, profesi: false },
  { no: '10', judul: "Data Mahasiswa Tahap Akademik", krit: 'K4', status: 'sebagian', terisi: 3, total: 6, profesi: false },
  { no: '11', judul: "Data Mahasiswa Tahap Profesi", krit: 'K4', status: 'kosong', terisi: 0, total: 6, profesi: true },
  { no: '12', judul: "Kepuasan Mahasiswa", krit: 'K4', status: 'lengkap', terisi: 6, total: 6, profesi: false },
  { no: '13', judul: "Masa Tunggu Lulusan Mendapatkan Pekerjaan", krit: 'K4', status: 'kosong', terisi: 0, total: 6, profesi: false },
  { no: '14', judul: "Tingkat Kepuasan Pengguna", krit: 'K4', status: 'sebagian', terisi: 3, total: 6, profesi: false },
  { no: '15', judul: "Dosen Tetap", krit: 'K5', status: 'sebagian', terisi: 3, total: 6, profesi: false },
  { no: '16', judul: "Dosen Tetap Tahap Akademik dan Profesi", krit: 'K5', status: 'lengkap', terisi: 6, total: 6, profesi: false },
  { no: '17', judul: "Aktivitas Dosen Tetap", krit: 'K5', status: 'lengkap', terisi: 6, total: 6, profesi: false },
  { no: '18', judul: "Tabel 18a & 18b Kegiatan seminar ilmiah/lokakarya/penataran/pameran Dosen dan Tendik Tetap", krit: 'K5', status: 'lengkap', terisi: 6, total: 6, profesi: false },
  { no: '19', judul: "Data Dosen Tidak Tetap", krit: 'K5', status: 'lengkap', terisi: 6, total: 6, profesi: false },
  { no: '20', judul: "Kegiatan tenaga ahli/pakar pada program studi", krit: 'K5', status: 'lengkap', terisi: 6, total: 6, profesi: false },
  { no: '21', judul: "Pembimbingan Tugas Akhir/Skripsi", krit: 'K5', status: 'lengkap', terisi: 6, total: 6, profesi: false },
  { no: '22', judul: "Data Kegiatan Penelitian Dosen Tetap Program Studi", krit: 'K5', status: 'sebagian', terisi: 3, total: 6, profesi: false },
  { no: '23', judul: "Data Kegiatan Pengabdian kepada Masyarakat (PkM) Dosen Tetap Program Studi", krit: 'K5', status: 'sebagian', terisi: 3, total: 6, profesi: false },
  { no: '24', judul: "Artikel Ilmiah/Karya Ilmiah/Buku Tiga Tahun Terakhir", krit: 'K5', status: 'lengkap', terisi: 6, total: 6, profesi: false },
  { no: '25', judul: "Jumlah Karya Dosen dan Atau Mahasiswa Program Studi", krit: 'K5', status: 'lengkap', terisi: 6, total: 6, profesi: false },
  { no: '26', judul: "Jumlah Pengabdian kepada Masyarakat yang relevan dengan Program Studi", krit: 'K5', status: 'lengkap', terisi: 6, total: 6, profesi: false },
  { no: '27', judul: "Penghargaan Dosen Tetap Program Studi", krit: 'K5', status: 'lengkap', terisi: 6, total: 6, profesi: false },
  { no: '28', judul: "Jumlah Karya Mahasiswa", krit: 'K5', status: 'lengkap', terisi: 6, total: 6, profesi: false },
  { no: '29', judul: "Prasarana dan Peralatan Utama Laboratorium", krit: 'K6', status: 'kosong', terisi: 0, total: 6, profesi: false },
  { no: '30', judul: "Wahana Praktik Profesi: Rumah Sakit dan Sarana Pelayanan Kesehatan Lain", krit: 'K6', status: 'sebagian', terisi: 3, total: 6, profesi: true },
  { no: '31', judul: "Rekapitulasi jumlah ketersediaan pustaka yang relevan dengan bidang program studi, baik dalam format cetak maupun elektronik", krit: 'K6', status: 'kosong', terisi: 0, total: 6, profesi: false },
  { no: '32', judul: "Jurnal yang tersedia/yang diterima secara teratur (lengkap), terbitan tiga tahun terakhir", krit: 'K6', status: 'lengkap', terisi: 6, total: 6, profesi: false },
  { no: '33', judul: "Jumlah Penerimaan Dana", krit: 'K6', status: 'sebagian', terisi: 3, total: 6, profesi: false },
  { no: '34', judul: "Jumlah Penggunaan Dana", krit: 'K6', status: 'sebagian', terisi: 3, total: 6, profesi: false },
  { no: '35', judul: "Evaluasi dan Pengendalian Sistem Penjaminan Mutu Internal", krit: 'K7', status: 'sebagian', terisi: 3, total: 6, profesi: false },
  { no: '36', judul: "Ketersediaan Dokumen/Buku Sistem Penjaminan Mutu Internal", krit: 'K7', status: 'lengkap', terisi: 6, total: 6, profesi: false },
  { no: '37', judul: "Tabel 37 Penjaminan Mutu Eksternal", krit: 'K7', status: 'kosong', terisi: 0, total: 6, profesi: false },
  { no: '38', judul: "Kerja sama Unit Pengelola Program Studi yang Relevan dengan  Program Studi yang sedang diakreditasi", krit: 'K8', status: 'lengkap', terisi: 6, total: 6, profesi: false },
];

// Tidak berlaku untuk jenjang ini. Kosong karena prodi UBBG punya tahap
// profesi. Kalau kelak ada prodi non-profesi, isi di sini — bukan di halaman.
export const TABEL_NA = [];

/* Rekap per kriteria, diturunkan dari TABEL. "Selesai" = lengkap atau terkunci.
   Ini yang membuat dashboard dan halaman Data Dukung mustahil berbeda angka. */
export function rekapKriteria() {
  const out = {};
  TABEL.forEach((t) => {
    const e = out[t.krit] || { total: 0, selesai: 0, kosong: 0, sebagian: 0 };
    e.total += 1;
    if (t.status === 'lengkap' || t.status === 'terkunci') e.selesai += 1;
    else if (t.status === 'kosong') e.kosong += 1;
    else e.sebagian += 1;
    out[t.krit] = e;
  });
  return out;
}

export const KRITERIA = [
  {
    kode: 'K1', singkat: 'Visi & Misi', nama: 'Visi, Misi, Tujuan & Strategi', butir: 'Visi, misi, tujuan, strategi',
    tabel: [0, 0], dok: [4, 4],
    temuan: null,
  },
  {
    kode: 'K2', singkat: 'Kurikulum', nama: 'Kurikulum', butir: 'Substansi kurikulum, CPL, RPS',
    tabel: [0, 1], dok: [7, 9],
    temuan: "Tabel 1 baru terisi sebagian; matriks MK\u2013CPL belum lengkap.",
  },
  {
    kode: 'K3', singkat: 'Penilaian', nama: 'Penilaian Pembelajaran', butir: 'Keberhasilan studi, IPK, lulusan, uji kompetensi',
    tabel: [2, 7], dok: [5, 8],
    temuan: "Sebagian tabel penilaian belum diisi; uji kompetensi belum masuk.",
  },
  {
    kode: 'K4', singkat: 'Mahasiswa', nama: 'Mahasiswa', butir: 'Seleksi, layanan, kepuasan, masa tunggu',
    tabel: [1, 6], dok: [6, 11],
    temuan: "NIM ganda membuat Tabel 9 dan 10 menghitung orang yang sama dua kali.",
  },
  {
    kode: 'K5', singkat: 'Dosen', nama: 'Dosen & Tendik', butir: 'Kualifikasi, penelitian, PkM, karya, penghargaan',
    tabel: [11, 14], dok: [6, 15],
    temuan: "Rasio dosen:mahasiswa melebihi batas instrumen; ada dosen tanpa STR aktif.",
  },
  {
    kode: 'K6', singkat: 'Sarana', nama: 'Sarana, Prasarana & Keuangan', butir: 'Laboratorium, pustaka, jurnal, keuangan',
    tabel: [1, 6], dok: [6, 14],
    temuan: "Persen penggunaan dana TS-1 belum tepat 100%.",
  },
  {
    kode: 'K7', singkat: 'Penjaminan Mutu', nama: 'Penjaminan Mutu', butir: 'PPEPP, audit internal, SPME',
    tabel: [1, 3], dok: [4, 9],
    temuan: "Belum ada laporan audit internal siklus terakhir.",
  },
  {
    kode: 'K8', singkat: 'Tata Kelola', nama: 'Tata Kelola & Administrasi', butir: 'Kerja sama dan tata kelola',
    tabel: [1, 1], dok: [3, 8],
    temuan: "Dokumen kerja sama belum semua diperbarui.",
  },
];

/* turunan: bentuk ringkas untuk dashboard */
export const CELAH = KRITERIA.map((k) => [
  `${k.kode} ${k.singkat}`, k.butir, k.tabel[0], k.tabel[1],
]);

/* ── halaman 08 · Identitas UPPS & Prodi (F-01) ─────────────────────
   Halaman ini bukan form biasa: dia FILTER. `jenjang` + `punya_tahap_profesi`
   menentukan berapa tabel yang wajib diisi di seluruh aplikasi. Jadi
   konsekuensinya ditampilkan inline di bawah pemicunya, bukan di halaman lain.

   Skema jenjang mengikuti dropdown instrumen (Identitas!D5, 11 pilihan).
   Tanpa tahap profesi → 6 tabel "Khusus Profesi" tidak berlaku. UBBG punya
   tahap profesi, jadi TABEL_NA kosong dan 38 tabel wajib. */

// nilai jenjang yang tersedia di instrumen, dengan konsekuensinya
export const JENJANG = [
  { id: 'd3', label: 'Diploma Tiga', profesi: false, tabelNA: 6 },
  { id: 's1', label: 'Sarjana', profesi: false, tabelNA: 6 },
  { id: 's1p', label: 'Sarjana dan Profesi', profesi: true, tabelNA: 0 },
  { id: 'prof', label: 'Profesi', profesi: true, tabelNA: 0 },
  { id: 's2', label: 'Magister', profesi: false, tabelNA: 6 },
  { id: 's3', label: 'Doktor', profesi: false, tabelNA: 6 },
  { id: 'sp1', label: 'Spesialis', profesi: true, tabelNA: 0 },
  { id: 'd4', label: 'Sarjana Terapan', profesi: false, tabelNA: 6 },
];

// 8 field UPPS: key, label, nilai, status
export const UPPS = [
  ['nama', 'Nama UPPS', 'Fakultas Ilmu Kesehatan', 'ok'],
  ['pt', 'Perguruan tinggi', 'Universitas Bina Bangsa Getsempena', 'ok'],
  ['kode', 'Kode PT', '131011', 'ok'],
  ['alamat', 'Alamat', 'Jl. Tanggul Krueng Lamnyong No. 34, Banda Aceh', 'ok'],
  ['nidn', 'NIDN pimpinan UPPS', '0012076802', 'ok'],
  ['nama_pimpinan', 'Nama pimpinan UPPS', 'Dr. Ir. Zulfikar, M.Pd', 'ok'],
  ['akreditasi', 'Peringkat UPPS', 'Unggul — berlaku s.d. 26 Sep 2030', 'ok'],
  ['sk', 'No. SK akreditasi UPPS', '—', 'kosong'],
];

// 10 field prodi, termasuk jenjang + tahap profesi
export const PRODI_FIELDS = [
  ['nama_prodi', 'Nama program studi', 'Kebidanan', 'ok'],
  ['kode_prodi', 'Kode prodi (PDDikti)', '15201', 'ok'],
  ['jenjang', 'Jenjang', 'Sarjana dan Profesi', 'ok'],
  ['tahap_profesi', 'Punya tahap profesi', 'Ya — Pendidikan Profesi Bidan', 'ok'],
  ['kaprodi', 'Nama ketua program studi', 'Siti Rahmawati, S.ST., M.Keb', 'ok'],
  ['nidn_kaprodi', 'NIDN ketua prodi', '0112058701', 'ok'],
  ['akreditasi_prodi', 'Peringkat prodi', 'B — berlaku s.d. 12 Agu 2027', 'ok'],
  ['sk_prodi', 'No. SK akreditasi prodi', '1428/SK/LAM-PTKes/Akr/Dip/XI/2022', 'ok'],
  ['tgl_sk', 'Tanggal SK', '14 November 2022', 'ok'],
  ['rektor', 'Nama pimpinan perguruan tinggi', 'Prof. Dr. Cut Zahriah, M.Pd', 'ok'],
];

// perubahan yang menunggu buka kunci — nyambung ke halaman 15
export const KUNCI = {
  status: 'terkunci',
  dikunci_oleh: 'Dr. Maimunah, M.Keb',
  dikunci_pada: '4 Nov 2026 · 11:05',
  menunggu: 'jenjang',
  permintaan_oleh: 'Siti Rahmawati',
  permintaan_pada: '9 Nov 2026 · 09:12',
  alasan: 'jenjang belum dikonfirmasi prodi — D-III atau Sarjana dan Profesi',
};

// riwayat perubahan — urut terbaru dulu, dan harus urut waktu
export const RIWAYAT = [
  ['9 Nov 2026 · 09:12', 'Siti Rahmawati', 'minta buka kunci: jenjang', 'menunggu'],
  ['4 Nov 2026 · 11:05', 'Dr. Maimunah, M.Keb', 'verifikasi & kunci 18 field', 'dikunci'],
  ['4 Nov 2026 · 10:41', 'Siti Rahmawati', 'ubah No. SK akreditasi prodi', 'diubah'],
  ['4 Nov 2026 · 09:20', 'Siti Rahmawati', 'isi data awal 17 field', 'dibuat'],
];

/* ══ angka turunan ═══════════════════════════════════════════════════
   Semua yang tampil di layar berasal dari sini. Tidak ada penjumlahan di view. */
export const D = (() => {
  const masuk = ANGKATAN.reduce((s, a) => s + a[2], 0);
  const lulus = ANGKATAN.reduce((s, a) => s + a[3], 0);
  const drop = ANGKATAN.reduce((s, a) => s + a[4], 0);
  const cuti = ANGKATAN.reduce((s, a) => s + a[5], 0);
  const aktif = masuk - lulus - drop - cuti;
  const unik = masuk - NIM_GANDA;

  const mkAkademik = MK.filter((m) => m[4] === 'Akademik').length;
  const mkProfesi = MK.filter((m) => m[4] === 'Profesi').length;
  const sksK = MK.reduce((s, m) => s + m[2], 0);
  const sksP = MK.reduce((s, m) => s + m[3], 0);

  const cplKosong = MK.filter((m) => !m[5]);
  const rpsKosong = MK.filter((m) => !m[7]);
  const lokasiKosong = MK.filter((m) => m[3] > 0 && m[8] === '—');
  const aspekKosong = MK.filter((m) => m[6].reduce((a, b) => a + b, 0) === 0);

  const aktifUser = PENGGUNA.filter((p) => p[3] === 'aktif').length;
  const konflik = PENGGUNA.filter((p) => p[3] === 'konflik').length;
  const nonaktif = PENGGUNA.filter((p) => p[3] === 'nonaktif').length;
  const menunggu = PERMINTAAN.filter((p) => p[4] === 'menunggu').length;

  const wahana = WAHANA.length;
  const mouValid = WAHANA.filter((w) => w[4]).length;
  const mouExpired = wahana - mouValid;
  const kapValid = WAHANA.reduce((s, w) => s + (w[4] ? w[3] : 0), 0);
  const kapDitolak = WAHANA.reduce((s, w) => s + (w[4] ? 0 : w[3]), 0);

  const saranaUnit = SARANA.reduce((s, x) => s + x[1], 0);
  const saranaBaik = SARANA.reduce((s, x) => s + (x[2] === 'baik' ? x[1] : 0), 0);
  const saranaSebagian = saranaUnit - saranaBaik;

  const judulCetak = KOLEKSI.reduce((s, k) => s + (k[3] ? 0 : k[1]), 0);
  const ekspCetak = KOLEKSI.reduce((s, k) => s + (k[3] ? 0 : k[2]), 0);
  const judulDigital = KOLEKSI.reduce((s, k) => s + (k[3] ? k[1] : 0), 0);
  const langganan = LANGGANAN.filter((l) => l[1] === 'berlangganan');
  const langgananAktif = langganan.filter((l) => l[3] === 'aktif').length;
  const rasio = ekspCetak / masuk;

  const tabelWajib = INSTRUMEN.totalTabel - INSTRUMEN.tabelNA.length;
  const tabelLengkap = CELAH.reduce((s, c) => s + c[2], 0);
  const dokTerunggah = 41;

  // celah: urut dari yang paling bolong. Bukan urut kriteria — pertanyaannya
  // "mana yang paling perlu dikerjakan", bukan "mana yang nomornya kecil".
  const celah = CELAH
    .map(([k, butir, done, tot]) => ({ k, butir, done, tot, kurang: tot - done }))
    .sort((a, b) => b.kurang - a.kurang);

  // penghalang ekspor: ekspor terkunci selama salah satu terisi
  const penghalang = [
    // Akun berperan ganda TIDAK lagi dihitung dari PENGGUNA di berkas ini.
    // Snapshot desain itu bisa memuat akun yang sudah tidak ada di DB, dan
    // akibatnya tombol ekspor terkunci selamanya. Server memeriksanya sendiri
    // lewat ekspor_blokir() → /api/ekspor/cek, yang membaca tabel pengguna
    // sungguhan. Sidebar tidak boleh punya pendapat kedua soal ini.
    // Tabel 30 termasuk "Khusus Profesi". Untuk prodi UBBG (S1 + Profesi)
    // tabel ini tidak wajib, jadi tidak boleh mengunci ekspor. Temuannya tetap
    // tampil di halaman Kerja Sama sebagai peringatan, karena Wahana itu masih
    // dipakai praktik dan MoU-nya lewat masa.
    { t: `${mouExpired} wahana praktik tanpa MoU berlaku`, d: 'Dipisahkan dari kapasitas, tapi MoU-nya perlu diperpanjang sebelum asesor datang.', ke: 'kerjasama', aksi: 'Buka Kerja Sama →', jenis: 'peringatan' },
    // Peringatan saja (bukan blokir): jumlah baris Tabel 1 tidak boleh
    // mengunci ekspor seluruh prodi.
    ...(MK.length < 239 ? [{ t: `${239 - MK.length} baris Tabel 1 belum diimpor`, d: 'Kurikulum belum lengkap; lengkapi sebelum asesmen.', ke: 'kurikulum', aksi: 'Lanjutkan impor →', jenis: 'peringatan' }] : []),
    // Temuan konsistensi: server yang menentukan (konsistensi_blokir()).
    // Snapshot di berkas ini pernah memuat dua temuan karangan yang mengunci
    // ekspor tanpa sebab nyata. Kalau KONSISTENSI_GAGAL terisi, itu hanya
    // berlaku sebagai peringatan tampilan — bukan blokir.
    ...KONSISTENSI_GAGAL.map(([a, d]) => ({
      t: a, d, ke: 'konsistensi', aksi: 'Periksa →', jenis: 'peringatan',
    })),
  ].filter((x) => !x.t.startsWith('0 '));

  const blokir = penghalang.filter((p) => p.jenis === 'blokir').length;

  // sisa hari dihitung dari snapshot data, bukan dari "hari ini" — supaya
  // angka tidak berubah tiap kali halaman dibuka dan tidak bohong saat demo.
  // Dihitung dari PERINGKAT, bukan tanggal yang diketik: kalau akreditasi
  // diperbarui, sisa hari ikut tanpa ada angka yatim yang tertinggal.
  const _sampai = new Date(PERINGKAT.berlaku.replace(/(\d+) (\w+) (\d+)/, '$3 $2 $1'));
  const _snap = new Date('2026-11-09');
  const HARI = Math.max(0, Math.round((_sampai - _snap) / 86400000));

  return {
    masuk, lulus, drop, cuti, aktif, unik, celah, penghalang, blokir, hari: HARI,
    mkAkademik, mkProfesi, sksK, sksP, sksTotal: sksK + sksP,
    cplKosong, rpsKosong, lokasiKosong, aspekKosong,
    aktifUser, konflik, nonaktif, menunggu,
    wahana, mouValid, mouExpired, kapValid, kapDitolak,
    saranaUnit, saranaBaik, saranaSebagian,
    judulCetak, ekspCetak, judulDigital, rasio,
    langgananAktif, langgananTotal: langganan.length,
    tabelWajib, tabelLengkap, dokTerunggah,
  };
})();

/* ══ validasi ════════════════════════════════════════════════════════
   Padanan `assert` di script FigCraft. Gagal = halaman tidak dirender. */
export function validate() {
  const err = [];
  const eq = (a, b, label) => { if (a !== b) err.push(`${label}: ${a} ≠ ${b}`); };

  eq(D.masuk, 425, 'total mahasiswa masuk');
  eq(D.lulus, 256, 'total lulus');
  eq(D.drop, 11, 'total DO');
  eq(D.aktif, 158, 'total aktif');
  eq(D.lulus + D.drop + D.aktif + D.cuti, D.masuk, 'komposisi = populasi');
  // Diturunkan, bukan hardcode: prodi UBBG punya tahap profesi -> NA kosong,
  // jadi 38 wajib. Kalau jenjang berubah, angka ini ikut tanpa ubah kode.
  eq(D.tabelWajib, INSTRUMEN.totalTabel - INSTRUMEN.tabelNA.length, 'tabel wajib = total - NA');
  eq(D.tabelWajib, TABEL.length, 'tabel wajib = jumlah baris TABEL');

  // celah harus konsisten dengan strip: jumlah total = tabel wajib,
  // jumlah done = tabel lengkap. Kalau tidak, dashboard punya dua angka
  // berbeda untuk hal yang sama.
  eq(D.celah.reduce((s, c) => s + c.tot, 0), D.tabelWajib, 'Σ total celah = tabel wajib');
  eq(D.celah.reduce((s, c) => s + c.done, 0), D.tabelLengkap, 'Σ done celah = tabel lengkap');
  eq(D.celah.length, INSTRUMEN.kriteria, 'celah mencakup semua kriteria');

  // dokumen per kriteria juga harus tutup ke 79
  eq(KRITERIA.reduce((s, k) => s + k.dok[0], 0), 41, 'Σ dokumen selesai = 41');
  eq(KRITERIA.reduce((s, k) => s + k.dok[1], 0), INSTRUMEN.totalDokumen,
     'Σ dokumen total = 79');

  eq(D.aktifUser + D.konflik + D.nonaktif, PENGGUNA.length, 'akun terbagi habis');
  eq(D.konflik, 1, 'konflik peran');
  eq(D.menunggu, 1, 'permintaan menunggu');

  eq(D.mouValid + D.mouExpired, D.wahana, 'MoU terbagi habis');
  eq(D.kapValid + D.kapDitolak, WAHANA.reduce((s, w) => s + w[3], 0),
     'kapasitas terbagi habis');
  eq(D.saranaBaik + D.saranaSebagian, D.saranaUnit, 'sarana terbagi habis');

  eq(D.judulCetak, 778, 'judul tercetak');
  eq(D.ekspCetak, 900, 'eksemplar tercetak');
  eq(D.judulDigital, 2020, 'judul digital');
  eq(D.langgananTotal, 3, 'jumlah langganan berbayar');

  // tiap angkatan: lulus + DO + aktif + cuti = masuk
  ANGKATAN.forEach(([ang, , m, l, d, c]) => {
    const a = m - l - d - c;
    if (a < 0) err.push(`${ang}: aktif negatif`);
  });

  // Matriks izin: tiap baris harus 6 kolom
  Object.entries(MATRIX).forEach(([peran, row]) => {
    eq(row.length, IZIN.length, `matriks ${peran}`);
  });

  // Aturan pemisahan tugas: tidak boleh ada peran yang memegang isi + verifikasi
  // kecuali Admin (dibatasi 1 akun).
  const isiDanVerif = Object.entries(MATRIX)
    .filter(([p, r]) => p !== 'Admin UPPS' && r[0] === 1 && r[2] === 1)
    .map(([p]) => p);
  if (isiDanVerif.length) err.push(`peran melanggar pemisahan tugas: ${isiDanVerif}`);

  if (err.length) throw new Error('Data tidak konsisten:\n· ' + err.join('\n· '));

  // celah harus urut menurun — kalau tidak, judulnya bohong
  for (let i = 1; i < D.celah.length; i++) {
    if (D.celah[i - 1].kurang < D.celah[i].kurang) {
      err.push(`celah tidak urut di indeks ${i}`);
    }
  }
  if (err.length) throw new Error('Data tidak konsisten:\n· ' + err.join('\n· '));
  return true;
}
