<?php
declare(strict_types=1);

/**
 * Modul: master — satu pintu untuk semua entitas master.
 *
 * Kenapa satu modul, bukan enam: entitas master dipakai bersama oleh tabel
 * data dukung, dan sidebar tidak perlu enam menu terpisah untuk data yang
 * diisi sekali. Yang membedakan hanya berkas entitasnya.
 *
 * Berkas entitas hidup sendiri-sendiri (`mahasiswa.php`, `dosen.php`, `mk.php`,
 * ...) supaya bisa dikerjakan paralel tanpa saling timpa. Berkas-berkas itu
 * hanya bertindak kalau `?a=` berawalan namanya, jadi index.php cukup
 * me-require semuanya.
 *
 * Aksi yang tersedia (lihat juga web/index.php):
 *   a=mahasiswa | mahasiswa-form | mahasiswa-simpan | mahasiswa-impor
 *   a=dosen     | dosen-form     | dosen-simpan     | dosen-impor
 *   a=mk        | wahana | lab | pustaka
 *
 * ALIAS PENTING: sidebar menautkan `m=dosen`, `m=mahasiswa`, `m=kurikulum`,
 * `m=sarana`, `m=pustaka`. Itu nama MENU, bukan nama modul. Front controller
 * memetakan `m` yang bukan modul ke sini dengan `a` yang sesuai, supaya URL
 * lama tidak 404 dan sidebar tidak perlu tahu nama berkas.
 */

require_once __DIR__ . '/_bersama.php';
require_once dirname(__DIR__, 2) . '/system/layout.php';
require_once dirname(__DIR__, 2) . '/tampilan/kerangka/komponen.php';

// Aksi default: kalau `m=master` dibuka langsung, tampilkan ringkasan entitas.
$aksi = (string) ($_GET['a'] ?? 'index');

/* Alias: ?m=dosen -> a=dosen, ?m=kurikulum -> a=mk, dst.
   Dipetakan di sini, bukan di front controller, supaya front controller tetap
   bodoh soal nama menu. */
$alias = [
    'dosen'     => 'dosen',
    'mahasiswa' => 'mahasiswa',
    'kurikulum' => 'mk',
    'sarana'    => 'wahana',
    'pustaka'   => 'pustaka',
];
$menu = (string) ($_GET['m'] ?? 'master');
if (isset($alias[$menu]) && $aksi === 'index') {
    $aksi = $alias[$menu];
    $_GET['a'] = $aksi;
}

// Semua berkas entitas di-require; masing-masing memeriksa sendiri apakah dia
// yang harus bertindak. Urutan tidak penting.
foreach (['mahasiswa', 'dosen', 'mk', 'wahana', 'lab', 'pustaka'] as $entitas) {
    $berkas = __DIR__ . '/' . $entitas . '.php';
    if (is_file($berkas)) {
        require $berkas;
    }
}

/* Kalau sampai di sini, tidak ada berkas entitas yang mengaku menangani aksi
   ini. Tampilkan daftar entitas — lebih berguna daripada 404. */
if ($aksi === 'index') {
    $masuk = [
        ['mahasiswa', 'Mahasiswa', 'F-05', (int) qv('SELECT COUNT(*) FROM mahasiswa WHERE prodi_id = ?', [$PRODI], 0)],
        ['dosen', 'Dosen & Tendik', 'F-06', (int) qv('SELECT COUNT(*) FROM dosen', [], 0)],
        ['mk', 'Kurikulum & Mata Kuliah', 'F-07', (int) qv('SELECT COUNT(*) FROM mata_kuliah WHERE prodi_id = ?', [$PRODI], 0)],
        ['wahana', 'Sarana & Wahana', 'F-15', (int) qv('SELECT COUNT(*) FROM wahana WHERE prodi_id = ?', [$PRODI], 0)],
        ['lab', 'Laboratorium & Alat', 'F-15', (int) qv('SELECT COUNT(*) FROM laboratorium WHERE prodi_id = ?', [$PRODI], 0)],
        ['pustaka', 'Pustaka & Jurnal', 'F-16', (int) qv('SELECT COUNT(*) FROM pustaka WHERE prodi_id = ?', [$PRODI], 0)],
    ];
    $isi = kartu('Data master', tabel_html(
        [['Master', ''], ['Fitur', ''], ['Jumlah baris', 'r'], ['', 'r']],
        array_map(fn($m) => [
            '<span class="strong">' . e($m[1]) . '</span>',
            '<span class="muted">' . e($m[2]) . '</span>',
            '<span class="num">' . e(number_format($m[3], 0, ',', '.')) . '</span>',
            '<a class="btn btn-sm" href="' . e(BASE_URL) . 'index.php?m=' . e($m[0]) . '">Buka →</a>',
        ], $masuk),
        ['kosong' => 'Belum ada data master. Mulai dari Data Dukung atau isi master di atas.']
    ));
    $isi .= callout('info', 'Kenapa master dulu',
        '<p>Tabel data dukung mengambil angka dari sini. Kalau master kosong, '
        . 'tabel yang seharusnya dihitung sistem akan tampil kosong — dan itu '
        . 'benar, bukan bug.</p>');
    render('Data Master', $isi, ['aktif' => 'master', 'sub' => 'Diisi sekali, dipakai banyak tabel']);
    return;
}

tolak(404, 'Aksi master tidak dikenal: ' . $aksi);
