<?php
declare(strict_types=1);

/**
 * SSO dari SPMI (spmi.bbg.ac.id) ke sistem akreditasi.
 *
 * SPMI membuat token bertanda tangan lalu mengalihkan browser ke
 * index.php?m=masuk&a=sso&t=<isi>.<tanda_tangan>. Berkas ini yang memeriksanya.
 *
 * KENAPA SERIKAT PEMERIKSAANNYA SEPANJANG INI:
 * Tautan SSO dulu hanya membawa email + tanda tangan. Itu cukup untuk
 * membuktikan "permintaan ini dari SPMI", tetapi tidak cukup untuk Mengizinkan
 * masuk, karena:
 *   - `x` (kedaluwarsa 90 detik) dibuat SPMI tetapi penerimanya tidak
 *     memeriksanya. Tautan lama yang bocor dari riwayat browser / log proxy
 *     bisa dipakai ulang kapan saja.
 *   - tanpa cek nonce, satu tautan = tiket masuk tanpa batas waktu.
 *   - membuat akun otomatis dari SSO berarti SIAPA PUN yang punya akun SPMI
 *     (seluruh dosen & tendik) otomatis jadi pengguna sistem akreditasi.
 *
 * PERAN TIDAK DIAMBIL DARI TOKEN. Token SPMI membawa `r` (LPM/Auditee), tetapi
 * SPMI hanya punya 2 portal sedangkan sistem ini punya 4 peran
 * (OPERATOR/VERIFIKATOR/ADMIN/PIMPINAN). Memetakan 2 -> 4 akan salah untuk
 * sebagian orang. Peran selalu dibaca dari tabel `pengguna`.
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

/** base64url -> string mentah. Null kalau bukan base64 sah. */
function sso_b64url_decode(string $s): ?string
{
    $s = strtr($s, '-_', '+/');
    $sisa = strlen($s) % 4;
    if ($sisa !== 0) {
        $s .= str_repeat('=', 4 - $sisa);
    }
    $v = base64_decode($s, true);
    return $v === false ? null : $v;
}

/**
 * Periksa token SSO dan, kalau sah, buat sesi.
 *
 * Tidak pernah `exit`/redirect: pemanggil (modul masuk) yang memutuskan
 * mau menampilkan galat atau mengalihkan. Itu juga yang membuat fungsi ini
 * bisa diuji tanpa server HTTP.
 *
 * @return array{ok: bool, pesan: string, pengguna: ?array}
 */
function sso_terima(string $token): array
{
    $tolak = static fn (string $pesan): array
        => ['ok' => false, 'pesan' => $pesan, 'pengguna' => null];

    $pesan_ulang = 'Buka ulang menu LAM-PTKes di SPMI.';

    if (strlen(SSO_RAHASIA) < 32) {
        // Konfigurasi salah = semua token akan gagal. Katakan apa yang salah,
        // jangan biarkan pengguna melihat "tanda tangan tidak sah" selamanya.
        return $tolak('Rahasia SSO belum diatur (SSO_RAHASIA minimal 32 karakter). Hubungi pengelola sistem.');
    }

    // 1. Bentuk token. Batas panjang mencegah token raksasa masuk ke hash.
    if ($token === '' || strlen($token) > 8192 || substr_count($token, '.') !== 1) {
        return $tolak('Tautan SSO tidak lengkap. ' . $pesan_ulang);
    }
    [$isi64, $ttd64] = explode('.', $token, 2);
    $ttd = sso_b64url_decode($ttd64);
    if ($ttd === null || $ttd === '') {
        return $tolak('Tautan SSO tidak sah. ' . $pesan_ulang);
    }

    // 2. Tanda tangan DIPERIKSA SEBELUM isi di-parse. Isi yang belum terbukti
    //    datang dari SPMI tidak boleh dipercaya, bahkan untuk dibaca.
    //    hash_equals, bukan ===: perbandingan biasa berhenti di byte pertama
    //    yang beda, dan selisih waktunya bisa dipakai menebak tanda tangan.
    $harusnya = hash_hmac('sha256', $isi64, SSO_RAHASIA, true);
    if (!hash_equals($harusnya, $ttd)) {
        return $tolak('Tanda tangan tautan SSO tidak sah. ' . $pesan_ulang);
    }

    $isi = sso_b64url_decode($isi64);
    $data = $isi === null ? null : json_decode($isi, true);
    if (!is_array($data)) {
        return $tolak('Tautan SSO tidak sah. ' . $pesan_ulang);
    }

    $email = strtolower(trim((string) ($data['e'] ?? '')));
    $nonce = strtolower(trim((string) ($data['j'] ?? '')));
    $kedaluwarsa = (int) ($data['x'] ?? 0);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)
        || preg_match('/^[a-f0-9]{32}$/', $nonce) !== 1
        || $kedaluwarsa <= 0) {
        return $tolak('Tautan SSO tidak sah. ' . $pesan_ulang);
    }

    // 3. Berlaku 90 detik, dihitung di server penerima — bukan di penerbit.
    //    Jam kedua server diasumsikan NTP; selisih beberapa detik masih muat
    //    dalam 90 detik.
    if ($kedaluwarsa < time()) {
        return $tolak('Tautan SSO kedaluwarsa (berlaku 90 detik). ' . $pesan_ulang);
    }

    // 4. Akun harus SUDAH TERDAFTAR. Tidak ada pembuatan akun otomatis:
    //    siapa pun yang punya akun SPMI akan masuk sistem akreditasi.
    $u = q1('SELECT id, email, nama, peran_kode, prodi_id, aktif
             FROM pengguna WHERE email = ?', [$email]);
    if (!$u) {
        return $tolak('Akun belum terdaftar di sistem akreditasi. Hubungi Admin UPPS.');
    }
    if (!(int) $u['aktif']) {
        return $tolak('Akun ini dinonaktifkan. Hubungi Admin UPPS.');
    }
    if (peran_ganda((string) $u['peran_kode'])) {
        return $tolak('Akun ini punya peran ganda. Hubungi Admin UPPS untuk memisahkannya.');
    }

    // 5. Sekali pakai. INSERT IGNORE, bukan SELECT lalu INSERT: dua permintaan
    //    bersamaan dengan nonce sama tidak bisa dua-duanya lolos. Kunci primer
    //    `sso_nonce.nonce` yang memutuskan, bukan kode PHP.
    $tercatat = ex('INSERT IGNORE INTO sso_nonce (nonce, email) VALUES (?, ?)', [$nonce, $email]);
    if ($tercatat !== 1) {
        return $tolak('Tautan SSO ini sudah dipakai. ' . $pesan_ulang);
    }
    // Tabel nonce tidak boleh tumbuh selamanya. Token hidup 90 detik, jadi baris
    // lebih tua dari sehari tidak mungkin dipakai lagi.
    // ponytail: bersih-bersih oportunistik 1 dari 50 login; kalau trafik naik,
    // pindahkan ke cron harian.
    if (random_int(1, 50) === 1) {
        ex('DELETE FROM sso_nonce WHERE dipakai < NOW() - INTERVAL 1 DAY');
    }

    masuk_sebagai((int) $u['id'], $u['prodi_id'] !== null ? (int) $u['prodi_id'] : null);

    return ['ok' => true, 'pesan' => '', 'pengguna' => $u];
}
