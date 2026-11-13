<?php
declare(strict_types=1);

/**
 * Modul: masuk / keluar / SSO.
 *
 * Ini modul contoh. Pola di berkas ini dipakai modul lain:
 *   1. ambil input lewat masuk_input()
 *   2. gerbang izin dengan wajib() bila aksi mengubah data
 *   3. csrf_periksa() untuk setiap POST
 *   4. semua keluaran lewat e()
 *   5. alihkan() setelah menulis, jangan render ulang
 */

$aksi = $_GET['a'] ?? 'index';

/* ── keluar ─────────────────────────────────────────────────────── */
if ($aksi === 'keluar') {
    keluar();
    alihkan(BASE_URL . 'index.php?m=masuk');
}

/* ── masuk lewat SPMI (SSO) ─────────────────────────────────────── */
// Token datang dari SPMI lewat tautan, jadi tidak ada POST dan tidak ada CSRF:
// yang membuktikan keasliannya tanda tangan HMAC, bukan token form.
if ($aksi === 'sso') {
    require_once __DIR__ . '/../../system/sso.php';

    $hasil = sso_terima((string) ($_GET['t'] ?? ''));
    if ($hasil['ok']) {
        alihkan(BASE_URL . 'index.php');
    }
    // Galat ditampilkan di form masuk, bukan halaman putih: pengguna masih
    // punya jalan masuk lain (sandi lokal) dari halaman yang sama.
    $galat = $hasil['pesan'];
}

/* ── masuk lokal ────────────────────────────────────────────────── */
if ($aksi === 'proses') {
    csrf_periksa();
    $email = strtolower(masuk_input('email'));
    $sandi = (string) ($_POST['sandi'] ?? '');

    $u = q1('SELECT * FROM pengguna WHERE email = ?', [$email]);
    // Pesan galat sengaja tidak membedakan "email tidak ada" dan "sandi salah":
    // membedakannya memberi tahu penyerang email mana yang terdaftar.
    $salah = 'Email atau sandi salah.';

    if (!$u) {
        $galat = $salah;
    } elseif (!$u['aktif']) {
        $galat = 'Akun ini dinonaktifkan. Hubungi Admin UPPS.';
    } elseif (!$u['sandi'] || !password_verify($sandi, $u['sandi'])) {
        $galat = $salah;
    } elseif (peran_ganda((string) $u['peran_kode'])) {
        $galat = 'Akun ini punya peran ganda. Hubungi Admin UPPS untuk memisahkannya.';
    } else {
        masuk_sebagai((int) $u['id'], $u['prodi_id'] !== null ? (int) $u['prodi_id'] : null);
        alihkan(BASE_URL . 'index.php');
    }
}

/* ── tampilkan form ─────────────────────────────────────────────── */
$galat = $galat ?? '';
?><!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Masuk · Sistem Akreditasi LAM-PTKes</title>
<link rel="stylesheet" href="<?= e(BASE_URL) ?>aset/css/app.css?v=1">
<style>
.masuk { display: grid; grid-template-columns: minmax(0,1.05fr) minmax(0,.95fr);
  min-height: 100vh; align-items: center; gap: clamp(24px,5vw,72px);
  padding: clamp(20px,5vw,64px) clamp(18px,6vw,88px); background: var(--bg); }
.masuk-kiri h1 { font-size: clamp(24px,2.4vw+12px,40px); font-weight: 700;
  letter-spacing: -.025em; margin: 16px 0 10px; max-width: 20ch; }
.masuk-kiri p { color: var(--ink2); max-width: 46ch; }
.masuk-kiri .brand-mark { width: 44px; height: 44px; border-radius: 12px; }
.masuk-kiri .brand-mark::after { inset: 10px; border-radius: 6px; }
.masuk-kanan { background: var(--surf); border: 1px solid var(--bd); border-radius: var(--r);
  padding: clamp(20px,2.4vw,32px); display: grid; gap: 14px; max-width: 420px;
  justify-self: end; width: 100%; box-shadow: 0 1px 2px rgba(16,24,40,.06); }
.masuk-kanan h2 { font-size: 22px; font-weight: 700; }
.masuk-kanan label { display: grid; gap: 6px; font-size: var(--fs-xs); font-weight: 600; color: var(--ink2); }
.masuk-kanan input { font: inherit; font-size: var(--fs-sm); padding: 10px 12px;
  border: 1px solid var(--bd); border-radius: 6px; }
.masuk-kanan input:focus { outline: 2px solid var(--ind-t); border-color: var(--ind); }
.galat { color: var(--bad); font-size: var(--fs-xs); min-height: 16px; }
@media (max-width: 900px) { .masuk { grid-template-columns: 1fr; min-height: 0; } .masuk-kanan { justify-self: stretch; max-width: none; } }
</style>
</head>
<body>
<div class="masuk">
  <div class="masuk-kiri">
    <div class="brand-mark"></div>
    <h1>Sistem Akreditasi LAM-PTKes</h1>
    <p>Data dukung 38 tabel, 78 dokumen pendukung, satu tempat. Setiap perubahan
       tercatat atas nama akun yang mengerjakannya.</p>
  </div>
  <form class="masuk-kanan" method="post" action="<?= e(BASE_URL) ?>index.php?m=masuk&amp;a=proses">
    <h2>Masuk</h2>
    <?= csrf_input() ?>
    <label>Email
      <input type="email" name="email" required autocomplete="username"
             value="<?= e(masuk_input('email')) ?>" placeholder="nama@bbg.ac.id">
    </label>
    <label>Sandi
      <input type="password" name="sandi" required autocomplete="current-password">
    </label>
    <div class="galat"><?= e($galat) ?></div>
    <button class="btn btn-primary" type="submit">Masuk</button>
    <p class="foot-note">Masuk juga bisa lewat menu <b>LAM-PTKes</b> di SPMI
      (tanpa mengisi form ini).</p>
  </form>
</div>
</body>
</html>
