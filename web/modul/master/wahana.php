<?php
declare(strict_types=1);

/**
 * Master akademik — wahana praktik.
 *
 * Aturan yang menentukan bentuk halaman ini: wahana yang dokumen kerja samanya
 * kedaluwarsa TIDAK disembunyikan. Kalau disembunyikan, R08 (konsistensi
 * jumlah wahana vs jumlah lahan praktik) akan "lulus" padahal mahasiswa
 * praktik di tempat yang MoU-nya sudah mati. Jadi yang kedaluwarsa tetap di
 * daftar, disorot, dengan tanggal dan sisa hari.
 */

require_once __DIR__ . '/mk.php';

/* ══ INTI ═══════════════════════════════════════════════════════════ */

const MKA_WAHANA_SEGERA = 90;   // hari: di bawah ini masuk daftar perhatian

/**
 * Status masa berlaku dokumen kerja sama.
 *
 * @return array{status: string, sisa: ?int, label: string}
 *   status: kosong (tanggal tidak diisi/rusak) | kedaluwarsa | segera | berlaku
 */
function mka_wahana_status(?string $sampai, ?string $hariIni = null): array
{
    if ($sampai === null || trim($sampai) === '') {
        return ['status' => 'kosong', 'sisa' => null, 'label' => 'Tanggal MoU belum diisi'];
    }
    try {
        $akhir = new DateTimeImmutable($sampai);
        $kini  = new DateTimeImmutable($hariIni ?? date('Y-m-d'));
    } catch (Exception) {
        return ['status' => 'kosong', 'sisa' => null, 'label' => 'Tanggal MoU tidak terbaca'];
    }
    // Dibandingkan per tanggal, bukan per detik: MoU "sampai 31 Des" masih
    // berlaku sepanjang 31 Des.
    $sisa = (int) $kini->setTime(0, 0)->diff($akhir->setTime(0, 0))->format('%r%a');
    if ($sisa < 0) {
        return ['status' => 'kedaluwarsa', 'sisa' => $sisa,
                'label' => sprintf('Kedaluwarsa %d hari lalu', abs($sisa))];
    }
    if ($sisa <= MKA_WAHANA_SEGERA) {
        return ['status' => 'segera', 'sisa' => $sisa,
                'label' => $sisa === 0 ? 'Berakhir hari ini' : sprintf('Berakhir dalam %d hari', $sisa)];
    }
    return ['status' => 'berlaku', 'sisa' => $sisa, 'label' => sprintf('Berlaku %d hari lagi', $sisa)];
}

/**
 * Wahana yang perlu perhatian: MoU kedaluwarsa atau habis dalam
 * MKA_WAHANA_SEGERA hari, urut paling mendesak dulu. Kosong = tidak ada
 * masalah, blok tidak dirender.
 */
function mka_wahana_perhatian(array $baris, ?string $hariIni = null): array
{
    $out = [];
    foreach ($baris as $b) {
        $s = mka_wahana_status($b['berlaku_sampai'] ?? null, $hariIni);
        if ($s['status'] === 'kedaluwarsa' || $s['status'] === 'segera') {
            $out[] = $b + ['_status' => $s['status'], '_sisa' => $s['sisa'], '_label' => $s['label']];
        }
    }
    usort($out, static fn($a, $b) => ($a['_sisa'] ?? 0) <=> ($b['_sisa'] ?? 0));
    return $out;
}

/** Simpan satu wahana. Kembalian: ['ok'=>bool, 'galat'=>string, 'id'=>int]. */
function mka_simpan_wahana(array $d, int $prodiId, ?int $id = null): array
{
    $tolak = static fn(string $p): array => ['ok' => false, 'galat' => $p, 'id' => 0];

    $nama = trim((string) ($d['nama'] ?? ''));
    $kual = trim((string) ($d['kualifikasi'] ?? ''));
    $vari = trim((string) ($d['variasi_kasus'] ?? ''));
    $dok  = trim((string) ($d['dokumen_kerja_sama'] ?? ''));
    $sampai = trim((string) ($d['berlaku_sampai'] ?? ''));
    $pemb = trim((string) ($d['jumlah_pembimbing'] ?? ''));

    if ($nama === '') {
        return $tolak('Nama wahana wajib diisi.');
    }
    if (mb_strlen($nama) > 160) {
        return $tolak('Nama wahana maksimal 160 karakter.');
    }
    if (mb_strlen($kual) > 120) {
        return $tolak('Kualifikasi maksimal 120 karakter.');
    }
    if (mb_strlen($vari) > 255) {
        return $tolak('Variasi kasus maksimal 255 karakter.');
    }
    if (mb_strlen($dok) > 255) {
        return $tolak('Dokumen kerja sama maksimal 255 karakter.');
    }
    if ($pemb !== '' && (!ctype_digit($pemb) || (int) $pemb > 255)) {
        return $tolak('Jumlah pembimbing harus angka 0–255.');
    }
    if ($sampai !== '') {
        // createFromFormat + banding balik: '2026-02-30' lolos cek format tapi
        // digeser PHP jadi 2 Maret. Yang digeser diam-diam = tanggal MoU salah.
        $u = DateTimeImmutable::createFromFormat('!Y-m-d', $sampai);
        if (!$u || $u->format('Y-m-d') !== $sampai) {
            return $tolak('Tanggal berlaku sampai tidak sah (format YYYY-MM-DD).');
        }
    }
    if ($id !== null) {
        $milik = (int) qv('SELECT prodi_id FROM wahana WHERE id = ?', [$id], 0);
        if ($milik !== $prodiId) {
            return $tolak('Wahana itu bukan milik prodi ini.');
        }
    }
    $kembar = qv('SELECT id FROM wahana WHERE prodi_id = ? AND nama = ? AND id <> ? LIMIT 1',
        [$prodiId, $nama, $id ?? 0]);
    if ($kembar !== null) {
        return $tolak(sprintf('Wahana "%s" sudah terdaftar di prodi ini (id %d).', $nama, (int) $kembar));
    }

    $data = [
        'prodi_id'           => $prodiId,
        'nama'               => $nama,
        'kualifikasi'        => $kual !== '' ? $kual : null,
        'variasi_kasus'      => $vari !== '' ? $vari : null,
        'jumlah_pembimbing'  => $pemb !== '' ? (int) $pemb : null,
        'dokumen_kerja_sama' => $dok !== '' ? $dok : null,
        'berlaku_sampai'     => $sampai !== '' ? $sampai : null,
    ];
    if ($id !== null) {
        ubah('wahana', $data, 'id', $id);
        return ['ok' => true, 'galat' => '', 'id' => $id];
    }
    return ['ok' => true, 'galat' => '', 'id' => sisip('wahana', $data)];
}

/* ══ PENJAGA ════════════════════════════════════════════════════════ */
if (($modul ?? null) !== 'master') {
    return;
}
$aksi = $aksi ?? '';
if ($aksi !== 'wahana' && $aksi !== 'wahana-simpan') {
    return;
}

$prodiId = mka_prodi();
$kembali = BASE_URL . 'index.php?m=master&a=wahana'
    . (empty($GLOBALS['PRODI']) ? '&p=' . $prodiId : '');

if ($aksi === 'wahana-simpan') {
    wajib('ISI');
    csrf_periksa();
    $id = (int) ($_POST['id'] ?? 0);
    $hasil = mka_simpan_wahana($_POST, $prodiId, $id > 0 ? $id : null);
    if (!$hasil['ok']) {
        alihkan($kembali . '&galat=' . urlencode($hasil['galat']) . ($id > 0 ? '&id=' . $id : '') . '#form');
    }
    alihkan($kembali);
}

$kolom = 'id, nama, kualifikasi, variasi_kasus, jumlah_pembimbing, dokumen_kerja_sama, berlaku_sampai';
$baris = q("SELECT $kolom FROM wahana WHERE prodi_id = ? ORDER BY nama", [$prodiId]);
$perhatian = mka_wahana_perhatian($baris);
$kedaluwarsa = count(array_filter($perhatian, static fn($b) => $b['_status'] === 'kedaluwarsa'));

$idEdit = (int) ($_GET['id'] ?? 0);
$edit = $idEdit > 0 ? q1("SELECT $kolom FROM wahana WHERE id = ? AND prodi_id = ?", [$idEdit, $prodiId]) : null;
$v = static fn(string $k, string $b = ''): string => e($edit[$k] ?? $b);

$html = mka_galat();

$html .= kartu('Sarana praktik',
    mka_pemilih_prodi($prodiId)
    . strip_angka([
        ['label' => 'Wahana terdaftar', 'nilai' => count($baris), 'satuan' => 'wahana'],
        ['label' => 'MoU kedaluwarsa', 'nilai' => $kedaluwarsa, 'satuan' => 'wahana'],
        ['label' => 'MoU berakhir ≤' . MKA_WAHANA_SEGERA . ' hari', 'nilai' => count($perhatian) - $kedaluwarsa,
         'satuan' => 'wahana'],
        ['label' => 'Pembimbing lapangan', 'nilai' => array_sum(array_map(
            static fn($b) => (int) ($b['jumlah_pembimbing'] ?? 0), $baris)), 'satuan' => 'orang'],
    ]));

if ($perhatian) {
    $daftar = '<ul class="mka-daftar">';
    foreach ($perhatian as $b) {
        $daftar .= '<li><b>' . e($b['nama']) . '</b> — berlaku sampai <span class="mono">'
            . e(tgl((string) $b['berlaku_sampai'])) . '</span> · ' . e($b['_label'])
            . ($b['dokumen_kerja_sama'] ? ' · ' . e((string) $b['dokumen_kerja_sama']) : '')
            . '</li>';
    }
    $daftar .= '</ul><p class="foot-note">Wahana kedaluwarsa tetap dipakai di tabel R08 '
        . 'sampai diperbarui — menyembunyikannya membuat konsistensi lulus palsu.</p>';

    $html .= callout($kedaluwarsa > 0 ? 'bad' : 'warn',
        count($perhatian) . ' dokumen kerja sama perlu diperbarui', $daftar);
}

$sel = [];
foreach ($baris as $b) {
    $s = mka_wahana_status($b['berlaku_sampai'] ?? null);
    $badge = match ($s['status']) {
        'kedaluwarsa' => '<span class="badge badge-bad">Kedaluwarsa</span>',
        'segera'      => '<span class="badge badge-sebagian">Segera berakhir</span>',
        'berlaku'     => '<span class="badge badge-lengkap">Berlaku</span>',
        default       => '<span class="badge badge-kosong">Tanggal kosong</span>',
    };
    $sel[] = [
        'nama'              => $b['nama'],
        'kualifikasi'       => $b['kualifikasi'],
        'variasi_kasus'     => $b['variasi_kasus'],
        'jumlah_pembimbing' => $b['jumlah_pembimbing'] !== null ? (int) $b['jumlah_pembimbing'] : null,
        'dokumen_kerja_sama'=> $b['dokumen_kerja_sama'],
        'berlaku_sampai'    => ['html' => '<span class="mono">' . e(tgl($b['berlaku_sampai'] ?? null)) . '</span>'],
        'status'            => ['html' => $badge
            . '<span class="mka-kecil">' . e($s['label']) . '</span>',
            'kelas' => $s['status'] === 'kedaluwarsa' ? 'mka-bad' : ''],
        'aksi'              => ['html' => '<a class="btn" href="' . e($kembali) . '&amp;id='
            . (int) $b['id'] . '#form">Ubah</a>'],
    ];
}

$html .= kartu('Daftar wahana',
    tabel_html([
        ['kunci' => 'nama', 'label' => 'Wahana'],
        ['kunci' => 'kualifikasi', 'label' => 'Kualifikasi'],
        ['kunci' => 'variasi_kasus', 'label' => 'Variasi kasus'],
        ['kunci' => 'jumlah_pembimbing', 'label' => 'Pembimbing', 'kelas' => 'r'],
        ['kunci' => 'dokumen_kerja_sama', 'label' => 'Dokumen kerja sama'],
        ['kunci' => 'berlaku_sampai', 'label' => 'Berlaku sampai'],
        ['kunci' => 'status', 'label' => 'Status MoU'],
        ['kunci' => 'aksi', 'label' => ''],
    ], $sel, ['kosong' => 'Belum ada wahana praktik untuk prodi ini.']));

if (boleh('ISI')) {
    $form = '<form class="mka-form" id="form" method="post" action="'
        . e(BASE_URL) . 'index.php?m=master&amp;a=wahana-simpan">'
        . csrf_input()
        . '<input type="hidden" name="id" value="' . (int) ($edit['id'] ?? 0) . '">'
        . '<fieldset>'
        . '<label>Nama wahana<input name="nama" required maxlength="160" value="' . $v('nama') . '"></label>'
        . '<label>Kualifikasi<input name="kualifikasi" maxlength="120" value="' . $v('kualifikasi') . '"></label>'
        . '<label>Variasi kasus<input name="variasi_kasus" maxlength="255" value="' . $v('variasi_kasus') . '"></label>'
        . '<label>Jumlah pembimbing<input type="number" name="jumlah_pembimbing" min="0" max="255" value="'
        . $v('jumlah_pembimbing') . '"></label>'
        . '<label>Dokumen kerja sama<input name="dokumen_kerja_sama" maxlength="255" value="'
        . $v('dokumen_kerja_sama') . '" placeholder="Nomor MoU / nama berkas"></label>'
        . '<label>Berlaku sampai<input type="date" name="berlaku_sampai" value="' . $v('berlaku_sampai')
        . '"><span class="mka-kecil">dipakai R08 — jangan dikosongkan</span></label>'
        . '</fieldset><div class="mka-tombol"><button class="btn btn-primary" type="submit">Simpan wahana</button>'
        . ($edit ? '<a class="btn" href="' . e($kembali) . '">Batal</a>' : '')
        . '</div></form>';

    $html .= kartu($edit ? 'Ubah wahana ' . (string) $edit['nama'] : 'Tambah wahana praktik', $form,
        ['id' => 'form', 'kelas' => 'mka-kartu-form']);
}

render('Sarana & Wahana Praktik', mka_css() . $html, [
    'aktif' => 'sarana',
    'sub'   => 'Wahana praktik, pembimbing, dan masa berlaku kerja sama',
]);
