<?php
declare(strict_types=1);

/**
 * Tabel 21 — Pembimbingan Tugas Akhir. Dihitung dari tabel `bimbingan`.
 *
 * BENTUK INSTRUMEN: satu baris per dosen, 13 kolom —
 *   No | Nama | Jenjang | Jabatan | mahasiswa TS-2/TS-1/TS |
 *   pertemuan TS-2/TS-1/TS | lama penyelesaian (bulan) TS-2/TS-1/TS
 *   + baris Jumlah dan Rata-rata di kaki tabel.
 *
 * KENAPA DIHITUNG, BUKAN DIKETIK: instrumen menanyakan jumlah mahasiswa,
 * jumlah pertemuan, dan lama penyelesaian per dosen per tahun. Kalau diketik
 * ulang, tiga angka itu bisa saling bertentangan (mis. 5 mahasiswa tapi 0
 * pertemuan) dan asesor menanyakannya. Sumbernya satu: tabel `bimbingan`.
 *
 * Yang TIDAK bisa dihitung dan karena itu ditampilkan null (bukan 0):
 * lama penyelesaian untuk bimbingan yang belum selesai. Nol berarti "selesai
 * dalam 0 bulan" — mustahil, dan menyesatkan.
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/rekap.php';
require_once __DIR__ . '/hitung.php';

/** Tiga kolom tahun akademik yang ditanyakan instrumen. */
const T21_KOLOM = ['TS-2', 'TS-1', 'TS'];

/**
 * Hitung Tabel 21 untuk satu prodi.
 *
 * @return array{
 *   kolom: string[], baris: array<int,array{sel:array,total:bool}>,
 *   catatan: string, kosong: string
 * }
 */
function t21_hitung(int $prodiId): array
{
    $periode = periode_prodi($prodiId);

    $kolom = [
        'No', 'Nama Dosen Pembimbing', 'Jenjang Pendidikan', 'Jabatan Akademik',
        'Mahasiswa TS-2', 'Mahasiswa TS-1', 'Mahasiswa TS',
        'Pertemuan TS-2', 'Pertemuan TS-1', 'Pertemuan TS',
        'Lama (bln) TS-2', 'Lama (bln) TS-1', 'Lama (bln) TS',
    ];

    if ($periode === null) {
        return ['kolom' => $kolom, 'baris' => [],
                'kosong' => 'Periode akreditasi prodi belum ditetapkan, jadi label TS tidak bisa diterjemahkan ke tahun.'];
    }

    // Label -> tahun kalender, sekali di awal. Semua perhitungan memakai peta
    // ini, tidak ada yang memakai date('Y').
    $tahun = [];
    foreach (T21_KOLOM as $ta) {
        $tahun[$ta] = ta_ke_tahun($ta, $periode);
    }

    // Dosen yang pernah membimbing prodi ini. Diambil dari bimbingan, bukan
    // dari seluruh master dosen: dosen yang tidak membimbing tidak punya baris
    // di Tabel 21, dan menampilkannya dengan angka 0 membuat tabel penuh nol.
    $dosen = q(
        'SELECT DISTINCT d.id, d.nama, d.gelar_akademik, d.jabatan_fungsional,
                (SELECT pd.jenjang FROM dosen_pendidikan pd WHERE pd.dosen_id = d.id
                  ORDER BY FIELD(pd.jenjang,"S3","Spesialis-2","Sp-2","S2","Spesialis-1","Sp-1","Profesi","S1","D4","D3"),
                           pd.tahun_lulus DESC LIMIT 1) AS jenjang
         FROM bimbingan b JOIN dosen d ON d.id = b.dosen_id
         WHERE b.prodi_id = ?
         ORDER BY d.nama', [$prodiId]);

    if ($dosen === []) {
        return ['kolom' => $kolom, 'baris' => [],
                'kosong' => 'Belum ada data bimbingan. Tabel ini dihitung dari daftar mahasiswa '
                    . 'yang dibimbing dosen, dan daftar itu belum diisi.'];
    }

    // Satu query untuk semua dosen, bukan per dosen per tahun (3N query).
    $angka = [];
    foreach (q('SELECT dosen_id, tahun_akademik,
                       COUNT(*) AS mhs,
                       COALESCE(SUM(jumlah_pertemuan), 0) AS pertemuan,
                       SUM(CASE WHEN selesai IS NOT NULL AND mulai IS NOT NULL THEN 1 ELSE 0 END) AS selesai,
                       SUM(CASE WHEN selesai IS NOT NULL AND mulai IS NOT NULL
                                THEN TIMESTAMPDIFF(DAY, mulai, selesai) ELSE 0 END) AS hari
                FROM bimbingan WHERE prodi_id = ?
                GROUP BY dosen_id, tahun_akademik', [$prodiId]) as $r) {
        $angka[(int) $r['dosen_id']][(int) $r['tahun_akademik']] = $r;
    }

    $baris = [];
    $totalMhs = array_fill_keys(T21_KOLOM, 0);
    $totalPertemuan = array_fill_keys(T21_KOLOM, 0);
    $totalLama = array_fill_keys(T21_KOLOM, 0.0);
    $nLama = array_fill_keys(T21_KOLOM, 0);
    $no = 0;

    foreach ($dosen as $d) {
        $no++;
        $sel = [
            $no,
            trim($d['nama'] . ($d['gelar_akademik'] ? ', ' . $d['gelar_akademik'] : '')),
            $d['jenjang'] ?? '—',
            $d['jabatan_fungsional'] ?? '—',
        ];

        $mhsKol = [];
        $pertemuanKol = [];
        $lamaKol = [];

        foreach (T21_KOLOM as $ta) {
            $th = $tahun[$ta];
            $a = $th !== null ? ($angka[(int) $d['id']][$th] ?? null) : null;

            $mhs = $a === null ? 0 : (int) $a['mhs'];
            $pert = $a === null ? 0 : (int) $a['pertemuan'];
            $selesai = $a === null ? 0 : (int) $a['selesai'];

            $mhsKol[] = $mhs;
            $pertemuanKol[] = $pert;
            $totalMhs[$ta] += $mhs;
            $totalPertemuan[$ta] += $pert;

            // Lama penyelesaian: hanya dari bimbingan yang SUDAH selesai dan
            // punya tanggal mulai. Belum selesai -> null, bukan 0.
            if ($selesai > 0 && (int) $a['hari'] > 0) {
                $bulan = round(((int) $a['hari'] / 30.44) / $selesai, 1);
                $lamaKol[] = $bulan;
                $totalLama[$ta] += $bulan;
                $nLama[$ta]++;
            } else {
                $lamaKol[] = null;
            }
        }

        $sel = array_merge($sel, $mhsKol, $pertemuanKol, $lamaKol);
        $baris[] = ['sel' => $sel, 'kelas' => ''];
    }

    // Kaki tabel: Jumlah dan Rata-rata, seperti instrumen.
    $jumlah = ['', 'Jumlah', '', ''];
    $rata = ['', 'Rata-rata', '', ''];
    foreach (T21_KOLOM as $ta) {
        $jumlah[] = $totalMhs[$ta];
        $rata[] = null;
    }
    foreach (T21_KOLOM as $ta) {
        $jumlah[] = $totalPertemuan[$ta];
        $rata[] = null;
    }
    foreach (T21_KOLOM as $ta) {
        // Rata-rata lama: dibagi jumlah dosen yang PUNYA data selesai, bukan
        // dibagi semua dosen. Kalau dibagi semua, dosen tanpa bimbingan
        // selesai menarik rata-rata turun seolah-olah bimbingannya cepat.
        $jumlah[] = $nLama[$ta] > 0 ? round($totalLama[$ta], 1) : null;
        $rata[] = $nLama[$ta] > 0 ? round($totalLama[$ta] / $nLama[$ta], 1) : null;
    }

    $baris[] = ['sel' => $jumlah, 'kelas' => 'total'];
    $baris[] = ['sel' => $rata, 'kelas' => 'total'];

    return [
        'kolom' => $kolom,
        'baris' => $baris,
        'catatan' => 'Jumlah mahasiswa dan pertemuan dihitung dari daftar bimbingan. '
            . 'Lama penyelesaian diisi "—" bila bimbingan belum selesai — itu bukan nol bulan, '
            . 'dan rata-ratanya hanya dari bimbingan yang sudah selesai.',
    ];
}
