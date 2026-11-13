<?php
declare(strict_types=1);

/**
 * Modul: Konsistensi lintas tabel.
 *
 * Halaman ini hanya menampilkan hasil `konsistensi_jalankan()`. Tidak ada
 * penilaian sendiri di sini: status dan selisih datang dari fungsi.php supaya
 * halaman dan modul ekspor tidak mungkin beda pendapat.
 */

require_once __DIR__ . '/../../system/layout.php';
require_once __DIR__ . '/../../tampilan/kerangka/komponen.php';
require_once __DIR__ . '/fungsi.php';

$PRODI ??= prodi_aktif();
$per = $PRODI ? periode_aktif((int) $PRODI) : null;
$perId = $per ? (int) $per['id'] : null;

$isi = '';

if (!$PRODI || !$per) {
    $isi .= callout('warn', 'Periode belum ada',
        'Pilih prodi dan isi periode akreditasi lebih dulu di menu Periode Akreditasi. '
        . 'Tanpa periode, tidak ada data yang bisa dibandingkan.');
    render('Konsistensi Lintas Tabel', $isi, ['aktif' => 'konsistensi',
        'sub' => 'Tidak ada periode aktif — aturan belum bisa dijalankan']);
    return;
}

$hasil = konsistensi_jalankan((int) $PRODI, $perId);
$ringkas = konsistensi_ringkas($hasil);
$blokir = array_values(array_filter($hasil, fn($h) => $h['tingkat'] === 'blokir' && $h['status'] === 'gagal'));

/** Angka aturan: null jadi "—", tidak pernah diganti 0. */
$kn_angka = static function (?float $n, string $satuan): string {
    if ($n === null) {
        return '<span class="muted">—</span>';
    }
    $teks = fmod($n, 1.0) === 0.0 ? number_format($n, 0, ',', '.') : number_format($n, 2, ',', '.');
    return '<span class="num">' . e($teks) . '</span>'
         . ($satuan !== '' ? ' <span class="kn-satuan">' . e($satuan) . '</span>' : '');
};

$ikon = ['lulus' => 'M20 6 9 17l-5-5', 'gagal' => 'M12 8v5M12 17h.01M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20Z',
         'belum' => 'M12 8h.01M11 12h1v4h1M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20Z'];
$kn_badge = static function (string $status) use ($ikon): string {
    return '<span class="kn-status kn-' . e($status) . '">'
         . '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"'
         . ' stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="'
         . $ikon[$status] . '"/></svg>' . e(KN_STATUS[$status]) . '</span>';
};

$tingkat_label = ['blokir' => 'Blokir', 'peringatan' => 'Peringatan', 'wajib' => 'Wajib'];

/* ── ringkasan + gerbang ekspor ─────────────────────────────────── */
$isi .= strip_angka([
    ['label' => 'Aturan aktif', 'nilai' => $ringkas['total'], 'satuan' => 'aturan'],
    ['label' => 'Lulus',        'nilai' => $ringkas['lulus'], 'dari' => $ringkas['total']],
    ['label' => 'Gagal',        'nilai' => $ringkas['gagal'], 'dari' => $ringkas['total']],
    ['label' => 'Belum ada data', 'nilai' => $ringkas['belum'], 'dari' => $ringkas['total']],
]);

if ($blokir !== []) {
    $kode = array_column($blokir, 'kode');
    $isi .= callout('bad', 'Ekspor dimatikan',
        sprintf('%d aturan tingkat blokir gagal (%s). Ekspor PDF/XLSX ditolak sampai '
              . 'aturan ini lulus — perbaiki tabel yang disebut di kolom Perbaiki.',
            count($blokir), e(implode(', ', $kode))));
} elseif ($ringkas['gagal'] > 0) {
    $kode = array_column(array_filter($hasil, fn($h) => $h['status'] === 'gagal'), 'kode');
    $isi .= callout('warn', 'Ada aturan yang tidak cocok',
        sprintf('%s gagal. Tidak mematikan ekspor karena tingkatnya bukan blokir, '
              . 'tetapi tetap harus diperbaiki sebelum berkas dikirim ke asesor.',
            e(implode(', ', $kode))));
} elseif ($ringkas['belum'] > 0) {
    $isi .= callout('info', 'Sebagian aturan belum bisa dihitung',
        sprintf('%d dari %d aturan belum punya data pembanding. Statusnya "Belum ada data", '
              . 'bukan "Lulus" — data yang belum diisi tidak boleh dianggap cocok.', $ringkas['belum'], $ringkas['total']));
} else {
    $isi .= callout('ok', 'Semua aturan terhitung dan lulus', 'Ekspor boleh dijalankan.');
}

/* ── tabel 10 aturan ────────────────────────────────────────────── */
$kolom = [
    ['kunci' => 'kode', 'label' => 'Kode', 'lebar' => '54px'],
    ['kunci' => 'nama', 'label' => 'Aturan'],
    ['kunci' => 'tingkat', 'label' => 'Tingkat', 'lebar' => '96px', 'html' => true],
    ['kunci' => 'a', 'label' => 'Nilai A', 'kelas' => 'r', 'html' => true, 'lebar' => '130px'],
    ['kunci' => 'b', 'label' => 'Nilai B', 'kelas' => 'r', 'html' => true, 'lebar' => '130px'],
    ['kunci' => 'selisih', 'label' => 'Selisih', 'kelas' => 'r', 'html' => true, 'lebar' => '96px'],
    ['kunci' => 'status', 'label' => 'Status', 'html' => true, 'lebar' => '126px'],
    ['kunci' => 'perbaikan', 'label' => 'Perbaiki di', 'html' => true, 'lebar' => '150px'],
];

$baris = [];
foreach ($hasil as $h) {
    $ket = [];
    if ($h['label_a'] !== '') {
        $ket[] = 'A: ' . $h['label_a'];
    }
    if ($h['label_b'] !== '') {
        $ket[] = 'B: ' . $h['label_b'];
    }
    if ($h['alasan'] !== '') {
        $ket[] = $h['alasan'];
    }

    $selisih = $h['selisih'] === null
        ? '<span class="muted" title="tidak bisa dihitung">—</span>'
        : '<span class="num' . (abs($h['selisih']) > 0.00005 ? ' kn-beda' : '') . '">'
          . e(($h['selisih'] > 0 ? '+' : '') . desimal($h['selisih'], fmod($h['selisih'], 1.0) === 0.0 ? 0 : 2)) . '</span>'
          . ($h['satuan'] !== '' ? ' <span class="kn-satuan">' . e($h['satuan']) . '</span>' : '');

    $perbaikan = $h['perbaikan'] === ''
        ? '<span class="muted">—</span>'
        : '<span class="kn-perbaikan">' . e($h['perbaikan']) . '</span>';

    $baris[] = [
        'kode'      => $h['kode'],
        'nama'      => $h['nama'] . ($ket !== [] ? '<div class="kn-ket">' . e(implode(' · ', $ket)) . '</div>' : ''),
        'tingkat'   => '<span class="kn-tingkat kn-t-' . e($h['tingkat']) . '">'
                       . e($tingkat_label[$h['tingkat']] ?? $h['tingkat']) . '</span>',
        'a'         => $kn_angka($h['a'], $h['satuan']),
        'b'         => $kn_angka($h['b'], $h['satuan']),
        'selisih'   => $selisih,
        'status'    => $kn_badge($h['status']),
        'perbaikan' => $perbaikan,
    ];
}

// Kolom 'nama' berisi HTML (sudah di-escape di atas) -> tandai html.
$kolom[1]['html'] = true;

$isi .= kartu('10 aturan lintas tabel', tabel_html($kolom, $baris), [
    'catatan' => 'Selisih = A − B, dihitung. "—" berarti tidak bisa dihitung, bukan nol.',
]);

$isi .= callout('info', 'Cara membaca',
    'Nilai A dan B diambil dari isian tabel yang disebut pada nama aturan. Selisih dihitung '
    . 'sistem (A − B); tanda hubung panjang berarti salah satu nilai belum ada, jadi tidak '
    . 'bisa dihitung. Aturan tingkat <b>blokir</b> yang gagal mematikan ekspor.');

render('Konsistensi Lintas Tabel', $isi, [
    'aktif' => 'konsistensi',
    'sub'   => sprintf('%s · TS %d · %d aturan, %d gagal, %d belum ada data',
        (string) ($U['prodi_nama'] ?? ''), tahun_ts(), $ringkas['total'], $ringkas['gagal'], $ringkas['belum']),
]);
