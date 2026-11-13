<?php
declare(strict_types=1);

/**
 * Uji SSO SPMI -> LAM-PTKes. Tanpa server HTTP: panggil sso_terima() langsung.
 * Token dibuat di sini dengan hash_hmac, persis seperti laminfokom.php.
 *
 * Jalankan: php web/uji/uji_sso.php
 */

ob_start();                             // uji mencetak sebelum sesi dimulai
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTPS'] = '';
putenv('LAMPTKES_ENV=lokal');

require_once __DIR__ . '/../system/sso.php';

$gagal = 0;
function cek(string $nama, bool $ok, string $detail = ''): void
{
    global $gagal;
    echo ($ok ? '  ok  ' : '  GAGAL ') . $nama . ($detail !== '' ? " — $detail" : '') . "\n";
    if (!$ok) {
        $gagal++;
    }
}

/** Sama persis dengan penerbit di SPMI. */
function token_buat(array $isi, ?string $rahasia = null): string
{
    $b64 = static fn (string $x): string => rtrim(strtr(base64_encode($x), '+/', '-_'), '=');
    $isi64 = $b64(json_encode($isi));
    $ttd = $b64(hash_hmac('sha256', $isi64, $rahasia ?? SSO_RAHASIA, true));
    return $isi64 . '.' . $ttd;
}

function isi_baru(string $email, int $x = 0, ?string $nonce = null): array
{
    return [
        'e' => $email,
        'n' => 'Nama Uji',
        'r' => 'Auditee',
        'j' => $nonce ?? bin2hex(random_bytes(16)),
        'x' => $x > 0 ? $x : time() + 90,
    ];
}

// ── persiapan: akun nonaktif buatan sendiri, dibersihkan di akhir ──────────
$email_mati = 'uji-sso-nonaktif@bbg.ac.id';
ex('DELETE FROM pengguna WHERE email = ?', [$email_mati]);
sisip('pengguna', [
    'email' => $email_mati, 'nama' => 'Akun Nonaktif Uji', 'sandi' => null,
    'peran_kode' => 'OPERATOR', 'prodi_id' => 1, 'aktif' => 0,
]);

$nonce_dipakai = [];    // untuk pembersihan

// ── (a) token sah -> sukses, sesi terbentuk ────────────────────────────────
$nonce_a = bin2hex(random_bytes(16));
$nonce_dipakai[] = $nonce_a;
$isi_a = isi_baru('siti@bbg.ac.id', 0, $nonce_a);
$h = sso_terima(token_buat($isi_a));
cek('(a) token sah diterima', $h['ok'] === true, $h['pesan']);
cek('(a) pengguna dikembalikan', ($h['pengguna']['email'] ?? '') === 'siti@bbg.ac.id');
cek('(a) peran dari DB, bukan dari token r=Auditee',
    ($h['pengguna']['peran_kode'] ?? '') === 'OPERATOR');
cek('(a) sesi terbentuk', ($_SESSION['pengguna_id'] ?? 0) === (int) ($h['pengguna']['id'] ?? -1),
    'pengguna_id=' . ($_SESSION['pengguna_id'] ?? 'null'));
cek('(a) nonce tercatat', (int) qv('SELECT COUNT(*) FROM sso_nonce WHERE nonce = ?', [$nonce_a]) === 1);

// ── (b) kedaluwarsa ────────────────────────────────────────────────────────
$nonce_b = bin2hex(random_bytes(16));
$nonce_dipakai[] = $nonce_b;
$h = sso_terima(token_buat(isi_baru('siti@bbg.ac.id', time() - 1, $nonce_b)));
cek('(b) token kedaluwarsa ditolak', $h['ok'] === false, $h['pesan']);
cek('(b) alasan menyebut kedaluwarsa', stripos($h['pesan'], 'kedaluwarsa') !== false);

// ── (c) tanda tangan salah ─────────────────────────────────────────────────
$nonce_c = bin2hex(random_bytes(16));
$nonce_dipakai[] = $nonce_c;
$h = sso_terima(token_buat(isi_baru('siti@bbg.ac.id', 0, $nonce_c), 'rahasia-yang-salah-tapi-panjangnya-32+'));
cek('(c) tanda tangan salah ditolak', $h['ok'] === false, $h['pesan']);

// ── (d) isi diubah setelah ditandatangani ──────────────────────────────────
$nonce_d = bin2hex(random_bytes(16));
$nonce_dipakai[] = $nonce_d;
$isi_d = isi_baru('siti@bbg.ac.id', 0, $nonce_d);
$ttd_d = substr(token_buat($isi_d), strpos(token_buat($isi_d), '.') + 1);
$isi_d_ubah = $isi_d;
$isi_d_ubah['e'] = 'zulfikar@bbg.ac.id';            // naikkan hak diam-diam
$b64 = static fn (string $x): string => rtrim(strtr(base64_encode($x), '+/', '-_'), '=');
$h = sso_terima($b64(json_encode($isi_d_ubah)) . '.' . $ttd_d);
cek('(d) isi diubah ditolak', $h['ok'] === false, $h['pesan']);

// ── (e) nonce dipakai dua kali ─────────────────────────────────────────────
$nonce_e = bin2hex(random_bytes(16));
$nonce_dipakai[] = $nonce_e;
$tok_e = token_buat(isi_baru('maimunah@bbg.ac.id', 0, $nonce_e));
$h1 = sso_terima($tok_e);
$h2 = sso_terima($tok_e);
cek('(e) percobaan pertama diterima', $h1['ok'] === true, $h1['pesan']);
cek('(e) percobaan kedua ditolak', $h2['ok'] === false, $h2['pesan']);
cek('(e) nonce hanya satu baris', (int) qv('SELECT COUNT(*) FROM sso_nonce WHERE nonce = ?', [$nonce_e]) === 1);

// ── (f) email tidak terdaftar: ditolak DAN tidak ada akun baru ─────────────
$email_baru = 'orang-baru-sso@bbg.ac.id';
ex('DELETE FROM pengguna WHERE email = ?', [$email_baru]);
$sebelum = (int) qv('SELECT COUNT(*) FROM pengguna');
$nonce_f = bin2hex(random_bytes(16));
$nonce_dipakai[] = $nonce_f;
$h = sso_terima(token_buat(isi_baru($email_baru, 0, $nonce_f)));
$sesudah = (int) qv('SELECT COUNT(*) FROM pengguna');
cek('(f) email tidak terdaftar ditolak', $h['ok'] === false, $h['pesan']);
cek('(f) pesan menyuruh hubungi Admin UPPS', stripos($h['pesan'], 'Hubungi Admin UPPS') !== false);
cek('(f) tidak ada akun dibuat', $sebelum === $sesudah, "$sebelum -> $sesudah");
cek('(f) nonce tidak dicatat (token tidak menghabiskan jatah)',
    (int) qv('SELECT COUNT(*) FROM sso_nonce WHERE nonce = ?', [$nonce_f]) === 0);
cek('(f) sesi tidak berubah', ($_SESSION['pengguna_id'] ?? 0) === (int) ($h1['pengguna']['id'] ?? -1));

// ── (g) akun nonaktif ──────────────────────────────────────────────────────
$nonce_g = bin2hex(random_bytes(16));
$nonce_dipakai[] = $nonce_g;
$h = sso_terima(token_buat(isi_baru($email_mati, 0, $nonce_g)));
cek('(g) akun nonaktif ditolak', $h['ok'] === false, $h['pesan']);
cek('(g) alasan menyebut dinonaktifkan', stripos($h['pesan'], 'dinonaktifkan') !== false);

// ── (h) batas: token kosong / tanpa titik / bukan base64 ───────────────────
cek('(h) token kosong ditolak', sso_terima('')['ok'] === false);
cek('(h) token tanpa titik ditolak', sso_terima('abcdef')['ok'] === false);
cek('(h) tanda tangan bukan base64 ditolak', sso_terima('abcdef.!!!')['ok'] === false);

// ── bersih-bersih ──────────────────────────────────────────────────────────
foreach ($nonce_dipakai as $n) {
    ex('DELETE FROM sso_nonce WHERE nonce = ?', [$n]);
}
ex('DELETE FROM pengguna WHERE email = ?', [$email_mati]);
sesi_mulai();
$_SESSION = [];
session_destroy();

$sisa_nonce = 0;
foreach ($nonce_dipakai as $n) {
    $sisa_nonce += (int) qv('SELECT COUNT(*) FROM sso_nonce WHERE nonce = ?', [$n]);
}
$sisa_akun = (int) qv('SELECT COUNT(*) FROM pengguna WHERE email IN (?, ?)', [$email_mati, $email_baru]);
cek('bersih: nonce uji terhapus', $sisa_nonce === 0, "sisa $sisa_nonce");
cek('bersih: akun uji terhapus', $sisa_akun === 0, "sisa $sisa_akun");

while (ob_get_level() > 0) { ob_end_flush(); }
echo $gagal === 0 ? "\nSEMUA UJI LULUS\n" : "\n$gagal UJI GAGAL\n";
exit($gagal === 0 ? 0 : 1);
