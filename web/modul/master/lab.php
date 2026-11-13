<?php
declare(strict_types=1);

/**
 * Master akademik — laboratorium & alat.
 *
 * Satu baris = satu pasangan (lab, alat). Lab dengan 6 alat jadi 6 baris; itu
 * yang ditanyakan instrumen ("jumlah unit alat X"), bukan "jumlah lab".
 */

require_once __DIR__ . '/mk.php';

/* ══ INTI ═══════════════════════════════════════════════════════════ */

const MKA_KEPEMILIKAN = ['SD' => 'Milik sendiri (SD)', 'SW' => 'Sewa / pinjam (SW)'];
const MKA_KONDISI = ['Baik', 'Rusak Ringan', 'Rusak Berat'];

/** Ringkasan lab: unit per kondisi dan asal kepemilikan, dihitung dari baris. */
function mka_rekap_lab(array $baris): array
{
    $r = [
        'lab' => [], 'unit' => 0,
        'baik' => 0, 'rusak_ringan' => 0, 'rusak_berat' => 0,
        'SD' => 0, 'SW' => 0, 'logbook' => 0, 'tanpa_kondisi' => 0,
    ];
    foreach ($baris as $b) {
        $r['lab'][(string) $b['nama_lab']] = true;
        $unit = max(0, (int) ($b['jumlah_unit'] ?? 0));
        $r['unit'] += $unit;
        match ((string) ($b['kondisi'] ?? '')) {
            'Baik'         => $r['baik'] += $unit,
            'Rusak Ringan' => $r['rusak_ringan'] += $unit,
            'Rusak Berat'  => $r['rusak_berat'] += $unit,
            default        => $r['tanpa_kondisi'] += $unit,
        };
        if (($b['kepemilikan'] ?? '') === 'SD') {
            $r['SD'] += $unit;
        } elseif (($b['kepemilikan'] ?? '') === 'SW') {
            $r['SW'] += $unit;
        }
        if ((int) ($b['ada_logbook'] ?? 0)) {
            $r['logbook']++;
        }
    }
    $r['jumlah_lab'] = count($r['lab']);
    return $r;
}

/** Simpan satu baris lab. Kembalian: ['ok'=>bool,'galat'=>string,'id'=>int]. */
function mka_simpan_lab(array $d, int $prodiId, ?int $id = null): array
{
    $tolak = static fn(string $p): array => ['ok' => false, 'galat' => $p, 'id' => 0];

    $lab  = trim((string) ($d['nama_lab'] ?? ''));
    $alat = trim((string) ($d['alat'] ?? ''));
    $unit = trim((string) ($d['jumlah_unit'] ?? ''));
    $milik = (string) ($d['kepemilikan'] ?? '');
    $kond  = (string) ($d['kondisi'] ?? '');
    $jam   = trim((string) ($d['jam_pakai_per_minggu'] ?? ''));

    if ($lab === '') {
        return $tolak('Nama laboratorium wajib diisi.');
    }
    if (mb_strlen($lab) > 120) {
        return $tolak('Nama laboratorium maksimal 120 karakter.');
    }
    if ($alat === '') {
        return $tolak('Nama alat wajib diisi.');
    }
    if (mb_strlen($alat) > 160) {
        return $tolak('Nama alat maksimal 160 karakter.');
    }
    if ($unit !== '' && (!ctype_digit($unit) || (int) $unit === 0 || (int) $unit > 65535)) {
        return $tolak('Jumlah unit harus angka 1–65535.');
    }
    if ($milik !== '' && !isset(MKA_KEPEMILIKAN[$milik])) {
        return $tolak('Kepemilikan harus SD atau SW.');
    }
    if ($kond !== '' && !in_array($kond, MKA_KONDISI, true)) {
        return $tolak('Kondisi tidak dikenal.');
    }
    if ($jam !== '' && (!ctype_digit($jam) || (int) $jam > 168)) {
        // 168 jam = 24 x 7. Di atas itu mustahil, bukan "rajin".
        return $tolak('Jam pakai per minggu maksimal 168.');
    }
    if ($id !== null) {
        $milikProdi = (int) qv('SELECT prodi_id FROM laboratorium WHERE id = ?', [$id], 0);
        if ($milikProdi !== $prodiId) {
            return $tolak('Baris laboratorium itu bukan milik prodi ini.');
        }
    }

    $data = [
        'prodi_id'              => $prodiId,
        'nama_lab'              => $lab,
        'alat'                  => $alat,
        'jumlah_unit'           => $unit !== '' ? (int) $unit : null,
        'kepemilikan'           => $milik !== '' ? $milik : null,
        'kondisi'               => $kond !== '' ? $kond : null,
        'ada_logbook'           => empty($d['ada_logbook']) ? 0 : 1,
        'jam_pakai_per_minggu'  => $jam !== '' ? (int) $jam : null,
    ];
    if ($id !== null) {
        ubah('laboratorium', $data, 'id', $id);
        return ['ok' => true, 'galat' => '', 'id' => $id];
    }
    return ['ok' => true, 'galat' => '', 'id' => sisip('laboratorium', $data)];
}

/* ══ PENJAGA ════════════════════════════════════════════════════════ */
if (($modul ?? null) !== 'master') {
    return;
}
$aksi = $aksi ?? '';
if ($aksi !== 'lab' && $aksi !== 'lab-simpan') {
    return;
}

$prodiId = mka_prodi();
$kembali = BASE_URL . 'index.php?m=master&a=lab'
    . (empty($GLOBALS['PRODI']) ? '&p=' . $prodiId : '');

if ($aksi === 'lab-simpan') {
    wajib('ISI');
    csrf_periksa();
    $id = (int) ($_POST['id'] ?? 0);
    $hasil = mka_simpan_lab($_POST, $prodiId, $id > 0 ? $id : null);
    if (!$hasil['ok']) {
        alihkan($kembali . '&galat=' . urlencode($hasil['galat']) . ($id > 0 ? '&id=' . $id : '') . '#form');
    }
    alihkan($kembali);
}

$kolom = 'id, nama_lab, alat, jumlah_unit, kepemilikan, kondisi, ada_logbook, jam_pakai_per_minggu';
$baris = q("SELECT $kolom FROM laboratorium WHERE prodi_id = ? ORDER BY nama_lab, alat", [$prodiId]);
$r = mka_rekap_lab($baris);

$idEdit = (int) ($_GET['id'] ?? 0);
$edit = $idEdit > 0 ? q1("SELECT $kolom FROM laboratorium WHERE id = ? AND prodi_id = ?", [$idEdit, $prodiId]) : null;
$v = static fn(string $k, string $b = ''): string => e($edit[$k] ?? $b);

$html = mka_galat();

$html .= kartu('Laboratorium & alat',
    mka_pemilih_prodi($prodiId)
    . strip_angka([
        ['label' => 'Laboratorium', 'nilai' => $r['jumlah_lab'], 'satuan' => 'lab'],
        ['label' => 'Unit alat', 'nilai' => $r['unit'], 'satuan' => 'unit'],
        ['label' => 'Unit kondisi baik', 'nilai' => $r['baik'], 'satuan' => 'unit'],
        ['label' => 'Unit rusak', 'nilai' => $r['rusak_ringan'] + $r['rusak_berat'], 'satuan' => 'unit',
         'ket' => $r['rusak_ringan'] . ' ringan + ' . $r['rusak_berat'] . ' berat'],
        ['label' => 'Milik sendiri (SD)', 'nilai' => $r['SD'], 'satuan' => 'unit'],
        ['label' => 'Sewa/pinjam (SW)', 'nilai' => $r['SW'], 'satuan' => 'unit'],
    ]),
    ['catatan' => count($baris) . ' baris alat · logbook ada di ' . $r['logbook'] . ' baris']);

if ($r['tanpa_kondisi'] > 0) {
    $html .= callout('warn', $r['tanpa_kondisi'] . ' unit tanpa kondisi tercatat',
        'Instrumen menanyakan jumlah alat kondisi baik; baris tanpa kondisi tidak bisa dihitung.');
}

$sel = [];
foreach ($baris as $b) {
    $kond = (string) ($b['kondisi'] ?? '');
    $badge = match ($kond) {
        'Baik'         => '<span class="badge badge-lengkap">Baik</span>',
        'Rusak Ringan' => '<span class="badge badge-sebagian">Rusak Ringan</span>',
        'Rusak Berat'  => '<span class="badge badge-bad">Rusak Berat</span>',
        default        => '<span class="badge badge-kosong">Belum dicatat</span>',
    };
    $sel[] = [
        'nama_lab'    => $b['nama_lab'],
        'alat'        => $b['alat'],
        'jumlah_unit' => $b['jumlah_unit'] !== null ? (int) $b['jumlah_unit'] : null,
        'kepemilikan' => MKA_KEPEMILIKAN[(string) ($b['kepemilikan'] ?? '')] ?? null,
        'kondisi'     => ['html' => $badge, 'kelas' => $kond === 'Rusak Berat' ? 'mka-bad' : ''],
        'ada_logbook' => ['html' => (int) $b['ada_logbook'] ? 'Ada' : '<span class="badge badge-bad">tidak ada</span>'],
        'jam'         => $b['jam_pakai_per_minggu'] !== null ? (int) $b['jam_pakai_per_minggu'] : null,
        'aksi'        => ['html' => '<a class="btn" href="' . e($kembali) . '&amp;id='
            . (int) $b['id'] . '#form">Ubah</a>'],
    ];
}

$html .= kartu('Daftar alat',
    tabel_html([
        ['kunci' => 'nama_lab', 'label' => 'Laboratorium'],
        ['kunci' => 'alat', 'label' => 'Alat'],
        ['kunci' => 'jumlah_unit', 'label' => 'Unit', 'kelas' => 'r'],
        ['kunci' => 'kepemilikan', 'label' => 'Kepemilikan'],
        ['kunci' => 'kondisi', 'label' => 'Kondisi'],
        ['kunci' => 'ada_logbook', 'label' => 'Logbook'],
        ['kunci' => 'jam', 'label' => 'Jam/minggu', 'kelas' => 'r'],
        ['kunci' => 'aksi', 'label' => ''],
    ], $sel, ['kosong' => 'Belum ada laboratorium untuk prodi ini.']));

if (boleh('ISI')) {
    $pilMilik = '';
    foreach (MKA_KEPEMILIKAN as $k => $label) {
        $pilMilik .= '<option value="' . e($k) . '"'
            . (($edit['kepemilikan'] ?? '') === $k ? ' selected' : '') . '>' . e($label) . '</option>';
    }
    $pilKondisi = '';
    foreach (MKA_KONDISI as $k) {
        $pilKondisi .= '<option value="' . e($k) . '"'
            . (($edit['kondisi'] ?? '') === $k ? ' selected' : '') . '>' . e($k) . '</option>';
    }

    $form = '<form class="mka-form" id="form" method="post" action="'
        . e(BASE_URL) . 'index.php?m=master&amp;a=lab-simpan">'
        . csrf_input()
        . '<input type="hidden" name="id" value="' . (int) ($edit['id'] ?? 0) . '">'
        . '<fieldset>'
        . '<label>Laboratorium<input name="nama_lab" required maxlength="120" value="' . $v('nama_lab') . '"></label>'
        . '<label>Alat<input name="alat" required maxlength="160" value="' . $v('alat') . '"></label>'
        . '<label>Jumlah unit<input type="number" name="jumlah_unit" min="1" max="65535" value="'
        . $v('jumlah_unit') . '"></label>'
        . '<label>Kepemilikan<select name="kepemilikan"><option value="">—</option>' . $pilMilik . '</select></label>'
        . '<label>Kondisi<select name="kondisi"><option value="">—</option>' . $pilKondisi . '</select></label>'
        . '<label>Jam pakai per minggu<input type="number" name="jam_pakai_per_minggu" min="0" max="168" value="'
        . $v('jam_pakai_per_minggu') . '"></label>'
        . '</fieldset><fieldset><div class="mka-cek"><label>'
        . '<input type="checkbox" name="ada_logbook" value="1"' . (!empty($edit['ada_logbook']) ? ' checked' : '')
        . '> Logbook pemakaian tersedia</label></div></fieldset>'
        . '<div class="mka-tombol"><button class="btn btn-primary" type="submit">Simpan alat</button>'
        . ($edit ? '<a class="btn" href="' . e($kembali) . '">Batal</a>' : '')
        . '</div></form>';

    $html .= kartu($edit ? 'Ubah alat ' . (string) $edit['alat'] : 'Tambah alat', $form,
        ['id' => 'form', 'kelas' => 'mka-kartu-form', 'catatan' => 'satu baris = satu alat di satu lab']);
}

render('Sarana & Wahana — Laboratorium', mka_css() . $html, [
    'aktif' => 'sarana',
    'sub'   => 'Laboratorium, alat, jumlah unit, kondisi, dan logbook',
]);
