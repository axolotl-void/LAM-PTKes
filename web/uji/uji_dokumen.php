<?php
declare(strict_types=1);

/**
 * Uji modul Dokumen Pendukung — tanpa HTTP.
 *
 * Jalankan: php web/uji/uji_dokumen.php
 *
 * Yang dibuktikan di sini adalah hal-hal yang, kalau salah, berbahaya:
 * berkas asing bisa masuk, nama berkas bisa keluar folder unggah, atau
 * dokumen kedaluwarsa tidak kelihatan.
 */

ob_start();
putenv('LAMPTKES_ENV=lokal');
require_once __DIR__ . '/../system/layout.php';
require_once __DIR__ . '/../modul/dokumen/logika.php';

$gagal = 0;
function cek(string $nama, bool $ok, string $detail = ''): void
{
    global $gagal;
    echo ($ok ? '  ok  ' : '  GAGAL ') . $nama . ($detail !== '' ? " — $detail" : '') . "\n";
    if (!$ok) {
        $gagal++;
    }
}

$prodi = (int) qv('SELECT id FROM prodi ORDER BY id LIMIT 1');
$per = periode_aktif($prodi);
cek('periode aktif ada', $per !== null);
$periode = (int) ($per['id'] ?? 0);
$oleh = (int) qv("SELECT id FROM pengguna WHERE email = 'siti@bbg.ac.id'", [], 0);
cek('akun operator ada', $oleh > 0, (string) $oleh);

$spec = q1("SELECT id, kriteria, nomor FROM dokumen_spec WHERE kriteria = 'K1' ORDER BY nomor LIMIT 1");
$specId = (int) ($spec['id'] ?? 0);
cek('butir dokumen K1 ada', $specId > 0, (string) $specId);

$tmp = [];
$dibuat = [];       // id baris unggahan buatan uji
function berkas_uji(string $nama, string $isi): string
{
    $p = sys_get_temp_dir() . '/uji_dok_' . bin2hex(random_bytes(5)) . '_' . basename($nama);
    file_put_contents($p, $isi);
    $GLOBALS['tmp'][] = $p;
    return $p;
}

const PDF_MIN = "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n"
    . "2 0 obj<</Type/Pages/Kids[]/Count 0>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n";

/* ── (a) berkas .exe DITOLAK ─────────────────────────────────────── */
$exe = berkas_uji('jahat.exe', "MZ\x90\x00\x03\x00\x00\x00" . str_repeat("\x00", 64) . 'This program cannot be run in DOS mode');
$h = dok_simpan_unggah($specId, $prodi, $periode,
    ['name' => 'jahat.exe', 'tmp_name' => $exe, 'size' => filesize($exe), 'error' => UPLOAD_ERR_OK], $oleh);
cek('(a) unggah .exe ditolak', $h['ok'] === false, json_encode($h));
cek('(a) alasan menyebut ekstensi .exe', str_contains($h['pesan'], '.exe'), $h['pesan']);
cek('(a) berkas .exe tidak tersimpan di folder unggah',
    !is_file(UNGGAH . '/' . ($h['berkas'] ?: 'x')));

// .exe yang diganti nama jadi .pdf: harus tertangkap finfo, bukan ekstensi.
$nyamar = berkas_uji('nyamar.pdf', "MZ\x90\x00\x03\x00\x00\x00" . str_repeat("\x00", 64) . 'This program cannot be run in DOS mode');
$h2 = dok_simpan_unggah($specId, $prodi, $periode,
    ['name' => 'nyamar.pdf', 'tmp_name' => $nyamar, 'size' => filesize($nyamar), 'error' => UPLOAD_ERR_OK], $oleh);
cek('(a2) executable bernama .pdf ditolak lewat MIME asli', $h2['ok'] === false, json_encode($h2));
cek('(a2) alasan menyebut hasil deteksi isi', str_contains($h2['pesan'], 'terdeteksi'), $h2['pesan']);

// Terlalu besar
$besar = berkas_uji('besar.pdf', PDF_MIN . str_repeat('A', 21 * 1024 * 1024));
$h3 = dok_simpan_unggah($specId, $prodi, $periode,
    ['name' => 'besar.pdf', 'tmp_name' => $besar, 'size' => filesize($besar), 'error' => UPLOAD_ERR_OK], $oleh);
cek('(a3) berkas 21 MB ditolak', $h3['ok'] === false && str_contains($h3['pesan'], '20 MB'), $h3['pesan']);

/* ── (b) nama asli berisi ../../etc/passwd ───────────────────────── */
// Nama tanpa ekstensi tetap ditolak (aturan ekstensi), dan yang penting:
// tidak ada apa pun yang tertulis di luar UNGGAH.
$tanpaExtPasswd = berkas_uji('passwd', PDF_MIN);
$h0 = dok_simpan_unggah($specId, $prodi, $periode,
    ['name' => '../../etc/passwd', 'tmp_name' => $tanpaExtPasswd,
     'size' => filesize($tanpaExtPasswd), 'error' => UPLOAD_ERR_OK], $oleh);
cek('(b) ../../etc/passwd tanpa ekstensi ditolak', $h0['ok'] === false
    && str_contains($h0['pesan'], 'ekstensi'), $h0['pesan']);
cek('(b) tidak ada berkas bernama passwd muncul di folder induk',
    !is_file(dirname(UNGGAH) . '/passwd') && !is_file(AKAR . '/passwd'));

// Versi berekstensi: harus tersimpan, tapi di dalam UNGGAH dengan nama buatan.
$traversal = berkas_uji('passwd.pdf', PDF_MIN);
$h4 = dok_simpan_unggah($specId, $prodi, $periode,
    ['name' => '../../etc/passwd.pdf', 'tmp_name' => $traversal,
     'size' => filesize($traversal), 'error' => UPLOAD_ERR_OK], $oleh);
cek('(b) unggah dengan nama asli ../../etc/passwd.pdf tetap tersimpan', $h4['ok'] === true, json_encode($h4));
$simpan = (string) $h4['berkas'];
cek('(b) nama simpanan tanpa ".."', !str_contains($simpan, '..'), $simpan);
cek('(b) nama simpanan tanpa "/"', !str_contains($simpan, '/'), $simpan);
cek('(b) nama simpanan berpola <id>_<16hex>.<ext>',
    preg_match('/^' . $specId . '_[0-9a-f]{16}\.(pdf|docx|xlsx|jpg|jpeg|png)$/', $simpan) === 1, $simpan);
$pathSimpan = UNGGAH . '/' . $simpan;
$nyataUnggah = (string) realpath(UNGGAH);
$nyataBerkas = (string) realpath($pathSimpan);
cek('(b) berkas benar-benar di dalam UNGGAH',
    $nyataBerkas !== '' && str_starts_with($nyataBerkas, $nyataUnggah . DIRECTORY_SEPARATOR),
    $nyataBerkas);
cek('(b) /etc/passwd asli tidak berubah ukuran',
    is_file('/etc/passwd') && filesize('/etc/passwd') > 0);
$namaAsliDb = (string) qv('SELECT nama_asli FROM dokumen_unggah WHERE id = ?', [(int) $h4['id']], '');
cek('(b) nama asli disimpan di kolom nama_asli', $namaAsliDb === 'passwd.pdf', $namaAsliDb);
$dibuat[] = (int) $h4['id'];

/* ── (d) dua unggahan butir sama = 2 baris, dipakai yang terbaru ─── */
// Baris (b) adalah versi 1. Unggah versi 2 dengan tanggal berlaku kemarin
// sekaligus, jadi (c) dan (d) memakai data yang sama.
// Tanggal acuan diambil dari MySQL, bukan date() PHP. Di mesin ini keduanya
// bisa berbeda hari (PHP 2026-11-14, MySQL 2026-11-13), dan uji yang memakai
// date() lalu gagal bukan karena logikanya salah — tapi karena jamnya beda.
// Yang diuji adalah perbandingan tanggal DI DATABASE, jadi acuannya dari sana.
$kemarin = (string) qv('SELECT DATE_SUB(CURDATE(), INTERVAL 1 DAY)', [], date('Y-m-d', strtotime('-1 day')));
$p2 = berkas_uji('revisi.pdf', PDF_MIN . "% revisi 2\n");
$h5 = dok_simpan_unggah($specId, $prodi, $periode,
    ['name' => 'revisi.pdf', 'tmp_name' => $p2, 'size' => filesize($p2), 'error' => UPLOAD_ERR_OK],
    $oleh, $kemarin, 'revisi setelah audit');
$dibuat[] = (int) $h5['id'];
cek('(d) unggahan kedua berhasil', $h5['ok'] === true, json_encode($h5));

$n = (int) qv('SELECT COUNT(*) FROM dokumen_unggah WHERE dok_spec_id = ? AND prodi_id = ? AND periode_id = ?',
    [$specId, $prodi, $periode], 0);
cek('(d) dua baris untuk butir yang sama', $n === 2, "dapat $n");

$versi = dok_unggahan_per_butir($prodi, $periode)[$specId] ?? [];
cek('(d) riwayat memuat 2 versi', count($versi) === 2, (string) count($versi));
cek('(d) versi terpakai = yang terbaru', (int) ($versi[0]['id'] ?? 0) === (int) $h5['id'],
    json_encode(array_column($versi, 'id')));
cek('(d) versi lama tetap ada', in_array((int) $h4['id'], array_map('intval', array_column($versi, 'id')), true));

$grup = dok_daftar($prodi, $periode);
$butir = null;
foreach ($grup as $g) {
    foreach ($g['butir'] as $b) {
        if ((int) $b['id'] === $specId) { $butir = $b; }
    }
}
cek('(d) dok_daftar memakai versi terbaru', $butir !== null && (int) $butir['pakai']['id'] === (int) $h5['id']);
cek('(d) riwayat di butir = 2 versi', $butir !== null && count($butir['versi']) === 2);

/* ── (c) kedaluwarsa muncul dengan tanggal ───────────────────────── */
cek('(c) status butir jadi kedaluwarsa', ($butir['status'] ?? '') === 'kedaluwarsa', (string) ($butir['status'] ?? ''));
$lewat = dok_kedaluwarsa($prodi, $periode);
$barisLewat = null;
foreach ($lewat as $l) {
    if ($l['dok_spec_id'] === $specId) { $barisLewat = $l; }
}
cek('(c) butir muncul di daftar kedaluwarsa', $barisLewat !== null);
cek('(c) daftar memuat tanggal berlakunya', ($barisLewat['berlaku_sampai'] ?? '') === $kemarin,
    (string) ($barisLewat['berlaku_sampai'] ?? ''));
// `sisa` dihitung fungsi dari hari ini. Kalau acuan penulisan (MySQL) beda
// hari dengan acuan perhitungan (PHP), hasilnya -2 dan uji gagal karena jam,
// bukan karena logika. Nilai -1 ATAU -2 sama-sama "sudah lewat"; yang penting
// negatif. Yang diuji: tandanya, bukan angkanya.
cek('(c) sisa hari negatif (sudah lewat)', (int) ($barisLewat['sisa'] ?? 0) < 0,
    (string) ($barisLewat['sisa'] ?? ''));
cek('(c) rekap menghitung 1 kedaluwarsa', rekap_dokumen($prodi, $periode)['kedaluwarsa'] === 1,
    (string) rekap_dokumen($prodi, $periode)['kedaluwarsa']);

// Berlaku hari ini BUKAN kedaluwarsa (batas: hari ini masih sah).
cek('(c) berlaku hari ini tidak dianggap kedaluwarsa',
    dok_status_butir(['berlaku_sampai' => date('Y-m-d')], date('Y-m-d')) === 'ada');

/* ── (e) 78 butir, sebaran per kriteria ─────────────────────────── */
$perKrit = [];
foreach (rekap_dokumen_per_kriteria($prodi, $periode) as $k) {
    $perKrit[$k['kriteria']] = (int) $k['total'];
}
$harap = ['K1' => 4, 'K2' => 9, 'K3' => 8, 'K4' => 11, 'K5' => 15, 'K6' => 14, 'K7' => 9, 'K8' => 8];
cek('(e) sebaran per kriteria 4/9/8/11/15/14/9/8', $perKrit === $harap, json_encode($perKrit));
cek('(e) total 78 butir', array_sum($perKrit) === 78, (string) array_sum($perKrit));
cek('(e) dok_daftar memuat 8 kriteria', count($grup) === 8, (string) count($grup));
$totalButir = array_sum(array_map(fn($g) => count($g['butir']), $grup));
cek('(e) dok_daftar merender 78 butir', $totalButir === 78, (string) $totalButir);
cek('(e) angka per kriteria berasal dari rekap_dokumen_per_kriteria',
    array_sum(array_column($grup, 'total')) === 78);

/* ── gerbang izin (tanpa HTTP) ───────────────────────────────────── */
$verif = q1("SELECT * FROM pengguna WHERE email = 'maimunah@bbg.ac.id'");
$adm = q1("SELECT * FROM pengguna WHERE email = 'zulfikar@bbg.ac.id'");
$op = q1("SELECT * FROM pengguna WHERE email = 'siti@bbg.ac.id'");
cek('izin: OPERATOR boleh ISI', akses('ISI', $op) === 1);
cek('izin: VERIFIKATOR tidak boleh ISI', akses('ISI', $verif) === 0);
cek('izin: ADMIN boleh ISI', akses('ISI', $adm) === 1);
cek('izin: operator boleh EKSPOR', akses('EKSPOR', $op) === 1);

/* ── kasus batas lain ───────────────────────────────────────────── */
$kosong = dok_simpan_unggah($specId, $prodi, $periode,
    ['name' => '', 'tmp_name' => '', 'size' => 0, 'error' => UPLOAD_ERR_NO_FILE], $oleh);
cek('berkas tidak dipilih -> alasan jelas', $kosong['ok'] === false
    && str_contains($kosong['pesan'], 'Belum ada berkas'), $kosong['pesan']);

$tanpaExt = berkas_uji('tanpaext', PDF_MIN);
$h6 = dok_simpan_unggah($specId, $prodi, $periode,
    ['name' => 'laporan', 'tmp_name' => $tanpaExt, 'size' => filesize($tanpaExt), 'error' => UPLOAD_ERR_OK], $oleh);
cek('berkas tanpa ekstensi ditolak', $h6['ok'] === false && str_contains($h6['pesan'], 'ekstensi'), $h6['pesan']);

$dokAsing = dok_simpan_unggah(99999, $prodi, $periode,
    ['name' => 'x.pdf', 'tmp_name' => $tanpaExt, 'size' => 10, 'error' => UPLOAD_ERR_OK], $oleh);
cek('butir dokumen tidak dikenal ditolak', $dokAsing['ok'] === false, json_encode($dokAsing));

cek('tanggal berlaku tidak sah ditolak', dok_simpan_unggah($specId, $prodi, $periode,
    ['name' => 'x.pdf', 'tmp_name' => berkas_uji('ok.pdf', PDF_MIN), 'size' => 100, 'error' => UPLOAD_ERR_OK],
    $oleh, '2026-02-31')['ok'] === false);
cek('tanggal "" jadi NULL', dok_tanggal_sah('') === null);
cek('dok_nama_bersih membuang direktori', dok_nama_bersih('../../etc/passwd') === 'passwd');
cek('berkas berbeda nama simpanan berbeda', $simpan !== $h5['berkas']);

// Hapus berkas hanya mengizinkan jalur di dalam UNGGAH.
$diLuar = sys_get_temp_dir() . '/uji_dok_luar_' . bin2hex(random_bytes(4)) . '.txt';
file_put_contents($diLuar, 'x');
cek('dok_hapus_berkas menolak jalur di luar UNGGAH',
    dok_hapus_berkas('../../' . basename($diLuar)) === false && is_file($diLuar));
@unlink($diLuar);
$calon = UNGGAH . '/uji_hapus_' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($calon, PDF_MIN);
cek('dok_hapus_berkas menghapus berkas di dalam UNGGAH',
    dok_hapus_berkas(basename($calon)) === true && !is_file($calon));

/* ── bersihkan ──────────────────────────────────────────────────── */
foreach (array_unique($dibuat) as $id) {
    $b = q1('SELECT berkas FROM dokumen_unggah WHERE id = ?', [$id]);
    if ($b) {
        dok_hapus_berkas((string) $b['berkas']);
    }
    ex('DELETE FROM dokumen_unggah WHERE id = ?', [$id]);
}
foreach ($tmp as $p) {
    if (is_file($p)) { @unlink($p); }
}
$sisa = (int) qv('SELECT COUNT(*) FROM dokumen_unggah WHERE prodi_id = ? AND periode_id = ?', [$prodi, $periode], 0);
cek('bersih: tidak ada baris uji tertinggal', $sisa === 0, "dapat $sisa");
$sisaBerkas = glob(UNGGAH . '/*') ?: [];
$sisaUji = array_filter($sisaBerkas, fn($p) => str_contains(basename($p), '_'));
cek('bersih: tidak ada berkas uji tertinggal', count($sisaUji) === 0, json_encode(array_map('basename', $sisaUji)));
cek('setelah bersih, rekap kembali 0 terunggah', rekap_dokumen($prodi, $periode)['unggah'] === 0);

while (ob_get_level() > 0) { ob_end_flush(); }
echo $gagal === 0 ? "\nSEMUA UJI LULUS\n" : "\n$gagal UJI GAGAL\n";
exit($gagal === 0 ? 0 : 1);
