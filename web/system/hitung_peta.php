<?php
declare(strict_types=1);

/**
 * Titik masuk tunggal untuk hitungan tabel data dukung.
 *
 * KENAPA SATU PINTU: tiap tabel punya fungsi sendiri (t02_hitung, t07_hitung,
 * ...) di berkas terpisah. Tanpa pemetaan di satu tempat, halaman harus tahu
 * berkas mana memuat apa — dan begitu satu nama fungsi berubah, halaman
 * rusak tanpa pesan yang jelas.
 *
 * Kembalian selalu bentuk seragam supaya satu komponen tampilan bisa
 * merender semuanya:
 *   [
 *     'judul'   => string,
 *     'kolom'   => ['Tahun', 'Jumlah', ...],   // header kolom
 *     'baris'   => [ ['sel'=>[...], 'kelas'=>''], ... ]   // sel sudah string
 *     'catatan' => string,     // keterbatasan/tafsir yang harus tampil di layar
 *     'kosong'  => string,     // penjelasan kalau belum ada data
 *   ]
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/hitung.php';

/** Tabel yang punya hitungan (bukan sekadar isian/tarikan). */
function hitung_tabel(): array
{
    return [
        '02' => ['judul' => 'Persentase Keberhasilan Studi', 'berkas' => 'tabel02.php', 'fn' => 't02_hitung'],
        '03' => ['judul' => 'Data IPK Lulusan Tahap Akademik', 'berkas' => 'tabel03.php', 'fn' => 't03_hitung'],
        // t04 belum dibuat: Tabel 04 = IPK lulusan tahap PROFESI, sedangkan
        // tabel03.php baru mengerjakan tahap akademik. Dibiarkan terdaftar
        // supaya halaman menampilkan "belum dibuat", bukan hilang tanpa kabar.
        '04' => ['judul' => 'Data IPK Lulusan Tahap Profesi', 'berkas' => 'tabel03.php', 'fn' => 't04_hitung'],
        '05' => ['judul' => 'Lulusan Tepat Waktu Tahap Akademik', 'berkas' => 'tabel050611.php', 'fn' => 't05_hitung'],
        '06' => ['judul' => 'Lulusan Tepat Waktu Tahap Profesi', 'berkas' => 'tabel050611.php', 'fn' => 't06_hitung'],
        '07' => ['judul' => 'Uji Kompetensi (UKMPS-CBT)', 'berkas' => 'tabel0709081012.php', 'fn' => 't07_hitung'],
        '08' => ['judul' => 'Uji Kompetensi (UKMPS-OSCE)', 'berkas' => 'tabel0709081012.php', 'fn' => 't08_hitung'],
        '09' => ['judul' => 'Total Mahasiswa pada UPPS', 'berkas' => 'tabel0709081012.php', 'fn' => 't09_hitung'],
        '10' => ['judul' => 'Mahasiswa Tahap Akademik', 'berkas' => 'tabel0709081012.php', 'fn' => 't10_hitung'],
        '11' => ['judul' => 'Mahasiswa Tahap Profesi', 'berkas' => 'tabel050611.php', 'fn' => 't11_hitung'],
        '12' => ['judul' => 'Kepuasan Mahasiswa', 'berkas' => 'tabel0709081012.php', 'fn' => 't12_hitung'],
        '13' => ['judul' => 'Masa Tunggu Lulusan Mendapatkan Pekerjaan', 'berkas' => 'tabel133334.php', 'fn' => 't13_hitung'],
        '14' => ['judul' => 'Tingkat Kepuasan Pengguna', 'berkas' => 'tabel0709081012.php', 'fn' => 't14_hitung'],
        '33' => ['judul' => 'Jumlah Penerimaan Dana', 'berkas' => 'tabel133334.php', 'fn' => 't33_hitung'],
        '34' => ['judul' => 'Jumlah Penggunaan Dana', 'berkas' => 'tabel133334.php', 'fn' => 't34_hitung'],
    ];
}

/**
 * Samakan bentuk keluaran agar satu komponen tampilan bisa merender semuanya.
 *
 * Tiap fungsi tabel mengembalikan bentuk yang wajar untuk tabelnya sendiri:
 * Tabel 02 punya 'masa_5_6', Tabel 13 punya 'lt6'/'mt6_18'. Memaksa semua
 * fungsi menulis 'sel'=>[...] berarti menyalin urutan kolom di dua tempat
 * (fungsi + definisi kolom) dan cepat atau lambat berbeda.
 *
 * Karena itu bentuknya diturunkan di sini:
 *   - kalau sudah ada 'sel', pakai apa adanya
 *   - kalau ada 'kolom' sebagai daftar kunci, ambil nilainya dari objek baris
 *   - kalau tidak ada 'kolom', susun dari kunci objek baris (urutannya
 *     dipertahankan PHP) dan labelnya dirapikan
 *
 * Kolom yang tidak dikenali tetap ditampilkan dengan nama kuncinya — lebih
 * baik terlihat janggal daripada angkanya hilang tanpa penjelasan.
 */
function hitung_seragamkan(array $h): array
{
    $baris = $h['baris'] ?? [];
    if (!is_array($baris) || $baris === []) {
        $h['kolom'] = $h['kolom'] ?? [];
        return $h;
    }

    // Baris yang seluruh selnya kosong tidak memberi informasi apa pun, tapi
    // menyisakan tabel penuh "—" yang terlihat seperti kerusakan. Buang, dan
    // beri tahu halaman bahwa sumbernya belum diisi.
    if (!isset($baris[0]['per_ta']) && !isset($baris[0]['sel'])) {
        $adaIsi = array_reduce($baris, static function ($c, $b) {
            foreach ($b as $k => $v) {
                if ($k === 'kelas' || $k === 'no') {
                    continue;
                }
                if ($v !== null && $v !== '') {
                    return true;
                }
            }
            return $c;
        }, false);
        if (!$adaIsi) {
            $h['baris'] = [];
            $h['kolom'] = [];
            $h['kosong'] = $h['kosong']
                ?? 'Sumber angka tabel ini belum diisi (keuangan/tracer). Isi dulu di halaman terkait.';
            return $h;
        }
    }

    // `kolom` berbentuk PETA (label => tahun), mis. ['TS-2'=>2023, 'TS-1'=>2024].
    // Kalau dipakai apa adanya sebagai daftar kolom, header tabel jadi
    // "[object Object]". Diubah jadi daftar label di sini.
    if (isset($h['kolom']) && is_array($h['kolom'])
        && $h['kolom'] !== [] && array_keys($h['kolom']) !== range(0, count($h['kolom']) - 1)) {
        $h['kolom_label'] = array_keys($h['kolom']);
        $h['kolom'] = array_keys($h['kolom']);
    }

    // Bentuk PIVOT KEUANGAN (Tabel 33/34): baris = ['sumber'|'jenis' => ...,
    // 'juta' => ['TS-2'=>n, 'TS-1'=>n, 'TS'=>n], 'rata_rata' => n].
    // Kekhususannya: kolom bertipe persen() di Tabel 34 datang dari
    // 'persen_tahun', bukan dari 'juta'.
    if (isset($baris[0]['juta']) && is_array($baris[0]['juta'])) {
        $kolomTa = array_keys($baris[0]['juta']);
        $labelPertama = isset($baris[0]['sumber']) ? 'Sumber dana'
            : (isset($baris[0]['jenis']) ? 'Jenis penggunaan' : 'Keterangan');
        $label = array_merge([$labelPertama], $kolomTa, ['Total', 'Rata-rata']);

        // Tabel 34 butuh pasangan (juta, %) per tahun. Kalau fungsinya
        // menyediakan 'persen_tahun', tampilkan sebagai kolom tambahan
        // per tahun — bukan mengabaikannya, karena persen itulah yang
        // ditanyakan instrumen.
        $adaPersen = !empty($h['persen_tahun']) && is_array($h['persen_tahun']);
        if ($adaPersen) {
            $label = [$labelPertama];
            foreach ($kolomTa as $ta) {
                $label[] = $ta . ' (juta)';
                $label[] = $ta . ' (%)';
            }
            $label[] = 'Total';
            $label[] = 'Rata-rata';
        }

        $baru = [];
        foreach ($baris as $b) {
            $nama = $b['sumber'] ?? ($b['jenis'] ?? null);
            $sel = [$nama];
            if ($adaPersen) {
                foreach ($kolomTa as $ta) {
                    $sel[] = $b['juta'][$ta] ?? null;
                    $p = $b['persen'][$ta] ?? null;
                    // 'persen' bisa berbentuk angka atau ['persen'=>..]. Ambil
                    // angkanya; kalau array mentah dipakai, sel tampil
                    // "[object Object]" dan angkanya hilang.
                    if (is_array($p)) {
                        $p = $p['persen'] ?? $p['nilai'] ?? null;
                    }
                    $sel[] = $p;
                }
            } else {
                foreach ($kolomTa as $ta) {
                    $sel[] = $b['juta'][$ta] ?? null;
                }
            }
            // 'total' mungkin tidak disediakan fungsi; jumlahkan sendiri
            // daripada menampilkan "—" padahal angkanya bisa dihitung.
            $total = $b['total'] ?? null;
            if ($total === null) {
                $isi = array_filter($b['juta'], fn($v) => $v !== null);
                $total = $isi === [] ? null : round(array_sum($isi), 2);
            }
            $sel[] = $total;
            $sel[] = $b['rata_rata'] ?? null;
            $baru[] = ['sel' => $sel, 'kelas' => $b['kelas'] ?? ''];
        }

        // Baris total per tahun.
        if (!empty($h['total_tahun']) && is_array($h['total_tahun'])) {
            $sel = ['Total'];
            if ($adaPersen) {
                foreach ($kolomTa as $ta) {
                    $sel[] = $h['total_tahun'][$ta] ?? null;
                    $p = $h['persen_tahun'][$ta] ?? null;
                    if (is_array($p)) {
                        $p = $p['persen'] ?? $p['nilai'] ?? null;
                    }
                    $sel[] = $p;
                }
            } else {
                foreach ($kolomTa as $ta) {
                    $sel[] = $h['total_tahun'][$ta] ?? null;
                }
            }
            $sel[] = null;
            $sel[] = null;
            $baru[] = ['sel' => $sel, 'kelas' => 'total'];
        }

        $h['baris'] = $baru;
        $h['kolom'] = $label;
        return $h;
    }

    // Bentuk MATRIKS TRANSPOS (Tabel 11): baris = jenis data ('label'), kolom
    // = tahun akademik. Ini kebalikan Tabel 05/06 yang baris = angkatan.
    // Kalau tidak dibedakan, kolom tahun dan kolom keterangan tercampur jadi
    // satu tabel yang tidak bisa dibaca.
    if (isset($baris[0]['label']) && isset($baris[0]['per_ta']) && is_array($baris[0]['per_ta'])
        && !isset($baris[0]['angkatan'])) {
        $kolomTa = $h['kolom_ta'] ?? array_keys($baris[0]['per_ta']);
        $label = array_merge(['No', 'Jenis data'], $kolomTa);
        $baru = [];
        foreach ($baris as $b) {
            $sel = [$b['no'] ?? null, $b['label'] ?? null];
            foreach ($kolomTa as $ta) {
                $sel[] = $b['per_ta'][$ta] ?? null;
            }
            $baru[] = ['sel' => $sel, 'kelas' => $b['kelas'] ?? ''];
        }
        if (!empty($h['total_per_ta']) && is_array($h['total_per_ta'])) {
            $sel = [null, 'Total'];
            foreach ($kolomTa as $ta) {
                $sel[] = $h['total_per_ta'][$ta] ?? 0;
            }
            $baru[] = ['sel' => $sel, 'kelas' => 'total'];
        }
        $h['baris'] = $baru;
        $h['kolom'] = $label;
        return $h;
    }

    // Bentuk MATRIKS: baris berisi ['angkatan'=>x, 'per_ta'=>['TS-6'=>n,...]].
    // Kolomnya datang dari 'kolom_ta' di tingkat atas, bukan dari kunci baris.
    // Kalau ini tidak ditangani, satu sel berisi array dan tampil
    // "[object Object]" — angkanya ada tapi tak terbaca.
    if (isset($baris[0]['per_ta']) && is_array($baris[0]['per_ta'])) {
        $kolomTa = $h['kolom_ta'] ?? array_keys($baris[0]['per_ta']);
        $label = ['Tahun Masuk'];
        foreach ($kolomTa as $ta) {
            $label[] = (string) $ta;
        }
        // Kolom ringkasan tambahan (mis. lulus_sd_ts) ikut ditampilkan kalau ada.
        $tambahan = [];
        foreach (array_keys($baris[0]) as $k) {
            if ($k === 'angkatan' || $k === 'per_ta' || $k === 'kelas') {
                continue;
            }
            $tambahan[] = $k;
            $label[] = hitung_label($k);
        }

        $baru = [];
        foreach ($baris as $b) {
            $sel = [$b['angkatan'] ?? null];
            foreach ($kolomTa as $ta) {
                $sel[] = $b['per_ta'][$ta] ?? null;
            }
            foreach ($tambahan as $k) {
                $sel[] = $b[$k] ?? null;
            }
            $baru[] = ['sel' => $sel, 'kelas' => $b['kelas'] ?? ''];
        }

        // Baris Total per tahun akademik, kalau server menyediakannya.
        if (!empty($h['total_per_ta']) && is_array($h['total_per_ta'])) {
            $sel = ['Total'];
            foreach ($kolomTa as $ta) {
                $sel[] = $h['total_per_ta'][$ta] ?? 0;
            }
            foreach ($tambahan as $k) {
                $sel[] = null;
            }
            $baru[] = ['sel' => $sel, 'kelas' => 'total'];
        }

        $h['baris'] = $baru;
        $h['kolom'] = $label;
        return $h;
    }

    // Sudah berbentuk {sel:[...]}?
    $sudahSel = isset($baris[0]['sel']) && is_array($baris[0]['sel']);

    if (!$sudahSel) {
        // `kolom` bisa berupa daftar kunci atau daftar ['kunci'=>,'label'=>].
        $def = $h['kolom'] ?? null;
        $kunci = [];
        $label = [];
        if (is_array($def) && $def !== []) {
            foreach ($def as $d) {
                if (is_array($d)) {
                    $kunci[] = $d['kunci'] ?? '';
                    $label[] = $d['label'] ?? ($d['kunci'] ?? '');
                } else {
                    $kunci[] = (string) $d;
                    $label[] = hitung_label((string) $d);
                }
            }
        } else {
            // Turunkan dari baris pertama, buang kolom bantu yang tidak
            // untuk dibaca (no tetap dipakai sebagai kolom pertama).
            foreach (array_keys($baris[0]) as $k) {
                $kunci[] = $k;
                $label[] = hitung_label($k);
            }
        }

        $baru = [];
        foreach ($baris as $b) {
            $sel = [];
            foreach ($kunci as $k) {
                $sel[] = $b[$k] ?? null;
            }
            $baru[] = [
                'sel' => $sel,
                'kelas' => $b['kelas'] ?? '',
            ];
        }
        $h['baris'] = $baru;
        $h['kolom'] = $label;
    }

    return $h;
}

/** 'ipk_rata_rata' -> 'Ipk rata rata'. Label lebih rapi diberikan fungsi tabel. */
function hitung_label(string $kunci): string
{
    $kamus = [
        'no' => 'No', 'ta' => 'Tahun Akademik', 'tahun' => 'Tahun',
        'tahun_akademik' => 'Tahun Akademik', 'angkatan' => 'Tahun Masuk',
        'jumlah_lulusan' => 'Jumlah lulusan', 'terlacak' => 'Terlacak',
        'lt6' => '< 6 bulan', 'mt6_18' => '6-18 bulan', 'gte18' => '>= 18 bulan',
        'ipk_minimum' => 'IPK min', 'ipk_rata_rata' => 'IPK rata-rata',
        'ipk_maksimum' => 'IPK maks', 'tanpa_ipk' => 'Tanpa IPK',
        'prodi' => 'Program Studi', 'reguler' => 'Reguler',
        'non_reguler' => 'Non reguler', 'asing' => 'Asing',
        'kelas' => '', 'selisih_terlacak' => 'Selisih terlacak',
        'sumber_ada' => 'Ada sumber',
    ];
    if (isset($kamus[$kunci])) {
        return $kamus[$kunci];
    }
    return ucfirst(str_replace('_', ' ', $kunci));
}

/**
 * Hitung satu tabel. Mengembalikan bentuk seragam di atas, ATAU ['error'=>...]
 * kalau berkas/fungsi belum ada — supaya halaman bilang "belum siap" alih-alih
 * fatal error tanpa penjelasan.
 */
function hitung_satu(string $kode, int $prodiId): array
{
    // Kunci peta berasal dari literal '02'..'38', tapi PHP mengubah kunci
    // numerik jadi int. Tanpa cast, array_keys() mengembalikan int dan
    // pemanggilan dengan tipe string gagal.
    $kode = str_pad((string) $kode, 2, '0', STR_PAD_LEFT);
    $peta = hitung_tabel()[$kode] ?? null;
    if ($peta === null) {
        return ['error' => 'Tabel ' . $kode . ' tidak punya hitungan.'];
    }

    $berkas = __DIR__ . '/' . $peta['berkas'];
    if (!is_file($berkas)) {
        return ['error' => 'Hitungan Tabel ' . $kode . ' belum dibuat (' . $peta['berkas'] . ').'];
    }
    require_once $berkas;

    $fn = $peta['fn'];
    if (!function_exists($fn)) {
        return ['error' => 'Fungsi ' . $fn . ' tidak ditemukan di ' . $peta['berkas'] . '.'];
    }

    try {
        $hasil = $fn($prodiId);
    } catch (Throwable $e) {
        // Kesalahan hitung harus kelihatan sebagai pesan, bukan halaman putih.
        return ['error' => 'Gagal menghitung: ' . $e->getMessage()];
    }

    if (!is_array($hasil)) {
        return ['error' => 'Hitungan Tabel ' . $kode . ' mengembalikan bentuk tak dikenal.'];
    }

    // Beberapa fungsi tidak menerima prodi (Tabel 09 menghitung semua prodi).
    // Panggilan dengan argumen tetap aman untuk PHP selama fungsi tidak
    // menyatakan parameter wajib lain.

    $hasil['judul'] = $hasil['judul'] ?? $peta['judul'];
    return hitung_seragamkan($hasil);
}
