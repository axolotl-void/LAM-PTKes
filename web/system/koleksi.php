<?php
declare(strict_types=1);

/**
 * Koleksi generik: satu mesin untuk banyak halaman master.
 *
 * Kenapa begini, bukan satu modul per halaman: Mahasiswa, Kurikulum, Sarana,
 * Pustaka, Uji Kompetensi, Survei, dan Tracer punya bentuk yang sama — daftar +
 * form + validasi. Menulis tujuh salinan berarti tujuh tempat yang harus
 * diperbaiki tiap kali aturannya berubah, dan tujuh kesempatan berbeda untuk
 * lupa `wajib('ISI')`.
 *
 * Yang TIDAK boleh digeneralisasi: halaman yang punya arti khusus (Kerja Sama
 * karena status MoU dihitung, Dosen karena satu orang lintas prodi, Identitas
 * karena satu baris UPPS). Itu tetap modul sendiri.
 *
 * Aturan tetap dipegang semua koleksi:
 *   - peran diambil dari sesi (wajib/wajib_masuk), tidak dari request
 *   - hapus = aktif=0 kalau tabelnya punya kolom aktif, selain itu DELETE
 *     hanya kalau tidak dipakai tabel lain
 *   - semua tulis lewat PDO prepared statement
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/view.php';

/**
 * Definisi koleksi. `jenis` menentukan validasi dan widget:
 *   teks | panjang | angka | tanggal | pilihan | centang
 * `wajib` = tidak boleh kosong. `unik` = dicek ke DB.
 */
function koleksi_def(): array
{
    static $def = null;
    if ($def !== null) {
        return $def;
    }

    $prodi = ['jenis' => 'pilihan', 'sumber' => 'prodi', 'label' => 'Program studi', 'wajib' => 1];

    $def = [
        /* ── F-05 Mahasiswa ─────────────────────────────────────────── */
        'mahasiswa' => [
            'judul' => 'Mahasiswa',
            'kode' => 'F-05',
            'tabel' => 'mahasiswa',
            'order' => 'tahun_masuk DESC, nama',
            'cari' => ['nama', 'nim'],
            'kolom' => ['NIM', 'Nama', 'Prodi', 'Angkatan', 'Jalur', 'Tahap', 'Status', 'IPK'],
            'field' => [
                'nim' => ['label' => 'NIM', 'jenis' => 'teks', 'wajib' => 1, 'maks' => 24, 'unik' => 1],
                'nama' => ['label' => 'Nama', 'jenis' => 'teks', 'wajib' => 1, 'maks' => 160],
                'prodi_id' => $prodi + ['kolom' => 'prodi_id'],
                'tahun_masuk' => ['label' => 'Tahun masuk', 'jenis' => 'angka', 'wajib' => 1, 'min' => 1990, 'maks' => 2100],
                'jalur_masuk' => ['label' => 'Jalur masuk', 'jenis' => 'pilihan',
                    'opsi' => ['Reguler', 'Transfer', 'Asing'], 'bawaan' => 'Reguler'],
                'tahap' => ['label' => 'Tahap', 'jenis' => 'pilihan',
                    'opsi' => ['Akademik', 'Profesi'], 'bawaan' => 'Akademik'],
                'status' => ['label' => 'Status', 'jenis' => 'pilihan',
                    'opsi' => ['Aktif', 'Lulus', 'DO', 'Cuti'], 'bawaan' => 'Aktif'],
                'ipk' => ['label' => 'IPK', 'jenis' => 'pecahan', 'min' => 0, 'maks' => 4],
                'tanggal_lulus' => ['label' => 'Tanggal lulus', 'jenis' => 'tanggal'],
                'tahun_lulus' => ['label' => 'Tahun lulus', 'jenis' => 'angka', 'min' => 1990, 'maks' => 2100],
            ],
            'keterangan' => 'Satu baris = satu orang (NIM unik). Halaman per angkatan dihitung dari sini, '
                . 'jadi NIM ganda tidak lagi membuat orang yang sama terhitung dua kali.',
        ],

        /* ── Kurikulum: mata kuliah ─────────────────────────────────── */
        'mata_kuliah' => [
            'judul' => 'Kurikulum & Mata Kuliah',
            'kode' => 'F-07',
            'tabel' => 'mata_kuliah',
            'order' => 'tahap, kode_mk',
            'cari' => ['nama_mk', 'kode_mk'],
            'kolom' => ['Kode', 'Mata kuliah', 'Prodi', 'SKS kuliah', 'SKS praktik', 'Tahap', 'RPS'],
            'field' => [
                'prodi_id' => $prodi,
                'kode_mk' => ['label' => 'Kode MK', 'jenis' => 'teks', 'wajib' => 1, 'maks' => 32, 'unik' => 1],
                'nama_mk' => ['label' => 'Nama mata kuliah', 'jenis' => 'teks', 'wajib' => 1, 'maks' => 160],
                'sks_kuliah' => ['label' => 'SKS kuliah', 'jenis' => 'angka', 'min' => 0, 'maks' => 20],
                'sks_praktik' => ['label' => 'SKS praktik', 'jenis' => 'angka', 'min' => 0, 'maks' => 20],
                'tahap' => ['label' => 'Tahap', 'jenis' => 'pilihan',
                    'opsi' => ['Akademik', 'Profesi'], 'bawaan' => 'Akademik'],
                'ada_rps' => ['label' => 'Punya RPS', 'jenis' => 'centang'],
                'aspek_sikap' => ['label' => 'Memuat aspek sikap', 'jenis' => 'centang'],
                'aspek_pengetahuan' => ['label' => 'Memuat aspek pengetahuan', 'jenis' => 'centang'],
                'aspek_ket_umum' => ['label' => 'Memuat keterampilan umum', 'jenis' => 'centang'],
                'aspek_ket_khusus' => ['label' => 'Memuat keterampilan khusus', 'jenis' => 'centang'],
                'cpl' => ['label' => 'CPL yang dibebankan', 'jenis' => 'panjang', 'maks' => 2000],
                'lokasi_praktik' => ['label' => 'Lokasi praktik', 'jenis' => 'teks', 'maks' => 160],
            ],
            'keterangan' => 'RPS dan empat aspek ini yang ditanyakan Tabel 7. '
                . 'Mata kuliah tanpa RPS akan muncul sebagai temuan di halaman Konsistensi.',
        ],

        /* ── Sarana: laboratorium ───────────────────────────────────── */
        'laboratorium' => [
            'judul' => 'Sarana & Laboratorium',
            'kode' => 'F-09',
            'tabel' => 'laboratorium',
            'order' => 'nama_lab',
            'cari' => ['nama_lab', 'alat'],
            'kolom' => ['Laboratorium', 'Prodi', 'Alat', 'Unit', 'Milik', 'Kondisi', 'Logbook'],
            'field' => [
                'prodi_id' => $prodi,
                'nama_lab' => ['label' => 'Nama laboratorium', 'jenis' => 'teks', 'wajib' => 1, 'maks' => 120],
                'alat' => ['label' => 'Alat utama', 'jenis' => 'teks', 'wajib' => 1, 'maks' => 160],
                'jumlah_unit' => ['label' => 'Jumlah unit', 'jenis' => 'angka', 'min' => 0, 'maks' => 65000],
                'kepemilikan' => ['label' => 'Kepemilikan', 'jenis' => 'pilihan',
                    'opsi' => ['SD', 'SW'], 'opsi_teks' => ['SD' => 'Sendiri (SD)', 'SW' => 'Sewa/pinjam (SW)']],
                'kondisi' => ['label' => 'Kondisi', 'jenis' => 'pilihan',
                    'opsi' => ['Baik', 'Rusak Ringan', 'Rusak Berat'], 'bawaan' => 'Baik'],
                'ada_logbook' => ['label' => 'Ada logbook pemakaian', 'jenis' => 'centang'],
                'jam_pakai_per_minggu' => ['label' => 'Jam pakai / minggu', 'jenis' => 'angka', 'min' => 0, 'maks' => 168],
            ],
            'keterangan' => 'Kondisi "Rusak Berat" tidak otomatis salah — yang salah adalah '
                . 'mengklaim alat itu dipakai tanpa logbook.',
        ],

        /* ── Pustaka ────────────────────────────────────────────────── */
        'pustaka' => [
            'judul' => 'Pustaka',
            'kode' => 'F-10',
            'tabel' => 'pustaka',
            'order' => 'jenis, judul',
            'cari' => ['judul', 'nomor'],
            'kolom' => ['Judul', 'Jenis', 'Prodi', 'Judul (jml)', 'Copy', 'Tahun', 'Nomor'],
            'field' => [
                'prodi_id' => $prodi,
                'jenis' => ['label' => 'Jenis', 'jenis' => 'pilihan', 'wajib' => 1,
                    'opsi' => ['Buku', 'Jurnal', 'E-book', 'Prosiding']],
                'judul' => ['label' => 'Judul', 'jenis' => 'teks', 'wajib' => 1, 'maks' => 255],
                'jumlah_judul' => ['label' => 'Jumlah judul', 'jenis' => 'angka', 'min' => 0, 'maks' => 65000],
                'jumlah_copy' => ['label' => 'Jumlah copy', 'jenis' => 'angka', 'min' => 0, 'maks' => 65000],
                'tahun' => ['label' => 'Tahun terbit', 'jenis' => 'angka', 'min' => 1900, 'maks' => 2100],
                'nomor' => ['label' => 'ISSN / ISBN', 'jenis' => 'teks', 'maks' => 60],
            ],
            'keterangan' => 'Tabel 10 membedakan jumlah judul dan jumlah copy — '
                . 'satu judul bisa punya banyak copy.',
        ],

        /* ── Uji kompetensi ─────────────────────────────────────────── */
        'uji_kompetensi' => [
            'judul' => 'Uji Kompetensi',
            'kode' => 'F-12',
            'tabel' => 'uji_kompetensi',
            'order' => 'periode DESC, jenis',
            'cari' => [],
            'kolom' => ['Jenis', 'Prodi', 'Periode', 'Peserta', 'Lulus tahap 1', 'Lulus retake'],
            'field' => [
                'prodi_id' => $prodi,
                'jenis' => ['label' => 'Jenis uji', 'jenis' => 'pilihan', 'wajib' => 1,
                    'opsi' => ['CBT', 'OSCE']],
                'periode' => ['label' => 'Periode (tahun)', 'jenis' => 'angka', 'wajib' => 1, 'min' => 2000, 'maks' => 2100],
                'selesai_stase' => ['label' => 'Peserta selesai stase', 'jenis' => 'angka', 'min' => 0, 'maks' => 65000],
                'peserta_total' => ['label' => 'Peserta total', 'jenis' => 'angka', 'min' => 0, 'maks' => 65000],
                'ft_peserta' => ['label' => 'Peserta first taker', 'jenis' => 'angka', 'min' => 0, 'maks' => 65000],
                'ft_lulus' => ['label' => 'Lulus first taker', 'jenis' => 'angka', 'min' => 0, 'maks' => 65000],
                'rt_peserta' => ['label' => 'Peserta retaker', 'jenis' => 'angka', 'min' => 0, 'maks' => 65000],
                'rt_lulus' => ['label' => 'Lulus retaker', 'jenis' => 'angka', 'min' => 0, 'maks' => 65000],
            ],
            'keterangan' => 'Lulus first taker dan retaker dipisah karena Tabel 12 menilai keduanya '
                . 'berbeda. Lulus retaker tidak boleh dihitung sebagai kelulusan pertama.',
            'cek' => 'uji',
        ],

        /* ── Survei ─────────────────────────────────────────────────── */
        'survei' => [
            'judul' => 'Survei Kepuasan',
            'kode' => 'F-14',
            'tabel' => 'survei',
            'order' => 'periode DESC, jenis, aspek',
            'cari' => ['aspek'],
            'kolom' => ['Jenis', 'Aspek', 'Prodi', 'Periode', 'Skor 1', 'Skor 2', 'Skor 3', 'Skor 4'],
            'field' => [
                'prodi_id' => $prodi,
                'jenis' => ['label' => 'Responden', 'jenis' => 'pilihan', 'wajib' => 1,
                    'opsi' => ['Mahasiswa', 'Pengguna Lulusan', 'Alumni']],
                'aspek' => ['label' => 'Aspek', 'jenis' => 'teks', 'maks' => 160],
                'periode' => ['label' => 'Periode (tahun)', 'jenis' => 'angka', 'min' => 2000, 'maks' => 2100],
                'skor_1' => ['label' => 'Jumlah jawaban skor 1', 'jenis' => 'angka', 'min' => 0, 'maks' => 65000],
                'skor_2' => ['label' => 'Jumlah jawaban skor 2', 'jenis' => 'angka', 'min' => 0, 'maks' => 65000],
                'skor_3' => ['label' => 'Jumlah jawaban skor 3', 'jenis' => 'angka', 'min' => 0, 'maks' => 65000],
                'skor_4' => ['label' => 'Jumlah jawaban skor 4', 'jenis' => 'angka', 'min' => 0, 'maks' => 65000],
                'tindak_lanjut' => ['label' => 'Tindak lanjut', 'jenis' => 'teks', 'maks' => 255],
            ],
            'keterangan' => 'Yang disimpan jumlah jawaban per skor, bukan rata-rata. '
                . 'Rata-rata dihitung sistem supaya bisa diperiksa ulang.',
            'cek' => 'survei',
        ],

        /* ── Tracer study ───────────────────────────────────────────── */
        'tracer' => [
            'judul' => 'Tracer Study',
            'kode' => 'F-15',
            'tabel' => 'tracer',
            'order' => 'tahun_lulus DESC',
            'cari' => [],
            'kolom' => ['Tahun lulus', 'Prodi', 'Lulusan', 'Terlacak', '<3 bln', '3-6 bln', '>6 bln'],
            'field' => [
                'prodi_id' => $prodi,
                'tahun_lulus' => ['label' => 'Tahun lulus', 'jenis' => 'angka', 'wajib' => 1, 'min' => 1990, 'maks' => 2100],
                'jumlah_lulusan' => ['label' => 'Jumlah lulusan', 'jenis' => 'angka', 'wajib' => 1, 'min' => 0, 'maks' => 65000],
                'terlacak' => ['label' => 'Terlacak', 'jenis' => 'angka', 'min' => 0, 'maks' => 65000],
                'tunggu_lt3' => ['label' => 'Dapat kerja < 3 bulan', 'jenis' => 'angka', 'min' => 0, 'maks' => 65000],
                'tunggu_3_6' => ['label' => 'Dapat kerja 3–6 bulan', 'jenis' => 'angka', 'min' => 0, 'maks' => 65000],
                'tunggu_gt6' => ['label' => 'Dapat kerja > 6 bulan', 'jenis' => 'angka', 'min' => 0, 'maks' => 65000],
            ],
            'keterangan' => 'Terlacak tidak boleh lebih besar dari jumlah lulusan.',
            'cek' => 'tracer',
        ],

        /* ── Stase (praktik klinik) ─────────────────────────────────── */
        'stase' => [
            'judul' => 'Stase Praktik Klinik',
            'kode' => 'F-13',
            'tabel' => 'stase',
            'order' => 'nama_stase',
            'cari' => ['nama_stase'],
            'kolom' => ['Stase', 'Prodi', 'Durasi', 'Kuota', 'Pembimbing', 'Wahana'],
            'field' => [
                'prodi_id' => $prodi,
                'nama_stase' => ['label' => 'Nama stase', 'jenis' => 'teks', 'wajib' => 1, 'maks' => 120],
                'durasi_minggu' => ['label' => 'Durasi (minggu)', 'jenis' => 'angka', 'min' => 0, 'maks' => 52],
                'kuota' => ['label' => 'Kuota mahasiswa', 'jenis' => 'angka', 'min' => 0, 'maks' => 65000],
                'pembimbing_id' => ['label' => 'Pembimbing', 'jenis' => 'pilihan', 'sumber' => 'dosen'],
                'wahana_id' => ['label' => 'Wahana', 'jenis' => 'pilihan', 'sumber' => 'wahana'],
            ],
            'keterangan' => 'Pembimbing diambil dari master Dosen, wahana dari master Mitra. '
                . 'Dua-duanya harus diisi lebih dulu.',
        ],
    ];
    return $def;
}

/** Daftar pilihan untuk field bersumber tabel lain. */
function koleksi_pilihan(string $sumber): array
{
    return match ($sumber) {
        'prodi' => q('SELECT id, nama_ps AS teks FROM prodi WHERE aktif = 1 ORDER BY id'),
        'dosen' => q('SELECT id, CONCAT(nama, COALESCE(CONCAT(", ", gelar_akademik), "")) AS teks
                      FROM dosen ORDER BY nama'),
        'wahana' => q('SELECT id, nama AS teks FROM wahana ORDER BY nama'),
        default => [],
    };
}

/**
 * Alias nama halaman -> nama koleksi. Halaman memakai nama menu (kurikulum,
 * sarana), koleksi memakai nama tabel (mata_kuliah, laboratorium). Kalau tidak
 * dipetakan, /api/kurikulum 404 dan halaman kosong tanpa pesan jelas.
 */
function koleksi_alias(string $nama): string
{
    return [
        'kurikulum' => 'mata_kuliah',
        'sarana' => 'laboratorium',
    ][$nama] ?? $nama;
}

function koleksi_ada(string $nama): bool
{
    return isset(koleksi_def()[koleksi_alias($nama)]);
}

/** Nama kolom yang boleh diisi (bukan hasil hitungan). */
function koleksi_field(string $nama): array
{
    return koleksi_def()[koleksi_alias($nama)]['field'];
}

function koleksi_daftar(string $nama, array $saring = []): array
{
    $d = koleksi_def()[koleksi_alias($nama)];
    $kolom = array_keys($d['field']);

    $where = ['1=1'];
    $param = [];
    if (!empty($saring['cari']) && $d['cari']) {
        $bagian = [];
        foreach ($d['cari'] as $c) {
            $bagian[] = "$c LIKE ?";
            $param[] = '%' . $saring['cari'] . '%';
        }
        $where[] = '(' . implode(' OR ', $bagian) . ')';
    }
    if (!empty($saring['prodi_id']) && in_array('prodi_id', $kolom, true)) {
        $where[] = 'prodi_id = ?';
        $param[] = (int) $saring['prodi_id'];
    }

    $rows = q('SELECT id, ' . implode(', ', $kolom) . ' FROM ' . $d['tabel']
        . ' WHERE ' . implode(' AND ', $where) . ' ORDER BY ' . $d['order'], $param);

    // Nama prodi untuk tampilan.
    $prodi = [];
    foreach (q('SELECT id, nama_ps FROM prodi') as $p) {
        $prodi[(int) $p['id']] = $p['nama_ps'];
    }
    foreach ($rows as &$r) {
        $r['id'] = (int) $r['id'];
        if (isset($r['prodi_id'])) {
            $r['prodi_nama'] = $prodi[(int) $r['prodi_id']] ?? '—';
        }
    }
    return $rows;
}

/**
 * Validasi + simpan. $id = 0 berarti tambah.
 * Semua nilai dikonversi menurut `jenis`; yang tidak dikenal ditolak, bukan
 * dibuang diam-diam — kalau dibuang, operator mengira sudah tersimpan.
 */
function koleksi_simpan(string $nama, array $b, int $id = 0): array
{
    $d = koleksi_def()[koleksi_alias($nama)];
    $field = $d['field'];
    $data = [];

    foreach ($field as $kol => $f) {
        $jenis = $f['jenis'] ?? 'teks';
        $v = $b[$kol] ?? null;

        if ($jenis === 'centang') {
            $data[$kol] = !empty($v) ? 1 : 0;
            continue;
        }

        $s = is_string($v) ? trim($v) : ($v === null ? '' : (string) $v);

        if (!empty($f['wajib']) && $s === '') {
            return ['error' => ($f['label'] ?? $kol) . ' wajib diisi.'];
        }

        if ($s === '') {
            $data[$kol] = !empty($f['bawaan']) ? $f['bawaan'] : null;
            continue;
        }

        switch ($jenis) {
            case 'angka':
                if (!preg_match('/^\d{1,6}$/', $s)) {
                    return ['error' => ($f['label'] ?? $kol) . ' harus angka bulat.'];
                }
                $n = (int) $s;
                if (isset($f['min']) && $n < $f['min']) {
                    return ['error' => ($f['label'] ?? $kol) . ' minimal ' . $f['min'] . '.'];
                }
                if (isset($f['maks']) && $n > $f['maks']) {
                    return ['error' => ($f['label'] ?? $kol) . ' maksimal ' . $f['maks'] . '.'];
                }
                $data[$kol] = $n;
                break;

            case 'pecahan':
                if (!is_numeric($s)) {
                    return ['error' => ($f['label'] ?? $kol) . ' harus angka.'];
                }
                $n = round((float) $s, 2);
                if (isset($f['min']) && $n < $f['min']) {
                    return ['error' => ($f['label'] ?? $kol) . ' minimal ' . $f['min'] . '.'];
                }
                if (isset($f['maks']) && $n > $f['maks']) {
                    return ['error' => ($f['label'] ?? $kol) . ' maksimal ' . $f['maks'] . '.'];
                }
                $data[$kol] = $n;
                break;

            case 'tanggal':
                $t = DateTimeImmutable::createFromFormat('Y-m-d', substr($s, 0, 10));
                if ($t === false || $t->format('Y-m-d') !== substr($s, 0, 10)) {
                    return ['error' => ($f['label'] ?? $kol) . ' harus tanggal yang sah.'];
                }
                $data[$kol] = $t->format('Y-m-d');
                break;

            case 'pilihan':
                $sah = $f['opsi'] ?? array_column(koleksi_pilihan($f['sumber'] ?? ''), 'id');
                if ($f['sumber'] ?? null) {
                    if (!in_array((int) $s, array_map('intval', $sah), true)) {
                        return ['error' => ($f['label'] ?? $kol) . ' tidak dikenal.'];
                    }
                    $data[$kol] = (int) $s;
                } else {
                    if (!in_array($s, $sah, true)) {
                        return ['error' => ($f['label'] ?? $kol) . ' tidak dikenal. Pilihan: '
                            . implode(', ', $sah)];
                    }
                    $data[$kol] = $s;
                }
                break;

            case 'panjang':
                if (isset($f['maks']) && mb_strlen($s) > $f['maks']) {
                    return ['error' => ($f['label'] ?? $kol) . ' maksimal ' . $f['maks'] . ' karakter.'];
                }
                $data[$kol] = $s;
                break;

            default: // teks
                if (isset($f['maks']) && mb_strlen($s) > $f['maks']) {
                    return ['error' => ($f['label'] ?? $kol) . ' maksimal ' . $f['maks'] . ' karakter.'];
                }
                $data[$kol] = $s;
        }
    }

    // Unik: dicek ke DB, bukan hanya di klien.
    foreach ($field as $kol => $f) {
        if (empty($f['unik']) || ($data[$kol] ?? null) === null) {
            continue;
        }
        $bentrok = q1('SELECT id FROM ' . $d['tabel'] . ' WHERE ' . $kol . ' = ? AND id <> ?',
            [$data[$kol], $id]);
        if ($bentrok) {
            return ['error' => ($f['label'] ?? $kol) . ' "' . $data[$kol] . '" sudah dipakai.'];
        }
    }

    // Aturan lintas-field yang tidak bisa ditangkap validasi per kolom.
    $cek = $d['cek'] ?? null;
    if ($cek === 'uji') {
        // Lulus tidak boleh melebihi peserta: kalau lewat, angkanya salah ketik.
        foreach ([['ft_peserta', 'ft_lulus', 'first taker'], ['rt_peserta', 'rt_lulus', 'retaker']] as [$p, $l, $siapa]) {
            if (($data[$p] ?? null) !== null && ($data[$l] ?? null) !== null
                && $data[$l] > $data[$p]) {
                return ['error' => "Lulus {$siapa} tidak boleh melebihi pesertanya."];
            }
        }
        if (($data['peserta_total'] ?? null) !== null
            && (($data['ft_peserta'] ?? 0) + ($data['rt_peserta'] ?? 0)) > $data['peserta_total']) {
            return ['error' => 'Peserta first taker + retaker melebihi peserta total.'];
        }
    }
    if ($cek === 'survei') {
        $jml = 0;
        foreach (['skor_1', 'skor_2', 'skor_3', 'skor_4'] as $s) {
            $jml += (int) ($data[$s] ?? 0);
        }
        if ($jml === 0) {
            return ['error' => 'Isi minimal satu jumlah jawaban skor, kalau tidak rata-rata tidak bisa dihitung.'];
        }
    }
    if ($cek === 'tracer') {
        if (($data['terlacak'] ?? 0) > ($data['jumlah_lulusan'] ?? 0)) {
            return ['error' => 'Terlacak tidak boleh melebihi jumlah lulusan.'];
        }
        $tunggu = (int) ($data['tunggu_lt3'] ?? 0) + (int) ($data['tunggu_3_6'] ?? 0) + (int) ($data['tunggu_gt6'] ?? 0);
        if (($data['terlacak'] ?? 0) > 0 && $tunggu > (int) $data['terlacak']) {
            return ['error' => 'Jumlah yang menunggu kerja melebihi jumlah terlacak.'];
        }
    }

    $aksi = $id > 0 ? 'ubah' : 'tambah';
    if ($id > 0 && !q1('SELECT id FROM ' . $d['tabel'] . ' WHERE id = ?', [$id])) {
        return ['error' => 'Data tidak ditemukan.'];
    }

    $idBaru = $id > 0 ? $id : null;
    transaksi(static function () use ($d, $data, $id, $aksi, &$idBaru) {
        if ($id > 0) {
            ubah($d['tabel'], $data, 'id', $id);
        } else {
            $idBaru = sisip($d['tabel'], $data);
        }
        ex('INSERT INTO audit (pengguna_id, prodi_id, aksi, entitas, entitas_id, nilai_baru)
            VALUES (?,?,?,?,?,?)',
            [pengguna()['id'] ?? null, prodi_aktif(), $aksi, $d['tabel'], (string) $idBaru,
             json_encode($data, JSON_UNESCAPED_UNICODE)]);
    });

    return ['ok' => true, 'id' => $idBaru];
}

/** Hapus. Tabel bertanda `aktif` dinonaktifkan, bukan dihapus. */
function koleksi_hapus(string $nama, int $id): array
{
    $d = koleksi_def()[koleksi_alias($nama)];
    $row = q1('SELECT * FROM ' . $d['tabel'] . ' WHERE id = ?', [$id]);
    if (!$row) {
        return ['error' => 'Data tidak ditemukan.'];
    }

    ex('DELETE FROM ' . $d['tabel'] . ' WHERE id = ?', [$id]);
    ex('INSERT INTO audit (pengguna_id, prodi_id, aksi, entitas, entitas_id, nilai_lama)
        VALUES (?,?,?,?,?,?)',
        [pengguna()['id'] ?? null, prodi_aktif(), 'hapus', $d['tabel'], (string) $id,
         json_encode($row, JSON_UNESCAPED_UNICODE)]);

    return ['ok' => true, 'hapus' => $id];
}
