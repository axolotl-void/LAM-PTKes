<?php
declare(strict_types=1);

/**
 * Master akademik — pustaka & jurnal.
 *
 * Dua angka yang sering tertukar di instrumen: jumlah JUDUL (berapa judul
 * koleksi) dan jumlah COPY (berapa eksemplar fisik). Keduanya dijumlah dari
 * baris, dipisah jenis — satu total gabungan tidak bisa dipakai menjawab
 * pertanyaan mana pun.
 */

require_once __DIR__ . '/mk.php';

/* ══ INTI ═══════════════════════════════════════════════════════════ */

const MKA_JENIS_PUSTAKA = ['Buku', 'Jurnal', 'E-book', 'Prosiding'];

/**
 * Total judul & copy, keseluruhan dan per jenis. Dihitung dari baris.
 *
 * @return array{judul:int, copy:int, baris:int, per_jenis:array<string,array>}
 */
function mka_rekap_pustaka(array $baris): array
{
    $per = [];
    foreach (MKA_JENIS_PUSTAKA as $j) {
        $per[$j] = ['judul' => 0, 'copy' => 0, 'baris' => 0];
    }
    $judul = 0;
    $copy  = 0;
    foreach ($baris as $b) {
        $j = (string) ($b['jenis'] ?? '');
        $n = max(0, (int) ($b['jumlah_judul'] ?? 0));
        $c = max(0, (int) ($b['jumlah_copy'] ?? 0));
        $judul += $n;
        $copy  += $c;
        if (isset($per[$j])) {
            $per[$j]['judul'] += $n;
            $per[$j]['copy']  += $c;
            $per[$j]['baris']++;
        }
    }
    return ['judul' => $judul, 'copy' => $copy, 'baris' => count($baris), 'per_jenis' => $per];
}

/** Simpan satu baris pustaka. Kembalian: ['ok'=>bool,'galat'=>string,'id'=>int]. */
function mka_simpan_pustaka(array $d, int $prodiId, ?int $id = null): array
{
    $tolak = static fn(string $p): array => ['ok' => false, 'galat' => $p, 'id' => 0];

    $jenis = (string) ($d['jenis'] ?? '');
    $judul = trim((string) ($d['judul'] ?? ''));
    $nJ    = trim((string) ($d['jumlah_judul'] ?? ''));
    $nC    = trim((string) ($d['jumlah_copy'] ?? ''));
    $tahun = trim((string) ($d['tahun'] ?? ''));
    $nomor = trim((string) ($d['nomor'] ?? ''));

    if (!in_array($jenis, MKA_JENIS_PUSTAKA, true)) {
        return $tolak('Jenis harus salah satu: ' . implode(', ', MKA_JENIS_PUSTAKA) . '.');
    }
    if ($judul === '') {
        return $tolak('Judul wajib diisi.');
    }
    if (mb_strlen($judul) > 255) {
        return $tolak('Judul maksimal 255 karakter.');
    }
    if ($nJ !== '' && (!ctype_digit($nJ) || (int) $nJ > 65535)) {
        return $tolak('Jumlah judul harus angka 0–65535.');
    }
    if ($nC !== '' && (!ctype_digit($nC) || (int) $nC > 65535)) {
        return $tolak('Jumlah copy harus angka 0–65535.');
    }
    // Copy tanpa judul = baris yang tidak bisa dipakai menjawab apa pun.
    if ((int) $nC > 0 && (int) $nJ === 0) {
        return $tolak('Jumlah copy terisi tapi jumlah judul 0.');
    }
    if ($tahun !== '') {
        $kini = (int) date('Y');
        if (!ctype_digit($tahun) || (int) $tahun < 1900 || (int) $tahun > $kini + 1) {
            return $tolak(sprintf('Tahun harus 1900–%d.', $kini + 1));
        }
    }
    if (mb_strlen($nomor) > 60) {
        return $tolak('Nomor maksimal 60 karakter.');
    }
    if ($id !== null) {
        $milik = (int) qv('SELECT prodi_id FROM pustaka WHERE id = ?', [$id], 0);
        if ($milik !== $prodiId) {
            return $tolak('Baris pustaka itu bukan milik prodi ini.');
        }
    }

    $data = [
        'prodi_id'     => $prodiId,
        'jenis'        => $jenis,
        'judul'        => $judul,
        'jumlah_judul' => (int) $nJ,
        'jumlah_copy'  => (int) $nC,
        'tahun'        => $tahun !== '' ? (int) $tahun : null,
        'nomor'        => $nomor !== '' ? $nomor : null,
    ];
    if ($id !== null) {
        ubah('pustaka', $data, 'id', $id);
        return ['ok' => true, 'galat' => '', 'id' => $id];
    }
    return ['ok' => true, 'galat' => '', 'id' => sisip('pustaka', $data)];
}

/* ══ PENJAGA ════════════════════════════════════════════════════════ */
if (($modul ?? null) !== 'master') {
    return;
}
$aksi = $aksi ?? '';
if ($aksi !== 'pustaka' && $aksi !== 'pustaka-simpan') {
    return;
}

$prodiId = mka_prodi();
$kembali = BASE_URL . 'index.php?m=master&a=pustaka'
    . (empty($GLOBALS['PRODI']) ? '&p=' . $prodiId : '');

if ($aksi === 'pustaka-simpan') {
    wajib('ISI');
    csrf_periksa();
    $id = (int) ($_POST['id'] ?? 0);
    $hasil = mka_simpan_pustaka($_POST, $prodiId, $id > 0 ? $id : null);
    if (!$hasil['ok']) {
        alihkan($kembali . '&galat=' . urlencode($hasil['galat']) . ($id > 0 ? '&id=' . $id : '') . '#form');
    }
    alihkan($kembali);
}

$kolom = 'id, jenis, judul, jumlah_judul, jumlah_copy, tahun, nomor';
$baris = q("SELECT $kolom FROM pustaka WHERE prodi_id = ? ORDER BY jenis, judul", [$prodiId]);
$r = mka_rekap_pustaka($baris);

$idEdit = (int) ($_GET['id'] ?? 0);
$edit = $idEdit > 0 ? q1("SELECT $kolom FROM pustaka WHERE id = ? AND prodi_id = ?", [$idEdit, $prodiId]) : null;
$v = static fn(string $k, string $b = ''): string => e($edit[$k] ?? $b);

$html = mka_galat();

$html .= kartu('Koleksi pustaka',
    mka_pemilih_prodi($prodiId)
    . strip_angka([
        ['label' => 'Judul koleksi', 'nilai' => $r['judul'], 'satuan' => 'judul'],
        ['label' => 'Copy fisik', 'nilai' => $r['copy'], 'satuan' => 'copy'],
        ['label' => 'Baris terisi', 'nilai' => $r['baris'], 'satuan' => 'baris'],
    ]),
    ['catatan' => 'judul & copy dijumlah dari baris, bukan diketik']);

$selJenis = [];
foreach ($r['per_jenis'] as $jenis => $n) {
    $selJenis[] = [
        'jenis' => $jenis,
        'baris' => $n['baris'],
        'judul' => $n['judul'],
        'copy'  => $n['copy'],
    ];
}

$html .= kartu('Per jenis koleksi',
    tabel_html([
        ['kunci' => 'jenis', 'label' => 'Jenis'],
        ['kunci' => 'baris', 'label' => 'Baris', 'kelas' => 'r'],
        ['kunci' => 'judul', 'label' => 'Judul', 'kelas' => 'r'],
        ['kunci' => 'copy', 'label' => 'Copy', 'kelas' => 'r'],
    ], $selJenis, ['kosong' => 'Belum ada koleksi tercatat.'])
    . '<p class="foot-note">Jumlah seluruh jenis: <b class="num">' . $r['judul'] . '</b> judul, '
    . '<b class="num">' . $r['copy'] . '</b> copy dari <b class="num">' . $r['baris'] . '</b> baris.</p>');

$sel = [];
foreach ($baris as $b) {
    $sel[] = [
        'jenis'        => $b['jenis'],
        'judul'        => $b['judul'],
        'jumlah_judul' => (int) $b['jumlah_judul'],
        'jumlah_copy'  => (int) $b['jumlah_copy'],
        'tahun'        => $b['tahun'] !== null ? (int) $b['tahun'] : null,
        'nomor'        => ['html' => '<span class="mono">' . e($b['nomor'] ?? '—') . '</span>'],
        'aksi'         => ['html' => '<a class="btn" href="' . e($kembali) . '&amp;id='
            . (int) $b['id'] . '#form">Ubah</a>'],
    ];
}

$html .= kartu('Daftar pustaka',
    tabel_html([
        ['kunci' => 'jenis', 'label' => 'Jenis'],
        ['kunci' => 'judul', 'label' => 'Judul'],
        ['kunci' => 'jumlah_judul', 'label' => 'Judul', 'kelas' => 'r'],
        ['kunci' => 'jumlah_copy', 'label' => 'Copy', 'kelas' => 'r'],
        ['kunci' => 'tahun', 'label' => 'Tahun', 'kelas' => 'r'],
        ['kunci' => 'nomor', 'label' => 'Nomor'],
        ['kunci' => 'aksi', 'label' => ''],
    ], $sel, ['kosong' => 'Belum ada pustaka untuk prodi ini.']));

if (boleh('ISI')) {
    $pilJenis = '';
    foreach (MKA_JENIS_PUSTAKA as $j) {
        $pilJenis .= '<option value="' . e($j) . '"'
            . (($edit['jenis'] ?? 'Buku') === $j ? ' selected' : '') . '>' . e($j) . '</option>';
    }

    $form = '<form class="mka-form" id="form" method="post" action="'
        . e(BASE_URL) . 'index.php?m=master&amp;a=pustaka-simpan">'
        . csrf_input()
        . '<input type="hidden" name="id" value="' . (int) ($edit['id'] ?? 0) . '">'
        . '<fieldset>'
        . '<label>Jenis<select name="jenis">' . $pilJenis . '</select></label>'
        . '<label>Judul<input name="judul" required maxlength="255" value="' . $v('judul') . '"></label>'
        . '<label>Jumlah judul<input type="number" name="jumlah_judul" min="0" max="65535" value="'
        . $v('jumlah_judul', '0') . '"></label>'
        . '<label>Jumlah copy<input type="number" name="jumlah_copy" min="0" max="65535" value="'
        . $v('jumlah_copy', '0') . '"></label>'
        . '<label>Tahun<input type="number" name="tahun" min="1900" max="' . ((int) date('Y') + 1)
        . '" value="' . $v('tahun') . '"></label>'
        . '<label>Nomor<input name="nomor" maxlength="60" value="' . $v('nomor')
        . '" placeholder="ISBN / ISSN / DOI"></label>'
        . '</fieldset><div class="mka-tombol"><button class="btn btn-primary" type="submit">Simpan pustaka</button>'
        . ($edit ? '<a class="btn" href="' . e($kembali) . '">Batal</a>' : '')
        . '</div></form>';

    $html .= kartu($edit ? 'Ubah pustaka' : 'Tambah pustaka', $form, ['id' => 'form', 'kelas' => 'mka-kartu-form']);
}

render('Pustaka & Jurnal', mka_css() . $html, [
    'aktif' => 'pustaka',
    'sub'   => 'Koleksi buku, jurnal, e-book, dan prosiding prodi',
]);
