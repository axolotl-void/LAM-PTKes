<?php
declare(strict_types=1);

/**
 * Modul: Ekspor PDF & XLSX.
 *
 *   a=index  halaman ekspor (tombol + alasan blokir)
 *   a=xlsx   unduh .xlsx, satu sheet per tabel berisi + sheet Ringkasan
 *   a=pdf    halaman cetak (window.print(), tanpa library PDF)
 *
 * Gerbang: wajib('EKSPOR') di KETIGA aksi. Ekspor adalah satu-satunya jalan
 * data keluar sistem, jadi tidak boleh bergantung pada tombol yang
 * disembunyikan di UI.
 *
 * Blokir: aturan konsistensi tingkat 'blokir' yang gagal, atau akun berperan
 * ganda. Alasan selalu disebut satu per satu.
 */

require_once __DIR__ . '/../../system/layout.php';
require_once __DIR__ . '/../../system/xlsx.php';
require_once __DIR__ . '/../../tampilan/kerangka/komponen.php';
require_once __DIR__ . '/fungsi.php';

$aksi  = $_GET['a'] ?? 'index';
$PRODI ??= prodi_aktif();
$per   = $PRODI ? periode_aktif((int) $PRODI) : null;
$perId = $per ? (int) $per['id'] : null;

$alasan = ekspor_blokir($PRODI !== null ? (int) $PRODI : null);

/** Halaman penolakan: sebut sebabnya, jangan cuma "tidak bisa ekspor". */
$tolak_ekspor = static function (array $alasan): never {
    http_response_code(409);
    $isi = '<link rel="stylesheet" href="' . e(BASE_URL) . 'aset/css/ekspor.css?v=1">'
         . callout('bad', 'Ekspor diblokir',
             'Data belum boleh keluar sistem. Perbaiki hal berikut, lalu ulangi ekspor:');
    $isi .= '<ul class="eks-blokir">';
    foreach ($alasan as $a) {
        $isi .= '<li><b>' . e($a['kode']) . ' · ' . e($a['nama']) . '</b><span>'
              . e($a['pesan']) . '</span></li>';
    }
    $isi .= '</ul>';
    $isi .= '<p class="foot-note">Rinciannya ada di menu <b>Konsistensi</b>.</p>';
    render('Ekspor diblokir', $isi, ['aktif' => '', 'sub' => 'Tidak ada berkas yang dibuat']);
    exit;
};

/* ── XLSX ───────────────────────────────────────────────────────── */
if ($aksi === 'xlsx') {
    wajib('EKSPOR');
    if ($alasan !== []) {
        $tolak_ekspor($alasan);
    }

    $sheet = ekspor_sheets((int) $PRODI, $perId);
    $nama  = sprintf('data-dukung-%s-TS%d.xlsx',
        preg_replace('/[^a-z0-9]+/', '-', strtolower((string) ($U['prodi_nama'] ?? 'prodi'))),
        tahun_ts());
    $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
    if ($tmp === false || !xlsx_buat($sheet, $tmp)) {
        tolak(500, 'Berkas XLSX gagal dibuat di server.');
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $nama . '"');
    header('Content-Length: ' . (string) filesize($tmp));
    header('Cache-Control: no-store');
    readfile($tmp);
    @unlink($tmp);
    exit;
}

/* ── PDF (halaman cetak) ────────────────────────────────────────── */
if ($aksi === 'pdf') {
    wajib('EKSPOR');

    $judul = sprintf('Data Dukung %s — TS %d', (string) ($U['prodi_nama'] ?? 'Prodi'), tahun_ts());

    if ($alasan !== []) {
        // Halaman HTML tetap dirender supaya pengguna melihat alasannya di
        // jendela cetak, bukan halaman JSON.
        ?><!DOCTYPE html>
<html lang="id"><head><meta charset="utf-8">
<title><?= e($judul) ?> · Ekspor diblokir</title>
<link rel="stylesheet" href="<?= e(BASE_URL) ?>aset/css/app.css?v=1">
<link rel="stylesheet" href="<?= e(BASE_URL) ?>aset/css/ekspor.css?v=1">
</head><body class="eks-cetak">
<main class="eks-lembar">
  <h1><?= e($judul) ?></h1>
  <div class="callout bad"><h3>Ekspor diblokir</h3>
    Data belum boleh dicetak. Perbaiki hal berikut lebih dulu:
  </div>
  <ul class="eks-blokir">
    <?php foreach ($alasan as $a): ?>
      <li><b><?= e($a['kode']) ?> · <?= e($a['nama']) ?></b><span><?= e($a['pesan']) ?></span></li>
    <?php endforeach; ?>
  </ul>
</main></body></html><?php
        exit;
    }

    $data = ekspor_data((int) $PRODI, $perId);
    ?><!DOCTYPE html>
<html lang="id"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($judul) ?></title>
<link rel="stylesheet" href="<?= e(BASE_URL) ?>aset/css/ekspor.css?v=1">
</head><body class="eks-cetak">

<div class="eks-alat">
  <button class="btn btn-primary" type="button" onclick="window.print()">Cetak / Simpan PDF</button>
  <a class="btn btn-ghost" href="<?= e(BASE_URL) ?>index.php?m=ekspor&amp;a=index">Kembali</a>
  <span class="eks-hint">Pilih tujuan <b>Save as PDF</b> di dialog cetak. Ukuran kertas A4.</span>
</div>

<main class="eks-lembar">
  <header class="eks-kop">
    <div>
      <div class="eks-kop-nama">Sistem Akreditasi LAM-PTKes</div>
      <div class="eks-kop-sub">Universitas Bina Bangsa Getsempena</div>
    </div>
    <div class="eks-kop-meta">
      <div><b><?= e((string) ($U['prodi_nama'] ?? '')) ?></b></div>
      <div>Jenjang <?= e((string) ($data['kepala']['jenjang'] ?? '—')) ?></div>
      <div>TS <?= e((string) tahun_ts()) ?> · <?= e(date('j M Y')) ?></div>
    </div>
  </header>

  <h1 class="eks-judul"><?= e($judul) ?></h1>
  <p class="eks-catatan">
    Dicetak dari sistem pada <?= e(date('d-m-Y H:i')) ?>.
    Nilai kosong berarti field belum diisi, bukan nol.
  </p>

  <?php foreach ($data['sheet'] as $s): ?>
    <section class="eks-tabel">
      <h2>T<?= e((string) $s['nomor']) ?> · <?= e($s['judul']) ?></h2>
      <table>
        <thead>
          <tr><?php foreach ($s['header'] as $h): ?><th><?= e($h) ?></th><?php endforeach; ?></tr>
        </thead>
        <tbody>
          <?php foreach ($s['baris'] as $r): ?>
            <tr><?php foreach ($r as $v): ?><td><?= e((string) $v) ?></td><?php endforeach; ?></tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php if ($s['kode'] === '05' || $s['kode'] === '06'): ?>
        <p class="eks-penanda">Penanda <?= e(EKSPOR_PENANDA) ?> wajib tercetak pada tabel ini.</p>
      <?php endif; ?>
    </section>
  <?php endforeach; ?>

  <?php if ($data['sheet'] === []): ?>
    <p class="eks-catatan">Belum ada tabel yang berisi untuk prodi ini.</p>
  <?php endif; ?>
</main>
</body></html><?php
    exit;
}

/* ── halaman index ──────────────────────────────────────────────── */
wajib('EKSPOR');

$isi = '<link rel="stylesheet" href="' . e(BASE_URL) . 'aset/css/ekspor.css?v=1">';

if (!$PRODI || !$per) {
    $isi .= callout('warn', 'Periode belum ada',
        'Isi periode akreditasi prodi ini lebih dulu. Tanpa periode aktif tidak ada '
        . 'data yang bisa diekspor.');
    render('Ekspor', $isi, ['aktif' => 'ekspor', 'sub' => 'Tidak ada periode aktif']);
    return;
}

$siap = $alasan === [];

if ($alasan !== []) {
    $isi .= callout('bad', 'Ekspor diblokir',
        sprintf('%d alasan harus diselesaikan sebelum data boleh keluar sistem.', count($alasan)));
    $isi .= '<ul class="eks-blokir">';
    foreach ($alasan as $a) {
        $isi .= '<li><b>' . e($a['kode']) . ' · ' . e($a['nama']) . '</b><span>'
              . e($a['pesan']) . '</span></li>';
    }
    $isi .= '</ul>';
} else {
    $sheet = ekspor_sheets((int) $PRODI, $perId);
    $isi .= callout('ok', 'Siap diekspor',
        sprintf('%d sheet: Ringkasan + %d tabel yang berisi. Kedua tombol membuat berkas dari '
              . 'data saat ini, bukan salinan lama.', count($sheet), count($sheet) - 1));
}

$tombol = '';
if ($siap) {
    $tombol = '<a class="btn btn-primary" href="' . e(BASE_URL) . 'index.php?m=ekspor&amp;a=pdf">PDF</a> '
            . '<a class="btn btn-ghost" href="' . e(BASE_URL) . 'index.php?m=ekspor&amp;a=xlsx">XLSX</a>';
}

$isi .= kartu('Berkas yang bisa dibuat', '
  <div class="eks-pilih">
    <div class="eks-opsi">
      <h3>PDF — halaman cetak</h3>
      <p>Satu halaman per tabel, A4, judul kolom diulang di setiap halaman. '
      . 'Tombol <b>Cetak / Simpan PDF</b> memakai dialog cetak peramban; tidak ada '
      . 'pustaka PDF di server.</p>
      ' . ($siap
          ? '<a class="btn btn-primary" href="' . e(BASE_URL) . 'index.php?m=ekspor&amp;a=pdf">Buka halaman cetak</a>'
          : '<button class="btn" disabled>Buka halaman cetak</button>') . '
    </div>
    <div class="eks-opsi">
      <h3>XLSX — satu sheet per tabel</h3>
      <p>Sheet <b>Ringkasan</b> lebih dulu, lalu satu sheet per tabel yang berisi. '
      . 'Angka ditulis sebagai angka, teks penanda (a)–(g) Tabel 5 dan 6 tetap ikut.</p>
      ' . ($siap
          ? '<a class="btn btn-ghost" href="' . e(BASE_URL) . 'index.php?m=ekspor&amp;a=xlsx">Unduh .xlsx</a>'
          : '<button class="btn" disabled>Unduh .xlsx</button>') . '
    </div>
  </div>
', ['catatan' => 'Berkas dibuat saat tombol ditekan']);

$isi .= callout('info', 'Kenapa ekspor bisa diblokir',
    'Ekspor ditolak bila ada aturan konsistensi bertingkat <b>blokir</b> yang gagal, '
    . 'atau ada akun aktif yang perannya memuat dua peran sekaligus. Alasannya selalu '
    . 'disebut di halaman ini, bukan hanya "tidak bisa".');

render('Ekspor PDF & XLSX', $isi, [
    'aktif' => 'ekspor',
    'sub'   => sprintf('%s · TS %d · %s', (string) ($U['prodi_nama'] ?? ''), tahun_ts(),
        $alasan === [] ? 'siap ekspor' : count($alasan) . ' alasan blokir'),
    'aksi'  => $tombol,
]);
