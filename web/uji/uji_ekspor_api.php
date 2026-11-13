<?php
declare(strict_types=1);

/**
 * Uji ekspor XLSX lewat jalur produksi (api.php), bukan modul lama.
 *
 * KENAPA PERLU: modul web/modul/ekspor/ sudah tidak dirutekan, jadi uji_ekspor
 * menguji logika yang tidak pernah dijalankan pengguna. Uji ini menguji
 * sambungannya: apakah berkasnya benar-benar dibuat, bisa dibuka sebagai ZIP
 * (format XLSX), dan berisi sheet yang dijanjikan.
 *
 * Yang juga diuji: BLOKIR. Ekspor harus menolak kalau konsistensi gagal.
 * File berisi angka tidak konsisten antar tabel lebih berbahaya daripada tidak
 * ada file — asesor bisa memakainya untuk menolak.
 *
 * Jalankan: LAMPTKES_ENV=lokal php web/uji/uji_ekspor_api.php
 */

require_once __DIR__ . '/../system/config.php';
require_once __DIR__ . '/../system/db.php';
require_once __DIR__ . '/../system/auth.php';
require_once __DIR__ . '/../system/rekap.php';
require_once __DIR__ . '/../system/xlsx.php';
require_once __DIR__ . '/../modul/ekspor/fungsi.php';
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

$p = (int) qv('SELECT id FROM prodi WHERE aktif = 1 ORDER BY id LIMIT 1', [], 0);
$per = $p > 0 ? periode_aktif($p) : null;
cek('prodi + periode aktif ada', $p > 0 && $per !== null);

if ($p === 0 || $per === null) {
    echo "\nTidak bisa melanjutkan: seed belum dijalankan.\n";
    exit(1);
}

echo "── berkas benar-benar dibuat ──\n";

$sheets = ekspor_sheets($p, (int) $per['id']);
cek('ekspor_sheets mengembalikan sheet', is_array($sheets) && count($sheets) >= 2,
    'dapat ' . (is_array($sheets) ? count($sheets) : 0));

$tmp = tempnam(sys_get_temp_dir(), 'uji-xlsx-') . '.xlsx';
$buat = xlsx_buat($sheets, $tmp);
cek('xlsx_buat berhasil', $buat === true);
cek('berkas ada', is_file($tmp));
cek('berkas tidak kosong', is_file($tmp) && filesize($tmp) > 1000,
    is_file($tmp) ? filesize($tmp) . ' byte' : 'tidak ada');

// XLSX adalah arsip ZIP. Kalau dua byte pertamanya bukan "PK", berkasnya
// rusak dan Excel akan menolaknya — tapi PHP tetap bilang "berhasil".
$kepala = is_file($tmp) ? (string) file_get_contents($tmp, false, null, 0, 2) : '';
cek('berkas berformat ZIP/XLSX (magic bytes PK)', $kepala === 'PK',
    'dapat ' . bin2hex($kepala));

echo "\n── isi sheet ──\n";

$nama = array_map(fn($s) => (string) ($s['nama'] ?? ''), $sheets);
cek('sheet Ringkasan paling depan', ($nama[0] ?? '') === 'Ringkasan', $nama[0] ?? '');
cek('lebih dari satu sheet', count($nama) > 1, (string) count($nama));

$panjangSalah = array_filter($nama, fn($n) => mb_strlen($n) > 31);
cek('semua nama sheet <= 31 karakter (batas Excel)', $panjangSalah === [],
    implode(',', $panjangSalah));

$terlarang = array_filter($nama, fn($n) => preg_match('#[:\\\\/?*\[\]]#', $n));
cek('nama sheet bebas karakter terlarang Excel', $terlarang === [],
    implode(',', $terlarang));

cek('nama sheet tidak ganda', count($nama) === count(array_unique($nama)));

@unlink($tmp);

echo "\n── blokir ekspor ──\n";

// Tanpa data, konsistensi seharusnya melaporkan blokir (mis. selisih angka).
// Yang penting: kalau ADA blokir, pesannya harus menyebut cara perbaikannya —
// bukan sekadar "tidak bisa".
$blokir = ekspor_blokir($p);
cek('ekspor_blokir mengembalikan array', is_array($blokir));
foreach ($blokir as $b) {
    cek('blokir "' . ($b['nama'] ?? '?') . '" punya pesan', ($b['pesan'] ?? '') !== '');
    cek('blokir "' . ($b['nama'] ?? '?') . '" menyebut perbaikan',
        stripos((string) $b['pesan'], 'perbaiki') !== false
        || stripos((string) $b['pesan'], 'pisahkan') !== false
        || stripos((string) $b['pesan'], 'pilih') !== false,
        $b['pesan'] ?? '');
}

// Prodi tidak dikenal: harus menolak dengan alasan, bukan membuat berkas kosong.
$blokir0 = ekspor_blokir(0);
cek('prodi 0 ditolak dengan alasan', $blokir0 !== []);
cek('alasan prodi 0 bisa dibaca manusia',
    stripos((string) ($blokir0[0]['pesan'] ?? ''), 'prodi') !== false,
    $blokir0[0]['pesan'] ?? '');

echo "\n── data ekspor berisi master ──\n";

$data = ekspor_data($p, (int) $per['id']);
cek('ekspor_data mengembalikan array', is_array($data));
// Bentuk aslinya: ['kepala','sheet','tabel','periode','prodi_id'].
cek('ekspor_data memuat identitas prodi',
    isset($data['prodi_id']) || isset($data['kepala']) || isset($data['identitas']),
    implode(',', array_slice(array_keys($data), 0, 8)));
cek('prodi_id di data ekspor cocok', (int) ($data['prodi_id'] ?? 0) === $p,
    (string) ($data['prodi_id'] ?? 'tidak ada'));
cek('ekspor_data memuat tabel', !empty($data['tabel']));

bersihkan_data_uji(db(), true);
printf("\n%d lulus, %d gagal\n", $lulus, $gagal);
exit($gagal === 0 ? 0 : 1);
