<?php
declare(strict_types=1);

/**
 * Tabel 03 — Data IPK Lulusan Tahap Akademik. 3 baris: TS-2, TS-1, TS.
 * Seluruhnya dihitung dari master mahasiswa, tidak ada isian.
 *
 * KEPUTUSAN YANG DIAMBIL DI SINI (dan kenapa):
 *
 * 1) TAHUN AKADEMIK, BUKAN RENTANG.
 *    master menyimpan tahun_lulus sebagai tahun kalender. Pemetaan yang
 *    dipakai: tahun_lulus = Y masuk ke TA Y. Alasannya aritmetika:
 *    ta_ke_rentang_kalender(TA) memberi Y..Y+1, dan kalau tiga baris
 *    berturut-turut memakai rentang itu, tahun Y+1 terhitung di DUA baris
 *    (TA Y dan TA Y+1). Jumlah lulusan tiga baris lalu lebih besar daripada
 *    jumlah mahasiswa lulus yang ada — dan Konsistensi R02
 *    ("Jumlah lulusan di Tabel 3 = akumulasi di Tabel 5") langsung gagal.
 *    rentang_ta() + ta_ke_tahun() karena itu dipakai, ta_ke_rentang_kalender()
 *    tidak. Batasnya tetap tahun ajaran: TA Y = Y/Y+1.
 *    KETERBATASAN: lulusan yang yudisiumnya jatuh di awal tahun takwim
 *    berikutnya (Jan-Agu) tetap masuk TA tahun itu, bukan TA sebelumnya —
 *    tanpa tanggal yudisium yang dipakai konsisten, itu pembulatan terdekat
 *    yang jujur.
 *
 * 2) HANYA TAHAP AKADEMIK.
 *    lulusan Profesi punya IPK tahap profesi; mencampurnya membuat rata-rata
 *    IPK akademik tidak bisa dibandingkan dengan prodi lain.
 *
 * 3) TANPA IPK BUKAN IPK 0.
 *    lulusan dengan ipk NULL tetap dihitung di kolom jumlah lulusan (mereka
 *    memang lulus), tetapi dikeluarkan dari MIN/AVG/MAX dan dari ketiga
 *    rentang. Menghitungnya sebagai 0 menarik rata-rata ke bawah dan membuat
 *    tabel terlihat lebih buruk daripada kenyataan. Jumlahnya dilaporkan di
 *    kolom 'tanpa_ipk' dan di 'catatan'.
 */

require_once __DIR__ . '/hitung.php';

/** Baris tabel 03: TS-2, TS-1, TS. */
const T03_BARIS = 3;

/**
 * Hitung Tabel 03 untuk satu prodi.
 *
 * @return array{
 *   baris: array<int,array{no:int,tahun_akademik:string,tahun:?int,
 *     jumlah_lulusan:int,jumlah_lulusan_reguler:int,jumlah_lulusan_transfer:int,
 *     jumlah_lulusan_asing:int,ipk_minimum:?float,ipk_rata_rata:?float,
 *     ipk_maksimum:?float,lulusan_ipk_2_00_3_00:int,lulusan_ipk_3_00_3_50:int,
 *     lulusan_ipk_3_50_4_00:int,tanpa_ipk:int}>,
 *   total: array{...}, catatan: string
 * }
 */
function t03_hitung(int $prodiId): array
{
    $periode = periode_prodi($prodiId);
    if ($periode === null) {
        return [
            'baris' => [],
            'total' => t03_kosong(),
            'catatan' => 'Periode prodi belum ditetapkan, jadi label TS tidak bisa '
                . 'diterjemahkan ke tahun. Tabel tidak dihitung.',
        ];
    }

    $lulus = lulusan_per_tahun($prodiId, 'Akademik');

    // Butuh jumlah yang ipk-nya NULL per tahun. lulusan_per_tahun() sengaja
    // tidak menyimpannya (yang dihitung hanya nilai yang ada), jadi satu query
    // ringkas di sini lebih jelas daripada menebak "lulus - isi rentang",
    // yang salah kalau ada IPK di luar 2,00-4,00.
    $tanpa = [];
    foreach (q("SELECT tahun_lulus, SUM(ipk IS NULL) n FROM mahasiswa
                 WHERE prodi_id = ? AND status = 'Lulus' AND tahap = 'Akademik'
                   AND tahun_lulus IS NOT NULL
                 GROUP BY tahun_lulus", [$prodiId]) as $r) {
        $tanpa[(int) $r['tahun_lulus']] = (int) $r['n'];
    }

    $baris = [];
    $total = t03_kosong();

    foreach (rentang_ta('TS', T03_BARIS) as $i => $label) {
        $tahun = ta_ke_tahun($label, $periode);
        $d = $tahun !== null ? ($lulus[$tahun] ?? null) : null;

        $b = [
            'no' => $i + 1,
            'tahun_akademik' => $label,
            'tahun' => $tahun,
            'jumlah_lulusan' => $d === null ? 0 : (int) $d['lulus'],
            'jumlah_lulusan_reguler' => $d === null ? 0 : (int) $d['reguler'],
            'jumlah_lulusan_transfer' => $d === null ? 0 : (int) $d['transfer'],
            'jumlah_lulusan_asing' => $d === null ? 0 : (int) $d['asing'],
            // null (bukan 0) kalau tidak ada satu pun IPK: kolom desimal yang
            // diisi 0,00 akan dibaca "semua lulusan IPK-nya 0".
            'ipk_minimum' => $d['ipk_min'] ?? null,
            'ipk_rata_rata' => $d['ipk_rata'] ?? null,
            'ipk_maksimum' => $d['ipk_maks'] ?? null,
            'lulusan_ipk_2_00_3_00' => $d === null ? 0 : (int) $d['ipk_2_3'],
            'lulusan_ipk_3_00_3_50' => $d === null ? 0 : (int) $d['ipk_3_35'],
            'lulusan_ipk_3_50_4_00' => $d === null ? 0 : (int) $d['ipk_35_4'],
            'tanpa_ipk' => $tahun === null ? 0 : (int) ($tanpa[$tahun] ?? 0),
        ];

        foreach (['jumlah_lulusan', 'jumlah_lulusan_reguler', 'jumlah_lulusan_transfer',
                  'jumlah_lulusan_asing', 'lulusan_ipk_2_00_3_00',
                  'lulusan_ipk_3_00_3_50', 'lulusan_ipk_3_50_4_00', 'tanpa_ipk'] as $k) {
            $total[$k] += $b[$k];
        }
        if ($b['ipk_minimum'] !== null) {
            $total['ipk_minimum'] = $total['ipk_minimum'] === null
                ? $b['ipk_minimum'] : min($total['ipk_minimum'], $b['ipk_minimum']);
            $total['ipk_maksimum'] = $total['ipk_maksimum'] === null
                ? $b['ipk_maksimum'] : max($total['ipk_maksimum'], $b['ipk_maksimum']);
            // Rata-rata total ditimbang jumlah lulusan ber-IPK per baris,
            // bukan rata-rata dari tiga rata-rata: baris dengan 2 lulusan
            // tidak boleh berbobot sama dengan baris dengan 200 lulusan.
            $n = $b['jumlah_lulusan'] - $b['tanpa_ipk'];
            $total['_bobot'] += $n;
            $total['_jumlah'] += $b['ipk_rata_rata'] * $n;
        }

        $baris[] = $b;
    }

    $total['ipk_rata_rata'] = $total['_bobot'] > 0
        ? round($total['_jumlah'] / $total['_bobot'], 2) : null;
    unset($total['_bobot'], $total['_jumlah']);

    $tanpaSemua = $total['tanpa_ipk'];
    $catatan = 'Hanya lulusan tahap Akademik. Tahun akademik = tahun kalender tahun_lulus '
        . '(TA Y = Y/Y+1) supaya satu kelulusan tidak terhitung di dua baris; lulusan awal '
        . 'tahun takwim berikutnya ikut TA tahun itu. IPK hanya dari lulusan yang punya IPK.';
    if ($tanpaSemua > 0) {
        $catatan .= ' ADA ' . $tanpaSemua . ' lulusan tanpa IPK pada master: tetap dihitung '
            . 'di kolom jumlah lulusan, TIDAK dihitung sebagai IPK 0,00 dan tidak masuk '
            . 'MIN/rata-rata/MAKS maupun ketiga rentang.';
    }

    return ['baris' => $baris, 'total' => $total, 'catatan' => $catatan];
}

/** Kerangka satu baris agregat dengan nilai netral. */
function t03_kosong(): array
{
    return [
        'jumlah_lulusan' => 0,
        'jumlah_lulusan_reguler' => 0,
        'jumlah_lulusan_transfer' => 0,
        'jumlah_lulusan_asing' => 0,
        'ipk_minimum' => null,
        'ipk_rata_rata' => null,
        'ipk_maksimum' => null,
        'lulusan_ipk_2_00_3_00' => 0,
        'lulusan_ipk_3_00_3_50' => 0,
        'lulusan_ipk_3_50_4_00' => 0,
        'tanpa_ipk' => 0,
        '_bobot' => 0,
        '_jumlah' => 0.0,
    ];
}
