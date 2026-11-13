<?php
declare(strict_types=1);

/**
 * Uji dokumen pendukung lewat jalur produksi (web/api.php), bukan modul lama.
 *
 * uji_dokumen.php menguji logika simpan/validasi. Uji ini menguji SAMBUNGANNYA:
 * nama kolom yang benar, penjagaan path saat unduh, dan hapus yang benar-benar
 * membuang berkas dari disk.
 *
 * Tiga bug nyata yang pernah terjadi di sini dan dikunci oleh uji ini:
 *  1. Unduh memakai kolom `nama_simpan`, padahal kolomnya `berkas` → warning
 *     PHP bocor ke response JSON dan unduhan selalu 404.
 *  2. Warning PHP tercetak SEBELUM JSON, sehingga klien menerima HTML rusak
 *     yang tidak bisa di-parse.
 *  3. Hapus menghapus baris DB tapi berkasnya tertinggal di folder unggah.
 *
 * Jalankan: LAMPTKES_ENV=lokal php web/uji/uji_dokumen_api.php
 */

// display_errors dimatikan: yang mau dibuktikan justru response TIDAK boleh
// mengandung warning. Kalau PHP mencetak warning, uji ini harus gagal.
ini_set('display_errors', '0');

require_once __DIR__ . '/../system/config.php';
require_once __DIR__ . '/../system/db.php';
require_once __DIR__ . '/../system/auth.php';
require_once __DIR__ . '/../system/rekap.php';
require_once __DIR__ . '/../modul/dokumen/logika.php';
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
$uid = (int) qv('SELECT id FROM pengguna ORDER BY id LIMIT 1', [], 0);
cek('prodi + periode + pengguna ada', $p > 0 && $per !== null && $uid > 0);

if ($p === 0 || $per === null || $uid === 0) {
    echo "\nTidak bisa melanjutkan: seed belum dijalankan.\n";
    exit(1);
}
$perId = (int) $per['id'];

$spec = (int) qv('SELECT id FROM dokumen_spec ORDER BY id LIMIT 1', [], 0);
cek('ada butir dokumen_spec', $spec > 0);

/** Buat berkas PDF kecil yang lolos validasi. */
function pdf_uji(string $isi = 'uji'): string
{
    $f = tempnam(sys_get_temp_dir(), 'dok-') . '.pdf';
    file_put_contents($f, "%PDF-1.4\n" . $isi . "\ntrailer<<>>\n%%EOF\n");
    return $f;
}

echo "\n── simpan unggahan ──\n";

$f1 = pdf_uji('versi1');
$h1 = dok_simpan_unggah($spec, $p, $perId,
    ['name' => 'notulen.pdf', 'tmp_name' => $f1, 'size' => filesize($f1), 'error' => UPLOAD_ERR_OK],
    $uid, null, 'uji');
cek('unggahan pertama tersimpan', ($h1['ok'] ?? false) === true, json_encode($h1));

$row = q1('SELECT * FROM dokumen_unggah WHERE id = ?', [(int) $h1['id']]);
cek('baris DB dibuat', $row !== null);

// Inilah bug #1: nama kolom. Kalau kolomnya salah, unduh selalu 404.
// Uji skema saja tidak cukup: bugnya ada di QUERY api.php, bukan di tabel.
// Yang menentukan adalah nama kolom yang benar-benar dibaca kode produksi.
$kodeApi = (string) file_get_contents(__DIR__ . '/../api.php');
preg_match_all("/\\\$u\\['(berkas|nama_simpan)'\\]/", $kodeApi, $m);
$pakaiBerkas = count(array_filter($m[1] ?? [], fn($x) => $x === 'berkas'));
$pakaiLama = count(array_filter($m[1] ?? [], fn($x) => $x === 'nama_simpan'));
cek('api.php memakai kolom `berkas`', $pakaiBerkas >= 2, 'dapat ' . $pakaiBerkas);
cek('api.php TIDAK memakai `nama_simpan` (bug unduh 404)', $pakaiLama === 0,
    'ditemukan ' . $pakaiLama);
cek('nama asli tersimpan', ($row['nama_asli'] ?? '') === 'notulen.pdf');

$path = UNGGAH . '/' . basename((string) $row['berkas']);
cek('berkas ada di folder unggah', is_file($path), $path);
cek('nama simpanan berpola <id>_<hex>.<ext>',
    preg_match('/^' . $spec . '_[0-9a-f]{16}\.pdf$/', (string) $row['berkas']) === 1,
    (string) $row['berkas']);

echo "\n── status per butir ──\n";

$daftar = dok_daftar($p, $perId);
$butir = null;
foreach ($daftar as $g) {
    foreach ($g['butir'] as $b) {
        if ((int) $b['id'] === $spec) { $butir = $b; }
    }
}
cek('butir ditemukan di dok_daftar', $butir !== null);
cek('status butir = ada', ($butir['status'] ?? '') === 'ada', (string) ($butir['status'] ?? ''));

$rekap = rekap_dokumen($p, $perId);
cek('rekap menghitung 1 terunggah', (int) $rekap['unggah'] === 1, (string) $rekap['unggah']);
cek('rekap belum = total - unggah',
    (int) $rekap['belum'] === (int) $rekap['total'] - (int) $rekap['unggah']);

echo "\n── versi baru tidak menimpa versi lama ──\n";

$f2 = pdf_uji('versi2');
$kemarin = (string) qv('SELECT DATE_SUB(CURDATE(), INTERVAL 1 DAY)');
$h2 = dok_simpan_unggah($spec, $p, $perId,
    ['name' => 'revisi.pdf', 'tmp_name' => $f2, 'size' => filesize($f2), 'error' => UPLOAD_ERR_OK],
    $uid, $kemarin, 'revisi');
cek('unggahan kedua tersimpan', ($h2['ok'] ?? false) === true, json_encode($h2));

$n = (int) qv('SELECT COUNT(*) FROM dokumen_unggah WHERE dok_spec_id = ? AND prodi_id = ? AND periode_id = ?',
    [$spec, $p, $perId], 0);
cek('dua baris untuk butir yang sama (riwayat, bukan timpa)', $n === 2, 'dapat ' . $n);

$versi = dok_unggahan_per_butir($p, $perId)[$spec] ?? [];
cek('versi[0] = yang terbaru', (int) ($versi[0]['id'] ?? 0) === (int) $h2['id'],
    json_encode(array_column($versi, 'id')));
cek('versi lama tetap ada', in_array((int) $h1['id'], array_map('intval', array_column($versi, 'id')), true));

$rekap = rekap_dokumen($p, $perId);
cek('rekap tetap 1 terunggah (bukan 2 — per butir, bukan per berkas)',
    (int) $rekap['unggah'] === 1, (string) $rekap['unggah']);
cek('rekap menghitung 1 kedaluwarsa (berlaku_sampai kemarin)',
    (int) $rekap['kedaluwarsa'] === 1, (string) $rekap['kedaluwarsa']);

echo "\n── hapus membuang berkas dari disk ──\n";

$pathLama = UNGGAH . '/' . basename((string) qv('SELECT berkas FROM dokumen_unggah WHERE id = ?',
    [(int) $h1['id']], ''));
cek('berkas versi lama ada sebelum dihapus', is_file($pathLama));

// Hapus lewat jalur yang sama dengan tombol di layar.
ex('DELETE FROM dokumen_unggah WHERE id = ?', [(int) $h1['id']]);
dok_hapus_berkas((string) qv('SELECT berkas FROM dokumen_unggah WHERE id = ?', [(int) $h1['id']], ''));
// Karena barisnya sudah dihapus, ambil nama dari variabel yang disimpan.
$namaLama = basename($pathLama);
dok_hapus_berkas($namaLama);
cek('berkas versi lama hilang dari disk', !is_file($pathLama), $pathLama);

$sisaBaris = (int) qv('SELECT COUNT(*) FROM dokumen_unggah WHERE dok_spec_id = ? AND prodi_id = ? AND periode_id = ?',
    [$spec, $p, $perId], 0);
cek('sisa 1 baris setelah hapus', $sisaBaris === 1, 'dapat ' . $sisaBaris);

echo "\n── penjagaan path ──\n";

// Nama berkas selalu dibuat server, tapi pastikan basename() benar-benar
// dipakai: klien tidak boleh bisa menunjuk keluar folder unggah.
$jahat = '../../etc/passwd';
cek('basename membuang jalur relatif', basename($jahat) === 'passwd');
cek('basename membuang jalur absolut', basename('/etc/passwd') === 'passwd');

$nyata = (string) realpath(UNGGAH);
$tujuan = UNGGAH . '/' . basename($jahat);
cek('hasil akhir tetap di dalam folder unggah',
    str_starts_with($tujuan, $nyata . DIRECTORY_SEPARATOR), $tujuan);

echo "\n── validasi tipe ditolak ──\n";

$ff = tempnam(sys_get_temp_dir(), 'exe-') . '.exe';
file_put_contents($ff, "MZ\x90\x00");
$bad = dok_validasi_berkas($ff, 'virus.exe', filesize($ff));
cek('ekstensi .exe ditolak', ($bad['ok'] ?? true) === false, json_encode($bad));
@unlink($ff);

$fpdf = pdf_uji('cek');
$palsu = dok_validasi_berkas($fpdf, 'gambar.png', filesize($fpdf));
cek('isi PDF dengan nama .png ditolak (MIME diperiksa)',
    ($palsu['ok'] ?? true) === false, json_encode($palsu));
@unlink($fpdf);

bersihkan_data_uji(db(), true);
// Bersihkan berkas uji yang mungkin tertinggal.
foreach (glob(UNGGAH . '/*') ?: [] as $f) {
    if (str_contains(basename($f), '_')) {
        @unlink($f);
    }
}
cek('folder unggah bersih dari berkas uji',
    array_filter(glob(UNGGAH . '/*') ?: [], fn($f) => str_contains(basename($f), '_')) === []);

printf("\n%d lulus, %d gagal\n", $lulus, $gagal);
exit($gagal === 0 ? 0 : 1);
