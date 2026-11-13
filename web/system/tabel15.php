<?php
declare(strict_types=1);

/**
 * Tabel 15 — Dosen Tetap pada UPPS. Seluruhnya dihitung, tidak ada isian.
 *
 * BENTUK INSTRUMEN (jangan disederhanakan):
 *   baris A = jabatan fungsional: Belum Memiliki, Asisten Ahli, Lektor,
 *             Lektor Kepala, Guru Besar/Profesor
 *   baris B = pendidikan tertinggi: Profesi, Sp-1/DLP/S-2, Sp-2/S-3
 *   kolom   = satu per prodi, lalu Total UPPS
 *
 * CATATAN INSTRUMEN YANG MENENTUKAN RUMUS:
 *   1) Satu dosen tetap BISA terdaftar di lebih dari satu prodi.
 *   2) Kolom Total: setiap dosen tetap dihitung SATU KALI.
 *
 * Akibatnya jumlah kolom prodi boleh MELEBIHI total, dan itu benar. Kalau
 * suatu saat angka itu terlihat "tidak cocok" lalu diperbaiki dengan
 * memaksa jumlah kolom = total, rumusnya jadi salah.
 */

require_once __DIR__ . '/db.php';

const T15_JABATAN = ['Belum Memiliki', 'Asisten Ahli', 'Lektor', 'Lektor Kepala', 'Guru Besar'];

/**
 * Baris B memakai tiga jenjang, sedangkan dosen_pendidikan menyimpan D3/D4/S1/
 * Profesi/S2/S3. Pemetaannya:
 *   Profesi      <- jenjang 'Profesi'
 *   Sp-1/DLP/S-2 <- 'S2' (dan 'Spesialis-1' kalau nanti ada)
 *   Sp-2/S-3     <- 'S3'
 * Yang hanya S1/D4/D3 tidak masuk baris B mana pun — instrumen hanya
 * menanyakan pendidikan TERTINGGI pada tiga tingkat itu. Dosen dengan
 * pendidikan terakhir S1 tetap muncul di blok A (jabatan), tidak di blok B.
 */
const T15_JENJANG = [
    'Profesi' => ['Profesi'],
    'Sp-1/DLP/S-2' => ['S2', 'Spesialis-1', 'Sp-1'],
    'Sp-2/S-3' => ['S3', 'Spesialis-2', 'Sp-2'],
];

/** Tabel yang disajikan sebagai matriks hitung, bukan daftar baris isian. */
function tabel_matriks(): array
{
    return ['15'];
}

/** Prodi yang ikut dihitung: semua prodi aktif di UPPS. */
function t15_prodi(): array
{
    return q('SELECT id, nama_ps FROM prodi WHERE aktif = 1 ORDER BY id');
}

/**
 * Hitung Tabel 15.
 *
 * @return array{
 *   prodi: array<int,array{id:int,nama:string}>,
 *   baris: array<int,array{no:string,hal:string,per_prodi:array<int,int>,total:int,blok:string}>,
 *   total_prodi: array<int,int>, total_upps: int, total_akhir: int,
 *   jumlah_dosen_tetap: int, catatan: string
 * }
 */
function t15_hitung(): array
{
    $prodi = t15_prodi();
    $prodiIds = array_map(fn($p) => (int) $p['id'], $prodi);

    // Dosen tetap + prodi yang diajar + pendidikan tertinggi. Diambil sekali,
    // bukan query per baris: 8 baris x N prodi kalau per baris jadi lambat
    // dan gampang tidak konsisten.
    $dosen = q("SELECT d.id, d.jabatan_fungsional,
                       GROUP_CONCAT(DISTINCT dp.prodi_id) AS prodi_ids,
                       (SELECT pd.jenjang FROM dosen_pendidikan pd
                         WHERE pd.dosen_id = d.id
                         ORDER BY FIELD(pd.jenjang,'S3','Spesialis-2','Sp-2','S2','Spesialis-1','Sp-1','Profesi','S1','D4','D3'),
                                  pd.tahun_lulus DESC LIMIT 1) AS jenjang_tertinggi
                FROM dosen d
                LEFT JOIN dosen_prodi dp ON dp.dosen_id = d.id
                WHERE d.jenis = 'Dosen Tetap'
                GROUP BY d.id
                ORDER BY d.nama");

    // kelompokkan: [blok][label] => ['per_prodi'=>[pid=>n], 'total'=>n]
    $baris = [];
    foreach (T15_JABATAN as $i => $jab) {
        $baris['A'][$jab] = ['no' => 'A' . ($i + 1), 'hal' => $jab, 'blok' => 'A',
                             'per_prodi' => array_fill_keys($prodiIds, 0), 'total' => 0];
    }
    foreach (array_keys(T15_JENJANG) as $i => $jen) {
        $baris['B'][$jen] = ['no' => 'B' . ($i + 1), 'hal' => $jen, 'blok' => 'B',
                             'per_prodi' => array_fill_keys($prodiIds, 0), 'total' => 0];
    }

    $totalProdi = array_fill_keys($prodiIds, 0);
    $totalUpps = 0;

    foreach ($dosen as $d) {
        $pids = array_filter(array_map('intval', explode(',', (string) $d['prodi_ids'])));
        // Hanya hitung prodi yang ada di UPPS ini.
        $pids = array_values(array_intersect($pids, $prodiIds));

        $jab = $d['jabatan_fungsional'];
        if (!in_array($jab, T15_JABATAN, true)) {
            // Jabatan kosong/tak dikenal masuk "Belum Memiliki" — bukan
            // dibuang. Dosen tetap yang belum punya jabatan tetap dosen tetap.
            $jab = 'Belum Memiliki';
        }

        // Blok A: per prodi.
        foreach ($pids as $pid) {
            $baris['A'][$jab]['per_prodi'][$pid]++;
            $totalProdi[$pid]++;
        }
        // Catatan 2: total dihitung SEKALI walau lintas prodi.
        $baris['A'][$jab]['total']++;
        $totalUpps++;

        // Blok B: pendidikan tertinggi.
        $jen = $d['jenjang_tertinggi'];
        foreach (T15_JENJANG as $label => $daftar) {
            if ($jen !== null && in_array($jen, $daftar, true)) {
                foreach ($pids as $pid) {
                    $baris['B'][$label]['per_prodi'][$pid]++;
                }
                $baris['B'][$label]['total']++;
                break;
            }
        }
    }

    $keluar = [];
    foreach ($baris as $blok) {
        foreach ($blok as $b) {
            $keluar[] = $b;
        }
    }

    return [
        'prodi' => array_map(fn($p) => ['id' => (int) $p['id'], 'nama' => $p['nama_ps']], $prodi),
        'baris' => $keluar,
        'total_prodi' => $totalProdi,
        'total_upps' => $totalUpps,
        'jumlah_dosen_tetap' => count($dosen),
        // Dijelaskan di layar supaya tidak ada yang "membetulkan" angkanya.
        'catatan' => 'Kolom Total menghitung setiap dosen satu kali, sedangkan kolom prodi '
            . 'menghitung per penugasan. Karena satu dosen bisa mengajar di dua prodi, '
            . 'jumlah kolom prodi boleh lebih besar dari Total — itu sesuai catatan instrumen.',
    ];
}
