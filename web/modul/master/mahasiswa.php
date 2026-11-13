<?php
declare(strict_types=1);

/**
 * Master — mahasiswa (aksi `mahasiswa*`).
 *
 * Di-require dari modul/master/index.php. Berkas ini hanya bertindak kalau
 * $_GET['a'] berawalan `mahasiswa`, jadi index.php boleh me-require semua
 * berkas entitas master tanpa saling menimpa.
 *
 * Bagian fungsi sengaja murni (tidak membaca $_GET/$_POST, tidak mencetak)
 * supaya web/uji/uji_master.php bisa menguji tanpa server HTTP.
 *
 * Hapus = NONAKTIFKAN (status -> DO), BUKAN DELETE: baris_tabel/sel merujuk
 * mahasiswa lewat data dukung, dan riwayat pengisian harus tetap punya rujukan.
 */

require_once __DIR__ . '/_bersama.php';
require_once dirname(__DIR__, 2) . '/tampilan/kerangka/komponen.php';

const MHS_STATUS = ['Aktif', 'Lulus', 'DO', 'Cuti'];
const MHS_JALUR  = ['Reguler', 'Transfer', 'Asing'];
const MHS_TAHAP  = ['Akademik', 'Profesi'];

/** Hitungan per status. Selalu 4 kunci walaupun nol — strip angka butuh semua. */
function mhs_hitung(int $prodiId): array
{
    $out = array_fill_keys(MHS_STATUS, 0);
    foreach (q('SELECT status, COUNT(*) AS n FROM mahasiswa WHERE prodi_id = ? GROUP BY status',
        [$prodiId]) as $r) {
        $out[$r['status']] = (int) $r['n'];
    }
    return $out;
}

/**
 * Validasi + bersihkan satu mahasiswa. Mengembalikan ['data'=>[], 'galat'=>[]].
 * $id null = baris baru, dipakai untuk mengecualikan diri sendiri saat cek NIM.
 */
function mhs_validasi(array $in, int $prodiId, ?int $id = null): array
{
    $galat = [];
    $nim   = trim((string) ($in['nim'] ?? ''));
    $nama  = trim((string) ($in['nama'] ?? ''));

    if ($nim === '') {
        $galat[] = 'NIM wajib diisi.';
    } elseif (!preg_match('/^[0-9A-Za-z._-]{3,24}$/', $nim)) {
        $galat[] = 'NIM hanya huruf/angka/titik/strip, 3–24 karakter.';
    } else {
        $lain = q1('SELECT nama FROM mahasiswa WHERE nim = ? AND id <> ?', [$nim, $id ?? 0]);
        if ($lain) {
            // Pesan wajib menyebut NIM-nya: operator harus tahu baris mana yang bentrok.
            $galat[] = sprintf('NIM %s sudah dipakai: %s.', $nim, $lain['nama']);
        }
    }

    if ($nama === '') {
        $galat[] = 'Nama wajib diisi.';
    } elseif (mb_strlen($nama) > 160) {
        $galat[] = 'Nama lebih dari 160 karakter.';
    }

    $tahun = trim((string) ($in['tahun_masuk'] ?? ''));
    if (!preg_match('/^(19|20)\d{2}$/', $tahun)) {
        $galat[] = 'Tahun masuk harus 4 angka (mis. 2023).';
    } elseif ((int) $tahun > (int) date('Y') + 1) {
        $galat[] = sprintf('Tahun masuk %s belum terjadi.', $tahun);
    }

    $enum = function (string $kunci, array $sah, string $bawaan) use ($in, &$galat): string {
        $v = trim((string) ($in[$kunci] ?? ''));
        if ($v === '') {
            return $bawaan;
        }
        if (!in_array($v, $sah, true)) {
            $galat[] = sprintf('%s harus salah satu: %s.', ucfirst(str_replace('_', ' ', $kunci)), implode(', ', $sah));
            return $bawaan;
        }
        return $v;
    };
    $jalur  = $enum('jalur_masuk', MHS_JALUR, 'Reguler');
    $tahap  = $enum('tahap', MHS_TAHAP, 'Akademik');
    $status = $enum('status', MHS_STATUS, 'Aktif');

    // IPK: koma Indonesia diterima, batas 0–4 ditegakkan di sini DAN oleh CHECK
    // di database. Pesan aplikasi yang lebih dulu muncul supaya bisa dibaca.
    $ipkRaw = str_replace(',', '.', trim((string) ($in['ipk'] ?? '')));
    $ipk    = null;
    if ($ipkRaw !== '') {
        if (!is_numeric($ipkRaw)) {
            $galat[] = 'IPK harus angka (mis. 3,45).';
        } elseif ((float) $ipkRaw < 0 || (float) $ipkRaw > 4) {
            $galat[] = sprintf('IPK %s di luar rentang 0–4.', number_format((float) $ipkRaw, 2, ',', '.'));
        } else {
            $ipk = round((float) $ipkRaw, 2);
        }
    }

    $tgl = trim((string) ($in['tanggal_lulus'] ?? ''));
    if ($tgl !== '') {
        $pisah = preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $tgl, $m);
        if (!$pisah || !checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            $galat[] = 'Tanggal lulus harus tanggal sah format YYYY-MM-DD.';
            $tgl = '';
        }
    }
    $thLulus = trim((string) ($in['tahun_lulus'] ?? ''));
    if ($thLulus === '' && $tgl !== '') {
        $thLulus = substr($tgl, 0, 4);
    }
    if ($thLulus !== '' && !preg_match('/^(19|20)\d{2}$/', $thLulus)) {
        $galat[] = 'Tahun lulus harus 4 angka.';
    }
    if ($status === 'Lulus' && $tgl === '') {
        $galat[] = 'Status Lulus wajib punya tanggal lulus.';
    }

    return [
        'data' => [
            'nim'            => $nim,
            'nama'           => $nama,
            'tahun_masuk'    => (int) $tahun,
            'jalur_masuk'    => $jalur,
            'tahap'          => $tahap,
            'status'         => $status,
            'ipk'            => $ipk,
            'tanggal_lulus'  => $tgl === '' ? null : $tgl,
            'tahun_lulus'    => $thLulus === '' ? null : (int) $thLulus,
        ],
        'galat' => $galat,
    ];
}

/** Simpan (baru/ubah). ['ok'=>bool,'pesan'=>string,'id'=>int|null] */
function mhs_simpan(array $in, int $prodiId, ?int $id = null): array
{
    $v = mhs_validasi($in, $prodiId, $id);
    if ($v['galat']) {
        return ['ok' => false, 'pesan' => implode(' ', $v['galat']), 'id' => $id];
    }
    if ($id === null) {
        $id = sisip('mahasiswa', $v['data'] + ['prodi_id' => $prodiId]);
        return ['ok' => true, 'pesan' => 'Mahasiswa ditambahkan.', 'id' => $id];
    }
    if (!q1('SELECT id FROM mahasiswa WHERE id = ? AND prodi_id = ?', [$id, $prodiId])) {
        return ['ok' => false, 'pesan' => 'Mahasiswa tidak ada di prodi ini.', 'id' => $id];
    }
    ubah('mahasiswa', $v['data'], 'id', $id);
    return ['ok' => true, 'pesan' => 'Data mahasiswa diubah.', 'id' => $id];
}

/** Nonaktifkan: status -> DO. Tidak menghapus baris. */
function mhs_nonaktif(int $id, int $prodiId): array
{
    $m = q1('SELECT status FROM mahasiswa WHERE id = ? AND prodi_id = ?', [$id, $prodiId]);
    if (!$m) {
        return ['ok' => false, 'pesan' => 'Mahasiswa tidak ada di prodi ini.'];
    }
    if ($m['status'] === 'DO') {
        return ['ok' => false, 'pesan' => 'Mahasiswa ini sudah nonaktif (DO).'];
    }
    ubah('mahasiswa', ['status' => 'DO'], 'id', $id);
    return ['ok' => true, 'pesan' => 'Status diubah menjadi DO. Baris tidak dihapus.'];
}

/** Urutan kolom CSV mahasiswa -> array input form. */
function mhs_baris_csv(array $k): array
{
    return [
        'nim'           => $k[0] ?? '',
        'nama'          => $k[1] ?? '',
        'tahun_masuk'   => $k[2] ?? '',
        'jalur_masuk'   => $k[3] ?? '',
        'tahap'         => $k[4] ?? '',
        'status'        => $k[5] ?? '',
        'ipk'           => $k[6] ?? '',
        'tanggal_lulus' => $k[7] ?? '',
        'tahun_lulus'   => $k[8] ?? '',
    ];
}

/** Baris CSV tanpa header. Baris 1 dilewati hanya kalau isinya memang header. */
function mhs_csv_isi(string $csv): array
{
    $baris = csv_baris($csv);
    if ($baris && strtolower((string) ($baris[0]['kolom'][0] ?? '')) === 'nim') {
        array_shift($baris);
    }
    return $baris;
}

/** Pratinjau: validasi semua baris TANPA menyimpan. */
function mhs_pratinjau(string $csv, int $prodiId): array
{
    $out = [];
    foreach (mhs_csv_isi($csv) as $b) {
        $v = mhs_validasi(mhs_baris_csv($b['kolom']), $prodiId);
        $out[] = ['baris' => $b['baris'], 'kolom' => $b['kolom'], 'galat' => $v['galat']];
    }
    return $out;
}

/**
 * Impor: baris gagal DITOLAK SENDIRI-SENDIRI, bukan membatalkan berkas.
 * Tanpa transaksi seluruh berkas — satu baris salah tidak boleh menghapus
 * pekerjaan 200 baris yang benar.
 */
function mhs_impor(string $csv, int $prodiId): array
{
    $masuk = 0;
    $tolak = [];
    foreach (mhs_csv_isi($csv) as $b) {
        $h = mhs_simpan(mhs_baris_csv($b['kolom']), $prodiId);
        if ($h['ok']) {
            $masuk++;
        } else {
            $tolak[] = ['baris' => $b['baris'], 'pesan' => $h['pesan']];
        }
    }
    return ['masuk' => $masuk, 'tolak' => $tolak];
}

/* ── routing ────────────────────────────────────────────────────── */
$aksi = $_GET['a'] ?? 'index';
if (defined('MASTER_TANPA_HTTP') || !str_starts_with($aksi, 'mahasiswa')) {
    return;
}

// layout.php hanya memuat app.css dan dibekukan; CSS modul ditautkan dari isi.
$css = '<link rel="stylesheet" href="' . e(BASE_URL) . 'aset/css/master.css?v=1">';
$PRODI ??= null;
// Admin tanpa prodi di sesi: pakai prodi pertama supaya halaman tidak kosong.
$prodiId = (int) ($PRODI ?? qv('SELECT id FROM prodi ORDER BY id LIMIT 1', [], 0));
$prodiNm = (string) qv('SELECT nama_ps FROM prodi WHERE id = ?', [$prodiId], '—');
$bolehUbah = boleh('ISI');
$isi = '';      // berkas form/impor menambah ke sini setelah di-require

/* simpan (tambah/ubah) */
if ($aksi === 'mahasiswa-simpan') {
    wajib('ISI');
    csrf_periksa();
    $id = (int) masuk_input('id');
    $h  = mhs_simpan($_POST, $prodiId, $id > 0 ? $id : null);
    if (!$h['ok']) {
        // Kembali ke form dengan isian utuh + pesan; jangan buang ketikan operator.
        $galat = $h['pesan'];
        $nilai = $_POST;
        $nilai['id'] = $id;
        require __DIR__ . '/mahasiswa_form.php';
        return;
    }
    alihkan(BASE_URL . 'index.php?m=master&a=mahasiswa&ok=' . rawurlencode($h['pesan']));
}

if ($aksi === 'mahasiswa-nonaktif') {
    wajib('ISI');
    csrf_periksa();
    $h = mhs_nonaktif((int) masuk_input('id'), $prodiId);
    alihkan(BASE_URL . 'index.php?m=master&a=mahasiswa&ok=' . rawurlencode($h['pesan']));
}

/* impor: unggah -> pratinjau -> konfirmasi */
if ($aksi === 'mahasiswa-impor' || $aksi === 'mahasiswa-impor-simpan') {
    wajib('IMPOR');
    $pratinjau = null;
    $hasil     = null;
    $galat     = '';
    if ($aksi === 'mahasiswa-impor' && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        csrf_periksa();
        $f = $_FILES['berkas'] ?? null;
        if (!$f || ($f['error'] ?? 4) !== UPLOAD_ERR_OK) {
            $galat = 'Berkas tidak terunggah.';
        } elseif (($f['size'] ?? 0) > 2 * 1024 * 1024) {
            $galat = 'Berkas lebih dari 2 MB.';
        } elseif (!preg_match('/\.(csv|txt)$/i', (string) $f['name'])) {
            $galat = 'Berkas harus .csv atau .txt.';
        } else {
            $csv = (string) file_get_contents($f['tmp_name']);
            sesi_mulai();
            $_SESSION['impor_mhs'] = $csv;
            $pratinjau = mhs_pratinjau($csv, $prodiId);
        }
    }
    if ($aksi === 'mahasiswa-impor-simpan') {
        csrf_periksa();
        sesi_mulai();
        $csv = (string) ($_SESSION['impor_mhs'] ?? '');
        if ($csv === '') {
            $galat = 'Tidak ada berkas yang menunggu konfirmasi. Unggah ulang.';
        } else {
            $hasil = mhs_impor($csv, $prodiId);
            unset($_SESSION['impor_mhs']);
        }
    }
    require __DIR__ . '/mahasiswa_impor.php';
    return;
}

/* form tambah/ubah */
if ($aksi === 'mahasiswa-form') {
    $id    = (int) masuk_input('id');
    $nilai = [];
    $galat = '';
    if ($id > 0) {
        $m = q1('SELECT id, nim, nama, tahun_masuk, jalur_masuk, tahap, status, ipk,
                        tanggal_lulus, tahun_lulus
                 FROM mahasiswa WHERE id = ? AND prodi_id = ?', [$id, $prodiId]);
        if (!$m) {
            tolak(404, 'Mahasiswa tidak ditemukan di prodi ini.');
        }
        $nilai = $m;
    }
    require __DIR__ . '/mahasiswa_form.php';
    return;
}

/* daftar */
$cari   = masuk_input('q');
$fStatus = masuk_input('status');
$fTahun  = masuk_input('tahun');

$syarat = ['prodi_id = ?'];
$param  = [$prodiId];
if ($cari !== '') {
    $syarat[] = '(nama LIKE ? OR nim LIKE ?)';
    $param[]  = '%' . $cari . '%';
    $param[]  = '%' . $cari . '%';
}
if (in_array($fStatus, MHS_STATUS, true)) {
    $syarat[] = 'status = ?';
    $param[]  = $fStatus;
}
if (preg_match('/^(19|20)\d{2}$/', $fTahun)) {
    $syarat[] = 'tahun_masuk = ?';
    $param[]  = (int) $fTahun;
}

$daftar = q(
    'SELECT id, nim, nama, tahun_masuk, jalur_masuk, tahap, status, ipk, tanggal_lulus
     FROM mahasiswa WHERE ' . implode(' AND ', $syarat) . '
     ORDER BY nim LIMIT 100',   // ponytail: 100 baris/halaman; paging kalau data >2000
    $param
);
$totalSaring = (int) qv(
    'SELECT COUNT(*) FROM mahasiswa WHERE ' . implode(' AND ', $syarat), $param, 0);
$hitung = mhs_hitung($prodiId);

$ok = masuk_input('ok');
$isi = '';
if ($ok !== '') {
    $isi .= callout('ok', 'Tersimpan', e($ok));
}

$isi .= '<div class="hitung">';
foreach (MHS_STATUS as $s) {
    $aktif = $fStatus === $s ? ' on' : '';
    $isi .= sprintf(
        '<a class="%s" href="%sindex.php?m=master&amp;a=mahasiswa&amp;status=%s"><b>%d</b><span>%s</span></a>',
        trim('hit' . $aktif), e(BASE_URL), e($s), $hitung[$s], e($s));
}
$isi .= sprintf('<a class="hit%s" href="%sindex.php?m=master&amp;a=mahasiswa"><b>%d</b><span>Semua</span></a>',
    $fStatus === '' ? ' on' : '', e(BASE_URL), array_sum($hitung));
$isi .= '</div>';

$formSaring = '<form class="saring" method="get" action="' . e(BASE_URL) . 'index.php">'
    . '<input type="hidden" name="m" value="master"><input type="hidden" name="a" value="mahasiswa">'
    . '<label>Cari nama / NIM<input type="search" name="q" value="' . e($cari) . '" placeholder="mis. 2023010 atau Siti"></label>'
    . '<label>Status<select name="status"><option value="">Semua</option>'
    . pilihan(MHS_STATUS, $fStatus) . '</select></label>'
    . '<label>Tahun masuk<input type="number" name="tahun" min="1990" max="' . e((string) (date('Y') + 1)) . '" value="' . e($fTahun) . '"></label>'
    . '<button class="btn btn-primary" type="submit">Saring</button>'
    . '<a class="btn" href="' . e(BASE_URL) . 'index.php?m=master&amp;a=mahasiswa">Atur ulang</a>'
    . '</form>';
$isi .= kartu('', $formSaring);

$kolom = [
    ['kunci' => 'nim', 'label' => 'NIM', 'kelas' => 'mono'],
    ['kunci' => 'nama', 'label' => 'Nama'],
    ['kunci' => 'tahun_masuk', 'label' => 'Tahun masuk', 'kelas' => 'r num'],
    ['kunci' => 'jalur_masuk', 'label' => 'Jalur'],
    ['kunci' => 'tahap', 'label' => 'Tahap'],
    ['kunci' => 'status', 'label' => 'Status', 'html' => true],
    ['kunci' => 'ipk', 'label' => 'IPK', 'kelas' => 'r num'],
    ['kunci' => 'lulus', 'label' => 'Lulus'],
];
if ($bolehUbah) {
    $kolom[] = ['kunci' => 'aksi', 'label' => 'Aksi', 'html' => true];
}
$badgeStatus = ['Aktif' => 'lengkap', 'Lulus' => 'terkunci', 'DO' => 'kosong', 'Cuti' => 'sebagian'];
$barisTabel = [];
foreach ($daftar as $m) {
    $aksiHtml = '<a class="btn" href="' . e(BASE_URL) . 'index.php?m=master&amp;a=mahasiswa-form&amp;id=' . (int) $m['id'] . '">Ubah</a>';
    if ($m['status'] !== 'DO') {
        $aksiHtml .= ' <form method="post" style="display:inline" action="' . e(BASE_URL) . 'index.php?m=master&amp;a=mahasiswa-nonaktif"'
            . ' data-konfirmasi="Nonaktifkan ' . e($m['nama']) . '? Status jadi DO, data tidak dihapus.">'
            . csrf_input() . '<input type="hidden" name="id" value="' . (int) $m['id'] . '">'
            . '<button class="btn" type="submit">Nonaktifkan</button></form>';
    }
    $barisTabel[] = [
        'nim'         => $m['nim'],
        'nama'        => $m['nama'],
        'tahun_masuk' => $m['tahun_masuk'],
        'jalur_masuk' => $m['jalur_masuk'],
        'tahap'       => $m['tahap'],
        'status'      => '<span class="badge badge-' . e($badgeStatus[$m['status']] ?? 'kosong') . '">' . e($m['status']) . '</span>',
        'ipk'         => $m['ipk'] === null ? null : desimal($m['ipk']),
        'lulus'       => $m['tanggal_lulus'] === null ? null : tgl($m['tanggal_lulus']),
        'aksi'        => $aksiHtml,
    ];
}

$isiTabel = !$barisTabel
    ? '<p class="muted">Belum ada mahasiswa yang cocok. '
        . ($bolehUbah ? '<a href="' . e(BASE_URL) . 'index.php?m=master&amp;a=mahasiswa-form">Tambah mahasiswa</a> atau ' : '')
        . (boleh('IMPOR') ? '<a href="' . e(BASE_URL) . 'index.php?m=master&amp;a=mahasiswa-impor">impor CSV</a>.' : '') . '</p>'
    : tabel_html($kolom, $barisTabel)
        // Hapus tidak tersedia: mahasiswa dinonaktifkan (status DO) supaya
        // riwayat pengisian data dukung tetap punya rujukan.
        . '<p class="foot-note">Nonaktifkan mengubah status jadi DO. Baris tidak pernah dihapus.</p>';

$isi .= kartu('Mahasiswa ' . $prodiNm, $isiTabel, [
    'catatan' => $totalSaring . ' mahasiswa cocok' . (count($daftar) < $totalSaring ? ' · 100 pertama ditampilkan' : ''),
]);

$aksiKanan = '';
if ($bolehUbah) {
    $aksiKanan .= '<a class="btn btn-primary" href="' . e(BASE_URL) . 'index.php?m=master&amp;a=mahasiswa-form">Tambah</a>';
}
if (boleh('IMPOR')) {
    $aksiKanan .= '<a class="btn" href="' . e(BASE_URL) . 'index.php?m=master&amp;a=mahasiswa-impor">Impor CSV</a>';
}
render('Mahasiswa', $css . $isi, [
    'aktif' => 'mahasiswa',
    'sub'   => 'Prodi ' . $prodiNm . ' · ' . array_sum($hitung) . ' mahasiswa terdaftar',
    'aksi'  => $aksiKanan,
]);
