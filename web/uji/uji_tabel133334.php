<?php
declare(strict_types=1);

/**
 * Uji Tabel 13, 33, 34.
 *
 * Yang dijaga di sini bukan cuma "angkanya keluar", tapi tiga hal yang paling
 * mudah dibetulkan salah oleh orang berikutnya:
 *   1) Label TS-2/TS-1/TS dipetakan dari periode.tahun_ts, bukan date('Y').
 *   2) Pembagian nol menghasilkan null, BUKAN 0/INF/NAN.
 *   3) Rata-rata Tabel 33 dibagi jumlah tahun TERISI, bukan selalu 3.
 *   4) Tafsir kolom "6 < MT < 1" pada Tabel 13 disebutkan, tidak disembunyikan.
 *
 * Jalankan: LAMPTKES_ENV=lokal php web/uji/uji_tabel133334.php
 */

require_once __DIR__ . '/../system/config.php';
require_once __DIR__ . '/../system/db.php';
require_once __DIR__ . '/../system/hitung.php';
require_once __DIR__ . '/../system/tabel133334.php';
require_once __DIR__ . '/bersih.php';

$gagal = 0;
$lulus = 0;

function cek(string $nama, bool $ok, string $ket = ''): void
{
    global $gagal, $lulus;
    if ($ok) {
        $lulus++;
        printf("  ok    %s\n", $nama);
    } else {
        $gagal++;
        printf("  GAGAL %s%s\n", $nama, $ket !== '' ? " — $ket" : '');
    }
}

function baris_t13(array $h, string $ta): array
{
    foreach ($h['baris'] as $b) {
        if ($b['ta'] === $ta) {
            return $b;
        }
    }
    return [];
}

function baris_t33(array $h, string $sumber): array
{
    foreach ($h['baris'] as $b) {
        if ($b['sumber'] === $sumber) {
            return $b;
        }
    }
    return [];
}

function baris_t34(array $h, string $jenis): array
{
    foreach ($h['baris'] as $b) {
        if ($b['jenis'] === $jenis) {
            return $b;
        }
    }
    return [];
}

bersihkan_data_uji(db(), true);

$prodi = (int) qv('SELECT id FROM prodi WHERE aktif = 1 ORDER BY id LIMIT 1', [], 0);
cek('ada prodi aktif', $prodi > 0);

$periode = periode_prodi($prodi);
cek('periode aktif ada', $periode !== null);
$ts = (int) ($periode['tahun_ts'] ?? 0);
cek('periode.tahun_ts terbaca', $ts > 0, "ts=$ts");

$tahun = t_kolom_tahun($periode);
cek('TS-2 = tahun_ts - 2', $tahun['TS-2'] === $ts - 2, "dapat {$tahun['TS-2']}");
cek('TS-1 = tahun_ts - 1', $tahun['TS-1'] === $ts - 1, "dapat {$tahun['TS-1']}");
cek('TS   = tahun_ts', $tahun['TS'] === $ts, "dapat {$tahun['TS']}");
$tahunAsal = $ts;
// Bukti pemetaan ikut periode, bukan tanggal server: geser tahun_ts sementara.
ex('UPDATE periode SET tahun_ts = ? WHERE prodi_id = ? AND aktif = 1', [$ts + 3, $prodi]);
$tahunGeser = t_kolom_tahun(periode_prodi($prodi));
cek('ganti tahun_ts -> label TS ikut geser', $tahunGeser['TS'] === $ts + 3,
    'dapat ' . var_export($tahunGeser['TS'], true));
ex('UPDATE periode SET tahun_ts = ? WHERE prodi_id = ? AND aktif = 1', [$tahunAsal, $prodi]);
cek('tahun_ts dipulihkan', (int) periode_prodi($prodi)['tahun_ts'] === $tahunAsal);

echo "\n── Tabel 13: masa tunggu ──\n";

// Data tracer 3 tahun. tunggu_lt3=2, tunggu_3_6=5 -> keduanya <6 bulan = 7.
// tunggu_gt6=3 -> 6-18 bulan. >=18 tidak ada di tracer -> null.
foreach ([
    [$ts - 2, 20, 15, 2, 5, 3],
    [$ts - 1, 21, 18, 3, 6, 4],
    [$ts,     22, 20, 4, 7, 5],
] as [$th, $jml, $ter, $l3, $a36, $g6]) {
    sisip('tracer', ['prodi_id' => $prodi, 'tahun_lulus' => $th, 'jumlah_lulusan' => $jml,
        'terlacak' => $ter, 'tunggu_lt3' => $l3, 'tunggu_3_6' => $a36, 'tunggu_gt6' => $g6]);
}

$h13 = t13_hitung($prodi);
cek('tabel 13 punya 3 baris (TS-2, TS-1, TS)', count($h13['baris']) === 3, 'dapat ' . count($h13['baris']));
cek('urutan baris TS-2, TS-1, TS',
    array_column($h13['baris'], 'ta') === ['TS-2', 'TS-1', 'TS'],
    implode(',', array_column($h13['baris'], 'ta')));

$bTs = baris_t13($h13, 'TS');
cek('TS memakai tahun kalender = tahun_ts', ($bTs['tahun'] ?? null) === $ts, 'dapat ' . ($bTs['tahun'] ?? 'null'));
cek('jumlah lulusan TS diambil dari tracer', ($bTs['jumlah_lulusan'] ?? null) === 22, 'dapat ' . ($bTs['jumlah_lulusan'] ?? 'null'));
cek('terlacak TS diambil dari tracer', ($bTs['terlacak'] ?? null) === 20, 'dapat ' . ($bTs['terlacak'] ?? 'null'));
cek('<6 bulan = tunggu_lt3 + tunggu_3_6 (4+7)', ($bTs['lt6'] ?? null) === 11, 'dapat ' . ($bTs['lt6'] ?? 'null'));
cek('6-18 bulan = tunggu_gt6', ($bTs['mt6_18'] ?? null) === 5, 'dapat ' . ($bTs['mt6_18'] ?? 'null'));
cek('>=18 bulan = null (tidak ada di tracer), BUKAN 0',
    array_key_exists('gte18', $bTs) && $bTs['gte18'] === null,
    'dapat ' . var_export($bTs['gte18'], true));
cek('selisih_terlacak dilaporkan (20 - 16 = 4)', ($bTs['selisih_terlacak'] ?? null) === 4,
    'dapat ' . ($bTs['selisih_terlacak'] ?? 'null'));

$bTs2 = baris_t13($h13, 'TS-2');
cek('TS-2 memakai tahun_ts - 2', ($bTs2['tahun'] ?? null) === $ts - 2, 'dapat ' . ($bTs2['tahun'] ?? 'null'));
cek('TS-2 jumlah lulusan 20', ($bTs2['jumlah_lulusan'] ?? null) === 20, 'dapat ' . ($bTs2['jumlah_lulusan'] ?? 'null'));
cek('total 3 tahun lulusan = 63', $h13['total']['jumlah_lulusan'] === 63, 'dapat ' . $h13['total']['jumlah_lulusan']);
cek('total <6 bulan = 2+5 + 3+6 + 4+7 = 27', $h13['total']['lt6'] === 27, 'dapat ' . $h13['total']['lt6']);

cek('catatan menyebut ambiguitas "6 < MT < 1"', str_contains($h13['catatan'], '6 < MT < 1'));
cek('catatan menyebut tafsir 6-18 bulan', str_contains($h13['catatan'], '6-18'));
cek('catatan menyebut >=18 bulan null, bukan nol', str_contains($h13['catatan'], 'bukan nol'));

// Tahun di luar TS-2..TS tidak boleh bocor ke baris mana pun.
sisip('tracer', ['prodi_id' => $prodi, 'tahun_lulus' => $ts - 5, 'jumlah_lulusan' => 99,
    'terlacak' => 99, 'tunggu_lt3' => 99, 'tunggu_3_6' => 0, 'tunggu_gt6' => 0]);
$h13b = t13_hitung($prodi);
cek('tracer di luar rentang TS-2..TS TIDAK ikut terhitung',
    $h13b['total']['jumlah_lulusan'] === 63, 'dapat ' . $h13b['total']['jumlah_lulusan']);
ex('DELETE FROM tracer WHERE prodi_id = ? AND tahun_lulus = ?', [$prodi, $ts - 5]);

echo "\n── Tabel 13: prodi tanpa data tracer ──\n";
$kosong = t13_hitung(999999);
cek('prodi tanpa periode -> baris kosong, tidak error', $kosong['baris'] === [], 'dapat ' . count($kosong['baris']));
cek('prodi tanpa periode -> catatan menjelaskan', str_contains($kosong['catatan'], 'Periode'));

echo "\n── Tabel 33: penerimaan dana ──\n";

// TS-2 lengkap 5 sumber, TS-1 hanya 2 sumber, TS 3 sumber.
// Tujuannya menguji rata-rata dibagi jumlah tahun TERISI.
$terima = [
    [$ts - 2, 'Mahasiswa', 100.00],
    [$ts - 2, 'Usaha sendiri', 50.00],
    [$ts - 2, 'Pemerintah Pusat', 200.00],
    [$ts - 2, 'Pemerintah Daerah', 30.00],
    [$ts - 2, 'Lain-lain', 20.00],
    [$ts - 1, 'Mahasiswa', 120.00],
    [$ts - 1, 'Pemerintah Pusat', 220.00],
    [$ts, 'Mahasiswa', 140.00],
    [$ts, 'Pemerintah Pusat', 240.00],
    [$ts, 'Pemerintah Daerah', 40.00],
    // Data di luar TS-2..TS: harus dilaporkan, bukan dihitung.
    [$ts - 7, 'Mahasiswa', 999.00],
];
foreach ($terima as [$th, $s, $j]) {
    sisip('keuangan_terima', ['prodi_id' => $prodi, 'tahun' => $th, 'sumber' => $s, 'jumlah_juta' => $j]);
}

$h33 = t33_hitung($prodi);
cek('tabel 33 punya 5 baris sumber', count($h33['baris']) === 5, 'dapat ' . count($h33['baris']));
cek('urutan baris sesuai instrumen',
    array_column($h33['baris'], 'sumber') === ['Mahasiswa', 'Usaha sendiri', 'Pemerintah Pusat', 'Pemerintah Daerah', 'Lain-lain'],
    implode(',', array_column($h33['baris'], 'sumber')));

$m = baris_t33($h33, 'Mahasiswa');
cek('Mahasiswa TS-2 = 100', ($m['juta']['TS-2'] ?? null) === 100.0, 'dapat ' . var_export($m['juta']['TS-2'] ?? null, true));
cek('Mahasiswa TS-1 = 120 (dipetakan dari tahun kalender)', ($m['juta']['TS-1'] ?? null) === 120.0,
    'dapat ' . var_export($m['juta']['TS-1'] ?? null, true));
cek('Mahasiswa TS = 140', ($m['juta']['TS'] ?? null) === 140.0, 'dapat ' . var_export($m['juta']['TS'] ?? null, true));
cek('Mahasiswa rata-rata (100+120+140)/3 = 120', $m['rata_rata'] === 120.0, 'dapat ' . var_export($m['rata_rata'], true));

$u = baris_t33($h33, 'Usaha sendiri');
cek('Usaha sendiri hanya terisi 1 tahun', $u['tahun_terisi'] === 1, 'dapat ' . $u['tahun_terisi']);
cek('Usaha sendiri rata-rata dibagi 1 (50), BUKAN 50/3=16,67', $u['rata_rata'] === 50.0,
    'dapat ' . var_export($u['rata_rata'], true));
cek('Usaha sendiri TS-1 = null, bukan 0',
    array_key_exists('TS-1', $u['juta']) && $u['juta']['TS-1'] === null,
    'dapat ' . var_export($u['juta']['TS-1'], true));

$pd = baris_t33($h33, 'Pemerintah Daerah');
cek('Pemerintah Daerah terisi 2 tahun', $pd['tahun_terisi'] === 2, 'dapat ' . $pd['tahun_terisi']);
cek('Pemerintah Daerah rata-rata (30+40)/2 = 35', $pd['rata_rata'] === 35.0,
    'dapat ' . var_export($pd['rata_rata'], true));

cek('tahun di luar TS-2..TS dilaporkan, tidak dihitung',
    $h33['tahun_di_luar_rentang'] === [$ts - 7], json_encode($h33['tahun_di_luar_rentang']));
cek('data luar rentang tidak masuk angka Mahasiswa',
    $m['juta']['TS-2'] === 100.0 && $h33['baris'][0]['rata_rata'] === 120.0);

// Dua baris sumber berbeda yang menormalkan ke sumber sama dijumlahkan.
sisip('keuangan_terima', ['prodi_id' => $prodi, 'tahun' => $ts, 'sumber' => 'Pusat', 'jumlah_juta' => 10.00]);
$h33b = t33_hitung($prodi);
$pp = baris_t33($h33b, 'Pemerintah Pusat');
cek('"Pusat" dinormalkan ke Pemerintah Pusat dan DIJUMLAHKAN (240+10=250)',
    ($pp['juta']['TS'] ?? null) === 250.0, 'dapat ' . var_export($pp['juta']['TS'] ?? null, true));
cek('rata-rata Pemerintah Pusat (200+220+250)/3 = 223,33',
    $pp['rata_rata'] === 223.33, 'dapat ' . var_export($pp['rata_rata'], true));
ex('DELETE FROM keuangan_terima WHERE prodi_id = ? AND tahun = ? AND sumber = ?', [$prodi, $ts, 'Pusat']);

cek('catatan menyebut satuan juta', str_contains($h33['catatan'], 'juta'));

echo "\n── Tabel 34: penggunaan dana ──\n";

// TS: 3 jenis 1:1:1 -> persen 33,33 masing-masing, jumlah 99,99, selisih +0,01.
// TS-1: sengaja nilai yang jumlah persennya tidak persis 100.
// TS-2: satu baris 0 dan satu baris 0 -> total 0 -> persen null semua.
$guna = [
    [$ts,     'Pendidikan', 100.00],
    [$ts,     'Penelitian', 100.00],
    [$ts,     'PkM',        100.00],
    [$ts - 1, 'Pendidikan', 300.00],
    [$ts - 1, 'Penelitian', 100.00],
    [$ts - 1, 'PkM',        100.00],
    [$ts - 2, 'Pendidikan', 0.00],
    [$ts - 2, 'Penelitian', 0.00],
];
foreach ($guna as [$th, $j, $v]) {
    sisip('keuangan_guna', ['prodi_id' => $prodi, 'tahun' => $th, 'jenis' => $j, 'jumlah_juta' => $v]);
}

$h34 = t34_hitung($prodi);
cek('tabel 34 punya 3 baris jenis', count($h34['baris']) === 3, 'dapat ' . count($h34['baris']));

cek('total penggunaan TS = 300', $h34['total_tahun']['TS'] === 300.0,
    'dapat ' . var_export($h34['total_tahun']['TS'], true));
cek('total penggunaan TS-1 = 500', $h34['total_tahun']['TS-1'] === 500.0,
    'dapat ' . var_export($h34['total_tahun']['TS-1'], true));

$pend = baris_t34($h34, 'Pendidikan');
cek('persen Pendidikan TS = 33,33', $pend['persen']['TS'] === 33.33,
    'dapat ' . var_export($pend['persen']['TS'], true));
cek('persen Pendidikan TS-1 = 60', $pend['persen']['TS-1'] === 60.0,
    'dapat ' . var_export($pend['persen']['TS-1'], true));

cek('jumlah persen TS = 99,99 (pembulatan, dilaporkan)',
    $h34['persen_tahun']['TS']['jumlah'] === 99.99,
    'dapat ' . var_export($h34['persen_tahun']['TS']['jumlah'], true));
cek('selisih pembulatan TS dicatat = 0,01',
    $h34['persen_tahun']['TS']['selisih'] === 0.01,
    'dapat ' . var_export($h34['persen_tahun']['TS']['selisih'], true));
cek('jumlah persen TS-1 = 100 persis', $h34['persen_tahun']['TS-1']['jumlah'] === 100.0,
    'dapat ' . var_export($h34['persen_tahun']['TS-1']['jumlah'], true));
cek('selisih pembulatan TS-1 = 0', $h34['persen_tahun']['TS-1']['selisih'] === 0.0,
    'dapat ' . var_export($h34['persen_tahun']['TS-1']['selisih'], true));

echo "\n── Tabel 34: pembagian nol ──\n";

cek('tahun total nol -> total = 0, bukan null', $h34['total_tahun']['TS-2'] === 0.0,
    'dapat ' . var_export($h34['total_tahun']['TS-2'], true));
cek('persen saat total 0 -> null (Pendidikan)', $pend['persen']['TS-2'] === null,
    'dapat ' . var_export($pend['persen']['TS-2'], true));
cek('persen saat total 0 -> null (Penelitian)', baris_t34($h34, 'Penelitian')['persen']['TS-2'] === null);
cek('persen null bukan INF/NaN',
    !is_float($pend['persen']['TS-2']) || is_finite($pend['persen']['TS-2']));
cek('jumlah persen tahun total nol -> null', $h34['persen_tahun']['TS-2']['jumlah'] === null,
    'dapat ' . var_export($h34['persen_tahun']['TS-2']['jumlah'], true));
cek('selisih persen tahun total nol -> null', $h34['persen_tahun']['TS-2']['selisih'] === null);

cek('PkM tidak punya baris TS-2 -> juta null', baris_t34($h34, 'PkM')['juta']['TS-2'] === null,
    'dapat ' . var_export(baris_t34($h34, 'PkM')['juta']['TS-2'], true));

cek('total per jenis: Pendidikan 400', $pend['total_juta'] === 400.0,
    'dapat ' . var_export($pend['total_juta'], true));
cek('rata-rata per jenis: Pendidikan 400/3 = 133,33', $pend['rata_rata_juta'] === 133.33,
    'dapat ' . var_export($pend['rata_rata_juta'], true));

echo "\n── semua angka keluaran terbatas (tidak ada INF/NAN) ──\n";

$jelek = [];
foreach ([$h13, $h33, $h34] as $nama => $h) {
    array_walk_recursive($h, function ($v, $k) use (&$jelek, $nama) {
        if (is_float($v) && !is_finite($v)) {
            $jelek[] = "$nama.$k";
        }
    });
}
cek('tidak ada INF/NAN di seluruh keluaran', $jelek === [], implode(',', $jelek));

echo "\n── kebersihan ──\n";
bersihkan_data_uji(db(), true);
cek('tracer bersih', (int) qv('SELECT COUNT(*) FROM tracer', [], 0) === 0);
cek('keuangan_terima bersih', (int) qv('SELECT COUNT(*) FROM keuangan_terima', [], 0) === 0);
cek('keuangan_guna bersih', (int) qv('SELECT COUNT(*) FROM keuangan_guna', [], 0) === 0);

printf("\n%d lulus, %d gagal\n", $lulus, $gagal);
exit($gagal === 0 ? 0 : 1);
