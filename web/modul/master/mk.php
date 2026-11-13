<?php
declare(strict_types=1);

/**
 * Master akademik — INTI bersama + mata kuliah.
 *
 * Dimuat index.php master untuk a=mk / a=mk-simpan. Fungsi berawalan mka_ di
 * bagian INTI juga dipakai wahana.php, lab.php, pustaka.php (require_once di
 * kepala berkas itu) dan oleh web/uji/uji_master_akademik.php — ganti nama
 * hanya kalau keempat berkas itu ikut disunting.
 *
 * Uji memuat berkas ini sebagai pustaka: penjaga di bawah membuat eksekusi
 * berhenti sebelum ada HTML yang tercetak, jadi fungsi tetap bisa dipanggil
 * tanpa server HTTP.
 */

require_once __DIR__ . '/../../system/layout.php';
require_once __DIR__ . '/../../tampilan/kerangka/komponen.php';

/* ══ INTI ═══════════════════════════════════════════════════════════ */

/**
 * Prodi yang sedang dikerjakan. Operator/Verifikator terkunci ke prodinya
 * lewat $PRODI dari front controller; Admin (prodi_id NULL) memilih dengan ?p=.
 */
function mka_prodi(): int
{
    $terkunci = (int) ($GLOBALS['PRODI'] ?? 0);
    if ($terkunci > 0) {
        return $terkunci;
    }
    $p = (int) ($_GET['p'] ?? 0);
    if ($p > 0 && q1('SELECT id FROM prodi WHERE id = ?', [$p])) {
        return $p;
    }
    return (int) qv('SELECT id FROM prodi ORDER BY id LIMIT 1', [], 0);
}

/** Pemilih prodi, hanya muncul untuk peran yang tidak terikat satu prodi. */
function mka_pemilih_prodi(int $terpilih): string
{
    if ((int) ($GLOBALS['PRODI'] ?? 0) > 0) {
        return '';
    }
    $opsi = '';
    foreach (q('SELECT id, nama_ps FROM prodi WHERE aktif = 1 ORDER BY id') as $p) {
        $opsi .= sprintf('<option value="%d"%s>%s</option>', (int) $p['id'],
            (int) $p['id'] === $terpilih ? ' selected' : '', e($p['nama_ps']));
    }
    return '<form class="mka-pilih" method="get" action="' . e(BASE_URL) . 'index.php">'
        . '<input type="hidden" name="m" value="master">'
        . '<input type="hidden" name="a" value="' . e((string) ($_GET['a'] ?? 'mk')) . '">'
        . '<label>Prodi <select name="p" onchange="this.form.submit()">' . $opsi . '</select></label>'
        . '<noscript><button class="btn" type="submit">Ganti</button></noscript></form>';
}

/** Dua berkas CSS: kerangka (komponen bersama) + milik modul ini. */
function mka_css(): string
{
    return gaya_kerangka()
        . '<link rel="stylesheet" href="' . e(BASE_URL) . 'aset/css/master-akademik.css?v=1">';
}

/** Pesan galat yang dibawa lewat redirect ?galat= (di-escape saat tampil). */
function mka_galat(): string
{
    $g = masuk_input('galat');
    return $g === '' ? '' : callout('bad', 'Perubahan tidak disimpan', e($g));
}

/** Nama tampilan tahap — satu tempat, dipakai hitungan dan tabel. */
function mka_tahap_label(string $tahap): string
{
    return $tahap === 'Profesi' ? 'Profesi' : 'Akademik';
}

/* ══ MATA KULIAH ════════════════════════════════════════════════════ */

/** Empat aspek penilaian. MK tanpa satu pun tidak bisa dinilai. */
const MKA_ASPEK = [
    'aspek_sikap'       => 'Sikap',
    'aspek_pengetahuan' => 'Pengetahuan',
    'aspek_ket_umum'    => 'Keterampilan umum',
    'aspek_ket_khusus'  => 'Keterampilan khusus',
];

/**
 * Total SKS per tahap, DIHITUNG dari baris MK — tidak pernah disimpan atau
 * diketik. Satu-satunya sumber: kolom sks_kuliah + sks_praktik tiap baris.
 *
 * @return array{Akademik: array, Profesi: array}
 */
function mka_total_sks(array $baris): array
{
    $out = [
        'Akademik' => ['mk' => 0, 'kuliah' => 0, 'praktik' => 0, 'total' => 0],
        'Profesi'  => ['mk' => 0, 'kuliah' => 0, 'praktik' => 0, 'total' => 0],
    ];
    foreach ($baris as $b) {
        $t = mka_tahap_label((string) ($b['tahap'] ?? ''));
        $k = max(0, (int) ($b['sks_kuliah'] ?? 0));
        $p = max(0, (int) ($b['sks_praktik'] ?? 0));
        $out[$t]['mk']++;
        $out[$t]['kuliah'] += $k;
        $out[$t]['praktik'] += $p;
        $out[$t]['total'] += $k + $p;
    }
    return $out;
}

/**
 * Simpan satu MK. Kembalian: ['ok'=>bool, 'galat'=>string, 'id'=>int].
 *
 * Kode ganda dalam SATU prodi ditolak (UNIQUE (prodi_id, kode_mk)); kode yang
 * sama di prodi lain boleh — kurikulum tiap prodi berdiri sendiri.
 */
function mka_simpan_mk(array $d, int $prodiId, ?int $id = null): array
{
    $tolak = static fn(string $pesan): array => ['ok' => false, 'galat' => $pesan, 'id' => 0];

    $kode = strtoupper(trim((string) ($d['kode_mk'] ?? '')));
    $nama = trim((string) ($d['nama_mk'] ?? ''));
    $kul  = (int) ($d['sks_kuliah'] ?? 0);
    $pra  = (int) ($d['sks_praktik'] ?? 0);
    $lok  = trim((string) ($d['lokasi_praktik'] ?? ''));

    if ($kode === '') {
        return $tolak('Kode MK wajib diisi.');
    }
    if (mb_strlen($kode) > 32) {
        return $tolak('Kode MK maksimal 32 karakter.');
    }
    if ($nama === '') {
        return $tolak('Nama MK wajib diisi.');
    }
    if (mb_strlen($nama) > 160) {
        return $tolak('Nama MK maksimal 160 karakter.');
    }
    if ($kul < 0 || $pra < 0 || $kul > 20 || $pra > 20) {
        return $tolak('SKS kuliah/praktik harus 0–20.');
    }
    if ($kul + $pra === 0) {
        return $tolak('SKS kuliah + praktik tidak boleh 0.');
    }
    if (mb_strlen($lok) > 160) {
        return $tolak('Lokasi praktik maksimal 160 karakter.');
    }
    if ($id !== null) {
        $milik = (int) qv('SELECT prodi_id FROM mata_kuliah WHERE id = ?', [$id], 0);
        if ($milik !== $prodiId) {
            return $tolak('MK itu bukan milik prodi ini.');
        }
    }
    $bentrok = qv('SELECT id FROM mata_kuliah WHERE prodi_id = ? AND kode_mk = ? AND id <> ? LIMIT 1',
        [$prodiId, $kode, $id ?? 0]);
    if ($bentrok !== null) {
        return $tolak(sprintf('Kode MK %s sudah dipakai di prodi ini (MK #%d).', $kode, (int) $bentrok));
    }

    $data = [
        'prodi_id'           => $prodiId,
        'kode_mk'            => $kode,
        'nama_mk'            => $nama,
        'sks_kuliah'         => $kul,
        'sks_praktik'        => $pra,
        'cpl'                => trim((string) ($d['cpl'] ?? '')) !== '' ? trim((string) $d['cpl']) : null,
        'aspek_sikap'        => empty($d['aspek_sikap']) ? 0 : 1,
        'aspek_pengetahuan'  => empty($d['aspek_pengetahuan']) ? 0 : 1,
        'aspek_ket_umum'     => empty($d['aspek_ket_umum']) ? 0 : 1,
        'aspek_ket_khusus'   => empty($d['aspek_ket_khusus']) ? 0 : 1,
        'ada_rps'            => empty($d['ada_rps']) ? 0 : 1,
        'lokasi_praktik'     => $lok !== '' ? $lok : null,
        'tahap'              => mka_tahap_label((string) ($d['tahap'] ?? '')),
    ];

    if ($id !== null) {
        ubah('mata_kuliah', $data, 'id', $id);
        return ['ok' => true, 'galat' => '', 'id' => $id];
    }
    return ['ok' => true, 'galat' => '', 'id' => sisip('mata_kuliah', $data)];
}

/* ══ PENJAGA: hanya jalan kalau dipanggil router m=master ═══════════ */
if (($modul ?? null) !== 'master') {
    return;
}
$aksi = $aksi ?? '';
if ($aksi !== 'mk' && $aksi !== 'mk-simpan') {
    return;
}

$prodiId = mka_prodi();
$kembali = BASE_URL . 'index.php?m=master&a=mk'
    . (empty($GLOBALS['PRODI']) ? '&p=' . $prodiId : '');

/* ══ AKSI TULIS ═════════════════════════════════════════════════════ */
if ($aksi === 'mk-simpan') {
    wajib('ISI');
    csrf_periksa();
    $id = (int) ($_POST['id'] ?? 0);
    $hasil = mka_simpan_mk($_POST, $prodiId, $id > 0 ? $id : null);
    if (!$hasil['ok']) {
        alihkan($kembali . '&galat=' . urlencode($hasil['galat'])
            . ($id > 0 ? '&id=' . $id : '') . '#form');
    }
    alihkan($kembali);
}

/* ══ TAMPILAN ═══════════════════════════════════════════════════════ */
$kolom = 'id, kode_mk, nama_mk, sks_kuliah, sks_praktik, cpl, aspek_sikap,
          aspek_pengetahuan, aspek_ket_umum, aspek_ket_khusus, ada_rps,
          lokasi_praktik, tahap';
$baris = q("SELECT $kolom FROM mata_kuliah WHERE prodi_id = ? ORDER BY tahap, kode_mk", [$prodiId]);
$total = mka_total_sks($baris);

$idEdit = (int) ($_GET['id'] ?? 0);
$edit = $idEdit > 0 ? q1("SELECT $kolom FROM mata_kuliah WHERE id = ? AND prodi_id = ?", [$idEdit, $prodiId]) : null;
$v = static fn(string $k, string $b = ''): string => e($edit[$k] ?? $b);
$cek = static fn(string $k): string => !empty($edit[$k]) ? ' checked' : '';

$html = mka_galat();

$html .= kartu('Rekap SKS',
    mka_pemilih_prodi($prodiId)
    . strip_angka([
        ['label' => 'MK tahap akademik', 'nilai' => $total['Akademik']['mk'], 'satuan' => 'MK'],
        ['label' => 'SKS tahap akademik', 'nilai' => $total['Akademik']['total'], 'satuan' => 'SKS',
         'ket' => $total['Akademik']['kuliah'] . ' kuliah + ' . $total['Akademik']['praktik'] . ' praktik'],
        ['label' => 'MK tahap profesi', 'nilai' => $total['Profesi']['mk'], 'satuan' => 'MK'],
        ['label' => 'SKS tahap profesi', 'nilai' => $total['Profesi']['total'], 'satuan' => 'SKS',
         'ket' => $total['Profesi']['kuliah'] . ' kuliah + ' . $total['Profesi']['praktik'] . ' praktik'],
        ['label' => 'Total SKS kurikulum', 'nilai' => $total['Akademik']['total'] + $total['Profesi']['total'],
         'satuan' => 'SKS'],
    ]),
    ['catatan' => 'dihitung dari ' . count($baris) . ' baris MK, bukan diketik']);

$tanpaAspek = 0;
foreach ($baris as $b) {
    if (!(int) $b['aspek_sikap'] && !(int) $b['aspek_pengetahuan']
        && !(int) $b['aspek_ket_umum'] && !(int) $b['aspek_ket_khusus']) {
        $tanpaAspek++;
    }
}
if ($tanpaAspek > 0) {
    $html .= callout('warn', $tanpaAspek . ' MK tanpa aspek penilaian',
        'Setiap MK dinilai pada minimal satu dari empat aspek (sikap, pengetahuan, '
        . 'keterampilan umum, keterampilan khusus).');
}

$selMk = [];
foreach ($baris as $b) {
    $aspek = '';
    foreach (MKA_ASPEK as $k => $label) {
        if ((int) $b[$k]) {
            $aspek .= '<span class="badge">' . e($label) . '</span> ';
        }
    }
    $selMk[] = [
        'kode_mk'    => ['html' => '<span class="mono">' . e($b['kode_mk']) . '</span>'],
        'nama_mk'    => $b['nama_mk'],
        'sks_kuliah' => (int) $b['sks_kuliah'],
        'sks_praktik'=> (int) $b['sks_praktik'],
        'tahap'      => mka_tahap_label((string) $b['tahap']),
        'aspek'      => ['html' => $aspek !== '' ? $aspek : '<span class="badge badge-bad">belum diisi</span>'],
        'ada_rps'    => ['html' => (int) $b['ada_rps'] ? 'Ada' : '<span class="badge badge-bad">tidak ada</span>'],
        'lokasi_praktik' => $b['lokasi_praktik'],
        'aksi'       => ['html' => '<a class="btn" href="' . e($kembali) . '&amp;id=' . (int) $b['id'] . '#form">Ubah</a>'],
    ];
}

$html .= kartu('Daftar mata kuliah',
    tabel_html([
        ['kunci' => 'kode_mk', 'label' => 'Kode'],
        ['kunci' => 'nama_mk', 'label' => 'Nama'],
        ['kunci' => 'sks_kuliah', 'label' => 'SKS K', 'kelas' => 'r'],
        ['kunci' => 'sks_praktik', 'label' => 'SKS P', 'kelas' => 'r'],
        ['kunci' => 'tahap', 'label' => 'Tahap'],
        ['kunci' => 'aspek', 'label' => 'Aspek penilaian'],
        ['kunci' => 'ada_rps', 'label' => 'RPS'],
        ['kunci' => 'lokasi_praktik', 'label' => 'Lokasi praktik'],
        ['kunci' => 'aksi', 'label' => ''],
    ], $selMk, ['kosong' => 'Belum ada mata kuliah untuk prodi ini.']),
    ['catatan' => count($baris) . ' MK']);

if (boleh('ISI')) {
    $form = '<form class="mka-form" id="form" method="post" action="'
        . e(BASE_URL) . 'index.php?m=master&amp;a=mk-simpan">'
        . csrf_input()
        . '<input type="hidden" name="id" value="' . (int) ($edit['id'] ?? 0) . '">'
        . '<fieldset>'
        . '<label>Kode MK<input name="kode_mk" required maxlength="32" value="' . $v('kode_mk') . '"></label>'
        . '<label>Nama MK<input name="nama_mk" required maxlength="160" value="' . $v('nama_mk') . '"></label>'
        . '<label>SKS kuliah<input type="number" name="sks_kuliah" min="0" max="20" value="' . $v('sks_kuliah', '0') . '"></label>'
        . '<label>SKS praktik<input type="number" name="sks_praktik" min="0" max="20" value="' . $v('sks_praktik', '0') . '"></label>'
        . '<label>Tahap<select name="tahap">'
        . '<option value="Akademik"' . (($edit['tahap'] ?? '') !== 'Profesi' ? ' selected' : '') . '>Akademik</option>'
        . '<option value="Profesi"' . (($edit['tahap'] ?? '') === 'Profesi' ? ' selected' : '') . '>Profesi</option>'
        . '</select></label>'
        . '<label>Lokasi praktik<input name="lokasi_praktik" maxlength="160" value="' . $v('lokasi_praktik') . '"></label>'
        . '</fieldset>'
        . '<fieldset><legend>Aspek penilaian</legend><div class="mka-cek">';
    foreach (MKA_ASPEK as $k => $label) {
        $form .= '<label><input type="checkbox" name="' . e($k) . '" value="1"' . $cek($k) . '> ' . e($label) . '</label>';
    }
    $form .= '<label><input type="checkbox" name="ada_rps" value="1"' . $cek('ada_rps') . '> RPS tersedia</label>'
        . '</div></fieldset>'
        . '<fieldset><label>CPL yang dibebankan (satu per baris)'
        . '<textarea name="cpl" rows="4">' . $v('cpl') . '</textarea></label></fieldset>'
        . '<div class="mka-tombol"><button class="btn btn-primary" type="submit">Simpan MK</button>'
        . ($edit ? '<a class="btn" href="' . e($kembali) . '">Batal</a>' : '')
        . '</div></form>';

    $html .= kartu($edit ? 'Ubah MK ' . (string) $edit['kode_mk'] : 'Tambah mata kuliah', $form,
        ['id' => 'form', 'kelas' => 'mka-kartu-form', 'catatan' => 'total SKS tidak diisi di sini — dihitung dari baris']);
}

render('Kurikulum & Mata Kuliah', mka_css() . $html, [
    'aktif' => 'kurikulum',
    'sub'   => 'Mata kuliah, SKS, CPL, dan aspek penilaian prodi',
]);
