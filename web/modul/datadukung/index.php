<?php
declare(strict_types=1);

/**
 * Modul: datadukung — daftar 38 tabel data dukung.
 *
 * Padanan `mockup/assets/js/pages/datadukung.js`. Kelas CSS dan struktur
 * tabelnya mengikuti desain asli (`.tbl`, `.badge`, `.kc-kode`, `.lnk`).
 *
 * Status tabel dihitung `status_satu_tabel()` — TIDAK disimpan. Ini yang
 * membuat "lengkap padahal 3/6" mustahil.
 */

require_once dirname(__DIR__, 2) . '/system/layout.php';
require_once dirname(__DIR__, 2) . '/tampilan/kerangka/komponen.php';

$p = prodi_aktif();
$per = $p ? periode_aktif($p) : null;

if (!$p || !$per) {
    render('Data Dukung', callout('warn', 'Prodi/periode belum ditetapkan',
        '<p>Tentukan prodi dan periode akreditasi dulu di menu <b>Identitas</b> '
        . 'dan <b>Periode Akreditasi</b>.</p>'), ['aktif' => 'datadukung']);
    return;
}

$filter = (string) ($_GET['k'] ?? '');
$semua = rekap_semua_tabel($p, (int) $per['id']);
$ringkas = rekap_ringkas($p, (int) $per['id']);

$baris = [];
foreach ($semua as $t) {
    if ($filter !== '' && $t['kriteria'] !== $filter) {
        continue;
    }
    if ($t['status'] === 'na') {
        continue;
    }
    $aksi = '';
    if ($t['status'] === 'terkunci') {
        $aksi = '<span class="lnk muted">Terkunci</span>';
    } else {
        $aksi = '<a class="lnk" href="' . e(BASE_URL) . 'index.php?m=tabel&amp;a=lihat&amp;t='
            . e($t['kode']) . '">' . ($t['terisi'] > 0 ? 'Buka' : 'Isi') . ' →</a>';
    }
    $baris[] = [
        '<span class="num strong">T-' . e($t['kode']) . '</span>',
        '<span>' . e($t['judul']) . '</span>',
        '<span class="kc-kode">' . e($t['kriteria']) . '</span>',
        badge_status($t['status']),
        '<span class="num">' . $t['terisi'] . '/' . $t['total'] . '</span>',
        $aksi,
    ];
}

$saring = '<div class="actions">';
$saring .= '<a class="btn btn-sm' . ($filter === '' ? ' btn-primary' : '') . '" href="'
    . e(BASE_URL) . 'index.php?m=datadukung">Semua</a>';
foreach (semua_kriteria() as $k) {
    $saring .= '<a class="btn btn-sm' . ($filter === $k['kode'] ? ' btn-primary' : '') . '" href="'
        . e(BASE_URL) . 'index.php?m=datadukung&amp;k=' . e($k['kode']) . '">' . e($k['kode']) . '</a>';
}
$saring .= '</div>';

$isi = strip([
    [e((string) $ringkas['wajib']), 'tabel wajib', ''],
    [e((string) $ringkas['selesai']), 'selesai', 'ok'],
    [e((string) $ringkas['sebagian']), 'sebagian', 'warn'],
    [e((string) $ringkas['kosong']), 'kosong', 'bad'],
]);

$isi .= kartu(
    cardHead('38 tabel data dukung', $ringkas['selesai'] . ' dari ' . $ringkas['wajib'] . ' selesai')
    . tabel_html(
        [['No', ''], ['Tabel', ''], ['Krit', ''], ['Status', ''], ['Field', 'r'], ['', 'r']],
        $baris
    )
    . '<p class="foot-note">Status dihitung dari field terisi, bukan disimpan. '
    . 'Tabel yang terkunci menolak isian sampai Admin UPPS membuka.</p>'
);

render('Data Dukung', $isi, ['aktif' => 'datadukung', 'aksi' => $saring,
    'sub' => 'Prodi ' . ($p ? qv('SELECT nama_ps FROM prodi WHERE id = ?', [$p], '') : '')]);
