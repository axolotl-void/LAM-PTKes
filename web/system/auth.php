<?php
declare(strict_types=1);

/**
 * Sesi, peran, dan gerbang izin.
 *
 * Satu aturan yang tidak boleh dilanggar: peran diambil dari DATABASE, tidak
 * pernah dari request. Semua gerbang memanggil `boleh()` yang membaca tabel
 * `izin` — sumber yang sama dipakai UI untuk menyembunyikan tombol. Kalau UI
 * dan server membaca tabel yang sama, keduanya tidak mungkin beda pendapat.
 */

require_once __DIR__ . '/db.php';

const AKSI = ['ISI', 'IMPOR', 'VERIFIKASI', 'BUKA_KUNCI', 'KELOLA_PENGGUNA', 'EKSPOR'];

function sesi_mulai(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    ]);
    session_name('lamptkes');
    session_start();
}

/** Pengguna yang sedang masuk, atau null. Dibaca sekali per request. */
function pengguna(): ?array
{
    static $cache = false;
    if ($cache !== false) {
        return $cache;
    }
    sesi_mulai();
    $id = $_SESSION['pengguna_id'] ?? null;
    if (!$id) {
        return $cache = null;
    }
    $r = q1('SELECT p.*, pr.nama_ps AS prodi_nama
             FROM pengguna p LEFT JOIN prodi pr ON pr.id = p.prodi_id
             WHERE p.id = ? AND p.aktif = 1', [$id]);
    return $cache = $r ?: null;
}

/** Prodi yang sedang dibuka. Operator/Verifikator terikat ke prodinya. */
function prodi_aktif(): ?int
{
    $u = pengguna();
    if (!$u) {
        return null;
    }
    if (!empty($u['prodi_id'])) {
        return (int) $u['prodi_id'];
    }
    return isset($_SESSION['prodi_id']) ? (int) $_SESSION['prodi_id'] : null;
}

/** 0 tidak boleh, 1 boleh, 2 hanya lihat. */
function akses(string $aksi, ?array $u = null): int
{
    $u ??= pengguna();
    if (!$u) {
        return 0;
    }
    return (int) qv('SELECT akses FROM izin WHERE peran_kode = ? AND aksi_kode = ?',
        [$u['peran_kode'], $aksi], 0);
}

function boleh(string $aksi): bool
{
    return akses($aksi) === 1;
}

function hanya_lihat(string $aksi): bool
{
    return akses($aksi) === 2;
}

/**
 * Hentikan request kalau tidak berhak. Dipanggil di awal setiap handler tulis.
 * 401 = belum masuk, 403 = sudah masuk tapi tidak berhak. Bedanya penting:
 * 401 memancing login ulang, 403 tidak.
 */
function wajib(string $aksi): void
{
    $u = pengguna();
    if (!$u) {
        tolak(401, 'Sesi berakhir. Silakan masuk lagi.');
    }
    if (akses($aksi, $u) !== 1) {
        tolak(403, sprintf('Peran %s tidak boleh: %s', $u['peran_kode'], $aksi));
    }
}

function wajib_masuk(): array
{
    $u = pengguna();
    if (!$u) {
        tolak(401, 'Sesi berakhir. Silakan masuk lagi.');
    }
    return $u;
}

/** Keluar. Sesi dihancurkan, cookie dibuang. */
function keluar(): void
{
    sesi_mulai();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

/** Buat sesi untuk pengguna. Dipakai login lokal DAN SSO. */
function masuk_sebagai(int $penggunaId, ?int $prodiId = null): void
{
    sesi_mulai();
    session_regenerate_id(true);
    $_SESSION['pengguna_id'] = $penggunaId;
    $_SESSION['prodi_id'] = $prodiId;
    $_SESSION['masuk_pada'] = time();
    ex('UPDATE pengguna SET masuk_terakhir = NOW() WHERE id = ?', [$penggunaId]);
}

/**
 * Cek peran ganda. Skema sudah mustahil, jadi ini hanya pertahanan kalau
 * data dimasukkan manual lewat phpMyAdmin dengan bentuk lama ("Operator + Verif").
 */
function peran_ganda(string $peran): bool
{
    $p = strtolower($peran);
    return str_contains($p, 'operator') && str_contains($p, 'verifikator');
}
