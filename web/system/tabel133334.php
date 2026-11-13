<?php
declare(strict_types=1);

/**
 * Tabel 13 (masa tunggu lulusan), 33 (penerimaan dana), 34 (penggunaan dana).
 *
 * KENAPA SATU BERKAS: tiga tabel ini berbagi satu hal yang mudah salah —
 * pemetaan label kolom (TS-2/TS-1/TS) ke tahun Masehi, dan aturan "kolom tanpa
 * data = null, bukan 0". Kalau tiap tabel punya salinannya sendiri, cepat atau
 * lambat satu tabel membagi dengan 3 sementara yang lain membagi dengan
 * "jumlah tahun terisi", dan dua tabel melaporkan rata-rata berbeda untuk data
 * yang sama. Semua label di sini dihitung lewat ta_ke_tahun() di hitung.php.
 *
 * ATURAN ANGKA KOSONG: tidak ada baris = null ("belum diisi"), angka 0 hanya
 * kalau memang tercatat nol. Rata-rata dan persen memakai null, tidak pernah
 * INF/NaN: pembagian dengan nol menghasilkan null.
 */

require_once __DIR__ . '/hitung.php';

/** Urutan baris Tabel 33 persis seperti instrumen. */
const T33_SUMBER = ['Mahasiswa', 'Usaha sendiri', 'Pemerintah Pusat', 'Pemerintah Daerah', 'Lain-lain'];

/** Label kolom tahun ketiga tabel, urut lama -> baru. */
const T3334_KOLOM = ['TS-2', 'TS-1', 'TS'];

/**
 * Label kolom -> tahun Masehi untuk satu periode.
 *
 * @return array<string,?int> null kalau periode belum punya tahun_ts.
 */
function t_kolom_tahun(array $periode): array
{
    $out = [];
    foreach (T3334_KOLOM as $label) {
        $out[$label] = ta_ke_tahun($label, $periode);
    }
    return $out;
}

/**
 * Tabel 13 — Masa tunggu lulusan.
 *
 * AMBIGUITAS DOKUMEN (jangan dihapus):
 * Instrumen menulis kolom kedua "6 < MT < 1" — jelas salah ketik, karena tidak
 * mungkin 6 lebih kecil dari 1. Yang dipakai di sini adalah tafsir menurut
 * URUTAN kolom: <6 bulan, 6–18 bulan, >=18 bulan. Tafsir ini disebutkan di
 * keluaran ('catatan'), bukan dipilih diam-diam, supaya asesor melihat dasar
 * pembacaannya dan bisa membantah kalau tafsirnya beda.
 *
 * SUMBER DATA vs KOLOM: tracer menyimpan tiga keranjang (<3, 3–6, >6 bulan),
 * BUKAN tiga kolom instrumen. Pemetaannya:
 *   <6 bulan   = tunggu_lt3 + tunggu_3_6   (dua-duanya di bawah 6 bulan)
 *   6–18 bulan = tunggu_gt6                (satu-satunya keranjang >6 bulan)
 *   >=18 bulan = null                      (TIDAK ADA di data tracer)
 * >=18 ditulis null, bukan 0: nol berarti "terbukti tidak ada", sedangkan di
 * sini artinya "tidak terinci". Selisih terhadap jumlah terlacak dilaporkan
 * per baris ('selisih_terlacak') supaya angkanya tidak terlihat lengkap padahal
 * ada lulusan yang belum masuk keranjang mana pun.
 * ponytail: pecah jadi <18/>=18 kalau tracer nanti punya kolomnya.
 *
 * TAHUN: tracer.tahun_lulus adalah tahun kalender, dan kuncinya unik per tahun.
 * Karena itu dipakai kesamaan tahun (ta_ke_tahun), BUKAN ta_ke_rentang_kalender:
 * rentang 2023–2024 dan 2024–2025 bertumpuk di 2024, dan lulusan 2024 akan
 * terhitung dua kali di dua baris.
 */
function t13_hitung(int $prodiId): array
{
    $periode = periode_prodi($prodiId);
    if ($periode === null) {
        return [
            'periode' => null, 'baris' => [],
            'total' => ['jumlah_lulusan' => 0, 'terlacak' => 0, 'lt6' => 0, 'mt6_18' => 0],
            'catatan' => 'Periode akreditasi prodi belum ditetapkan, jadi TS/TS-1/TS-2 belum bisa dihitung.',
        ];
    }

    $kolom = t_kolom_tahun($periode);
    $baris = [];

    foreach ($kolom as $label => $tahun) {
        $b = ['ta' => $label, 'tahun' => $tahun, 'jumlah_lulusan' => null,
              'terlacak' => null, 'lt6' => null, 'mt6_18' => null, 'gte18' => null,
              'selisih_terlacak' => null, 'sumber_ada' => false];

        if ($tahun !== null) {
            $t = q1('SELECT jumlah_lulusan, terlacak, tunggu_lt3, tunggu_3_6, tunggu_gt6
                     FROM tracer WHERE prodi_id = ? AND tahun_lulus = ?', [$prodiId, $tahun]);
            if ($t !== null) {
                $lt6 = (int) $t['tunggu_lt3'] + (int) $t['tunggu_3_6'];
                $mt6 = (int) $t['tunggu_gt6'];
                $jml = (int) $t['jumlah_lulusan'];
                $ter = (int) $t['terlacak'];

                $b['jumlah_lulusan'] = $jml;
                $b['terlacak'] = $ter;
                $b['lt6'] = $lt6;
                $b['mt6_18'] = $mt6;
                $b['gte18'] = null;                       // lihat catatan sumber di atas
                // Negatif = keranjang masa tunggu melebihi yang terlacak: data
                // tracer tidak konsisten. Dibiarkan terlihat, bukan dirapikan.
                $b['selisih_terlacak'] = $ter - ($lt6 + $mt6);
                $b['sumber_ada'] = true;
            }
        }
        $baris[] = $b;
    }

    $jumlah = 0;
    $terlacak = 0;
    $lt6Total = 0;
    $mt6Total = 0;
    foreach ($baris as $b) {
        $jumlah += (int) $b['jumlah_lulusan'];
        $terlacak += (int) $b['terlacak'];
        $lt6Total += (int) $b['lt6'];
        $mt6Total += (int) $b['mt6_18'];
    }

    return [
        'periode' => ['tahun_ts' => (int) $periode['tahun_ts'], 'tahun_ss' => $periode['tahun_ss']],
        'baris' => $baris,
        'total' => ['jumlah_lulusan' => $jumlah, 'terlacak' => $terlacak,
                    'lt6' => $lt6Total, 'mt6_18' => $mt6Total],
        'catatan' => 'Kolom kedua instrumen tertulis "6 < MT < 1", yang tidak mungkin; '
            . 'dipakai tafsir menurut urutan kolom: <6 bulan, 6-18 bulan, >=18 bulan. '
            . 'Data tracer hanya memecah <3, 3-6, dan >6 bulan, sehingga >=18 bulan '
            . 'dibiarkan kosong (null = belum terinci, bukan nol) dan selisih terhadap '
            . 'lulusan terlacak dilaporkan per baris. Data lama bisa tetap terlihat lengkap '
            . 'walau >=18 bulan belum terisi.',
    ];
}

/** Samakan penulisan sumber dana ke lima baris instrumen. */
function t33_normalisasi(string $s): string
{
    $k = strtolower(trim($s));
    if ($k === '' ) {
        return 'Lain-lain';
    }
    if (str_contains($k, 'mahasiswa')) {
        return 'Mahasiswa';
    }
    if (str_contains($k, 'usaha')) {
        return 'Usaha sendiri';
    }
    if (str_contains($k, 'pusat')) {
        return 'Pemerintah Pusat';
    }
    if (str_contains($k, 'daerah')) {
        return 'Pemerintah Daerah';
    }
    // Apa pun selebihnya masuk Lain-lain, TIDAK dibuang: dana yang tidak
    // dikenal tetap uang yang diterima prodi, dan menghilangkannya membuat
    // total penerimaan lebih kecil dari kenyataan.
    return 'Lain-lain';
}

/**
 * Tabel 33 — Penerimaan dana per sumber (juta rupiah).
 *
 * SATUAN: juta, tidak dikonversi ke rupiah penuh. Kolom DB-nya sudah juta dan
 * instrumen meminta juta; mengonversi di sini hanya menambah peluang salah
 * faktor 1.000.000 di layar.
 *
 * RATA-RATA: dibagi jumlah kolom yang TERISI, bukan selalu 3. Prodi baru punya
 * data 2 tahun; membagi 3 akan melaporkan rata-rata yang terlalu kecil tanpa
 * ada yang tahu sebabnya. Jumlah pembagi dilaporkan di 'catatan'.
 */
function t33_hitung(int $prodiId): array
{
    $periode = periode_prodi($prodiId);
    if ($periode === null) {
        return ['periode' => null, 'baris' => [], 'catatan' => 'Periode akreditasi prodi belum ditetapkan.'];
    }

    $kolom = t_kolom_tahun($periode);
    $tahunKeLabel = [];
    foreach ($kolom as $label => $tahun) {
        if ($tahun !== null) {
            $tahunKeLabel[$tahun] = $label;
        }
    }

    $baris = [];
    foreach (T33_SUMBER as $s) {
        $baris[$s] = ['sumber' => $s, 'juta' => array_fill_keys(T3334_KOLOM, null),
                      'rata_rata' => null, 'tahun_terisi' => 0];
    }

    $luar = [];
    foreach (q('SELECT tahun, sumber, jumlah_juta FROM keuangan_terima WHERE prodi_id = ?', [$prodiId]) as $r) {
        $tahun = (int) $r['tahun'];
        $label = $tahunKeLabel[$tahun] ?? null;
        if ($label === null) {
            $luar[$tahun] = true;   // ada datanya, tapi di luar TS-2..TS
            continue;
        }
        $s = t33_normalisasi((string) $r['sumber']);
        // Satu tahun bisa punya dua baris yang menormalkan ke sumber yang sama
        // (mis. "Pemerintah Pusat" dan "Pusat"): dijumlahkan, bukan ditimpa.
        $baris[$s]['juta'][$label] = ($baris[$s]['juta'][$label] ?? 0.0) + (float) $r['jumlah_juta'];
    }

    foreach ($baris as &$b) {
        $isi = array_values(array_filter($b['juta'], fn($v) => $v !== null));
        $b['tahun_terisi'] = count($isi);
        $b['rata_rata'] = $isi === [] ? null : round(array_sum($isi) / count($isi), 2);
    }
    unset($b);

    return [
        'periode' => ['tahun_ts' => (int) $periode['tahun_ts'], 'tahun_ss' => $periode['tahun_ss']],
        'kolom' => $kolom,
        'baris' => array_values($baris),
        'tahun_di_luar_rentang' => array_keys($luar),
        'catatan' => 'Satuan juta rupiah. Rata-rata = jumlah kolom yang terisi dibagi '
            . 'banyaknya kolom terisi (bukan selalu dibagi 3), supaya prodi yang baru '
            . 'punya data 2 tahun tidak tampak kekurangan dana.',
    ];
}

/**
 * Tabel 34 — Penggunaan dana per jenis (juta rupiah + persen).
 *
 * PERSEN TIDAK DISIMPAN, dihitung dari total penggunaan TAHUN ITU. Kalau
 * disimpan, satu koreksi nominal membuat total dan persen tidak lagi cocok dan
 * tidak ada cara mengetahui mana yang benar.
 *
 * Pembagian nol -> null. Tahun yang totalnya 0 (semua jenis tercatat 0) tidak
 * punya persen yang bermakna; menulis 0% berarti "jenis ini tidak dapat dana",
 * padahal yang benar "tidak ada dana sama sekali tahun itu".
 *
 * PERSEN DIBULATKAN 2 DESIMAL, jadi jumlahnya bisa 99,99 atau 100,01. Selisih
 * itu dilaporkan per tahun ('persen_tahun'[...]['selisih']) dan TIDAK ditambal
 * dengan menggeser pembulatan salah satu baris: instrumen menampilkan angka
 * yang bisa dijumlahkan asesor, dan selisih 0,01 jauh lebih mudah dijelaskan
 * daripada baris yang persennya diubah diam-diam.
 */
function t34_hitung(int $prodiId): array
{
    $periode = periode_prodi($prodiId);
    if ($periode === null) {
        return ['periode' => null, 'baris' => [], 'total_tahun' => [], 'persen_tahun' => [],
                'catatan' => 'Periode akreditasi prodi belum ditetapkan.'];
    }

    $kolom = t_kolom_tahun($periode);
    $tahunKeLabel = [];
    foreach ($kolom as $label => $tahun) {
        if ($tahun !== null) {
            $tahunKeLabel[$tahun] = $label;
        }
    }

    $data = [];       // [jenis][label] = juta
    $luar = [];
    foreach (q('SELECT jenis, tahun, jumlah_juta FROM keuangan_guna WHERE prodi_id = ?', [$prodiId]) as $r) {
        $tahun = (int) $r['tahun'];
        $label = $tahunKeLabel[$tahun] ?? null;
        if ($label === null) {
            $luar[$tahun] = true;
            continue;
        }
        $j = (string) $r['jenis'];
        $data[$j][$label] = ($data[$j][$label] ?? 0.0) + (float) $r['jumlah_juta'];
    }

    $jenis = array_keys($data);
    sort($jenis);

    // Total per tahun lebih dulu: persen tiap baris bergantung padanya.
    $totalTahun = array_fill_keys(T3334_KOLOM, null);
    foreach (T3334_KOLOM as $label) {
        $ada = false;
        $sum = 0.0;
        foreach ($jenis as $j) {
            if (isset($data[$j][$label])) {
                $ada = true;
                $sum += $data[$j][$label];
            }
        }
        $totalTahun[$label] = $ada ? round($sum, 2) : null;
    }

    $baris = [];
    foreach ($jenis as $j) {
        $b = ['jenis' => $j, 'juta' => [], 'persen' => [],
              'total_juta' => null, 'rata_rata_juta' => null];
        $terisi = 0;
        $sumJenis = 0.0;
        foreach (T3334_KOLOM as $label) {
            $v = $data[$j][$label] ?? null;
            $b['juta'][$label] = $v === null ? null : round($v, 2);
            $tot = $totalTahun[$label];
            $b['persen'][$label] = ($v === null || $tot === null || $tot <= 0)
                ? null
                : round($v / $tot * 100, 2);
            if ($v !== null) {
                $terisi++;
                $sumJenis += $v;
            }
        }
        $b['total_juta'] = $terisi === 0 ? null : round($sumJenis, 2);
        $b['rata_rata_juta'] = $terisi === 0 ? null : round($sumJenis / $terisi, 2);
        $baris[] = $b;
    }

    // Jumlah persen per tahun + selisih pembulatan terhadap 100.
    $persenTahun = [];
    foreach (T3334_KOLOM as $label) {
        $sum = 0.0;
        $ada = false;
        foreach ($baris as $b) {
            $p = $b['persen'][$label];
            if ($p !== null) {
                $ada = true;
                $sum += $p;
            }
        }
        $persenTahun[$label] = [
            'jumlah' => $ada ? round($sum, 2) : null,
            'selisih' => $ada ? round(100 - $sum, 2) : null,
        ];
    }

    return [
        'periode' => ['tahun_ts' => (int) $periode['tahun_ts'], 'tahun_ss' => $periode['tahun_ss']],
        'kolom' => $kolom,
        'baris' => $baris,
        'total_tahun' => $totalTahun,
        'persen_tahun' => $persenTahun,
        'tahun_di_luar_rentang' => array_keys($luar),
        'catatan' => 'Persen dihitung dari total penggunaan tahun itu, tidak disimpan. '
            . 'Persen dibulatkan 2 desimal sehingga jumlahnya bisa 99,99% atau 100,01%; '
            . 'selisihnya dilaporkan per tahun dan tidak ditambal. Tahun yang totalnya nol '
            . 'diberi persen kosong (null), bukan 0%.',
    ];
}
