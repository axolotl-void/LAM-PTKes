<?php
/**
 * Uji asap HTTP tanpa curl: panggil handler langsung di CLI.
 * Dipakai untuk memastikan jalur masuk/keluar jalan sebelum 10 agent mulai.
 *
 * Jalankan: php web/uji/asap_masuk.php
 */
declare(strict_types=1);

ob_start();                             // uji mencetak sebelum sesi dimulai
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTPS'] = '';
putenv('LAMPTKES_ENV=lokal');

require_once __DIR__ . '/../system/view.php';

$gagal = 0;
function cek(string $nama, bool $ok, string $detail = ''): void
{
    global $gagal;
    echo ($ok ? '  ok  ' : '  GAGAL ') . $nama . ($detail !== '' ? " — $detail" : '') . "\n";
    if (!$ok) {
        $gagal++;
    }
}

// 1. koneksi + data awal
cek('koneksi database', (bool) qv('SELECT 1'), 'db.php / config.php');
cek('2 prodi ter-seed', (int) qv('SELECT COUNT(*) FROM prodi') === 2);
cek('6 akun ter-seed', (int) qv('SELECT COUNT(*) FROM pengguna') === 6);
cek('38 tabel dukung', (int) qv('SELECT COUNT(*) FROM tabel_dukung') === 38);
// Lantai 243 = jumlah field sebelum label bertanda-√ dipecah jadi beberapa kolom (kini 265).
// Yang dijaga bukan angkanya, tapi bahwa regenerasi skema tidak menghilangkan field.
$dbField = (int) qv('SELECT COUNT(*) FROM spec_field');
cek('field spec tidak menyusut', $dbField >= 243, "dapat $dbField");
cek('78 dokumen pendukung', (int) qv('SELECT COUNT(*) FROM dokumen_spec') === 78);
cek('10 aturan konsistensi', (int) qv('SELECT COUNT(*) FROM konsistensi_aturan') === 10);
cek('matriks izin 24 baris', (int) qv('SELECT COUNT(*) FROM izin') === 24);

// 2. sandi benar-benar cocok (bukan hash karangan)
$u = q1('SELECT * FROM pengguna WHERE email = ?', ['siti@bbg.ac.id']);
cek('akun operator ditemukan', $u !== null);
cek('sandi demo1234 cocok', $u !== null && password_verify('demo1234', (string) $u['sandi']));
cek('sandi salah ditolak', $u !== null && !password_verify('salah', (string) $u['sandi']));

// 3. gerbang izin membaca tabel izin, bukan tebakan
$op = $u;
$vf = q1('SELECT * FROM pengguna WHERE email = ?', ['maimunah@bbg.ac.id']);
cek('operator boleh ISI', akses('ISI', $op) === 1);
cek('operator tidak boleh VERIFIKASI', akses('VERIFIKASI', $op) === 0);
cek('verifikator boleh VERIFIKASI', akses('VERIFIKASI', $vf) === 1);
cek('verifikator tidak boleh ISI', akses('ISI', $vf) === 0);
cek('tanpa pengguna semua akses 0', akses('ISI', null) === 0);

// 4. status tabel diturunkan, bukan disimpan
cek('status kosong', status_tabel(0, 6, false, true) === 'kosong');
cek('status sebagian', status_tabel(3, 6, false, true) === 'sebagian');
cek('status lengkap', status_tabel(6, 6, false, true) === 'lengkap');
cek('status terkunci menang atas lengkap', status_tabel(6, 6, true, true) === 'terkunci');
cek('status na untuk tabel tak berlaku', status_tabel(0, 6, false, false) === 'na');

// 5. label tahun dihitung dari tahun_ts
cek('label TS-1 dari 2025', label_ts(2025, 1) === 'TS-1 (2024/2025)');
cek('label TS-0 dari 2025', label_ts(2025, 0) === 'TS-0 (2025/2026)');

// 6. spesifikasi 38 tabel bisa dibaca PHP
$spec = require __DIR__ . '/../spesifikasi/tabel.php';
cek('spec PHP 38 tabel', count($spec) === 38);
$total = 0;
$bedaTabel = 0;
foreach ($spec as $kode => $t) {
    $total += count($t['fields']);
    $n = (int) qv('SELECT COUNT(*) FROM spec_field WHERE tabel_kode = ?', [$kode]);
    if ($n !== count($t['fields'])) {
        cek("spec $kode sama dengan DB", false, "DB $n vs PHP " . count($t['fields']));
        $bedaTabel++;
    }
}
// Dulu baris ini cek('spec PHP = spec DB', true) — selalu lulus, jadi tidak menjaga apa pun.
// Sekarang benar-benar dibandingkan: kalau gen_skema_tabel.py dan migrasi tidak sinkron,
// total PHP dan total DB berbeda dan uji ini gagal.
cek('spec PHP = spec DB', $bedaTabel === 0 && $total === $dbField, "PHP $total vs DB $dbField");
// Lantai 243, bukan angka mati: 243 adalah jumlah sebelum label bertanda-√ dipecah jadi
// beberapa kolom (kini 265). Turun di bawahnya berarti ada field hilang saat regenerasi.
cek('spec PHP tidak menyusut', $total >= 243, "dapat $total field");

// 7. tabel khusus profesi tepat 6
$khusus = array_filter($spec, fn($t) => $t['khusus']);
cek('6 tabel khusus profesi', count($khusus) === 6,
    'nomor: ' . implode(',', array_column($khusus, 'nomor')));

while (ob_get_level() > 0) { ob_end_flush(); }
echo $gagal === 0 ? "\nSEMUA UJI LULUS\n" : "\n$gagal UJI GAGAL\n";
exit($gagal === 0 ? 0 : 1);
