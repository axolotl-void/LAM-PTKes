<?php
declare(strict_types=1);

/**
 * Layout: header, sidebar, isi, footer. Satu tempat untuk semua halaman.
 *
 * Aturan yang mengikat:
 *  - navigasi membaca `boleh()` / `hanya_lihat()` dari tabel izin. Menu yang
 *    tampil hanya yang boleh dipakai; server tetap menolak sendiri.
 *  - status selalu label teks + ikon, tidak pernah warna saja.
 *  - tanpa framework CSS. Token warna ada di aset/css/app.css.
 *
 * Pemakaian dari modul:
 *   render('Dashboard', $isiHtml, ['aktif' => 'dashboard']);
 */

require_once __DIR__ . '/view.php';
require_once __DIR__ . '/rekap.php';
require_once dirname(__DIR__) . '/spesifikasi/tabel.php';

function nav_items(): array
{
    $u = pengguna();
    $items = [
        ['kelompok' => 'MENU', 'baris' => [
            ['dashboard',   'Dashboard',            'F-04', null],
            ['ringkasan',   'Ringkasan 8 Kriteria',  'F-04', null],
        ]],
        ['kelompok' => 'BERSAMA UPPS', 'baris' => [
            ['identitas', 'Identitas UPPS & Prodi', 'F-01', null],
            ['periode',   'Periode Akreditasi',     'F-02', null],
            ['dosen',     'Dosen & Tendik',         'F-06', null],
            ['keuangan',  'Keuangan',               'F-17', null],
            ['kerjasama', 'Kerja Sama',             'F-19', null],
        ]],
        ['kelompok' => 'PRODI', 'baris' => [
            ['mahasiswa', 'Mahasiswa',        'F-05', null],
            ['kurikulum', 'Kurikulum & MK',   'F-07', null],
            ['sarana',    'Sarana & Wahana',  'F-15', null],
            ['pustaka',   'Pustaka & Jurnal', 'F-16', null],
            ['uji',       'Uji Kompetensi',   'F-09', null],
            ['survei',    'Survei & Tracer',  'F-10', null],
        ]],
        ['kelompok' => null, 'baris' => [
            ['tabel',       'Data Dukung',        null, 'tabel'],
            ['dokumen',     'Dokumen Pendukung',  null, 'dokumen'],
            ['konsistensi', 'Konsistensi',        null, 'konsistensi'],
        ]],
    ];
    // Menu Pengguna hanya untuk yang boleh mengelola akun.
    if (boleh('KELOLA_PENGGUNA')) {
        $items[1]['baris'][] = ['pengguna', 'Pengguna & Hak Akses', 'F-22', null];
    }
    return $items;
}

function render(string $judul, string $isi, array $opsi = []): void
{
    $aktif = $opsi['aktif'] ?? '';
    $U = pengguna();
    $sub = $opsi['sub'] ?? '';
    $aksi = $opsi['aksi'] ?? '';
    $nav = nav_items();
    ?><!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light">
<title><?= e($judul) ?> · Sistem Akreditasi LAM-PTKes</title>
<link rel="stylesheet" href="<?= e(BASE_URL) ?>aset/css/app.css?v=1">
</head>
<body>
<header class="topbar">
  <button class="sb-toggle" id="sbToggle" aria-label="Buka menu" aria-expanded="false">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M3 6h18M3 12h18M3 18h18"/></svg>
  </button>
  <div class="brand">
    <div class="brand-mark"></div>
    <div class="brand-text">
      <div class="brand-name">Sistem Akreditasi LAM-PTKes</div>
      <div class="brand-sub">Universitas Bina Bangsa Getsempena</div>
    </div>
  </div>
  <div class="topbar-right">
    <span class="topbar-ctx">Fakultas Ilmu Kesehatan · UPPS FIK</span>
    <div class="who">
      <div class="who-txt">
        <div class="who-nama"><?= e($U['nama'] ?? '') ?></div>
        <div class="who-peran"><?= e($U['peran_kode'] ?? '') ?><?= $U['prodi_nama'] ? ' · ' . e($U['prodi_nama']) : '' ?></div>
      </div>
      <div class="avatar"><?= e(inisial($U['nama'] ?? '')) ?></div>
      <a class="who-keluar" href="<?= e(BASE_URL) ?>index.php?m=masuk&amp;a=keluar">Keluar</a>
    </div>
  </div>
</header>

<aside class="sidebar" id="sidebar">
  <button class="period"><b>TS <?= e(tahun_ts()) ?></b></button>
  <?php foreach ($nav as $grup): ?>
    <?php if ($grup['kelompok']): ?><div class="nav-label"><?= e($grup['kelompok']) ?></div><?php else: ?><div class="nav-sep"></div><?php endif; ?>
    <?php foreach ($grup['baris'] as [$kode, $nama, $fitur, $badge]): ?>
      <a class="nav-item<?= $aktif === $kode ? ' aktif' : '' ?>" href="<?= e(BASE_URL) ?>index.php?m=<?= e($kode) ?>">
        <span class="lbl"><?= e($nama) ?></span>
        <?php if ($badge === 'tabel'): ?><span class="badge"><?= e(rekap_tabel_ringkas()) ?></span>
        <?php elseif ($badge === 'dokumen'): ?><span class="badge"><?= e(rekap_dokumen_ringkas()) ?></span>
        <?php elseif ($badge === 'konsistensi'): ?><span class="badge badge-bad">?</span>
        <?php elseif ($fitur): ?><span class="code"><?= e($fitur) ?></span><?php endif; ?>
      </a>
    <?php endforeach; ?>
  <?php endforeach; ?>
  <div class="sb-foot">
    <div class="nav-label">EKSPOR PRODI INI</div>
    <div class="export-row">
      <?php if (boleh('EKSPOR')): ?>
        <a class="btn btn-primary" href="<?= e(BASE_URL) ?>index.php?m=ekspor&amp;a=pdf">PDF</a>
        <a class="btn btn-ghost" href="<?= e(BASE_URL) ?>index.php?m=ekspor&amp;a=xlsx">XLSX</a>
      <?php else: ?>
        <button class="btn" disabled>PDF</button><button class="btn" disabled>XLSX</button>
      <?php endif; ?>
    </div>
  </div>
</aside>

<main class="main">
  <div class="page-head">
    <div>
      <h1><?= e($judul) ?></h1>
      <?php if ($sub): ?><div class="page-sub"><?= e($sub) ?></div><?php endif; ?>
    </div>
    <?php if ($aksi): ?><div class="actions"><?= $aksi ?></div><?php endif; ?>
  </div>
  <?= $isi ?>
</main>

<script src="<?= e(BASE_URL) ?>aset/js/app.js?v=1" defer></script>
</body>
</html><?php
}

function inisial(string $nama): string
{
    $bersih = preg_replace('/,.*$/', '', $nama);
    $kata = preg_split('/\s+/', trim($bersih)) ?: [];
    $out = '';
    foreach ($kata as $k) {
        if ($k !== '') {
            $out .= mb_substr($k, 0, 1);
        }
        if (mb_strlen($out) >= 2) {
            break;
        }
    }
    return mb_strtoupper($out);
}

/** Tahun TS dari periode aktif prodi. Tanpa ini semua label tahun salah. */
function tahun_ts(): int
{
    static $ts = null;
    if ($ts !== null) {
        return $ts;
    }
    $p = prodi_aktif();
    $ts = (int) qv('SELECT tahun_ts FROM periode WHERE prodi_id = ? AND aktif = 1
                    ORDER BY tahun_ts DESC LIMIT 1',
        [$p], (int) date('Y'));
    return $ts;
}

/** Badge sidebar "3/38 selesai" — dihitung dari rekap.php, bukan diketik. */
function rekap_tabel_ringkas(): string
{
    $p = prodi_aktif();
    $per = $p ? periode_aktif($p) : null;
    if (!$p || !$per) {
        return '—';
    }
    $r = rekap_ringkas($p, (int) $per['id']);
    return sprintf('%d/%d', $r['selesai'], $r['wajib']);
}

function rekap_dokumen_ringkas(): string
{
    $p = prodi_aktif();
    $per = $p ? periode_aktif($p) : null;
    if (!$p || !$per) {
        return '—';
    }
    $r = rekap_dokumen($p, (int) $per['id']);
    return sprintf('%d/%d', $r['unggah'], $r['total']);
}
