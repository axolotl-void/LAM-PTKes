<?php
declare(strict_types=1);

/**
 * Helper tampilan. Semua keluaran ke HTML lewat `e()`.
 *
 * Aturan tampilan yang mengikat (dari 08 - Aturan Desain Anti-Slop):
 *  - status SELALU label teks + ikon, bukan warna saja (asesor cetak hitam-putih)
 *  - bar di samping angka harus mengkodekan besaran yang sama dengan angkanya
 *  - angka yang bisa dihitung jangan diketik
 */

require_once __DIR__ . '/auth.php';

/** Escape untuk HTML. Satu-satunya jalan keluarnya data ke halaman. */
function e(mixed $v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Escape untuk atribut JSON di dalam tag. */
function ejson(mixed $v): string
{
    return e(json_encode($v, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP));
}

function rupiah(float|int|string|null $n): string
{
    if ($n === null || $n === '') {
        return '—';
    }
    return number_format((float) $n, 0, ',', '.');
}

function desimal(float|int|string|null $n, int $digit = 2): string
{
    if ($n === null || $n === '') {
        return '—';
    }
    return number_format((float) $n, $digit, ',', '.');
}

/** Tanggal Indonesia: 2026-11-13 -> 13 Nov 2026 */
function tgl(?string $iso): string
{
    if (!$iso) {
        return '—';
    }
    $bulan = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun',
              'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    $t = strtotime($iso);
    if ($t === false) {
        return '—';
    }
    return date('j', $t) . ' ' . $bulan[(int) date('n', $t)] . ' ' . date('Y', $t);
}

/**
 * Label tahun relatif instrumen. Tahun absolut disimpan; label dihitung.
 * TS = tahun terakhir periode pelaporan. TS-1 = satu tahun sebelumnya.
 */
function label_ts(int $tahunTs, int $mundur): string
{
    $t = $tahunTs - $mundur;
    return sprintf('TS-%d (%d/%d)', $mundur, $t, $t + 1);
}

/**
 * Label status tabel. Teks + kelas ikon, tidak pernah warna saja.
 * Status dihitung dari field_terisi/field_total — tidak disimpan.
 */
function status_tabel(int $terisi, int $total, bool $dikunci, bool $berlaku): string
{
    if (!$berlaku) {
        return 'na';
    }
    if ($dikunci) {
        return 'terkunci';
    }
    if ($total > 0 && $terisi >= $total) {
        return 'lengkap';
    }
    return $terisi === 0 ? 'kosong' : 'sebagian';
}

const STATUS_LABEL = [
    'kosong'    => 'Kosong',
    'sebagian'  => 'Sebagian',
    'lengkap'   => 'Lengkap',
    'terkunci'  => 'Terkunci',
    'na'        => 'Tidak berlaku',
];

function badge_status(string $status): string
{
    $label = STATUS_LABEL[$status] ?? $status;
    return sprintf('<span class="badge badge-%s">%s</span>', e($status), e($label));
}

/** Hentikan request dengan JSON + kode HTTP, lalu keluar. */
function tolak(int $kode, string $pesan): never
{
    if (!headers_sent()) {
        http_response_code($kode);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode(['galat' => $pesan], JSON_UNESCAPED_UNICODE);
    exit;
}

/** Redirect dan keluar. Selalu `exit` — tanpa itu eksekusi berlanjut. */
function alihkan(string $tujuan): never
{
    header('Location: ' . $tujuan, true, 302);
    exit;
}

/** Nomor halaman dari query string, minimal 1. */
function halaman(int $bawaan = 1): int
{
    return max(1, (int) ($_GET['hal'] ?? $bawaan));
}

/** Ambil input POST/GET sebagai string, sudah di-trim. */
function masuk_input(string $nama, string $bawaan = ''): string
{
    $v = $_POST[$nama] ?? $_GET[$nama] ?? $bawaan;
    return is_string($v) ? trim($v) : $bawaan;
}

/** Csrf token sederhana per sesi. Wajib di setiap form POST. */
function csrf(): string
{
    sesi_mulai();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function csrf_input(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf()) . '">';
}

function csrf_periksa(): void
{
    sesi_mulai();
    $kirim = (string) ($_POST['_csrf'] ?? '');
    if (empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $kirim)) {
        tolak(419, 'Token form kedaluwarsa. Muat ulang halaman lalu coba lagi.');
    }
}
