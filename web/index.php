<?php
declare(strict_types=1);

/**
 * Front controller tunggal.
 *
 * Rute: index.php?m=<modul>&a=<aksi>&...
 *
 *   m=masuk       a=index | proses | keluar | sso
 *   m=dashboard   a=index
 *   m=ringkasan   a=index
 *   m=identitas   a=index | simpan
 *   m=periode     a=index | simpan
 *   m=tabel       a=index | lihat | simpan | kunci | buka
 *   m=master      a=mahasiswa | dosen | mk | wahana | lab | pustaka
 *   m=dokumen     a=index | unggah
 *   m=konsistensi a=index
 *   m=pengguna    a=index | simpan
 *   m=ekspor      a=pdf | xlsx
 *
 * Setiap modul ada di modul/<nama>/index.php dan WAJIB memanggil gerbang
 * izinnya sendiri — front controller tidak tahu aksi bisnis apa yang terjadi.
 * Peta modul ada di bawah; menambah modul = menambah satu baris.
 */

require_once __DIR__ . '/system/view.php';

$modul = preg_replace('/[^a-z]/', '', $_GET['m'] ?? 'dashboard');
$aksi  = preg_replace('/[^a-z0-9\-]/', '', $_GET['a'] ?? 'index');

/* Sidebar memakai nama MENU sebagai `m` (dosen, mahasiswa, kurikulum, sarana,
   pustaka). Itu bukan nama modul. Dipetakan ke modul `master` dengan aksi
   yang sesuai, supaya URL di sidebar dan tautan lama tidak 404. */
const ALIAS_MODUL = [
    'dosen'     => ['master', 'dosen'],
    'mahasiswa' => ['master', 'mahasiswa'],
    'kurikulum' => ['master', 'mk'],
    'sarana'    => ['master', 'wahana'],
    'pustaka'   => ['master', 'pustaka'],
];
// Halaman menu yang isinya belum punya modul sendiri: tampilkan keadaan
// jujur (apa yang dibutuhkan), bukan 404 yang tidak menjelaskan apa pun.
const MODUL_BELUM = [
    'uji'       => ['Uji Kompetensi', 'F-09', ['Data uji CBT/OSCE per periode', 'Jumlah peserta dan kelulusan first taker / re-taker']],
    'survei'    => ['Survei & Tracer', 'F-10', ['Skor survei kepuasan mahasiswa', 'Tracer study: masa tunggu lulusan']],
    'keuangan'  => ['Keuangan', 'F-17', ['Penerimaan dana (Tabel 33)', 'Penggunaan dana per tahun (Tabel 34)']],
    'kerjasama' => ['Kerja Sama', 'F-19', ['Daftar MoU/PKS beserta masa berlaku', 'Keterkaitan dengan wahana praktik (Tabel 38)']],
];
if (isset(MODUL_BELUM[$modul])) {
    [$judul, $fitur, $butuh] = MODUL_BELUM[$modul];
    require_once __DIR__ . '/system/layout.php';
    require_once __DIR__ . '/tampilan/kerangka/komponen.php';
    render($judul, kosong($judul . ' — belum dibangun', $butuh),
        ['aktif' => $modul, 'sub' => 'Fitur ' . $fitur . ' · menunggu modulnya']);
    return;
}

if (isset(ALIAS_MODUL[$modul])) {
    [$modul, $aksi] = ALIAS_MODUL[$modul];
    $_GET['a'] = $aksi;
}

// Menu "Data Dukung" memakai m=datadukung, tapi modulnya m=tabel.
if ($modul === 'datadukung') {
    require __DIR__ . '/modul/datadukung/index.php';
    return;
}

const MODUL = [
    'masuk'       => true,    // tidak butuh sesi
    'dashboard'   => true,
    'ringkasan'   => true,
    'identitas'   => true,
    'periode'     => true,
    'tabel'       => true,
    'master'      => true,
    'dokumen'     => true,
    'konsistensi' => true,
    'pengguna'    => true,
    'ekspor'      => true,
    'datadukung'  => true,    // alias menu -> m=tabel
    'uji'         => true,
    'survei'      => true,
    'keuangan'    => true,
    'kerjasama'   => true,
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
