<?php
declare(strict_types=1);

/**
 * Modul: Data Dukung — 38 tabel, 243 field.
 * Pola mengikuti modul/masuk/index.php: gerbang izin dulu, data lewat helper,
 * semua keluaran lewat e().
 *
 * Logika tulis ada di proses.php supaya bisa diuji tanpa HTTP.
 */

require_once __DIR__ . '/../../system/layout.php';
require_once dirname(__DIR__, 2) . '/tampilan/kerangka/komponen.php';
require_once __DIR__ . '/proses.php';

/** CSS modul. layout.php dibekukan dan hanya memuat app.css. */
function gaya_tabel(): string
{
    return '<link rel="stylesheet" href="' . e(BASE_URL) . 'aset/css/kerangka.css?v=1">'
         . '<link rel="stylesheet" href="' . e(BASE_URL) . 'aset/css/tabel.css?v=1">';
}

$aksi = preg_replace('/[^a-z]/', '', $_GET['a'] ?? 'index');

// Akun tanpa prodi (ADMIN/PIMPINAN) memilih prodi yang dibuka. Pilihannya
// disimpan di sesi, dan prodi_aktif() sudah membacanya — jadi modul lain ikut.
if ($aksi === 'prodi') {
    sesi_mulai();
    $pilih = (int) ($_GET['p'] ?? 0);
    // Hanya akun tanpa prodi sendiri yang boleh memilih; pengguna ber-prodi
    // selalu dibaca dari akunnya oleh prodi_aktif().
    if ($pilih > 0 && empty(pengguna()['prodi_id'])
        && qv('SELECT 1 FROM prodi WHERE id = ?', [$pilih]) !== null) {
        $_SESSION['prodi_id'] = $pilih;
    }
    alihkan(BASE_URL . 'index.php?m=tabel');
}

$PRODI = prodi_aktif();
$per = $PRODI ? periode_aktif($PRODI) : null;

if (!$PRODI || !$per) {
    $daftarProdi = q('SELECT id, nama_ps FROM prodi ORDER BY nama_ps');
    if ($daftarProdi && pengguna() && empty(pengguna()['prodi_id'])) {
        $tombol = '<div class="tf-pilih-prodi">';
        foreach ($daftarProdi as $p) {
            $tombol .= '<a class="btn" href="' . e(BASE_URL) . 'index.php?m=tabel&amp;a=prodi&amp;p='
                . e((string) $p['id']) . '">' . e($p['nama_ps']) . '</a>';
        }
        $tombol .= '</div>';
        render('Data Dukung', gaya_tabel()
            . callout('info', 'Pilih prodi', 'Akun Anda tidak terikat ke satu prodi. Pilih prodi yang akan dibuka.')
            . kartu('Prodi', $tombol));
        return;
    }
    render('Data Dukung', gaya_tabel() . halaman_galat(409,
        'Periode akreditasi belum ditetapkan. Minta Admin UPPS mengisi <b>Periode Akreditasi</b> dulu.'));
    return;
}
$PERIODE = (int) $per['id'];
// admin/pimpinan tidak punya prodi di akunnya, jadi nama prodi diambil dari
// prodi yang sedang dibuka, bukan dari sesi pengguna.
$PRODI_NAMA = $PRODI_NAMA ?? qv('SELECT nama_ps FROM prodi WHERE id = ?', [$PRODI], '—');
$tabelKode = (string) ($_GET['t'] ?? $_POST['t'] ?? '');

/* ── simpan ─────────────────────────────────────────────────────── */
if ($aksi === 'simpan') {
    wajib('ISI');
    csrf_periksa();
    $tabelKode = preg_replace('/[^0-9]/', '', $tabelKode);
    try {
        $h = tabel_simpan($tabelKode, $PRODI, $PERIODE, $_POST);
        // Kembali ke halaman baris yang tadi dibuka, bukan selalu halaman 1.
        $hal = max(1, (int) ($_POST['hal'] ?? 1));
        alihkan(BASE_URL . 'index.php?m=tabel&a=lihat&t=' . urlencode($tabelKode)
            . '&hal=' . $hal
            . '&ok=' . rawurlencode(sprintf('%d baris disimpan, %d sel terisi, %d sel dikosongkan.',
                $h['baris'], $h['sel'], $h['dibuang'])));
    } catch (TabelDitolak $x) {
        alihkan(BASE_URL . 'index.php?m=tabel&a=lihat&t=' . urlencode($tabelKode)
            . '&galat=' . rawurlencode($x->getMessage()));
    }
}

/* ── kunci / buka ───────────────────────────────────────────────── */
if ($aksi === 'kunci' || $aksi === 'buka') {
    wajib($aksi === 'kunci' ? 'VERIFIKASI' : 'BUKA_KUNCI');
    csrf_periksa();
    $tabelKode = preg_replace('/[^0-9]/', '', $tabelKode);
    try {
        if ($aksi === 'kunci') {
            $n = tabel_kunci($tabelKode, $PRODI, $PERIODE, (int) ($U['id'] ?? 0));
            $pesan = $n > 0 ? 'Tabel dikunci.' : 'Tidak ada baris untuk dikunci.';
        } else {
            $n = tabel_buka($tabelKode, $PRODI, $PERIODE);
            $pesan = $n > 0 ? 'Kunci dibuka. Operator bisa mengisi lagi.' : 'Tabel tidak terkunci.';
        }
        alihkan(BASE_URL . 'index.php?m=tabel&a=lihat&t=' . urlencode($tabelKode)
            . '&ok=' . rawurlencode($pesan));
    } catch (TabelDitolak $x) {
        alihkan(BASE_URL . 'index.php?m=tabel&a=lihat&t=' . urlencode($tabelKode)
            . '&galat=' . rawurlencode($x->getMessage()));
    }
}

/* ── form satu tabel ────────────────────────────────────────────── */
if ($aksi === 'lihat') {
    $tabelKode = preg_replace('/[^0-9]/', '', $tabelKode);
    try {
        $info = tabel_info($tabelKode);
    } catch (TabelDitolak $x) {
        render('Tabel tidak ditemukan', gaya_tabel() . halaman_galat(404,
            'Kode tabel <b>' . e($tabelKode) . '</b> tidak ada di instrumen.'));
        return;
    }

    $status = status_satu_tabel($tabelKode, $PRODI, $PERIODE);
    $field = tabel_field($tabelKode);
    $input = array_values(array_filter($field, fn($f) => $f['asal'] === 'Input'));
    $lain = array_values(array_filter($field, fn($f) => $f['asal'] !== 'Input'));
    $baris = tabel_baris($tabelKode, $PRODI, $PERIODE);
    $terkunci = $status['dikunci'];
    $bisaIsi = boleh('ISI') && !$terkunci && $status['berlaku'];
    $drafKosong = !$baris;

    // Satu baris kosong supaya operator tidak perlu klik "tambah" untuk tabel
    // satu-baris, tapi tetap bisa menambah lebih banyak.
    if ($drafKosong) {
        $baris = [['id' => 0, 'urut' => 1, 'dikunci' => false, 'nilai' => [], 'pengunci' => null, 'dikunci_pada' => null]];
    }

    // Tabel 1 punya 239 baris. Merender semuanya sekaligus menembus
    // max_input_vars PHP (default 1000) dan browser membuang field terakhir
    // tanpa pesan — isian hilang diam-diam. Halaman dibatasi per TABEL_PER_HAL.
    $totalBaris = count($baris);
    $halMax = max(1, (int) ceil($totalBaris / TABEL_PER_HAL));
    $hal = min(max(1, halaman()), $halMax);
    $tampilBaris = array_slice($baris, ($hal - 1) * TABEL_PER_HAL, TABEL_PER_HAL);

    $isi = gaya_tabel();

    if (isset($_GET['ok'])) {
        $isi .= callout('ok', 'Tersimpan', e((string) $_GET['ok']));
    }
    if (isset($_GET['galat'])) {
        $isi .= callout('bad', 'Tidak tersimpan', e((string) $_GET['galat']));
    }
    if (!$status['berlaku']) {
        $isi .= callout('info', 'Tidak berlaku',
            'Tabel ini khusus tahap profesi dan prodi ini tidak menyelenggarakan tahap profesi.');
    }
    if ($terkunci) {
        $isi .= callout('info', 'Terkunci',
            'Dikunci ' . e(tgl((string) $baris[0]['dikunci_pada'])) . ' oleh ' . e((string) ($baris[0]['pengunci'] ?? '—'))
            . '. Isian tidak bisa diubah sampai Admin membuka kunci.');
    }
    if (!$bisaIsi && !$terkunci && $status['berlaku']) {
        $isi .= callout('info', 'Hanya lihat',
            'Peran Anda tidak boleh mengisi data dukung. Hubungi Operator prodi.');
    }
    if ($input === []) {
        $isi .= callout('warn', 'Tidak ada field isian',
            'Semua field tabel ini berasal dari master atau dihitung sistem. '
            . 'Menambah baris akan membuat statusnya lengkap tanpa isian.');
    }

    $rincian = '';
    foreach ($field as $f) {
        $rincian .= '<li><span class="tf-sumber tf-' . e(strtolower($f['asal'])) . '">'
            . e($f['asal']) . '</span> ' . e($f['label'])
            . ($f['keterangan'] && $f['keterangan'] !== '-' ? ' <span class="muted">— ' . e($f['keterangan']) . '</span>' : '')
            . '</li>';
    }

    // ── kepala: status + tombol ──
    $aksiHtml = '<a class="btn" href="' . e(BASE_URL) . 'index.php?m=tabel">Daftar tabel</a>';
    if ($terkunci && boleh('BUKA_KUNCI')) {
        $aksiHtml .= '<form method="post" action="' . e(BASE_URL) . 'index.php?m=tabel&amp;a=buka&amp;t=' . e($tabelKode)
            . '" data-konfirmasi="Buka kunci tabel ini? Operator bisa mengubah isian lagi.">'
            . csrf_input() . '<input type="hidden" name="t" value="' . e($tabelKode) . '">'
            . '<button class="btn btn-warn" type="submit">Buka kunci</button></form>';
    } elseif (!$terkunci && $status['status'] === 'lengkap' && boleh('VERIFIKASI')) {
        $aksiHtml .= '<form method="post" action="' . e(BASE_URL) . 'index.php?m=tabel&amp;a=kunci&amp;t=' . e($tabelKode)
            . '" data-konfirmasi="Kunci tabel ini? Isian tidak bisa diubah setelah dikunci.">'
            . csrf_input() . '<input type="hidden" name="t" value="' . e($tabelKode) . '">'
            . '<button class="btn btn-primary" type="submit">Kunci tabel</button></form>';
    }

    $kepala = '<div class="tf-head">'
        . '<div><div class="tf-kode num">Tabel ' . e($info['nomor']) . '</div>'
        . '<div class="tf-kriteria">Kriteria ' . e($info['kriteria']) . ' · ' . e(nama_kriteria('K' . $info['kriteria'])) . '</div></div>'
        . '<div class="tf-status">' . badge_status($status['status'])
        . '<span class="tf-terisi">' . e((string) $status['terisi']) . '/' . e((string) $status['total']) . ' field terisi</span></div>'
        . '<div class="tf-jumlah"><b class="num">' . e((string) $totalBaris) . '</b> baris'
        . ($drafKosong ? ' <span class="muted">(belum disimpan)</span>' : '') . '</div>'
        . '</div>';

    $isi .= kartu('Rincian field', '<ul class="tf-rincian">' . $rincian . '</ul>',
        ['catatan' => count($field) . ' field · ' . count($input) . ' bisa diisi operator']);

    // ── form ──
    // Urut terbesar dihitung dari SELURUH baris, bukan hanya halaman ini:
    // tabel.js memakai angka itu untuk baris baru, dan nomor tidak boleh
    // bertabrakan dengan baris di halaman lain.
    $urutMax = 0;
    foreach ($baris as $b) {
        $urutMax = max($urutMax, (int) $b['urut']);
    }

    $form = '<form method="post" action="' . e(BASE_URL) . 'index.php?m=tabel&amp;a=simpan" id="formTabel" class="tf-form"'
        . ' data-urut-max="' . e((string) $urutMax) . '">'
        . csrf_input() . '<input type="hidden" name="t" value="' . e($tabelKode) . '">'
        . '<input type="hidden" name="hal" value="' . e((string) $hal) . '">'
        . '<div class="tf-baris-list">';

    foreach ($tampilBaris as $b) {
        $form .= tabel_render_baris($b, $input, $lain, $bisaIsi);
    }

    $form .= '</div>';
    // Baris yang dihapus dicatat di sini oleh tabel.js sebagai hapus[]=urut.
    $form .= '<div id="tfHapus" hidden></div>';

    if ($bisaIsi) {
        $form .= '<div class="tf-aksi">'
            . '<button class="btn" type="button" id="tfTambah">+ Tambah baris</button>'
            . '<span class="muted tf-hitung" id="tfHitung">' . count($baris) . ' baris</span>'
            . '<button class="btn btn-primary" type="submit">Simpan isian</button>'
            . '</div>';
    }
    $form .= '</form>';

    if ($bisaIsi) {
        $form .= '<template id="tfTemplate">' . tabel_render_baris(
            ['id' => 0, 'urut' => 0, 'dikunci' => false, 'nilai' => [], 'pengunci' => null, 'dikunci_pada' => null],
            $input, $lain, true) . '</template>';
    }

    $navigasi = '';
    if ($halMax > 1) {
        $navigasi = '<nav class="tf-halaman" aria-label="Halaman baris">';
        for ($i = 1; $i <= $halMax; $i++) {
            if ($halMax > 9 && abs($i - $hal) > 2 && $i !== 1 && $i !== $halMax) {
                if ($i === 2 || $i === $halMax - 1) {
                    $navigasi .= '<span class="muted">…</span>';
                }
                continue;
            }
            $navigasi .= '<a class="tf-chip' . ($i === $hal ? ' aktif' : '') . '"'
                . ' href="' . e(BASE_URL) . 'index.php?m=tabel&amp;a=lihat&amp;t=' . e($tabelKode) . '&amp;hal=' . $i . '">'
                . $i . '</a>';
        }
        $navigasi .= '</nav>';
    }

    $isi .= kartu($info['judul'], $kepala . $navigasi . $form,
        ['catatan' => $halMax > 1
            ? sprintf('Satu baris = satu record · halaman %d dari %d', $hal, $halMax)
            : 'Satu baris = satu record']);
    if ($bisaIsi) {
        $isi .= '<script src="' . e(BASE_URL) . 'aset/js/tabel.js?v=1" defer></script>';
    }

    render($info['judul'], $isi, [
        'aktif' => 'tabel',
        'sub' => 'Data dukung · Kriteria ' . $info['kriteria'] . ' · periode TS ' . $per['tahun_ts'],
        'aksi' => $aksiHtml,
    ]);
    return;
}

/* ── daftar 38 tabel ────────────────────────────────────────────── */
$saring = preg_replace('/[^0-9K]/', '', (string) ($_GET['k'] ?? ''));
$saring = $saring === '' ? '' : 'K' . ltrim($saring, 'K');

$semua = rekap_semua_tabel($PRODI, $PERIODE);
$ringkas = rekap_ringkas($PRODI, $PERIODE);

$kriteriaNama = [];
foreach (semua_kriteria() as $k) {
    $kriteriaNama[$k['kode']] = $k['nama'];
}

$tampil = [];
foreach ($semua as $t) {
    if ($saring !== '' && 'K' . $t['kriteria'] !== $saring) {
        continue;
    }
    $tampil[] = $t;
}

$isi = gaya_tabel();
$isi .= strip_angka([
    ['label' => 'Tabel wajib', 'nilai' => $ringkas['wajib'], 'dari' => $ringkas['total'], 'satuan' => 'tabel'],
    ['label' => 'Selesai (lengkap + terkunci)', 'nilai' => $ringkas['selesai'], 'dari' => $ringkas['wajib'], 'satuan' => 'tabel'],
    ['label' => 'Sebagian', 'nilai' => $ringkas['sebagian'], 'dari' => $ringkas['wajib'], 'satuan' => 'tabel'],
    ['label' => 'Kosong', 'nilai' => $ringkas['kosong'], 'dari' => $ringkas['wajib'], 'satuan' => 'tabel'],
]);

// Saringan per kriteria. K1 tidak punya tabel — tetap ditampilkan supaya
// "tidak ada tabel" tidak terbaca sebagai tabel yang hilang.
$saringHtml = '<nav class="tf-saring" aria-label="Saring tabel per kriteria">'
    . '<a class="tf-chip' . ($saring === '' ? ' aktif' : '') . '" href="' . e(BASE_URL) . 'index.php?m=tabel">'
    . 'Semua <span class="num">' . count($semua) . '</span></a>';
foreach ($kriteriaNama as $kode => $nama) {
    $n = count(array_filter($semua, fn($t) => 'K' . $t['kriteria'] === $kode));
    $saringHtml .= '<a class="tf-chip' . ($saring === $kode ? ' aktif' : '') . ($n === 0 ? ' kosong' : '') . '"'
        . ' href="' . e(BASE_URL) . 'index.php?m=tabel&amp;k=' . e($kode) . '" title="' . e($nama) . '">'
        . e($kode) . ' <span class="num">' . $n . '</span></a>';
}
$saringHtml .= '</nav>';

$barisTabel = [];
foreach ($tampil as $t) {
    $kode = (string) $t['kode'];
    $tautan = BASE_URL . 'index.php?m=tabel&amp;a=lihat&amp;t=' . e($kode);
    $barisTabel[] = [
        'nomor' => ['html' => '<a class="tf-nomor num" href="' . $tautan . '">' . e($t['nomor']) . '</a>'],
        'judul' => ['html' => '<a class="tf-judul" href="' . $tautan . '">' . e($t['judul']) . '</a>'
            . ($t['khusus_profesi'] ? ' <span class="tf-tanda">khusus profesi</span>' : '')],
        'kriteria' => ['html' => '<span class="num">' . e($t['kriteria']) . '</span> '
            . '<span class="muted tf-krit-nama">' . e($kriteriaNama['K' . $t['kriteria']] ?? '') . '</span>'],
        'status' => ['html' => badge_status($t['status'])],
        'terisi' => ['html' => '<span class="num">' . e((string) $t['terisi']) . '/' . e((string) $t['total']) . '</span>', 'kelas' => 'r'],
        'aksi' => ['html' => '<a class="btn btn-kecil" href="' . $tautan . '">'
            . ($t['status'] === 'terkunci' || !boleh('ISI') ? 'Lihat' : 'Isi') . '</a>'],
    ];
}

$isi .= kartu('38 tabel instrumen', $saringHtml . tabel_html([
    ['kunci' => 'nomor', 'label' => 'No', 'html' => true, 'lebar' => '64px'],
    ['kunci' => 'judul', 'label' => 'Judul tabel', 'html' => true],
    ['kunci' => 'kriteria', 'label' => 'Kriteria', 'html' => true],
    ['kunci' => 'status', 'label' => 'Status', 'html' => true],
    ['kunci' => 'terisi', 'label' => 'Field terisi', 'html' => true, 'kelas' => 'r'],
    ['kunci' => 'aksi', 'label' => '', 'html' => true],
], $barisTabel, [
    'kosong' => $saring === ''
        ? 'Belum ada tabel.'
        : 'Kriteria ' . $saring . ' tidak punya tabel data dukung' . (($kriteriaNama[$saring] ?? '') !== ''
            ? ' (' . $kriteriaNama[$saring] . ')' : '') . '. Isinya hanya dokumen pendukung.',
]), ['catatan' => $saring === '' ? 'Semua kriteria' : 'Saring: ' . $saring]);

render('Data Dukung', $isi, [
    'aktif' => 'tabel',
    'sub' => '38 tabel · prodi ' . ($PRODI_NAMA ?? '—') . ' · periode TS ' . $per['tahun_ts'],
]);

/**
 * Satu baris form. Dipakai untuk baris tersimpan DAN sebagai template JS.
 * Baris baru memakai urut 0; tabel.js menggantinya saat ditambahkan.
 */
function tabel_render_baris(array $b, array $input, array $lain, bool $bisaIsi): string
{
    $urut = (int) $b['urut'];
    $beku = !$bisaIsi || $b['dikunci'];
    $html = '<fieldset class="tf-baris' . ($b['dikunci'] ? ' terkunci' : '') . '" data-urut="' . e((string) $urut) . '"'
        . ' data-baris-id="' . e((string) $b['id']) . '">'
        . '<legend><span class="tf-baris-no num">' . e((string) $urut) . '</span>'
        . ($b['dikunci'] ? ' <span class="tf-kunci">terkunci</span>' : '') . '</legend>';

    if ($bisaIsi && !$b['dikunci']) {
        $html .= '<button class="tf-hapus" type="button" data-hapus-baris aria-label="Hapus baris ' . e((string) $urut) . '">Hapus baris</button>';
    }

    $html .= '<div class="tf-grid">';
    foreach ($input as $f) {
        $nama = 'sel[' . $urut . '][' . $f['kunci'] . ']';
        $sel = $b['nilai'][$f['kunci']] ?? null;
        $kolom = tabel_kolom($f['tipe']);
        if ($kolom === 'angka') {
            $nilai = $f['tipe'] === 'tanggal'
                ? tabel_angka_ke_tgl($sel['nilai_angka'] ?? null)
                : (string) ($sel['nilai_angka'] ?? '');
        } else {
            $nilai = (string) ($sel['nilai_teks'] ?? '');
        }

        $html .= '<label class="tf-field"><span class="tf-label">' . e($f['label']) . '</span>';
        if ($beku) {
            $tampil = $f['tipe'] === 'tanggal' && $nilai !== '' ? tgl($nilai) : ($nilai === '' ? '—' : $nilai);
            $html .= '<span class="tf-nilai">' . e($tampil) . '</span>';
        } else {
            $html .= tabel_kontrol($nama, $f, $nilai);
        }
        $html .= '</label>';
    }
    $html .= '</div>';

    if ($lain) {
        $html .= '<div class="tf-lain">';
        foreach ($lain as $f) {
            $sel = $b['nilai'][$f['kunci']] ?? null;
            $nilai = tabel_kolom($f['tipe']) === 'angka'
                ? (string) ($sel['nilai_angka'] ?? '')
                : (string) ($sel['nilai_teks'] ?? '');
            $html .= '<div class="tf-lain-item"><span class="tf-sumber tf-' . e(strtolower($f['asal'])) . '">'
                . e($f['asal']) . '</span><span class="tf-label">' . e($f['label']) . '</span>'
                . '<span class="tf-nilai">' . e($nilai === '' ? '—' : $nilai) . '</span></div>';
        }
        $html .= '</div>';
    }

    return $html . '</fieldset>';
}

/** Kontrol input sesuai `tipe`. Teks panjang -> textarea, tanggal -> date. */
function tabel_kontrol(string $nama, array $f, string $nilai): string
{
    $namaAttr = ' name="' . e($nama) . '"';

    if (tabel_kolom($f['tipe']) === 'angka') {
        if ($f['tipe'] === 'tanggal') {
            return '<input type="date" class="tf-input"' . $namaAttr . ' value="' . e($nilai) . '">';
        }
        $langkah = in_array($f['tipe'], ['desimal', 'persen'], true) ? '0.01' : '1';
        return '<input type="number" class="tf-input" step="' . $langkah . '"' . $namaAttr
            . ' value="' . e($nilai) . '" inputmode="decimal">';
    }

    if ($f['tipe'] === 'teks panjang') {
        return '<textarea class="tf-input" rows="2"' . $namaAttr . '>'
            . e($nilai) . '</textarea>';
    }

    return '<input type="text" class="tf-input" maxlength="500"' . $namaAttr
        . ' value="' . e($nilai) . '">';
}
