<?php
declare(strict_types=1);

/**
 * Router tunggal: /api/* ke api.php, sisanya file statis dari folder `app/`.
 *
 * Kenapa satu pintu: frontend (`app/`) dan API harus satu origin, kalau tidak
 * cookie sesi tidak ikut terkirim dan browser menolak fetch karena CORS.
 * Di cPanel ini jadi `.htaccess`; di lokal ini `php -S router.php`.
 *
 * Frontend TIDAK disalin ke sini. `app/index.html` + `app/assets/*` dipakai
 * apa adanya — itu tampilan yang sudah disetujui.
 */

$akar = dirname(__DIR__);
$app = $akar . '/app';
$jalur = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

// API
if (str_starts_with($jalur, '/api/')) {
    require __DIR__ . '/api.php';
    return true;
}

// Berkas statis dari app/
$relatif = ltrim($jalur, '/');
if ($relatif === '') {
    $relatif = 'index.html';
}
$berkas = realpath($app . '/' . $relatif);

// Cegah keluar folder app/ — '../..' tidak boleh menyentuh web/ atau repo.
if ($berkas === false || !str_starts_with($berkas, realpath($app))) {
    http_response_code(404);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'berkas tidak ada']);
    return true;
}
if (is_dir($berkas)) {
    $berkas = rtrim($berkas, '/') . '/index.html';
    if (!is_file($berkas)) {
        http_response_code(404);
        echo 'tidak ada index.html';
        return true;
    }
}

$ext = strtolower(pathinfo($berkas, PATHINFO_EXTENSION));
$tipe = [
    'html' => 'text/html; charset=utf-8',
    'css' => 'text/css; charset=utf-8',
    'js' => 'text/javascript; charset=utf-8',
    'json' => 'application/json; charset=utf-8',
    'svg' => 'image/svg+xml',
    'png' => 'image/png',
    'jpg' => 'image/jpeg',
    'ico' => 'image/x-icon',
    'woff2' => 'font/woff2',
][$ext] ?? 'application/octet-stream';

header('Content-Type: ' . $tipe);
// ES module di Safari tersimpan di cache memori yang tidak terhapus oleh
// hard-reload. no-store mencegah modul lama dipakai setelah edit.
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
readfile($berkas);
return true;
