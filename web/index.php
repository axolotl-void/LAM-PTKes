<?php
declare(strict_types=1);

/**
 * Front controller tunggal.
 *
 * Rute: index.php?m=<modul>&a=<aksi>&...
 *   m=dashboard  a=index
 *   m=tabel      a=index | lihat | simpan | kunci | buka
 *   m=master     a=<entitas> | <entitas>-simpan
 *   m=dokumen    a=index | unggah
 *   m=konsistensi a=index
 *   m=pengguna   a=index | simpan
 *   m=ekspor     a=pdf | xlsx
 *   m=masuk      a=index | keluar | sso
 *
 * Setiap modul ada di modul/<nama>/index.php dan WAJIB memanggil gerbang
 * izinnya sendiri — front controller tidak tahu aksi bisnis apa yang terjadi.
 * Peta modul ada di bawah; menambah modul = menambah satu baris.
 */

require_once __DIR__ . '/system/view.php';

$modul = preg_replace('/[^a-z]/', '', $_GET['m'] ?? 'dashboard');
$aksi  = preg_replace('/[^a-z0-9\-]/', '', $_GET['a'] ?? 'index');

const MODUL = [
    'masuk'       => true,    // tidak butuh sesi
    'dashboard'   => true,
    'tabel'       => true,
    'master'      => true,
    'dokumen'     => true,
    'konsistensi' => true,
    'pengguna'    => true,
    'ekspor'      => true,
];

if (!isset(MODUL[$modul])) {
    tolak(404, 'Modul tidak dikenal: ' . $modul);
}

// Semua modul selain `masuk` butuh sesi. Ini gerbang pertama; gerbang per
// aksi (ISI/VERIFIKASI/...) dipanggil di dalam modul.
if ($modul !== 'masuk' && !pengguna()) {
    alihkan(BASE_URL . 'index.php?m=masuk');
}

$berkas = __DIR__ . '/modul/' . $modul . '/index.php';
if (!is_file($berkas)) {
    tolak(404, 'Modul belum dibangun: ' . $modul);
}

// Variabel yang tersedia untuk semua modul.
$U = pengguna();                     // null kalau di modul `masuk`
$PRODI = prodi_aktif();
$PRODI_NAMA = $U['prodi_nama'] ?? null;

require $berkas;
