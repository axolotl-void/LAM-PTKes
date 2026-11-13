<?php
declare(strict_types=1);

/**
 * Lapisan BACA bersama. Hanya SELECT, tidak pernah menulis.
 *
 * Kenapa ada berkas ini: dashboard, sidebar, halaman Data Dukung, dan
 * Ringkasan 8 Kriteria harus menampilkan angka yang SAMA. Kalau tiap modul
 * menghitung sendiri, cepat atau lambat angkanya bertengkar — kelas bug yang
 * sudah kejadian di prototipe ("dashboard 26 lengkap, Data Dukung 25").
 *
 * Aturan: angka yang bisa dihitung, dihitung di sini. Tidak ada angka yang
 * diketik di tampilan.
 */

require_once __DIR__ . '/db.php';

/**
 * Status satu tabel untuk satu prodi/periode.
 * Diturunkan dari: jumlah field Input yang wajib, berapa yang terisi,
 * apakah ada baris yang dikunci, dan apakah tabel berlaku untuk jenjang ini.
 *
 * Status TIDAK disimpan di database. Inilah yang membuat "lengkap padahal 3/6"
 * mustahil.
 */
function status_satu_tabel(string $tabelKode, int $prodiId, int $periodeId): array
{
    $info = q1('SELECT kode, khusus_profesi FROM tabel_dukung WHERE kode = ?', [$tabelKode]);
    if (!$info) {
        return ['status' => 'na', 'terisi' => 0, 'total' => 0, 'dikunci' => false, 'berlaku' => false];
    }

    $berlaku = berlaku_untuk_prodi($tabelKode, $prodiId);

    $total = (int) qv('SELECT COUNT(*) FROM spec_field WHERE tabel_kode = ? AND asal = "Input"',
        [$tabelKode], 0);

    $terisi = (int) qv(
        'SELECT COUNT(*) FROM sel s
         JOIN baris_tabel b ON b.id = s.baris_id
         JOIN spec_field f ON f.tabel_kode = b.tabel_kode AND f.kunci = s.field_kunci
         WHERE b.tabel_kode = ? AND b.prodi_id = ? AND b.periode_id = ?
           AND f.asal = "Input"
           AND (s.nilai_angka IS NOT NULL OR (s.nilai_teks IS NOT NULL AND s.nilai_teks <> ""))',
        [$tabelKode, $prodiId, $periodeId], 0);

    $kunci = q1(
        'SELECT COUNT(*) AS n, MAX(dikunci_oleh IS NOT NULL) AS dikunci
         FROM baris_tabel WHERE tabel_kode = ? AND prodi_id = ? AND periode_id = ?',
        [$tabelKode, $prodiId, $periodeId]);

    $adaBaris = (int) ($kunci['n'] ?? 0);
    $dikunci = $adaBaris > 0 && (int) ($kunci['dikunci'] ?? 0) === 1;

    // Tabel dengan 0 field Input TIDAK otomatis lengkap: isinya dihitung sistem
    // dari master, jadi harus ada baris dulu. Tabel 26 misalnya hanya punya
    // 1 field Hitung — kosong sampai master terisi.
    if ($adaBaris === 0) {
        $status = 'kosong';
    } elseif ($total === 0) {
        // tidak ada yang bisa diketik, tapi baris sudah ada -> dianggap lengkap
        $status = 'lengkap';
    } elseif ($terisi >= $total) {
        $status = 'lengkap';
    } elseif ($terisi === 0) {
        $status = 'kosong';
    } else {
        $status = 'sebagian';
    }

    if (!$berlaku) {
        $status = 'na';
    } elseif ($dikunci) {
        $status = 'terkunci';
    }

    return [
        'status' => $status, 'terisi' => $terisi, 'total' => $total,
        'dikunci' => $dikunci, 'berlaku' => $berlaku, 'baris' => $adaBaris,
    ];
}

/**
 * Apakah tabel ini wajib untuk prodi tersebut.
 * 6 tabel khusus profesi (4,6,7,8,11,30) hanya wajib bila punya_tahap_profesi.
 */
function berlaku_untuk_prodi(string $tabelKode, int $prodiId): bool
{
    $khusus = (int) qv('SELECT khusus_profesi FROM tabel_dukung WHERE kode = ?', [$tabelKode], 0);
    if (!$khusus) {
        return true;
    }
    return (int) qv('SELECT punya_tahap_profesi FROM prodi WHERE id = ?', [$prodiId], 0) === 1;
}

/** Semua 38 tabel + status, urut nomor. Satu query per tabel; 38 tabel masih ringan. */
function rekap_semua_tabel(int $prodiId, int $periodeId): array
{
    $daftar = q('SELECT kode, nomor, judul, kriteria, khusus_profesi
                 FROM tabel_dukung ORDER BY nomor');
    foreach ($daftar as &$t) {
        $t += status_satu_tabel($t['kode'], $prodiId, $periodeId);
    }
    return $daftar;
}

/** Rekap per kriteria: total, lengkap, wajib. Diturunkan, bukan diketik.
 *
 * SEMUA 8 kriteria selalu dikembalikan, termasuk yang 0 tabel (K1 hanya
 * dokumen). Kalau kriteria kosong tidak ditampilkan, dashboard kehilangan
 * informasi "K1 tidak punya tabel" dan pembaca mengira ada yang hilang.
 */
function rekap_per_kriteria(int $prodiId, int $periodeId): array
{
    $out = [];
    foreach (semua_kriteria() as $k) {
        $out[$k['kode']] = ['kriteria' => $k['kode'], 'nama' => $k['nama'],
                            'total' => 0, 'wajib' => 0, 'lengkap' => 0,
                            'sebagian' => 0, 'kosong' => 0];
    }
    foreach (rekap_semua_tabel($prodiId, $periodeId) as $t) {
        $k = $t['kriteria'];
        $out[$k]['total']++;
        if ($t['status'] === 'na') {
            continue;
        }
        $out[$k]['wajib']++;
        if (in_array($t['status'], ['lengkap', 'terkunci'], true)) {
            $out[$k]['lengkap']++;
        } else {
            $out[$k][$t['status']]++;
        }
    }
    ksort($out);
    return array_values($out);
}

/** Ringkasan satu baris untuk dashboard. */
function rekap_ringkas(int $prodiId, int $periodeId): array
{
    $semua = rekap_semua_tabel($prodiId, $periodeId);
    $wajib = array_filter($semua, fn($t) => $t['status'] !== 'na');
    $hitung = ['lengkap' => 0, 'terkunci' => 0, 'sebagian' => 0, 'kosong' => 0];
    foreach ($wajib as $t) {
        $hitung[$t['status']] = ($hitung[$t['status']] ?? 0) + 1;
    }
    $selesai = $hitung['lengkap'] + $hitung['terkunci'];
    return [
        'total'    => count($semua),
        'wajib'    => count($wajib),
        'na'       => count($semua) - count($wajib),
        'lengkap'  => $hitung['lengkap'],
        'terkunci' => $hitung['terkunci'],
        'sebagian' => $hitung['sebagian'],
        'kosong'   => $hitung['kosong'],
        'selesai'  => $selesai,
        'belum'    => count($wajib) - $selesai,
    ];
}

/** Dokumen pendukung: 78 butir, berapa yang sudah diunggah prodi ini. */
function rekap_dokumen(int $prodiId, int $periodeId): array
{
    $total = (int) qv('SELECT COUNT(*) FROM dokumen_spec', [], 0);
    $unggah = (int) qv(
        'SELECT COUNT(DISTINCT dok_spec_id) FROM dokumen_unggah
         WHERE prodi_id = ? AND periode_id = ?', [$prodiId, $periodeId], 0);
    $kedaluwarsa = (int) qv(
        'SELECT COUNT(DISTINCT dok_spec_id) FROM dokumen_unggah
         WHERE prodi_id = ? AND periode_id = ? AND berlaku_sampai IS NOT NULL
           AND berlaku_sampai < CURDATE()', [$prodiId, $periodeId], 0);
    return ['total' => $total, 'unggah' => $unggah, 'belum' => $total - $unggah,
            'kedaluwarsa' => $kedaluwarsa];
}

/** Dokumen per kriteria — untuk halaman Dokumen Pendukung. */
function rekap_dokumen_per_kriteria(int $prodiId, int $periodeId): array
{
    return q(
        'SELECT d.kriteria, COUNT(*) AS total,
                SUM(EXISTS(SELECT 1 FROM dokumen_unggah u
                           WHERE u.dok_spec_id = d.id AND u.prodi_id = ? AND u.periode_id = ?)) AS unggah
         FROM dokumen_spec d
         GROUP BY d.kriteria ORDER BY d.kriteria',
        [$prodiId, $periodeId]);
}

/** Periode aktif untuk prodi. Dipakai semua modul; jangan bikin versi sendiri. */
function periode_aktif(int $prodiId): ?array
{
    return q1('SELECT * FROM periode WHERE prodi_id = ? AND aktif = 1
               ORDER BY tahun_ts DESC LIMIT 1', [$prodiId]);
}

/** Kode kriteria -> nama panjang. */
function nama_kriteria(string $kode): string
{
    return (string) qv('SELECT nama FROM kriteria WHERE kode = ?', [$kode], $kode);
}

/** Daftar semua kriteria, urut. */
function semua_kriteria(): array
{
    return q('SELECT kode, nama FROM kriteria ORDER BY kode');
}
