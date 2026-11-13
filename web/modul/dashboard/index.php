<?php
declare(strict_types=1);

/**
 * Dashboard: satu pandangan atas keadaan prodi aktif.
 *
 * Semua ANGKA di sini berasal dari web/system/rekap.php. Dashboard tidak
 * menghitung sendiri — itu yang dulu membuat "dashboard 26 lengkap, Data
 * Dukung 25" mustahil dipercaya.
 *
 * Halaman ini hanya membaca. Tidak ada gerbang tulis, tidak ada form.
 */

require_once __DIR__ . '/../../system/layout.php';
require_once __DIR__ . '/../../tampilan/kerangka/komponen.php';

$prodi = $PRODI ?? prodi_aktif();
$prodiNama = $U['prodi_nama'] ?? null;
$periode = $prodi ? periode_aktif($prodi) : null;

/* ── tanpa prodi / periode: berhenti jujur, jangan tampilkan nol ── */
if (!$prodi || !$periode) {
    $pesan = $prodi
        ? 'Prodi ini belum punya periode akreditasi aktif, jadi tidak ada yang bisa direkap. '
          . '<a href="' . e(BASE_URL . 'index.php?m=periode') . '">Atur periode akreditasi</a>.'
        : 'Akun ini belum terikat ke program studi. Pilih prodi dulu, atau minta Admin UPPS mengaturnya.';
    render('Dashboard', gaya_kerangka() . halaman_galat(409, $pesan), ['aktif' => 'dashboard']);
    return;
}

$perId = (int) $periode['id'];
$ringkas = rekap_ringkas($prodi, $perId);
$dokumen = rekap_dokumen($prodi, $perId);
$semua = rekap_semua_tabel($prodi, $perId);

$adaIsi = $ringkas['selesai'] > 0 || $ringkas['sebagian'] > 0 || $dokumen['unggah'] > 0;

/* ── 5 tabel paling tertinggal, urut nomor ────────────────────────────
   "Tertinggal" = jumlah field kosong terbesar; kosong total mengalahkan
   sebagian karena kosong total berarti belum disentuh sama sekali.
   Tabel `na` tidak ikut: bukan pekerjaan prodi ini. */
$tertinggal = array_values(array_filter($semua, fn($t) => in_array($t['status'], ['kosong', 'sebagian'], true)));
usort($tertinggal, function (array $a, array $b): int {
    $ka = $a['status'] === 'kosong' ? 1 : 0;
    $kb = $b['status'] === 'kosong' ? 1 : 0;
    if ($ka !== $kb) {
        return $kb <=> $ka;
    }
    $sa = $a['total'] - $a['terisi'];
    $sb = $b['total'] - $b['terisi'];
    if ($sa !== $sb) {
        return $sb <=> $sa;
    }
    return $a['nomor'] <=> $b['nomor'];
});
$tertinggal = array_slice($tertinggal, 0, 5);
usort($tertinggal, fn(array $a, array $b): int => $a['nomor'] <=> $b['nomor']);

/* ── dokumen kedaluwarsa: jumlahnya dari rekap_dokumen(), daftarnya di sini */
$kedaluwarsa = [];
if ($dokumen['kedaluwarsa'] > 0) {
    $kedaluwarsa = q(
        'SELECT d.nomor, d.nama, d.kriteria, MAX(u.berlaku_sampai) AS berlaku_sampai
         FROM dokumen_unggah u
         JOIN dokumen_spec d ON d.id = u.dok_spec_id
         WHERE u.prodi_id = ? AND u.periode_id = ?
           AND u.berlaku_sampai IS NOT NULL AND u.berlaku_sampai < CURDATE()
         GROUP BY d.id, d.nomor, d.nama, d.kriteria
         ORDER BY berlaku_sampai',
        [$prodi, $perId]);
}

$isi = gaya_kerangka();

/* ── strip angka ──────────────────────────────────────────────────── */
$isi .= strip_angka([
    ['label' => 'Tabel wajib', 'nilai' => $ringkas['wajib'], 'satuan' => 'tabel'],
    ['label' => 'Selesai', 'nilai' => $ringkas['selesai'], 'dari' => $ringkas['wajib'],
     'ket' => $ringkas['terkunci'] > 0 ? $ringkas['terkunci'] . ' terkunci' : ''],
    ['label' => 'Sebagian', 'nilai' => $ringkas['sebagian'], 'dari' => $ringkas['wajib']],
    ['label' => 'Kosong', 'nilai' => $ringkas['kosong'], 'dari' => $ringkas['wajib']],
    ['label' => 'Dokumen terunggah', 'nilai' => $dokumen['unggah'], 'dari' => $dokumen['total'],
     'ket' => $dokumen['kedaluwarsa'] > 0 ? $dokumen['kedaluwarsa'] . ' kedaluwarsa' : ''],
]);

/* ── keadaan kosong yang jujur ────────────────────────────────────── */
if (!$adaIsi) {
    $isi .= callout('info', 'Belum ada data diisi',
        'Periode ' . e(label_ts(tahun_ts(), 0)) . ' belum punya satu pun tabel terisi dan belum ada '
        . 'dokumen terunggah. Angka di atas adalah keadaan sebenarnya, bukan ringkasan sementara. '
        . 'Mulai dari <a href="' . e(BASE_URL . 'index.php?m=tabel') . '">Data Dukung</a> atau '
        . '<a href="' . e(BASE_URL . 'index.php?m=dokumen') . '">Dokumen Pendukung</a>.');
}

/* ── tabel paling tertinggal ──────────────────────────────────────── */
$barisTertinggal = [];
foreach ($tertinggal as $t) {
    $celah = $t['total'] - $t['terisi'];
    // Bar = porsi TERISI, angka = porsi terisi. Sama-sama terisi, tidak menyesatkan.
    $barisTertinggal[] = [
        'nomor'  => $t['nomor'],
        'judul'  => ['html' => '<a href="' . e(BASE_URL . 'index.php?m=tabel&a=lihat&t=' . $t['kode']) . '">'
                              . e($t['judul']) . '</a>'],
        'kriteria' => 'K' . $t['kriteria'],
        'status' => ['html' => badge_status($t['status'])],
        'terisi' => ['html' => '<span class="num">' . e($t['terisi']) . '/' . e($t['total']) . '</span>'
                               . bar_bagian((float) $t['terisi'], (float) $t['total'])],
        'celah'  => ['html' => '<span class="num">' . e((string) (int) $celah) . '</span> <span class="muted">field</span>'],
    ];
}

$isi .= kartu('Tabel paling tertinggal',
    tabel_html([
        ['kunci' => 'nomor', 'label' => 'No', 'kelas' => 'r', 'lebar' => '44px'],
        ['kunci' => 'judul', 'label' => 'Tabel', 'html' => false],
        ['kunci' => 'kriteria', 'label' => 'Kriteria', 'lebar' => '68px'],
        ['kunci' => 'status', 'label' => 'Status'],
        ['kunci' => 'terisi', 'label' => 'Terisi'],
        ['kunci' => 'celah', 'label' => 'Celah', 'kelas' => 'r'],
    ], $barisTertinggal, [
        'kosong' => 'Tidak ada tabel yang tertinggal — semua tabel wajib sudah terisi.',
    ]),
    ['catatan' => count($tertinggal) . ' dari ' . $ringkas['belum'] . ' tabel belum selesai']);

/* ── peringatan dokumen kedaluwarsa ───────────────────────────────── */
if ($dokumen['kedaluwarsa'] > 0) {
    $daftar = '';
    foreach (array_slice($kedaluwarsa, 0, 5) as $d) {
        $daftar .= '<li><b>' . e('No ' . $d['nomor']) . '</b> — ' . e($d['kriteria']) . ' · '
                 . 'berlaku sampai ' . e(tgl((string) $d['berlaku_sampai'])) . ': '
                 . e(mb_strimwidth((string) $d['nama'], 0, 120, '…')) . '</li>';
    }
    $lebih = $dokumen['kedaluwarsa'] - min(5, count($kedaluwarsa));
    if ($lebih > 0) {
        $daftar .= '<li class="muted">dan ' . e($lebih) . ' dokumen lain</li>';
    }
    $isi .= kartu('Dokumen kedaluwarsa',
        callout('warn', $dokumen['kedaluwarsa'] . ' dokumen sudah lewat masa berlaku',
            'Dokumen kedaluwarsa tetap dihitung terunggah, tetapi tidak bisa dipakai sebagai bukti '
            . 'pada periode ini. Unggah ulang versi terbaru.') . '<ul class="daftar-ringkas">' . $daftar . '</ul>',
        ['catatan' => 'tanggal berlaku lewat hari ini']);
}

/* ── sebaran 8 kriteria ──────────────────────────────────────────── */
$perKrit = rekap_kriteria($prodi, $perId);
$barisKrit = [];
$tanpaTabelKrit = [];
foreach ($perKrit as $k) {
    if ($k['total'] === 0) {
        $tanpaTabelKrit[] = $k['kriteria'];
    }
    $barisKrit[] = [
        'kode'   => $k['kriteria'],
        'nama'   => $k['nama'],
        'tabel'  => ['html' => $k['total'] === 0
            ? '<span class="muted">—</span>'
            : '<span class="num">' . e($k['lengkap']) . '/' . e($k['wajib']) . '</span>'
              . bar_bagian((float) $k['lengkap'], (float) $k['wajib'])],
    ];
}
$isi .= kartu('8 kriteria', tabel_html([
    ['kunci' => 'kode', 'label' => 'Kode', 'lebar' => '56px'],
    ['kunci' => 'nama', 'label' => 'Kriteria'],
    ['kunci' => 'tabel', 'label' => 'Tabel lengkap'],
], $barisKrit, [
    'kosong' => 'Kriteria belum terbaca.',
]), ['catatan' => $tanpaTabelKrit === []
    ? 'rincian per kriteria ada di Ringkasan 8 Kriteria'
    : 'rincian per kriteria ada di Ringkasan 8 Kriteria · ' . implode(', ', $tanpaTabelKrit)
      . ' tanpa tabel, dinilai dari dokumen']);

render('Dashboard', $isi, [
    'aktif' => 'dashboard',
    'sub'   => ($prodiNama !== null && $prodiNama !== '' ? $prodiNama . ' · ' : '') . label_ts(tahun_ts(), 0)
               . ' · diperbarui ' . date('j/n/Y'),
]);
