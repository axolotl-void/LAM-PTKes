<?php
declare(strict_types=1);

/**
 * Tabel 05, 06, 11 — matriks Tahun Masuk × Tahun Akademik.
 *
 * KEPUTUSAN YANG MENENTUKAN SEMUA ANGKA DI BERKAS INI:
 * instrumen memakai TAHUN AKADEMIK ("TS", "TS-1", "2023/2024"), sedangkan
 * master menyimpan TAHUN KALENDER (mahasiswa.tahun_masuk, .tahun_lulus,
 * .tanggal_lulus). Satu TA mencakup DUA tahun kalender:
 *
 *     TA 2023 = 2023/2024 = kalender 2023 (gasal, Jul–Des)
 *                         + kalender 2024 (genap, Jan–Jun)
 *
 * Karena itu:
 *   1) rentang kalender diambil dari ta_ke_rentang_kalender() — bukan ditulis
 *      ulang di sini, supaya tabel lain tidak punya definisi TA yang berbeda;
 *   2) setiap mahasiswa dipetakan ke TEPAT SATU TA oleh ta_lulus(), memakai
 *      tanggal_lulus untuk memilih semester.
 *
 * Tanpa aturan pemilik tunggal itu, TA 2023 dan TA 2024 sama-sama memuat
 * kalender 2024 sehingga satu mahasiswa terhitung dua kali — dan tabel
 * akreditasi yang jumlah lulusannya melebihi jumlah mahasiswanya adalah
 * temuan asesor, bukan angka yang bisa "dibetulkan" belakangan.
 *
 * BENTUK INSTRUMEN (jangan disederhanakan):
 *   Tabel 05: baris = angkatan, kolom TS-6 … TS (7), + Jumlah lulusan s.d. TS
 *   Tabel 06: sama, kolom TS-3 … TS (4), tahap PROFESI
 *   Tabel 11: rekap per TA TS-2 … TS (3): daya tampung (Input), lulus tahap
 *             akademik, mahasiswa baru profesi, total aktif profesi
 *
 * Sel = berapa mahasiswa ANGKATAN ITU yang lulus pada TA kolom tersebut.
 * Jadi sel (2020, TS-2) = mahasiswa tahun_masuk 2020 dengan TA lulus TS-2.
 */

require_once __DIR__ . '/hitung.php';

/**
 * TA pemilik satu kelulusan.
 *
 * tanggal_lulus menentukan semester: Jul–Des milik TA tahun itu, Jan–Jun
 * milik TA tahun sebelumnya. Pengecekan rentangnya memakai
 * ta_ke_rentang_kalender() supaya aturan "satu TA = dua tahun kalender" tetap
 * satu sumber; kalau kelak rentangnya berubah, fungsi ini ikut berubah.
 *
 * Tanpa tanggal_lulus: tahun kalender dipakai apa adanya sebagai TA. Itu
 * tebakan paling tidak mengarang, dan TETAP memetakan satu mahasiswa ke satu
 * TA. Jumlah yang jatuh ke jalur ini dilaporkan di 'diabaikan'.
 */
function ta_lulus(int $tahunLulus, ?string $tanggalLulus): int
{
    $bulan = 0;
    if ($tanggalLulus !== null && strlen($tanggalLulus) >= 7) {
        $bulan = (int) substr($tanggalLulus, 5, 2);
    }
    if ($bulan < 1 || $bulan > 12) {
        return $tahunLulus;
    }

    foreach ([$tahunLulus, $tahunLulus - 1] as $ta) {
        [$awal, $akhir] = ta_ke_rentang_kalender($ta);
        if ($tahunLulus < $awal || $tahunLulus > $akhir) {
            continue;   // kalender ini tidak masuk TA tersebut
        }
        // Di dalam rentang: semester gasal (>= Juli) milik TA tahun kalender,
        // semester genap (<= Juni) milik TA sebelumnya.
        if ($bulan >= 7 && $ta === $tahunLulus) {
            return $ta;
        }
        if ($bulan <= 6 && $ta === $tahunLulus - 1) {
            return $ta;
        }
    }
    return $tahunLulus;
}

/**
 * Kolom TA untuk satu prodi: ['TS-6' => 2019, …, 'TS' => 2025].
 * Kosong kalau periode belum ditetapkan — JANGAN pakai date('Y'), itu
 * menghasilkan tabel yang terlihat benar pada tahun yang salah.
 */
function t050611_kolom(int $prodiId, int $jumlah): array
{
    $per = periode_prodi($prodiId);
    if ($per === null) {
        return [];
    }
    $out = [];
    foreach (rentang_ta('TS', $jumlah) as $label) {
        $tahun = ta_ke_tahun($label, $per);
        if ($tahun !== null) {
            $out[$label] = $tahun;
        }
    }
    return $out;
}

/**
 * Matriks angkatan × TA untuk satu tahap.
 *
 * @return array{kolom_ta:array<int,string>,
 *               baris:array<int,array{angkatan:int,per_ta:array<string,int>,lulus_sd_ts:int}>,
 *               total_per_ta:array<string,int>, catatan:string,
 *               luar_rentang:int, diabaikan:array{tanpa_tahun_lulus:int,di_luar_kolom:int}}
 */
function t050611_matriks(int $prodiId, string $tahap, int $jumlahKolom): array
{
    $kolom = t050611_kolom($prodiId, $jumlahKolom);
    if ($kolom === []) {
        return [
            'kolom_ta' => [], 'baris' => [], 'total_per_ta' => [],
            'luar_rentang' => 0,
            'diabaikan' => ['tanpa_tahun_lulus' => 0, 'di_luar_kolom' => 0],
            'catatan' => 'Periode prodi belum ditetapkan, jadi kolom tahun akademik tidak bisa '
                . 'ditentukan. Tabel dikosongkan, bukan diisi tahun berjalan.',
        ];
    }

    $labelPerTahun = array_flip($kolom);            // tahun TA => label
    $tahunAwal = min($kolom);
    $tahunAkhir = max($kolom);

    // Satu query untuk seluruh matriks. Angkatan dicatat dari SEMUA baris
    // (termasuk yang masih aktif), karena baris angkatan dengan nol lulusan
    // juga informasi: angkatan itu ada, belum ada yang lulus.
    $mhs = q('SELECT tahun_masuk, tahun_lulus, tanggal_lulus, status
              FROM mahasiswa WHERE prodi_id = ? AND tahap = ?', [$prodiId, $tahap]);

    $sel = [];                                     // [angkatan][label] => n
    $totalPerTa = array_fill_keys(array_keys($kolom), 0);
    $angkatan = [];
    $luarRentang = [];
    $tanpaTahun = 0;
    $diLuarKolom = 0;
    $tanpaTanggal = 0;

    foreach ($mhs as $m) {
        $masuk = (int) $m['tahun_masuk'];
        if ($masuk >= $tahunAwal && $masuk <= $tahunAkhir) {
            $angkatan[$masuk] = true;
        }
        if ($m['status'] !== 'Lulus') {
            continue;                              // belum lulus: tidak masuk sel mana pun
        }
        if ($m['tahun_lulus'] === null) {
            $tanpaTahun++;                         // dilaporkan, tidak dibuang diam-diam
            continue;
        }
        $tahunLulus = (int) $m['tahun_lulus'];
        if ($m['tanggal_lulus'] === null) {
            $tanpaTanggal++;
        }
        $label = $labelPerTahun[ta_lulus($tahunLulus, $m['tanggal_lulus'])] ?? null;
        if ($label === null) {
            $diLuarKolom++;                        // lulus di luar rentang kolom tabel
            continue;
        }
        // Angkatan di luar rentang TETAP dapat baris: kalau dibuang, lulusannya
        // hilang dari tabel tanpa jejak dan total kolom tidak bisa dicocokkan.
        $angkatan[$masuk] = true;
        if ($masuk < $tahunAwal || $masuk > $tahunAkhir) {
            $luarRentang[$masuk] = true;
        }
        $sel[$masuk][$label] = ($sel[$masuk][$label] ?? 0) + 1;
        $totalPerTa[$label]++;
    }

    ksort($angkatan);
    $baris = [];
    foreach (array_keys($angkatan) as $th) {
        $perTa = [];
        foreach (array_keys($kolom) as $label) {
            $perTa[$label] = $sel[$th][$label] ?? 0;
        }
        // "Jumlah lulusan s.d. TS": kolom tabel seluruhnya <= TS, jadi sama
        // dengan jumlah sel satu baris. Uji mengunci kesamaan ini; kalau suatu
        // saat tidak sama, ada lulusan yang hilang atau terhitung dua kali.
        $baris[] = ['angkatan' => $th, 'per_ta' => $perTa, 'lulus_sd_ts' => array_sum($perTa)];
    }

    $catatan = sprintf(
        'Kolom memakai tahun akademik: %s … %s, masing-masing mencakup dua tahun kalender '
        . '(gasal Jul–Des tahun itu + genap Jan–Jun tahun berikutnya). Satu mahasiswa masuk '
        . 'tepat satu TA; kalender yang sama tidak dihitung dua kali karena semester dipilih '
        . 'dari tanggal_lulus. %d lulusan tanpa tahun_lulus dan %d lulusan di luar rentang kolom '
        . 'tidak masuk sel mana pun; %d baris angkatan di luar rentang tetap ditampilkan.',
        array_key_first($kolom), array_key_last($kolom),
        $tanpaTahun, $diLuarKolom, count($luarRentang)
    );
    if ($tanpaTanggal > 0) {
        $catatan .= sprintf(' %d lulusan tanpa tanggal_lulus dipetakan dengan tahun kalender '
            . 'apa adanya (TA = tahun lulus).', $tanpaTanggal);
    }

    return [
        'kolom_ta' => array_keys($kolom),
        'baris' => $baris,
        'total_per_ta' => $totalPerTa,
        'catatan' => $catatan,
        'luar_rentang' => count($luarRentang),
        // Angka diagnosa, bukan angka instrumen.
        'diabaikan' => ['tanpa_tahun_lulus' => $tanpaTahun, 'di_luar_kolom' => $diLuarKolom],
    ];
}

/** Tabel 05 — tahap akademik, kolom TS-6 … TS (7 kolom). */
function t05_hitung(int $prodiId): array
{
    return t050611_matriks($prodiId, 'Akademik', 7);
}

/** Tabel 06 — tahap profesi, kolom TS-3 … TS (4 kolom). */
function t06_hitung(int $prodiId): array
{
    return t050611_matriks($prodiId, 'Profesi', 4);
}

/**
 * Daya tampung per TA untuk Tabel 11.
 *
 * Tidak ada master daya tampung di skema: spec_field Tabel 11 menandainya
 * asal 'Input', jadi angkanya dibaca dari isian operator (sel) dan dicocokkan
 * lewat label tahun_akademik di baris yang sama. Belum diisi -> null, BUKAN 0:
 * daya tampung nol berarti "tidak menerima mahasiswa", padahal yang terjadi
 * adalah "belum diisi".
 *
 * @return array<string,?int> label TA => daya tampung
 */
function t11_daya_tampung(int $prodiId, array $kolom): array
{
    $per = periode_prodi($prodiId);
    $out = array_fill_keys(array_keys($kolom), null);
    if ($per === null) {
        return $out;
    }
    $periodeId = (int) qv('SELECT id FROM periode WHERE prodi_id = ? AND aktif = 1
                           ORDER BY tahun_ts DESC LIMIT 1', [$prodiId], 0);

    $labelPerTahun = array_flip($kolom);
    $rows = q("SELECT b.id,
                      s1.nilai_teks                 AS label_ta,
                      s2.nilai_angka                AS daya
               FROM baris_tabel b
               LEFT JOIN sel s1 ON s1.baris_id = b.id AND s1.field_kunci = 'tahun_akademik'
               LEFT JOIN sel s2 ON s2.baris_id = b.id AND s2.field_kunci = 'daya_tampung'
               WHERE b.prodi_id = ? AND b.periode_id = ? AND b.tabel_kode = '11'
               ORDER BY b.urut", [$prodiId, $periodeId]);

    foreach ($rows as $r) {
        if ($r['daya'] === null || $r['label_ta'] === null) {
            continue;
        }
        $tahun = ta_ke_tahun((string) $r['label_ta'], $per);
        $label = $tahun === null ? null : ($labelPerTahun[$tahun] ?? null);
        if ($label !== null) {
            $out[$label] = (int) $r['daya'];
        }
    }
    return $out;
}

/**
 * Tabel 11 — rekap per TA TS-2 … TS.
 *
 * Barisnya KOMPONEN, bukan angkatan: instrumen Tabel 11 memang menanyakan
 * empat besaran untuk tiap tahun akademik, jadi bentuknya tetap matriks
 * komponen × TA. 'lulus_sd_ts' tidak berlaku di sini (null).
 *
 * @return array{kolom_ta:array<int,string>,
 *               baris:array<int,array{no:string,label:string,asal:string,per_ta:array<string,?int>,lulus_sd_ts:null}>,
 *               total_per_ta:array<string,int>, catatan:string,
 *               luar_rentang:int, diabaikan:array{tanpa_tahun_lulus:int,di_luar_kolom:int}}
 */
function t11_hitung(int $prodiId): array
{
    $kolom = t050611_kolom($prodiId, 3);
    if ($kolom === []) {
        return [
            'kolom_ta' => [], 'baris' => [], 'total_per_ta' => [],
            'luar_rentang' => 0,
            'diabaikan' => ['tanpa_tahun_lulus' => 0, 'di_luar_kolom' => 0],
            'catatan' => 'Periode prodi belum ditetapkan, jadi kolom tahun akademik tidak bisa '
                . 'ditetapkan. Tabel dikosongkan, bukan diisi tahun berjalan.',
        ];
    }

    // Lulus tahap akademik per TA: pakai matriks Tabel 05 berkolom 3 (rentang
    // sama: TS-2 … TS). Rumusnya tidak ditulis ulang, jadi mustahil Tabel 11
    // dan Tabel 05 melaporkan angka lulusan akademik yang berbeda.
    $akademik = t050611_matriks($prodiId, 'Akademik', 3);

    $mhs = q('SELECT tahun_masuk, tahun_lulus, tanggal_lulus, status
              FROM mahasiswa WHERE prodi_id = ? AND tahap = ?', [$prodiId, 'Profesi']);

    $baru = array_fill_keys(array_keys($kolom), 0);
    $aktif = array_fill_keys(array_keys($kolom), 0);
    $labelPerTahun = array_flip($kolom);
    foreach ($mhs as $m) {
        $masuk = (int) $m['tahun_masuk'];
        // tahun_masuk adalah tahun kalender mulai studi (September), dan
        // September berada di TA tahun itu juga — jadi dipakai apa adanya.
        $labelMasuk = $labelPerTahun[$masuk] ?? null;
        if ($labelMasuk !== null) {
            $baru[$labelMasuk]++;
        }
        // DO/Cuti tidak dihitung aktif: sama dengan mhs_per_angkatan().
        if ($m['status'] === 'DO' || $m['status'] === 'Cuti') {
            continue;
        }
        $taLulus = $m['tahun_lulus'] === null
            ? null : ta_lulus((int) $m['tahun_lulus'], $m['tanggal_lulus']);
        // Lulus tanpa tahun_lulus tidak bisa ditempatkan pada TA mana pun;
        // menghitungnya aktif di semua TA akan melebih-lebihkan.
        if ($m['status'] === 'Lulus' && $taLulus === null) {
            continue;
        }
        // Aktif pada TA kolom: sudah masuk (<= tahun TA) dan belum lulus pada
        // TA itu. Lulusan TETAP dihitung pada TA sebelum ia lulus — kalau
        // hanya status 'Aktif' yang dihitung, angka TA lampau jadi terlalu
        // kecil dan turun sendiri setiap kali ada mahasiswa lulus.
        foreach ($kolom as $label => $tahunTa) {
            if ($masuk > $tahunTa) {
                continue;
            }
            if ($taLulus !== null && $taLulus <= $tahunTa) {
                continue;
            }
            $aktif[$label]++;
        }
    }

    $daya = t11_daya_tampung($prodiId, $kolom);

    $baris = [
        ['no' => '1', 'label' => 'Daya tampung', 'asal' => 'Input',
         'per_ta' => $daya, 'lulus_sd_ts' => null],
        ['no' => '2', 'label' => 'Lulus tahap akademik', 'asal' => 'Hitung',
         'per_ta' => $akademik['total_per_ta'], 'lulus_sd_ts' => null],
        ['no' => '3', 'label' => 'Mahasiswa baru profesi (reguler/transfer/asing)', 'asal' => 'Hitung',
         'per_ta' => $baru, 'lulus_sd_ts' => null],
        ['no' => '4', 'label' => 'Total aktif profesi (reguler/transfer/asing)', 'asal' => 'Hitung',
         'per_ta' => $aktif, 'lulus_sd_ts' => null],
    ];

    $belum = count(array_filter($daya, fn($v) => $v === null));

    return [
        'kolom_ta' => array_keys($kolom),
        'baris' => $baris,
        'total_per_ta' => $akademik['total_per_ta'],
        'luar_rentang' => $akademik['luar_rentang'],
        'diabaikan' => $akademik['diabaikan'],
        'catatan' => 'Daya tampung TIDAK punya master di skema (spec_field menandainya Input), '
            . 'jadi dibaca dari isian Tabel 11 dan dicocokkan lewat label tahun_akademik; '
            . 'belum diisi ditampilkan null, bukan 0.'
            . ($belum > 0 ? " $belum dari " . count($daya) . ' kolom masih kosong.' : '')
            . ' Lulus tahap akademik memakai rumus Tabel 05 (kolom TS-2 … TS) sehingga angkanya '
            . 'selalu sama dengan Tabel 05. Total aktif profesi = status Aktif yang sudah masuk '
            . 'pada/sebelum TA itu dan belum lulus pada TA itu; Cuti tidak dihitung aktif.'
            . ' ' . $akademik['catatan'],
    ];
}
