<?php
declare(strict_types=1);

/**
 * Uji Tabel 02 (keberhasilan studi per angkatan) dan Tabel 03 (IPK lulusan).
 *
 * YANG DIKUNCI DI SINI — semuanya pernah jadi sumber angka salah:
 *   1) Label TS-7..TS (8 label: TS-7, TS-6, ..., TS-1, TS) dan 3 baris
 *      TS-2..TS. Jumlah 8, bukan 7: "7 baris" pada spesifikasi bertabrakan
 *      dengan "TS-7 s.d. TS", dan label yang dipakai — sama dengan
 *      spec_field tabel 02 ('TS-7 sampai TS') di migrasi.

 *   2) Batas rentang masa studi: 5 dan 6 masuk kolom B, 7 dan 10 masuk kolom C,
 *      dan angka 6 TIDAK dihitung dua kali (kalau dobel, A-(B+C) jadi kecil).
 *   3) DO = A-(B+C), tidak negatif, dan ikut memuat mahasiswa yang masih aktif
 *      — itu rumus instrumen, bukan bug.
 *   4) IPK: min <= rata <= maks, jumlah ketiga rentang = lulusan BER-IPK, dan
 *      lulusan tanpa IPK tidak ditarik jadi 0,00 (yang akan menurunkan rata).
 *
 * Jalankan: LAMPTKES_ENV=lokal php web/uji/uji_tabel02_03.php
 */

require_once __DIR__ . '/../system/config.php';
require_once __DIR__ . '/../system/db.php';
require_once __DIR__ . '/../system/hitung.php';
require_once __DIR__ . '/../system/tabel02.php';
require_once __DIR__ . '/../system/tabel03.php';
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

$nim = 0;
/** Tambah mahasiswa uji. $lulus null = belum lulus. */
function mhs(int $prodi, int $masuk, ?int $tahunLulus, string $status = 'Aktif',
             ?float $ipk = null, string $jalur = 'Reguler', string $tahap = 'Akademik'): int
{
    global $nim;
    $nim++;
    return sisip('mahasiswa', [
        'prodi_id' => $prodi,
        'nim' => 'UJI' . str_pad((string) $nim, 6, '0', STR_PAD_LEFT),
        'nama' => 'Mahasiswa Uji ' . $nim,
        'tahun_masuk' => $masuk,
        'jalur_masuk' => $jalur,
        'tahap' => $tahap,
        'status' => $status,
        'ipk' => $ipk,
        'tahun_lulus' => $tahunLulus,
    ]);
}

bersihkan_data_uji(db(), true);

$p1 = (int) qv('SELECT id FROM prodi WHERE aktif = 1 ORDER BY id LIMIT 1', [], 0);
$p2 = (int) qv('SELECT id FROM prodi WHERE aktif = 1 AND id <> ? ORDER BY id LIMIT 1', [$p1], 0);
cek('dua prodi aktif tersedia', $p1 > 0 && $p2 > 0, "p1=$p1 p2=$p2");

$per = periode_prodi($p1);
$ts = (int) ($per['tahun_ts'] ?? 0);
cek('periode prodi 1 punya tahun_ts', $ts > 0, 'ts=' . $ts);

echo "\n── Tabel 02: bentuk dan label ──\n";

// Lulusan HARUS berstatus 'Lulus' + tahun_lulus: lulusan_per_tahun()
// menyaring status = 'Lulus'. Baris berstatus lain tidak punya masa studi,
// jadi mengisinya dengan status 'Aktif' berarti menguji data yang mustahil.
//
// TS-7 = ts-7. Angkatan 2018..2025 untuk ts=2025.
mhs($p1, $ts - 7, $ts - 1, 'Lulus');   // masa 6 -> kolom B
mhs($p1, $ts - 7, $ts, 'Lulus');       // masa 7 -> kolom C
mhs($p1, $ts - 7, null);               // aktif -> hanya A, muncul di DO

// Angkatan ts-6: masa 3 dan 12 -> tidak masuk B/C mana pun.
mhs($p1, $ts - 6, $ts - 3, 'Lulus');   // masa 3
mhs($p1, $ts - 6, $ts + 6, 'Lulus');   // masa 12

// Angkatan ts-5: batas 5, 6, 7, 10 + dua di luar rentang.
mhs($p1, $ts - 5, $ts, 'Lulus');       // 5
mhs($p1, $ts - 5, $ts + 1, 'Lulus');   // 6
mhs($p1, $ts - 5, $ts + 2, 'Lulus');   // 7
mhs($p1, $ts - 5, $ts + 5, 'Lulus');   // 10
mhs($p1, $ts - 5, $ts - 1, 'Lulus');   // 4
mhs($p1, $ts - 5, $ts + 6, 'Lulus');   // 11

$h = t02_hitung($p1);
$b = $h['baris'];

// Jumlah baris = banyak label pada rentang, bukan angka 7 yang ditulis
// tangan: yang diuji adalah "rentangnya TS-7..TS", dan itu 8 label.
cek('baris = sebanyak label rentang TS-7..TS (8)', count($b) === count(rentang_ta('TS', 8)),
    'dapat ' . count($b));
$label = array_column($b, 'tahun_akademik');
cek('label TS-7..TS', $label === ['TS-7', 'TS-6', 'TS-5', 'TS-4', 'TS-3', 'TS-2', 'TS-1', 'TS'],
    implode(',', $label));
cek('label pertama TS-7', $label[0] === 'TS-7', $label[0]);
cek('label terakhir TS', end($label) === 'TS');
cek('tahun baris 1 = ts-7', $b[0]['tahun'] === $ts - 7, (string) $b[0]['tahun']);
cek('tahun baris terakhir = ts', end($b)['tahun'] === $ts, (string) end($b)['tahun']);

echo "\n── Tabel 02: hitungan per angkatan ──\n";

$r7 = $b[0];   // TS-7
cek('TS-7 jumlah mahasiswa = 3 (semua status)', $r7['jumlah_mahasiswa'] === 3, 'dapat ' . $r7['jumlah_mahasiswa']);
cek('TS-7 masa 5,5-6 th = 1 (masa 6)', $r7['masa_studi_5_5_6_th'] === 1, 'dapat ' . $r7['masa_studi_5_5_6_th']);
cek('TS-7 masa 6-10 th = 1 (masa 7)', $r7['masa_studi_6_10_th'] === 1, 'dapat ' . $r7['masa_studi_6_10_th']);
cek('TS-7 DO = 1 (yang masih aktif, rumus instrumen)', $r7['do'] === 1, 'dapat ' . $r7['do']);

$r6 = $b[1];   // TS-6
cek('TS-6 masa 3 & 12 tidak masuk B/C', $r6['masa_studi_5_5_6_th'] === 0 && $r6['masa_studi_6_10_th'] === 0,
    "B={$r6['masa_studi_5_5_6_th']} C={$r6['masa_studi_6_10_th']}");
cek('TS-6 DO = 2 (masa 3 dan 12 tetap terhitung)', $r6['do'] === 2, 'dapat ' . $r6['do']);

$r5 = $b[2];   // TS-5
cek('TS-5 jumlah = 6', $r5['jumlah_mahasiswa'] === 6, 'dapat ' . $r5['jumlah_mahasiswa']);
cek('TS-5 masa 5 dan 6 -> kolom B = 2', $r5['masa_studi_5_5_6_th'] === 2, 'dapat ' . $r5['masa_studi_5_5_6_th']);
cek('TS-5 masa 7 dan 10 -> kolom C = 2', $r5['masa_studi_6_10_th'] === 2, 'dapat ' . $r5['masa_studi_6_10_th']);
cek('TS-5 masa 4 dan 11 -> DO = 2', $r5['do'] === 2, 'dapat ' . $r5['do']);

echo "\n── Tabel 02: DO = A-(B+C), tidak negatif ──\n";

$semuaCocok = true;
$tidakNegatif = true;
$ket = '';
foreach ($b as $r) {
    if ($r['do_mentah'] !== $r['jumlah_mahasiswa'] - ($r['masa_studi_5_5_6_th'] + $r['masa_studi_6_10_th'])) {
        $semuaCocok = false;
        $ket .= $r['tahun_akademik'] . ' ';
    }
    if ($r['do'] < 0 || $r['do'] !== max(0, $r['do_mentah'])) {
        $tidakNegatif = false;
    }
}
cek('setiap baris: do_mentah = A-(B+C)', $semuaCocok, $ket);
cek('DO tidak pernah negatif (negatif -> 0)', $tidakNegatif);
cek('tidak ada baris negatif pada data wajar', $h['jumlah_negatif'] === 0, 'dapat ' . $h['jumlah_negatif']);
cek('catatan keterbatasan tersedia', $h['catatan'] !== '');

// Cabang negatif diuji langsung pada fungsinya. Dari master, B+C tak pernah
// melebihi A, jadi tanpa memanggil t02_do() sendiri cabang itu tidak pernah
// dieksekusi uji — dan kode yang tidak pernah dieksekusi bukan kode teruji.
[$doNeg, $mentahNeg] = t02_do(2, 2, 1);          // 2 - 3 = -1 (data ganda)
cek('t02_do: A-(B+C) negatif -> kolom DO 0', $doNeg === 0, 'dapat ' . $doNeg);
cek('t02_do: angka mentah negatif tetap disimpan', $mentahNeg === -1, 'dapat ' . $mentahNeg);
cek('t02_do: kasus wajar tidak diubah', t02_do(10, 3, 4) === [3, 3], json_encode(t02_do(10, 3, 4)));
cek('t02_do: pas nol tetap 0', t02_do(5, 2, 3) === [0, 0], json_encode(t02_do(5, 2, 3)));

echo "\n── Tabel 02: angkatan lain tidak bocor ──\n";

$kosong = $b[3];   // TS-4, tidak diisi
cek('TS-4 (tanpa mahasiswa) semua nol', $kosong['jumlah_mahasiswa'] === 0 && $kosong['do'] === 0,
    "A={$kosong['jumlah_mahasiswa']} DO={$kosong['do']}");

echo "\n── Tabel 03: bentuk dan label ──\n";

// Prodi 2 khusus Tabel 03. Hanya tahap Akademik yang boleh masuk.
mhs($p2, $ts - 4, $ts, 'Lulus', 3.50, 'Reguler');
mhs($p2, $ts - 5, $ts, 'Lulus', 2.75, 'Transfer');
mhs($p2, $ts - 6, $ts, 'Lulus', 3.80, 'Asing');
mhs($p2, $ts - 4, $ts, 'Lulus', null, 'Reguler');          // tanpa IPK
mhs($p2, $ts - 4, $ts, 'Lulus', 2.50, 'Reguler', 'Profesi'); // bukan Akademik
mhs($p2, $ts - 4, $ts - 1, 'Lulus', 3.25, 'Reguler');
mhs($p2, $ts - 5, $ts - 2, 'Lulus', 3.00, 'Reguler');
mhs($p2, $ts - 6, $ts - 2, 'Lulus', 3.90, 'Reguler');

$t = t03_hitung($p2);
$tb = $t['baris'];

cek('3 baris', count($tb) === 3, 'dapat ' . count($tb));
cek('label TS-2, TS-1, TS', array_column($tb, 'tahun_akademik') === ['TS-2', 'TS-1', 'TS'],
    implode(',', array_column($tb, 'tahun_akademik')));

echo "\n── Tabel 03: hitungan ──\n";

$ts1 = $tb[0];  // TS-2 → ts-2
cek('TS-2 jumlah lulusan = 2', $ts1['jumlah_lulusan'] === 2, 'dapat ' . $ts1['jumlah_lulusan']);
cek('TS-2 IPK min 3.00', abs((float) $ts1['ipk_minimum'] - 3.00) < 0.001, (string) $ts1['ipk_minimum']);
cek('TS-2 IPK maks 3.90', abs((float) $ts1['ipk_maksimum'] - 3.90) < 0.001, (string) $ts1['ipk_maksimum']);

$ts0 = $tb[2];  // TS
cek('TS jumlah lulusan = 4 (profesi tidak ikut)', $ts0['jumlah_lulusan'] === 4, 'dapat ' . $ts0['jumlah_lulusan']);
cek('TS reguler = 2', $ts0['jumlah_lulusan_reguler'] === 2, 'dapat ' . $ts0['jumlah_lulusan_reguler']);
cek('TS transfer = 1', $ts0['jumlah_lulusan_transfer'] === 1, 'dapat ' . $ts0['jumlah_lulusan_transfer']);
cek('TS asing = 1', $ts0['jumlah_lulusan_asing'] === 1, 'dapat ' . $ts0['jumlah_lulusan_asing']);
cek('TS tanpa IPK = 1', $ts0['tanpa_ipk'] === 1, 'dapat ' . $ts0['tanpa_ipk']);

echo "\n── Tabel 03: IPK tanpa nilai tidak jadi 0,00 ──\n";

// (3,50 + 2,75 + 3,80) / 3 = 3,35. Kalau yang tanpa IPK dihitung 0:
// (3,50 + 2,75 + 3,80 + 0) / 4 = 2,51 — itu yang harus TIDAK terjadi.
cek('rata-rata TS = 3.35 (bukan 2.51)', abs((float) $ts0['ipk_rata_rata'] - 3.35) < 0.005,
    (string) $ts0['ipk_rata_rata']);
cek('rata-rata TS tidak sama dengan versi IPK-0', abs((float) $ts0['ipk_rata_rata'] - 2.51) > 0.5,
    (string) $ts0['ipk_rata_rata']);
cek('IPK min TS = 2.75 (bukan 0)', abs((float) $ts0['ipk_minimum'] - 2.75) < 0.005, (string) $ts0['ipk_minimum']);

// Uji selisih: menambah SATU lulusan tanpa IPK tidak boleh mengubah
// MIN/rata-rata/MAKS sama sekali. Inilah bentuk paling langsung dari aturan
// "tanpa IPK bukan IPK 0" — kalau nilai NULL diperlakukan 0, rata-rata
// bergerak dan uji ini menangkapnya.
$sebelumRata = (float) $ts0['ipk_rata_rata'];
$sebelumMin = (float) $ts0['ipk_minimum'];
$sebelumMaks = (float) $ts0['ipk_maksimum'];
$sebelumJml = $ts0['jumlah_lulusan'];
$idTambahan = mhs($p2, $ts - 4, $ts, 'Lulus', null, 'Reguler');
$sesudah = t03_hitung($p2)['baris'][2];
cek('+1 lulusan tanpa IPK: jumlah lulusan naik 1', $sesudah['jumlah_lulusan'] === $sebelumJml + 1,
    "sebelum $sebelumJml, sesudah {$sesudah['jumlah_lulusan']}");
cek('+1 lulusan tanpa IPK: tanpa_ipk naik 1', $sesudah['tanpa_ipk'] === 2, 'dapat ' . $sesudah['tanpa_ipk']);
cek('+1 lulusan tanpa IPK: rata-rata TIDAK bergerak',
    abs((float) $sesudah['ipk_rata_rata'] - $sebelumRata) < 0.005,
    "sebelum $sebelumRata, sesudah {$sesudah['ipk_rata_rata']}");
cek('+1 lulusan tanpa IPK: min & maks TIDAK bergerak',
    abs((float) $sesudah['ipk_minimum'] - $sebelumMin) < 0.005
    && abs((float) $sesudah['ipk_maksimum'] - $sebelumMaks) < 0.005,
    "min={$sesudah['ipk_minimum']} maks={$sesudah['ipk_maksimum']}");
cek('+1 lulusan tanpa IPK: 3 rentang IPK tetap 3 terisi',
    $sesudah['lulusan_ipk_2_00_3_00'] + $sesudah['lulusan_ipk_3_00_3_50']
    + $sesudah['lulusan_ipk_3_50_4_00'] === 3,
    'dapat ' . ($sesudah['lulusan_ipk_2_00_3_00'] + $sesudah['lulusan_ipk_3_00_3_50'] + $sesudah['lulusan_ipk_3_50_4_00']));

// Kembalikan supaya angka total di bawah tetap 7 lulusan.
ex('DELETE FROM mahasiswa WHERE id = ?', [$idTambahan]);

echo "\n── Tabel 03: min <= rata <= maks, rentang utuh ──\n";

$urut = true;
$jumlahRentang = true;
$ket = '';
foreach ($tb as $r) {
    if ($r['ipk_rata_rata'] === null) {
        continue;
    }
    if (!($r['ipk_minimum'] <= $r['ipk_rata_rata'] && $r['ipk_rata_rata'] <= $r['ipk_maksimum'])) {
        $urut = false;
        $ket .= $r['tahun_akademik'] . ' ';
    }
    $n = $r['lulusan_ipk_2_00_3_00'] + $r['lulusan_ipk_3_00_3_50'] + $r['lulusan_ipk_3_50_4_00'];
    if ($n !== $r['jumlah_lulusan'] - $r['tanpa_ipk']) {
        $jumlahRentang = false;
        $ket .= $r['tahun_akademik'] . "(rentang=$n, berIPK=" . ($r['jumlah_lulusan'] - $r['tanpa_ipk']) . ') ';
    }
}
cek('setiap baris: min <= rata <= maks', $urut, $ket);
cek('jumlah 3 rentang = lulusan ber-IPK', $jumlahRentang, $ket);
cek('TS: 3 rentang masing-masing 1', $ts0['lulusan_ipk_2_00_3_00'] === 1
    && $ts0['lulusan_ipk_3_00_3_50'] === 1 && $ts0['lulusan_ipk_3_50_4_00'] === 1,
    "{$ts0['lulusan_ipk_2_00_3_00']}/{$ts0['lulusan_ipk_3_00_3_50']}/{$ts0['lulusan_ipk_3_50_4_00']}");

echo "\n── Tabel 03: total ditimbang, bukan rata-rata dari rata-rata ──\n";

cek('total lulusan 3 baris = 7', $t['total']['jumlah_lulusan'] === 7, 'dapat ' . $t['total']['jumlah_lulusan']);
cek('total tanpa IPK = 1', $t['total']['tanpa_ipk'] === 1, 'dapat ' . $t['total']['tanpa_ipk']);
// (3,35x3 + 3,25x1 + 3,45x2)/6 = 3,3666 -> 3,37
cek('total rata-rata ditimbang = 3.37', abs((float) $t['total']['ipk_rata_rata'] - 3.37) < 0.011,
    (string) $t['total']['ipk_rata_rata']);
cek('total IPK min 2.75, maks 3.90', abs((float) $t['total']['ipk_minimum'] - 2.75) < 0.005
    && abs((float) $t['total']['ipk_maksimum'] - 3.90) < 0.005,
    $t['total']['ipk_minimum'] . '..' . $t['total']['ipk_maksimum']);
cek('catatan menyebut lulusan tanpa IPK', str_contains($t['catatan'], 'tanpa IPK'));

echo "\n── baris tanpa lulusan: null, bukan 0,00 ──\n";

// Prodi 1 punya lulusan 2022/2024/2025, tapi tidak di TA ts-2 (2023).
$t1 = t03_hitung($p1);
$rKosong = $t1['baris'][0];
cek('TS-2 prodi tanpa lulusan: ipk rata-rata null', $rKosong['ipk_rata_rata'] === null,
    var_export($rKosong['ipk_rata_rata'], true));
cek('TS-2 prodi tanpa lulusan: jumlah 0', $rKosong['jumlah_lulusan'] === 0, 'dapat ' . $rKosong['jumlah_lulusan']);

echo "\n── prodi tanpa periode: tidak mengarang tahun ──\n";

ex('UPDATE periode SET aktif = 0 WHERE prodi_id = ?', [$p1]);
$h2 = t02_hitung($p1);
$t2 = t03_hitung($p1);
cek('t02 tanpa periode: baris kosong', $h2['baris'] === []);
cek('t02 tanpa periode: ada alasan', str_contains($h2['catatan'], 'Periode'));
cek('t03 tanpa periode: baris kosong', $t2['baris'] === []);
cek('t03 tanpa periode: ada alasan', str_contains($t2['catatan'], 'Periode'));
ex('UPDATE periode SET aktif = 1 WHERE prodi_id = ?', [$p1]);

$h3 = t02_hitung($p1);
cek('periode dipulihkan: t02 kembali 8 baris', count($h3['baris']) === 8, 'dapat ' . count($h3['baris']));

bersihkan_data_uji(db(), true);
cek('data uji bersih setelah uji', (int) qv('SELECT COUNT(*) FROM mahasiswa', [], -1) === 0);

printf("\n%d lulus, %d gagal\n", $lulus, $gagal);
exit($gagal === 0 ? 0 : 1);
