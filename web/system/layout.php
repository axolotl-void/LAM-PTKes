<?php
declare(strict_types=1);

/**
 * Layout: header, sidebar, isi. MENGIKUTI TAMPILAN YANG SUDAH ADA.
 *
 * Aturan yang tidak boleh dilanggar: kelas CSS dan struktur HTML di berkas ini
 * harus sama dengan `mockup/assets/js/pagekit.js` + `views.js` + `index.html`.
 * Aset (app.css, anim.css, icons.js, anim.js) disalin apa adanya dari `mockup/`,
 * jadi kalau markup di sini berbeda, tampilannya ikut berbeda — dan itu sudah
 * pernah terjadi (13 Nov: CSS ditulis ulang dari nol, animasi hilang).
 *
 * Kalau perlu komponen baru: tambahkan di `pagekit.php` dengan kelas yang SUDAH
 * ADA di app.css. Jangan menambah CSS baru untuk hal yang sudah ada gaya.
 */

require_once __DIR__ . '/view.php';
require_once __DIR__ . '/rekap.php';
require_once dirname(__DIR__) . '/assets/ikon.php';
require_once dirname(__DIR__) . '/spesifikasi/tabel.php';

/**
 * Navigasi sidebar. Urutan, label, kode fitur, dan nama ikon SAMA dengan
 * `views.js` milik desain lama — 17 baris.
 */
function nav_items(): array
{
    $items = [
        ['kelompok' => 'MENU', 'baris' => [
            ['dashboard', 'Dashboard', null, 'dashboard'],
            ['ringkasan', 'Ringkasan 8 Kriteria', null, 'summary'],
        ]],
        ['kelompok' => 'BERSAMA UPPS', 'baris' => [
            ['identitas', 'Identitas UPPS & Prodi', 'F-01', 'identity'],
            ['periode', 'Periode Akreditasi', 'F-02', 'period'],
            ['dosen', 'Dosen & Tendik', 'F-06', 'lecturer'],
            ['keuangan', 'Keuangan', 'F-17', 'finance'],
            ['kerjasama', 'Kerja Sama', 'F-19', 'partner'],
        ]],
        ['kelompok' => 'PRODI', 'baris' => [
            ['mahasiswa', 'Mahasiswa', 'F-05', 'student'],
            ['kurikulum', 'Kurikulum & MK', 'F-07', 'curriculum'],
            ['sarana', 'Sarana & Wahana', 'F-15', 'facility'],
            ['pustaka', 'Pustaka & Jurnal', 'F-16', 'library'],
            ['uji', 'Uji Kompetensi', 'F-09', 'competency'],
            ['survei', 'Survei & Tracer', 'F-10', 'survey'],
        ]],
    ];

    // Menu Pengguna hanya untuk yang boleh mengelola akun (aturan server).
    if (boleh('KELOLA_PENGGUNA')) {
        $items[1]['baris'][] = ['pengguna', 'Pengguna & Hak Akses', 'F-22', 'access'];
    }

    $items[] = ['kelompok' => null, 'baris' => [
        ['datadukung', 'Data Dukung', null, 'archive', rekap_tabel_badge()],
        ['dokumen', 'Dokumen Pendukung', null, 'document', rekap_dokumen_badge()],
        ['konsistensi', 'Konsistensi', null, 'consistency', konsistensi_badge()],
    ]];

    return $items;
}

function render(string $judul, string $isi, array $opsi = []): void
{
    $aktif = $opsi['aktif'] ?? '';
    $sub = $opsi['sub'] ?? '';
    $aksi = $opsi['aksi'] ?? '';
    $U = pengguna();
    $nav = nav_items();
    ?><!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light">
<meta name="theme-color" content="#FFFFFF">
<title><?= e($judul) ?> · Sistem Akreditasi LAM-PTKes</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(BASE_URL) ?>assets/css/app.css?v=1">
<link rel="stylesheet" href="<?= e(BASE_URL) ?>assets/css/anim.css?v=1">
</head>
<body>

<header class="topbar">
  <button class="sb-toggle" id="sbToggle" aria-label="Buka menu" aria-expanded="false" aria-controls="sidebar">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
      <path d="M3 6h18M3 12h18M3 18h18"/>
    </svg>
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
    <div class="avatar" title="<?= e($U['nama'] ?? '') ?>"><?= e(inisial($U['nama'] ?? '')) ?></div>
    <a class="who-keluar" href="<?= e(BASE_URL) ?>index.php?m=masuk&amp;a=keluar">Keluar</a>
  </div>
</header>

<aside class="sidebar" id="sidebar" aria-label="Navigasi utama">
  <div class="switch">
    <?php foreach (semua_prodi() as $p): ?>
      <button data-prodi="<?= (int) $p['id'] ?>" aria-pressed="<?= (int) $p['id'] === prodi_aktif() ? 'true' : 'false' ?>"><?= e($p['nama_ps']) ?></button>
    <?php endforeach; ?>
  </div>
  <button class="period"><b>TS <?= e(tahun_ts()) ?></b></button>
  <?php foreach ($nav as $grup): ?>
    <div class="nav-sep"></div>
    <?php if ($grup['kelompok']): ?><div class="nav-label"><?= e($grup['kelompok']) ?></div><?php endif; ?>
    <?php foreach ($grup['baris'] as $b): ?>
      <?php $kode = $b[0]; $nama = $b[1]; $fitur = $b[2] ?? null; $ikon = $b[3] ?? null; $badge = $b[4] ?? null; ?>
      <a class="nav-item<?= $aktif === $kode ? ' aktif' : '' ?>" href="<?= e(BASE_URL) ?>index.php?m=<?= e($kode) ?>"<?= $aktif === $kode ? ' aria-current="page"' : '' ?>>
        <?= ikon((string) $ikon) ?><span class="lbl"><?= e($nama) ?></span>
        <?php if ($badge !== null && $badge !== ''): ?>
          <span class="badge"><?= e($badge) ?></span>
        <?php elseif ($fitur): ?>
          <span class="code"><?= e($fitur) ?></span>
        <?php endif; ?>
      </a>
    <?php endforeach; ?>
  <?php endforeach; ?>
  <div class="sb-foot">
    <div class="nav-label">EKSPOR PRODI INI</div>
    <div class="export-row">
      <?php if (boleh('EKSPOR')): ?>
        <a class="btn btn-warn" href="<?= e(BASE_URL) ?>index.php?m=ekspor&amp;a=pdf">PDF</a>
        <a class="btn btn-ghost" href="<?= e(BASE_URL) ?>index.php?m=ekspor&amp;a=xlsx">XLSX</a>
      <?php else: ?>
        <button class="btn btn-ghost" disabled>PDF</button>
        <button class="btn btn-ghost" disabled>XLSX</button>
      <?php endif; ?>
    </div>
  </div>
</aside>

<main class="main">
  <div class="page-head">
    <div>
      <h1><?= e($judul) ?></h1>
      <?php if ($sub !== ''): ?><div class="page-sub"><?= $sub ?></div><?php endif; ?>
    </div>
    <?php if ($aksi !== ''): ?><div class="actions"><?= $aksi ?></div><?php endif; ?>
  </div>
  <?= $isi ?>
</main>

<script type="module">
  import { animateIn } from '<?= e(BASE_URL) ?>assets/js/anim.js';
  const main = document.querySelector('.main');
  if (main) animateIn(main);

  // Drawer sidebar di mobile — perilaku sama dengan app.js lama.
  const body = document.body, tog = document.getElementById('sbToggle');
  tog?.addEventListener('click', () => {
    const buka = body.classList.toggle('sb-open');
    tog.setAttribute('aria-expanded', String(buka));
  });
  document.addEventListener('click', (e) => {
    if (!body.classList.contains('sb-open')) return;
    if (e.target.closest('.sidebar') || e.target.closest('#sbToggle')) return;
    body.classList.remove('sb-open');
    tog?.setAttribute('aria-expanded', 'false');
  });
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') { body.classList.remove('sb-open'); tog?.setAttribute('aria-expanded', 'false'); }
  });
</script>
</body>
</html><?php
}

/** "Siti Rahmawati, S.ST., M.Keb" -> "SR" */
function inisial(string $nama): string
{
    $bersih = preg_replace('/,.*$/', '', $nama);
    $kata = preg_split('/\s+/', trim((string) $bersih)) ?: [];
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

function semua_prodi(): array
{
    return q('SELECT id, nama_ps FROM prodi WHERE aktif = 1 ORDER BY id');
}

/** Tahun TS dari periode aktif prodi. Tanpa ini semua label tahun salah. */
function tahun_ts(): int
{
    static $ts = null;
    if ($ts !== null) {
        return $ts;
    }
    $p = prodi_aktif();
    return $ts = (int) qv('SELECT tahun_ts FROM periode WHERE prodi_id = ? AND aktif = 1
                            ORDER BY tahun_ts DESC LIMIT 1', [$p], (int) date('Y'));
}

/** Badge sidebar "17/38". Dihitung dari rekap.php, bukan diketik. */
function rekap_tabel_badge(): string
{
    $p = prodi_aktif();
    $per = $p ? periode_aktif($p) : null;
    if (!$p || !$per) {
        return '—';
    }
    $r = rekap_ringkas($p, (int) $per['id']);
    return sprintf('%d/%d', $r['selesai'], $r['wajib']);
}

function rekap_dokumen_badge(): string
{
    $p = prodi_aktif();
    $per = $p ? periode_aktif($p) : null;
    if (!$p || !$per) {
        return '—';
    }
    $r = rekap_dokumen($p, (int) $per['id']);
    return sprintf('%d/%d', $r['unggah'], $r['total']);
}

function konsistensi_badge(): string
{
    return '—';
}
