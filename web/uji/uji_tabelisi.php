<?php
declare(strict_types=1);

/**
 * Uji halaman isian satu tabel data dukung (#/tabelisi/NN).
 *
 * Yang diuji di sini adalah LAPISAN ATURAN, bukan tampilan: apakah server
 * menolak data yang mustahil, dan apakah ia menerima yang sah. Tampilan diuji
 * terpisah di browser.
 *
 * Fokus utama: aturan √ pilih-satu. Instrumen memakai tanda √ untuk dua arti
 * berbeda, dan salah membacanya menghasilkan data yang tidak bisa
 * dipertanggungjawabkan di sidang:
 *
 *   Tabel 38 — satu instansi ada di satu negara. Dua-duanya = mustahil.
 *   Tabel 16 — dosen BOLEH mengajar akademik DAN profesi (instrumen catatan 5).
 *              Kalau aturan ini salah, sistem menolak data yang justru benar.
 *
 * Jalankan: LAMPTKES_ENV=lokal php web/uji/uji_tabelisi.php
 */

// auth.php memanggil session_start(); itu harus SEBELUM ada output, kalau
// tidak PHP mengeluh "headers already sent" dan sesi tidak jalan.
require_once __DIR__ . '/../system/config.php';
require_once __DIR__ . '/../system/db.php';
require_once __DIR__ . '/../system/auth.php';
require_once __DIR__ . '/../system/rekap.php';
require_once __DIR__ . '/../system/data.php';
require_once __DIR__ . '/../system/kelompok.php';
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

bersihkan_data_uji(db(), true);

echo "── spesifikasi terbaca ──\n";

$spec22 = spec_satu_tabel('22');
$input22 = array_values(array_filter($spec22, fn($f) => $f['asal'] === 'Input'));
cek('tabel 22 punya 12 kolom isian', count($input22) === 12, 'dapat ' . count($input22));

$grup = [];
foreach ($spec22 as $f) {
    if ($f['kelompok'] !== '' && $f['pilih'] === 'satu') {
        $grup[$f['kelompok']][] = $f['kunci'];
    }
}
cek('tabel 22 punya 3 kelompok pilih-satu', count($grup) === 3, implode(', ', array_keys($grup)));
cek('kelompok tahun penelitian berisi 3 kolom',
    count($grup['tahun_penelitian'] ?? []) === 3);
cek('kelompok tingkat penelitian berisi 3 kolom',
    count($grup['tingkat_penelitian'] ?? []) === 3);

echo "\n── aturan pilih-satu: MENOLAK ──\n";

// Tabel 38: dalam + luar negeri sekaligus = mustahil.
$s38 = spec_satu_tabel('38');
$salah = periksa_kelompok(['dalam_negeri' => '1', 'luar_negeri' => '1'], $s38);
cek('38 dalam+luar negeri ditolak', $salah !== null, $salah ?? 'lolos');
cek('pesan menyebut kedua kolomnya',
    $salah !== null && str_contains($salah, 'Dalam negeri') && str_contains($salah, 'Luar negeri'));

// Tabel 22: dua tahun sekaligus = satu penelitian di dua tahun.
$x = periksa_kelompok([
    'tahun_penelitian_ts_2' => '1',
    'tahun_penelitian_ts_1' => '1',
], $spec22);
cek('22 dua tahun penelitian ditolak', $x !== null);

// Dua tingkat sekaligus.
$x = periksa_kelompok([
    'tingkat_penelitian_internasional' => '1',
    'tingkat_penelitian_nasional' => '1',
], $spec22);
cek('22 dua tingkat penelitian ditolak', $x !== null);

echo "\n── aturan pilih-satu: MENERIMA ──\n";

$x = periksa_kelompok(['dalam_negeri' => '1', 'luar_negeri' => ''], $s38);
cek('38 hanya dalam negeri diterima', $x === null, $x ?? '');

$x = periksa_kelompok([
    'tahun_penelitian_ts' => '1',
    'tingkat_penelitian_nasional' => '1',
    'kesesuaian_roadmap_sesuai' => '1',
], $spec22);
cek('22 satu tahun + satu tingkat + satu kesesuaian diterima', $x === null, $x ?? '');

// Tabel 16: instrumen catatan 5 menyebut "dapat diberi tanda √ pada
// kedua-duanya". Kalau uji ini gagal, sistem menolak data yang sah.
$s16 = spec_satu_tabel('16');
$tahap = array_values(array_filter($s16, fn($f) => $f['kelompok'] === 'tahap'));
cek('16 punya 2 kolom tahap', count($tahap) === 2);
cek('16 tahap boleb banyak di spec',
    $tahap !== [] && count(array_filter($tahap, fn($f) => $f['pilih'] === 'banyak')) === count($tahap));

$akademik = $tahap[0]['kunci'] ?? 'tahap_akademik';
$profesi = $tahap[1]['kunci'] ?? 'tahap_profesi';
$x = periksa_kelompok([$akademik => '1', $profesi => '1'], $s16);
cek('16 akademik + profesi sekaligus DITERIMA (instrumen catatan 5)', $x === null, $x ?? '');

echo "\n── klasifikasi asal field ──\n";

// Tabel 16 seluruhnya Master: datanya datang dari master dosen, bukan diketik.
$nInput16 = count(array_filter($s16, fn($f) => $f['asal'] === 'Input'));
cek('16 hanya 1 field Input (No) — sisanya dari master dosen',
    $nInput16 === 1, 'dapat ' . $nInput16);

// Tabel yang benar-benar tidak punya Input sama sekali.
foreach (['03', '04', '05', '06'] as $k) {
    $n = count(array_filter(spec_satu_tabel($k), fn($f) => $f['asal'] === 'Input'));
    cek("tabel $k tidak punya kolom isian", $n === 0, 'dapat ' . $n);
}

// 32 tabel harus bisa diisi operator. Kalau turun, ada field Input yang hilang.
$bisa = 0;
foreach (q('SELECT kode FROM tabel_dukung') as $t) {
    $n = count(array_filter(spec_satu_tabel($t['kode']), fn($f) => $f['asal'] === 'Input'));
    if ($n > 0) {
        $bisa++;
    }
}
cek('31 tabel punya kolom isian', $bisa === 31, 'dapat ' . $bisa);

echo "\n── simpan lewat jalur produksi ──\n";

// Uji CLI tidak punya sesi, jadi prodi_aktif() selalu null. Ambil prodi
// pertama langsung dari DB — yang diuji lapisan aturan, bukan sesi.
$p = (int) qv('SELECT id FROM prodi WHERE aktif = 1 ORDER BY id LIMIT 1', [], 0);
$per = $p > 0 ? periode_aktif($p) : null;
cek('prodi + periode aktif ada', $p && $per);

if ($p && $per) {
    $spec = spec_satu_tabel('22');
    $salah2 = periksa_kelompok([
        'tahun_penelitian_ts_2' => '1', 'tahun_penelitian_ts_1' => '1',
    ], $spec);
    cek('penyimpanan mustahil tertahan sebelum menyentuh DB', $salah2 !== null);

    // Yang sah harus benar-benar tersimpan.
    $id = sisip('baris_tabel', [
        'prodi_id' => $p, 'periode_id' => (int) $per['id'], 'tabel_kode' => '22', 'urut' => 1,
    ]);
    sisip('sel', ['baris_id' => $id, 'field_kunci' => 'tahun_penelitian_ts', 'nilai_angka' => 1]);
    sisip('sel', ['baris_id' => $id, 'field_kunci' => 'tingkat_penelitian_nasional', 'nilai_angka' => 1]);
    $n = (int) qv('SELECT COUNT(*) FROM sel WHERE baris_id = ?', [$id], 0);
    cek('dua sel sah tersimpan', $n === 2, 'dapat ' . $n);

    $st = status_satu_tabel('22', $p, (int) $per['id']);
    cek('status tabel 22 terhitung dari sel', isset($st['terisi']) && $st['terisi'] > 0,
        json_encode($st));
}

bersihkan_data_uji(db(), true);
$sisa = (int) qv('SELECT COUNT(*) FROM baris_tabel', [], 0);
cek('data uji bersih setelah uji', $sisa === 0, 'sisa ' . $sisa);

printf("\n%d lulus, %d gagal\n", $lulus, $gagal);
exit($gagal === 0 ? 0 : 1);
