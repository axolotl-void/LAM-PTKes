<?php
declare(strict_types=1);

/**
 * Modul: pengguna & hak akses.
 *
 * yang boleh masuk hanya peran dengan KELOLA_PENGGUNA = 1 (ADMIN). Gerbang
 * dipasang di awal, sebelum satu baris data pun dibaca.
 *
 * Tombol di sini "Nonaktifkan", tidak pernah "Hapus": baris pengguna dirujuk
 * `audit.pengguna_id` dan riwayat pengisian, jadi menghapus akun akan
 * memutus rujukan nama pelaku di masa lalu.
 */

require_once __DIR__ . '/../../system/layout.php';
require_once __DIR__ . '/logika.php';

$aksi = $_GET['a'] ?? 'index';

/* ── simpan perubahan ───────────────────────────────────────────── */
if ($aksi === 'simpan') {
    wajib('KELOLA_PENGGUNA');
    csrf_periksa();

    $id = (int) ($_POST['id'] ?? 0);
    $hasil = pengguna_ubah($id, [
        'peran_kode' => masuk_input('peran_kode'),
        'prodi_id'   => masuk_input('prodi_id'),
        'aktif'      => (int) masuk_input('aktif', '1'),
    ], wajib_masuk());

    // Pesan dibawa lewat sesi supaya POST selalu diakhiri redirect — kalau
    // halaman dirender ulang setelah POST, refresh browser mengirim POST lagi.
    sesi_mulai();
    if ($hasil['ok']) {
        $_SESSION['pgn_pesan'] = $hasil['pesan'];
    } else {
        $_SESSION['pgn_galat'] = $hasil['pesan'];
    }
    alihkan(BASE_URL . 'index.php?m=pengguna');
}

/* ── daftar ─────────────────────────────────────────────────────── */
wajib('KELOLA_PENGGUNA');

sesi_mulai();
$pesan = (string) ($_SESSION['pgn_pesan'] ?? '');
$galat = (string) ($_SESSION['pgn_galat'] ?? '');
unset($_SESSION['pgn_pesan'], $_SESSION['pgn_galat']);

$sPeran  = masuk_input('peran');
$sStatus = masuk_input('status');
$daftar  = pengguna_daftar($sPeran, $sStatus);
$prodi   = prodi_pilihan();
$aku     = (int) (pengguna()['id'] ?? 0);

$opsiPeran = '<option value="">Semua peran</option>';
foreach (peran_peta() as $kode => $nama) {
    $opsiPeran .= sprintf('<option value="%s"%s>%s</option>',
        e($kode), $sPeran === $kode ? ' selected' : '', e($nama));
}
$opsiStatus = '<option value="">Semua status</option>'
    . '<option value="1"' . ($sStatus === '1' ? ' selected' : '') . '>Aktif</option>'
    . '<option value="0"' . ($sStatus === '0' ? ' selected' : '') . '>Nonaktif</option>';

$opsiProdi = '<option value="">— semua prodi —</option>';
foreach ($prodi as $p) {
    $opsiProdi .= sprintf('<option value="%d">%s</option>', (int) $p['id'],
        e($p['nama_ps'] . ' (' . $p['jenjang'] . ')'));
}

/* ── baris tabel ────────────────────────────────────────────────── */
$barisHtml = '';
$formHtml = '';
foreach ($daftar as $a) {
    $id = (int) $a['id'];
    $sendiri = $id === $aku;
    $idForm = 'f' . $id;

    $selPeran = '';
    foreach (peran_peta() as $kode => $nama) {
        $selPeran .= sprintf('<option value="%s"%s>%s</option>',
            e($kode), $a['peran_kode'] === $kode ? ' selected' : '', e($nama));
    }
    $selProdi = '';
    foreach ($prodi as $p) {
        $selProdi .= sprintf('<option value="%d"%s>%s</option>', (int) $p['id'],
            (int) $a['prodi_id'] === (int) $p['id'] ? ' selected' : '',
            e($p['nama_ps']));
    }

    $badge = (int) $a['aktif'] === 1
        ? '<span class="badge badge-lengkap">Aktif</span>'
        : '<span class="badge badge-na">Nonaktif</span>';

    $barisHtml .= '<tr' . ($sendiri ? ' class="is-aku"' : '') . '>'
        . '<td><div class="strong">' . e($a['nama']) . '</div>'
        . '<div class="muted kecil">' . e($a['email']) . '</div></td>'
        . '<td>' . $badge . '</td>'
        // Peran tunggal: satu kolom di skema, jadi satu pilihan. Peran ganda mustahil.
        . '<td><select name="peran_kode" form="' . $idForm . '" aria-label="Peran ' . e($a['nama']) . '">'
        . $selPeran . '</select></td>'
        . '<td><select name="prodi_id" form="' . $idForm . '" aria-label="Prodi ' . e($a['nama']) . '">'
        . '<option value="">semua prodi</option>' . $selProdi . '</select></td>'
        . '<td><select name="aktif" form="' . $idForm . '" aria-label="Status ' . e($a['nama']) . '">'
        . '<option value="1"' . ((int) $a['aktif'] === 1 ? ' selected' : '') . '>Aktif</option>'
        . '<option value="0"' . ((int) $a['aktif'] === 0 ? ' selected' : '') . '>Nonaktifkan</option>'
        . '</select></td>'
        . '<td class="kecil muted">' . e($a['masuk_terakhir'] ? tgl(substr((string) $a['masuk_terakhir'], 0, 10)) : 'belum pernah') . '</td>'
        . '<td><button class="btn btn-primary kecil-btn" type="submit" form="' . $idForm . '"'
        . ' data-konfirmasi="Simpan perubahan akun ' . e($a['nama']) . '?">Simpan</button></td>'
        . '</tr>';

    // form dipisah dari tabel (form tidak sah sebagai anak <tr>), input
    // disambungkan lewat atribut form="..." — tanpa JS.
    $formHtml .= '<form id="' . $idForm . '" method="post" action="'
        . e(BASE_URL) . 'index.php?m=pengguna&amp;a=simpan" hidden>'
        . csrf_input()
        . '<input type="hidden" name="id" value="' . $id . '"></form>';
}

$tabelAkun = $daftar
    ? '<div class="tbl-wrap"><table class="tbl">'
      . '<thead><tr><th>Nama &amp; email</th><th>Status</th><th>Peran</th><th>Prodi</th>'
      . '<th>Aktif / nonaktif</th><th>Masuk terakhir</th><th></th></tr></thead>'
      . '<tbody>' . $barisHtml . '</tbody></table></div>'
      . $formHtml
    : '<p class="muted">Tidak ada akun yang cocok dengan saringan ini. '
      . '<a href="' . e(BASE_URL) . 'index.php?m=pengguna">Hapus saringan</a>.</p>';

/* ── matriks izin ───────────────────────────────────────────────── */
$legenda = '';
foreach (ARTI_AKSES as $v => [$arti, $ket]) {
    $legenda .= '<li><b class="iz-legend iz-' . $v . '">' . $v . '</b> ' . e($arti)
        . ' — ' . e($ket) . '</li>';
}
$matriks = pengguna_matriks_html();

/* ── riwayat ────────────────────────────────────────────────────── */
$riwayat = audit_akun(10);
$riwayatHtml = '';
foreach ($riwayat as $r) {
    $riwayatHtml .= '<tr>'
        . '<td class="kecil muted">' . e(substr((string) $r['waktu'], 0, 16)) . '</td>'
        . '<td class="kecil">' . e($r['pelaku'] ?? '—') . '</td>'
        . '<td class="kecil">' . e($r['sasaran'] ?? ('#' . $r['entitas_id'])) . '</td>'
        . '<td class="kecil"><code>' . e((string) $r['field']) . '</code></td>'
        . '<td class="kecil">' . e($r['nilai_lama'] ?? '—') . '</td>'
        . '<td class="kecil">' . e($r['nilai_baru'] ?? '—') . '</td>'
        . '</tr>';
}
$tabelRiwayat = $riwayat
    ? '<div class="tbl-wrap"><table class="tbl"><thead><tr><th>Waktu</th><th>Diubah oleh</th>'
      . '<th>Akun</th><th>Field</th><th>Nilai lama</th><th>Nilai baru</th></tr></thead>'
      . '<tbody>' . $riwayatHtml . '</tbody></table></div>'
    : '<p class="muted">Belum ada perubahan akun yang tercatat.</p>';

$isi = '<link rel="stylesheet" href="' . e(BASE_URL) . 'aset/css/pengguna.css?v=1">'
    . ($pesan !== '' ? '<div class="callout info" role="status"><h3>Tersimpan</h3>' . e($pesan) . '</div>' : '')
    . ($galat !== '' ? '<div class="callout bad" role="alert"><h3>Ditolak</h3>' . e($galat) . '</div>' : '')
    . '<section class="card">'
    . '<div class="card-head"><h2>Akun (' . count($daftar) . ')</h2>'
    . '<span class="note">Perubahan dicatat di riwayat di bawah, atas nama akun Anda.</span></div>'
    . '<form class="saring" method="get" action="' . e(BASE_URL) . 'index.php">'
    . '<input type="hidden" name="m" value="pengguna">'
    . '<label>Peran <select name="peran">' . $opsiPeran . '</select></label>'
    . '<label>Status <select name="status">' . $opsiStatus . '</select></label>'
    . '<button class="btn" type="submit">Saring</button>'
    . '</form>'
    . '<p class="foot-note">Tombolnya <b>Nonaktifkan</b>, bukan Hapus. Akun nonaktif tidak bisa '
    . 'masuk, tetapi namanya tetap ada supaya riwayat pengisian dan jejak audit tidak kehilangan '
    . 'rujukan. Akun Anda sendiri tidak bisa dinonaktifkan.</p>'
    . $tabelAkun
    . '</section>'

    . '<section class="card">'
    . '<div class="card-head"><h2>Matriks izin</h2>'
    . '<span class="note">' . count(izin_matriks()) . ' sel · dibaca dari tabel <code>izin</code></span></div>'
    . '<div class="callout info"><h3>Matriks ini mengikat server, bukan hiasan</h3>'
    . 'Setiap tombol dan setiap aksi tulis di sistem ini diperiksa fungsi yang membaca tabel '
    . '<code>izin</code> yang sama dengan matriks di bawah — <code>akses()</code> dan '
    . '<code>wajib()</code>. Menyembunyikan tombol tidak cukup: permintaan langsung ke server '
    . 'tetap ditolak 403 kalau nilai selnya 0 atau 2. Matriks ini tidak bisa diubah dari halaman '
    . 'ini; mengubahnya berarti mengubah baris tabel <code>izin</code>.</div>'
    . '<ul class="legenda">' . $legenda . '</ul>'
    . $matriks
    . '</section>'

    . '<section class="card">'
    . '<div class="card-head"><h2>Riwayat perubahan akun</h2>'
    . '<span class="note">10 terakhir · tabel <code>audit</code></span></div>'
    . $tabelRiwayat
    . '</section>';

render('Pengguna & Hak Akses', $isi, [
    'aktif' => 'pengguna',
    'sub'   => 'Akun, peran tunggal per akun, dan matriks izin 4 peran × 6 aksi',
]);
