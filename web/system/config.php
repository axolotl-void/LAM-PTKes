<?php
declare(strict_types=1);

/**
 * Konfigurasi. Satu berkas, dua lingkungan.
 *
 * Di produksi (cPanel) berkas ini TIDAK di-commit — isinya kredensial.
 * Di lokal, nilai di bawah dipakai apa adanya untuk container `si-lab-mysql`.
 *
 * RAHASIA SSO harus SAMA PERSIS dengan $sso_lamptkes_secret di
 * spmi.bbg.ac.id/system/config.php. Minimal 32 karakter.
 */

$lingkungan = getenv('LAMPTKES_ENV') ?: 'lokal';

if ($lingkungan === 'produksi') {
    define('DB_HOST', 'localhost');            // cPanel selalu localhost
    define('DB_PORT', '3306');
    define('DB_NAME', 'GANTI_nama_db_cpanel');
    define('DB_USER', 'GANTI_user_db_cpanel');
    define('DB_PASS', 'GANTI_sandi_db_cpanel');
    define('BASE_URL', 'https://GANTI_subdomain/');
    define('SSO_RAHASIA', 'GANTI_rahasia_bersama_minimal_32_karakter');
    define('DEBUG', false);
} else {
    // Container `si-lab-mysql` (dipakai bersama SPMI) memetakan 3306 ke 3307
    // di host. Kalau MySQL lokal di 3306, ganti port di sini.
    define('DB_HOST', '127.0.0.1');
    define('DB_PORT', '3307');
    define('DB_NAME', 'bbg_lamptkes');
    define('DB_USER', 'root');
    define('DB_PASS', 'root');
    define('BASE_URL', '/');
    define('SSO_RAHASIA', 'rahasia-lokal-uji-sso-minimal-32-karakter-ok');
    define('DEBUG', true);
}

/** Path fisik akar aplikasi (folder tempat index.php). */
define('AKAR', dirname(__DIR__));

/** Folder unggahan dokumen bukti. Di produksi taruh di luar webroot bila bisa. */
define('UNGGAH', AKAR . '/unggah');

if (DEBUG) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
}

date_default_timezone_set('Asia/Jakarta');
