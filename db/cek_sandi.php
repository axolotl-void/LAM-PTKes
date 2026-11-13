<?php
/** Verifikasi hash sandi di 005_seed.sql benar-benar cocok dengan 'demo1234'.
 *  Jalankan: php db/cek_sandi.php */
declare(strict_types=1);

$sql = file_get_contents(__DIR__ . '/migrasi/005_seed.sql');
preg_match_all('/\$2y\$12\$[A-Za-z0-9.\/]+/', $sql, $m);
$hash = $m[0][0] ?? '';
echo 'hash ditemukan: ' . count($m[0]) . " baris\n";
echo 'hash: ' . $hash . "\n";
echo 'panjang: ' . strlen($hash) . "\n";
$ok = password_verify('demo1234', $hash);
echo 'password_verify("demo1234"): ' . ($ok ? 'COCOK' : 'TIDAK COCOK') . "\n";
exit($ok ? 0 : 1);
