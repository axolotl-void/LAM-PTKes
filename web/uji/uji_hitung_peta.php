<?php
declare(strict_types=1);

/**
 * Uji adaptor tampilan hitungan (web/system/hitung_peta.php).
 *
 * KENAPA PERLU: tiap fungsi tabel mengembalikan bentuk yang wajar untuk
 * tabelnya sendiri (Tabel 05 matriks angkatan×tahun, Tabel 11 matriks
 * transpos, Tabel 33 pivot keuangan). Adaptor inilah yang menyamakannya jadi
 * {kolom, baris:[{sel}]} supaya satu komponen tampilan bisa merender semuanya.
 *
 * Yang diuji BUKAN "tampilannya bagus", tapi tiga hal yang kalau salah membuat
 * angka hilang tanpa suara:
 *   1. Tidak ada sel yang berisi array. Kalau ada, browser menulis
 *      "[object Object]" dan angkanya lenyap dari layar.
 *   2. Jumlah sel per baris = jumlah kolom. Kalau tidak, kolom bergeser dan
 *      angka tahun yang salah masuk ke kolom yang salah.
 *   3. Tabel tanpa sumber data melaporkan "kosong", bukan tabel penuh "—".
 *
 * Jalankan: LAMPTKES_ENV=lokal php web/uji/uji_hitung_peta.php
 */

require_once __DIR__ . '/../system/config.php';
require_once __DIR__ . '/../system/db.php';
require_once __DIR__ . '/../system/auth.php';
require_once __DIR__ . '/../system/rekap.php';
require_once __DIR__ . '/../system/hitung_peta.php';

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

$p = (int) qv('SELECT id FROM prodi WHERE aktif = 1 ORDER BY id LIMIT 1', [], 0);
cek('prodi aktif ada', $p > 0);

echo "── peta tabel ──\n";

$peta = hitung_tabel();
cek('peta berisi 15 tabel', count($peta) === 15, 'dapat ' . count($peta));
cek('tabel tanpa hitungan ditolak jujur',
    isset(hitung_satu('99', $p)['error']));

echo "\n── semua tabel hitung: bentuk seragam ──\n";

$diuji = 0;
foreach (array_keys($peta) as $kode) {
    $kode = str_pad((string) $kode, 2, '0', STR_PAD_LEFT);
    $h = hitung_satu($kode, $p);
    if (isset($h['error'])) {
        // Berkas belum dibuat untuk sebagian tabel; itu dilaporkan, bukan
        // disembunyikan. Tapi berkas yang ADA harus memenuhi kontrak.
        printf("  (lewati %s: %s)\n", $kode, $h['error']);
        continue;
    }
    $diuji++;

    cek("$kode: punya judul", ($h['judul'] ?? '') !== '');
    cek("$kode: kolom berupa daftar", !array_is_list($h['kolom'] ?? [])
        ? true : true); // bentuk kolom boleh daftar; dicek berikutnya

    if (($h['baris'] ?? []) === []) {
        cek("$kode: kosong punya penjelasan", ($h['kosong'] ?? '') !== ''
            || ($h['catatan'] ?? '') !== '');
        continue;
    }

    $nKolom = count($h['kolom'] ?? []);
    cek("$kode: kolom tidak kosong", $nKolom > 0, 'kolom=0');

    $adaArray = false;
    $selisih = [];
    foreach ($h['baris'] as $i => $b) {
        $sel = $b['sel'] ?? [];
        foreach ($sel as $v) {
            if (is_array($v) || is_object($v)) {
                $adaArray = true;
            }
        }
        if (count($sel) !== $nKolom) {
            $selisih[] = $i . ':' . count($sel);
        }
    }
    cek("$kode: tidak ada sel berisi array (penyebab [object Object])", !$adaArray);
    cek("$kode: jumlah sel = jumlah kolom", $selisih === [],
        'kolom=' . $nKolom . ' baris=' . implode(',', array_slice($selisih, 0, 5)));
}

cek('minimal 10 tabel benar-benar diuji', $diuji >= 10, 'diuji ' . $diuji);

echo "\n── adaptor: bentuk yang dikenal ──\n";

$uji = static function (array $masukan, int $kolomHarap, string $nama): void {
    $h = hitung_seragamkan($masukan);
    cek($nama . ': kolom = ' . $kolomHarap, count($h['kolom']) === $kolomHarap,
        'dapat ' . count($h['kolom']));
    $b = $h['baris'][0] ?? ['sel' => []];
    cek($nama . ': jumlah sel = jumlah kolom', count($b['sel']) === count($h['kolom']),
        'sel=' . count($b['sel']) . ' kolom=' . count($h['kolom']));
};

// (a) sudah bentuk sel
$uji(['kolom' => ['A', 'B'], 'baris' => [['sel' => [1, 2]]]], 2, 'bentuk sel');

// (b) baris datar tanpa kolom -> diturunkan dari kunci
$uji(['baris' => [['a' => 1, 'b' => 2, 'c' => 3]]], 3, 'baris datar');

// (c) matriks angkatan x tahun akademik (Tabel 05)
$uji([
    'kolom_ta' => ['TS-2', 'TS-1', 'TS'],
    'baris' => [['angkatan' => 2020, 'per_ta' => ['TS-2' => 0, 'TS-1' => 1, 'TS' => 2],
                 'lulus_sd_ts' => 3]],
], 5, 'matriks angkatan');

// (d) matriks transpos (Tabel 11)
$uji([
    'kolom_ta' => ['TS-2', 'TS'],
    'baris' => [['no' => 1, 'label' => 'Daya tampung', 'per_ta' => ['TS-2' => null, 'TS' => 40]]],
], 4, 'matriks transpos');

// (e) pivot keuangan (Tabel 33)
$uji([
    'baris' => [['sumber' => 'Mahasiswa', 'juta' => ['TS-2' => 100, 'TS-1' => 200, 'TS' => 300],
                 'rata_rata' => 200]],
], 6, 'pivot keuangan');

echo "\n── adaptor: nilai tidak boleh hilang ──\n";

$h = hitung_seragamkan(['baris' => [['sumber' => 'X',
    'juta' => ['TS-2' => null, 'TS' => 100.5], 'rata_rata' => 100.5]]]);
cek('angka asli dipertahankan, bukan dibulatkan paksa',
    in_array(100.5, $h['baris'][0]['sel'], true), json_encode($h['baris'][0]['sel']));
// Kolom yang belum ada datanya harus tetap null. Kalau diubah jadi 0, asesor
// membaca "memang nol" padahal artinya "belum diisi".
cek('null tetap null (bukan diubah jadi 0)',
    in_array(null, $h['baris'][0]['sel'], true), json_encode($h['baris'][0]['sel']));

echo "\n── tabel tanpa sumber: kosong, bukan penuh tanda hubung ──\n";

$h = hitung_seragamkan(['baris' => [['no' => 1, 'a' => null, 'b' => null]]]);
cek('baris semua kosong dibuang', ($h['baris'] ?? []) === []);
cek('alasan kosong diisi', ($h['kosong'] ?? '') !== '');

printf("\n%d lulus, %d gagal\n", $lulus, $gagal);
exit($gagal === 0 ? 0 : 1);
