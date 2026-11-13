<?php
declare(strict_types=1);

/**
 * Ringkasan 8 Kriteria: keadaan tiap kriteria, tabel dan dokumen.
 *
 * Dua besaran berbeda ditampilkan di kolom berbeda, masing-masing dengan
 * bar-nya sendiri: bar tabel = porsi tabel lengkap, bar dokumen = porsi
 * dokumen terunggah. Tidak ada satu bar yang mewakili dua hal.
 *
 * K1 memang tidak punya tabel — hanya 4 dokumen. Itu ditampilkan apa adanya
 * dengan catatan, bukan disembunyikan dan bukan diisi 0 palsu.
 */

require_once __DIR__ . '/../../system/layout.php';
require_once __DIR__ . '/../../tampilan/kerangka/komponen.php';

$prodi = $PRODI ?? prodi_aktif();
$prodiNama = $U['prodi_nama'] ?? null;
$periode = $prodi ? periode_aktif($prodi) : null;

if (!$prodi || !$periode) {
    $pesan = $prodi
        ? 'Prodi ini belum punya periode akreditasi aktif.'
        : 'Akun ini belum terikat ke program studi.';
    render('Ringkasan 8 Kriteria', gaya_kerangka() . halaman_galat(409, $pesan), ['aktif' => 'ringkasan']);
    return;
}

$perId = (int) $periode['id'];
$perKrit = rekap_kriteria($prodi, $perId);
$ringkas = rekap_ringkas($prodi, $perId);
$dokumen = rekap_dokumen($prodi, $perId);

// Dokumen per kriteria: kunci 'K1'..'K8' dari dokumen_spec.
$dokPer = [];
foreach (rekap_dokumen_per_kriteria($prodi, $perId) as $d) {
    $dokPer[(string) $d['kriteria']] = ['total' => (int) $d['total'], 'unggah' => (int) $d['unggah']];
}

$isi = gaya_kerangka();

$isi .= strip_angka([
    ['label' => 'Kriteria', 'nilai' => count($perKrit), 'satuan' => 'kriteria'],
    ['label' => 'Tabel lengkap', 'nilai' => $ringkas['selesai'], 'dari' => $ringkas['wajib'],
     'satuan' => 'tabel'],
    ['label' => 'Dokumen terunggah', 'nilai' => $dokumen['unggah'], 'dari' => $dokumen['total'],
     'satuan' => 'butir'],
    ['label' => 'Dokumen kedaluwarsa', 'nilai' => $dokumen['kedaluwarsa'],
     'satuan' => 'butir', 'ket' => 'lewat masa berlaku'],
]);

$baris = [];
$tanpaTabel = [];
foreach ($perKrit as $k) {
    $kode = $k['kriteria'];
    $dok = $dokPer[$kode] ?? ['total' => 0, 'unggah' => 0];

    if ($k['total'] > 0) {
        $selTabel = '<span class="num">' . e($k['lengkap']) . '</span>'
                  . ' <span class="muted">/ ' . e($k['wajib']) . ' wajib</span>'
                  . bar_bagian((float) $k['lengkap'], (float) $k['wajib']);
        $sisa = $k['wajib'] - $k['lengkap'];
        $selSisa = $sisa > 0
            ? '<span class="num">' . e($sisa) . '</span> <span class="muted">tabel</span>'
            : '<span class="muted">—</span>';
    } else {
        $tanpaTabel[] = $kode;
        // 0 tabel bukan "0 dari 0 selesai": tidak ada yang bisa diisi di sini.
        $selTabel = '<span class="muted">tidak ada tabel</span>';
        $selSisa = '<span class="muted">—</span>';
    }

    $selDok = $dok['total'] > 0
        ? '<span class="num">' . e($dok['unggah']) . '</span>'
          . ' <span class="muted">/ ' . e($dok['total']) . '</span>'
          . bar_bagian((float) $dok['unggah'], (float) $dok['total'])
        : '<span class="muted">tidak ada butir</span>';

    $baris[] = [
        'kode'  => $kode,
        'nama'  => $k['nama'],
        'tabel' => ['html' => $selTabel],
        'sisa'  => ['html' => $selSisa],
        'dok'   => ['html' => $selDok],
    ];
}

$isi .= kartu('Per kriteria',
    tabel_html([
        ['kunci' => 'kode', 'label' => 'Kode', 'lebar' => '56px'],
        ['kunci' => 'nama', 'label' => 'Kriteria'],
        ['kunci' => 'tabel', 'label' => 'Tabel lengkap'],
        ['kunci' => 'sisa', 'label' => 'Sisa', 'kelas' => 'r', 'lebar' => '96px'],
        ['kunci' => 'dok', 'label' => 'Dokumen'],
    ], $baris),
    ['catatan' => 'bar dan angka di kolom yang sama mengukur besaran yang sama']);

if ($tanpaTabel !== []) {
    $isi .= callout('info',
        implode(', ', $tanpaTabel) . ' tidak punya tabel data dukung',
        'Kriteria ini dinilai dari dokumen pendukung saja. Kolom "Sisa" sengaja diisi tanda pisah, '
        . 'bukan 0 — tidak ada tabel yang tertinggal di sini.');
}

render('Ringkasan 8 Kriteria', $isi, [
    'aktif' => 'ringkasan',
    'sub'   => ($prodiNama !== null && $prodiNama !== '' ? $prodiNama . ' · ' : '') . label_ts(tahun_ts(), 0),
]);
