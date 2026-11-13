<?php
declare(strict_types=1);

/**
 * Fondasi perhitungan 38 tabel: menerjemahkan label tahun akademik ke tahun
 * Masehi, dan mengambil dasar angka dari master.
 *
 * KENAPA HARUS SATU TEMPAT: hampir semua tabel memakai label "TS", "TS-1",
 * "TS-2", "SS-5". Kalau tiap tabel menerjemahkannya sendiri, cepat atau lambat
 * ada yang memakai tahun berjalan (kalender) dan ada yang memakai tahun_ts
 * dari periode — dan dua tabel melaporkan angka berbeda untuk tahun yang sama.
 * Asesor akan menanyakannya.
 *
 * Rujukan tunggal: tabel `periode` (tahun_ts, tahun_ss). Semua label dihitung
 * dari sana, tidak pernah dari date('Y').
 */

require_once __DIR__ . '/db.php';

/**
 * Terjemahkan label tahun akademik ke tahun Masehi.
 *
 *   TS   -> periode.tahun_ts
 *   TS-1 -> tahun_ts - 1 ... TS-7
 *   SS   -> periode.tahun_ss
 *   SS-5 -> tahun_ss - 5
 *
 * Label lain (mis. "2025/2026", "2025") diterima apa adanya kalau angkanya
 * masuk akal — sebagian tabel menuliskan tahun langsung.
 *
 * @return int|null null kalau label tidak bisa ditafsirkan; JANGAN diam-diam
 *                  memakai tahun berjalan, karena itu menghasilkan angka salah
 *                  yang terlihat benar.
 */
function ta_ke_tahun(string $label, array $periode): ?int
{
    $s = strtoupper(trim($label));
    if ($s === '') {
        return null;
    }

    $ts = isset($periode['tahun_ts']) ? (int) $periode['tahun_ts'] : 0;
    $ss = isset($periode['tahun_ss']) ? (int) $periode['tahun_ss'] : 0;

    // TS-n / SS-n
    // Modifier /u wajib: tanpa itu en-dash (–) yang multibyte tidak dikenali
    // dan label "TS–2" dari Excel gagal diterjemahkan.
    if (preg_match('/^(TS|SS)\s*[-–]\s*(\d+)$/u', $s, $m)) {
        $dasar = $m[1] === 'TS' ? $ts : $ss;
        return $dasar > 0 ? $dasar - (int) $m[2] : null;
    }
    if ($s === 'TS') {
        return $ts > 0 ? $ts : null;
    }
    if ($s === 'SS') {
        return $ss > 0 ? $ss : null;
    }

    // "2025/2026" -> 2025 (tahun awal tahun akademik)
    if (preg_match('/^(\d{4})\s*\/\s*\d{4}$/', $s, $m)) {
        return (int) $m[1];
    }
    if (preg_match('/^\d{4}$/', $s)) {
        return (int) $s;
    }

    return null;
}

/** Label tahun akademik untuk satu tahun Masehi, relatif ke periode. */
function tahun_ke_ta(int $tahun, array $periode): string
{
    $ts = (int) ($periode['tahun_ts'] ?? 0);
    $ss = (int) ($periode['tahun_ss'] ?? 0);

    // Tahun <= tahun_ss berada di wilayah SS. Urutan pengecekan ini
    // menentukan: kalau TS dicek lebih dulu tanpa batas bawah, tahun 2015
    // akan tertulis "TS-10" padahal seharusnya "SS-5".
    if ($ss > 0 && $tahun <= $ss) {
        $selisih = $ss - $tahun;
        return $selisih === 0 ? 'SS' : 'SS-' . $selisih;
    }
    if ($ts > 0) {
        $selisih = $ts - $tahun;
        if ($selisih >= 0) {
            return $selisih === 0 ? 'TS' : 'TS-' . $selisih;
        }
    }
    return (string) $tahun;
}

/**
 * Daftar tahun akademik yang dipakai tabel bertipe rentang.
 * Contoh: rentang_ta('TS', 7) -> ['TS-7', ..., 'TS'] urut lama ke baru.
 */
function rentang_ta(string $sampai, int $jumlah): array
{
    $out = [];
    for ($i = $jumlah - 1; $i >= 0; $i--) {
        $out[] = $i === 0 ? $sampai : $sampai . '-' . $i;
    }
    return $out;
}

/**
 * Tahun lulus vs tahun akademik lulus.
 * Instrumen memakai tahun akademik (2025 = 2025/2026), sedangkan
 * mahasiswa.tahun_lulus menyimpan tahun kalender. Untuk tabel kelulusan,
 * tahun akademik X berarti lulus pada tahun kalender X atau X+1 ( semester
 * gasal/genap ). Karena itu dipakai rentang, bukan kesamaan.
 *
 * @return array{0:int,1:int} tahun kalender awal dan akhir
 */
function ta_ke_rentang_kalender(int $tahunTa): array
{
    return [$tahunTa, $tahunTa + 1];
}

/** Periode aktif untuk prodi; null kalau belum ditetapkan. */
function periode_prodi(int $prodiId): ?array
{
    $p = q1('SELECT * FROM periode WHERE prodi_id = ? AND aktif = 1 ORDER BY tahun_ts DESC LIMIT 1',
        [$prodiId]);
    return $p ?: null;
}

/**
 * Dasar hitung Tabel 02/05/06/09/10/11: mahasiswa per angkatan.
 *
 * @return array<int,array{masuk:int,aktif:int,lulus:int,do:int,cuti:int,total:int}>
 *         kunci = tahun masuk
 */
function mhs_per_angkatan(int $prodiId): array
{
    $rows = q("SELECT tahun_masuk,
                      COUNT(*) total,
                      SUM(status = 'Aktif') aktif,
                      SUM(status = 'Lulus') lulus,
                      SUM(status = 'DO') do,
                      SUM(status = 'Cuti') cuti
               FROM mahasiswa WHERE prodi_id = ?
               GROUP BY tahun_masuk ORDER BY tahun_masuk", [$prodiId]);
    $out = [];
    foreach ($rows as $r) {
        $th = (int) $r['tahun_masuk'];
        $out[$th] = [
            'masuk' => $th,
            'total' => (int) $r['total'],
            'aktif' => (int) $r['aktif'],
            'lulus' => (int) $r['lulus'],
            'do' => (int) $r['do'],
            'cuti' => (int) $r['cuti'],
        ];
    }
    return $out;
}

/**
 * Lulusan per tahun kalender + jalur + IPK.
 * Dipakai Tabel 03/04 (IPK lulusan) dan 02 (masa studi).
 *
 * @return array<int,array{lulus:int,reguler:int,transfer:int,asing:int,
 *                        ipk_min:?float,ipk_rata:?float,ipk_maks:?float,
 *                        ipk_2_3:int,ipk_3_35:int,ipk_35_4:int,masa_studi:array<int,int>}>
 */
function lulusan_per_tahun(int $prodiId, ?string $tahap = null): array
{
    $where = ['prodi_id = ?', "status = 'Lulus'", 'tahun_lulus IS NOT NULL'];
    $param = [$prodiId];
    if ($tahap !== null) {
        $where[] = 'tahap = ?';
        $param[] = $tahap;
    }

    $rows = q('SELECT tahun_lulus, jalur_masuk, ipk, tahun_masuk
               FROM mahasiswa WHERE ' . implode(' AND ', $where) . '
               ORDER BY tahun_lulus', $param);

    $out = [];
    foreach ($rows as $r) {
        $th = (int) $r['tahun_lulus'];
        if (!isset($out[$th])) {
            $out[$th] = [
                'lulus' => 0, 'reguler' => 0, 'transfer' => 0, 'asing' => 0,
                'ipk_2_3' => 0, 'ipk_3_35' => 0, 'ipk_35_4' => 0,
                'masa_studi' => [], '_ipk' => [],
            ];
        }
        $out[$th]['lulus']++;
        $jalur = $r['jalur_masuk'];
        if ($jalur === 'Reguler') {
            $out[$th]['reguler']++;
        } elseif ($jalur === 'Transfer') {
            $out[$th]['transfer']++;
        } elseif ($jalur === 'Asing') {
            $out[$th]['asing']++;
        }

        $ipk = $r['ipk'] !== null ? (float) $r['ipk'] : null;
        if ($ipk !== null) {
            $out[$th]['_ipk'][] = $ipk;
            if ($ipk >= 2.00 && $ipk <= 3.00) {
                $out[$th]['ipk_2_3']++;
            } elseif ($ipk > 3.00 && $ipk <= 3.50) {
                $out[$th]['ipk_3_35']++;
            } elseif ($ipk > 3.50 && $ipk <= 4.00) {
                $out[$th]['ipk_35_4']++;
            }
        }

        // Masa studi dalam tahun (dibulatkan ke bawah): 2023 -> 2028 = 5 th.
        if ($r['tahun_masuk'] !== null) {
            $masa = $th - (int) $r['tahun_masuk'];
            $out[$th]['masa_studi'][$masa] = ($out[$th]['masa_studi'][$masa] ?? 0) + 1;
        }
    }

    foreach ($out as &$o) {
        $ipk = $o['_ipk'];
        unset($o['_ipk']);
        $o['ipk_min'] = $ipk === [] ? null : min($ipk);
        $o['ipk_rata'] = $ipk === [] ? null : round(array_sum($ipk) / count($ipk), 2);
        $o['ipk_maks'] = $ipk === [] ? null : max($ipk);
    }
    unset($o);

    return $out;
}
