<?php
declare(strict_types=1);

/**
 * Master — dosen & tendik (aksi `dosen*`).
 *
 * Di-require dari modul/master/index.php; hanya bertindak kalau aksi berawalan
 * `dosen`. Fungsi di bawah murni supaya bisa diuji tanpa HTTP.
 *
 * SATU ORANG = SATU BARIS. Prodi tidak disimpan di tabel dosen, melainkan di
 * `dosen_prodi` (many-to-many). Prototipe lama menduplikasi baris dosen per
 * prodi sehingga "jumlah dosen" terhitung dobel dan rasio dosen:mahasiswa
 * salah. Di sini dosen lintas prodi tetap satu baris `dosen` dengan dua baris
 * `dosen_prodi`.
 */

require_once __DIR__ . '/_bersama.php';
require_once dirname(__DIR__, 2) . '/tampilan/kerangka/komponen.php';

const DOSEN_JENIS = ['Dosen Tetap', 'Dosen Tidak Tetap', 'Tendik'];
const DOSEN_JABATAN = ['Belum Memiliki', 'Asisten Ahli', 'Lektor', 'Lektor Kepala', 'Guru Besar'];
const DOSEN_PROFESI = ['Bidan', 'Perawat', 'Ners'];
const DOSEN_TAHAP = ['Akademik', 'Profesi', 'Keduanya'];
const DOSEN_JENJANG = ['D3', 'D4', 'S1', 'Profesi', 'S2', 'S3'];

/** Daftar prodi (id => nama). Dipakai form dan impor. */
function dosen_prodi_peta(): array
{
    $out = [];
    foreach (q('SELECT id, nama_ps FROM prodi ORDER BY nama_ps') as $p) {
        $out[(int) $p['id']] = (string) $p['nama_ps'];
    }
    return $out;
}

/** Prodi tempat dosen mengajar, urut nama. */
function dosen_prodi_dari(int $dosenId): array
{
    return array_map('intval', array_column(
        q('SELECT dp.prodi_id FROM dosen_prodi dp JOIN prodi p ON p.id = dp.prodi_id
           WHERE dp.dosen_id = ? ORDER BY p.nama_ps', [$dosenId]), 'prodi_id'));
}

/** Riwayat pendidikan satu dosen, terbaru dulu. */
function dosen_pendidikan_dari(int $dosenId): array
{
    return q('SELECT id, jenjang, bidang, asal_pt, tahun_lulus FROM dosen_pendidikan
              WHERE dosen_id = ? ORDER BY tahun_lulus DESC, id', [$dosenId]);
}

/**
 * Ubah daftar prodi dosen jadi tepat $prodiIds.
 * Hanya baris yang berubah yang disentuh, dan tidak ada baris `dosen` disentuh —
 * inilah yang membuat dosen lintas prodi tetap satu orang.
 */
function dosen_prodi_set(int $dosenId, array $prodiIds): void
{
    $sah = array_keys(dosen_prodi_peta());
    $baru = array_values(array_unique(array_filter(array_map('intval', $prodiIds),
        fn(int $p) => in_array($p, $sah, true))));
    if (!$baru) {
        return;   // jangan sampai dosen kehilangan semua prodi karena form kosong
    }
    $lama = dosen_prodi_dari($dosenId);
    foreach (array_diff($baru, $lama) as $tambah) {
        ex('INSERT INTO dosen_prodi (dosen_id, prodi_id) VALUES (?, ?)', [$dosenId, $tambah]);
    }
    foreach (array_diff($lama, $baru) as $buang) {
        ex('DELETE FROM dosen_prodi WHERE dosen_id = ? AND prodi_id = ?', [$dosenId, $buang]);
    }
}

/** Validasi satu dosen. ['data'=>[], 'prodi'=>[], 'galat'=>[]]. */
function dosen_validasi(array $in, ?int $id = null): array
{
    $galat = [];
    $nidn  = preg_replace('/\s+/', '', trim((string) ($in['nidn_nidk_nup'] ?? ''))) ?? '';
    $nama  = trim((string) ($in['nama'] ?? ''));

    if ($nidn === '') {
        $galat[] = 'NIDN/NIDK/NUP wajib diisi.';
    } elseif (!preg_match('/^[0-9A-Za-z._-]{3,32}$/', $nidn)) {
        $galat[] = 'NIDN/NIDK/NUP hanya huruf/angka/titik/strip, 3–32 karakter.';
    } else {
        $lain = q1('SELECT nama FROM dosen WHERE nidn_nidk_nup = ? AND id <> ?', [$nidn, $id ?? 0]);
        if ($lain) {
            $galat[] = sprintf('NIDN %s sudah dipakai: %s.', $nidn, $lain['nama']);
        }
    }

    if ($nama === '') {
        $galat[] = 'Nama wajib diisi.';
    } elseif (mb_strlen($nama) > 160) {
        $galat[] = 'Nama lebih dari 160 karakter.';
    }

    $jenis = trim((string) ($in['jenis'] ?? 'Dosen Tetap'));
    if (!in_array($jenis, DOSEN_JENIS, true)) {
        $galat[] = 'Jenis harus salah satu: ' . implode(', ', DOSEN_JENIS) . '.';
        $jenis = 'Dosen Tetap';
    }

    $pilih = function (string $kunci, array $sah, string $bawaan = '') use ($in, &$galat): ?string {
        $v = trim((string) ($in[$kunci] ?? ''));
        if ($v === '') {
            return $bawaan === '' ? null : $bawaan;
        }
        if (!in_array($v, $sah, true)) {
            return null;   // diam-diam null: kolom ini opsional, bukan data kunci
        }
        return $v;
    };

    $tgl = function (string $kunci) use ($in, &$galat): ?string {
        $v = trim((string) ($in[$kunci] ?? ''));
        if ($v === '') {
            return null;
        }
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $v, $m) || !checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            $galat[] = ucfirst(str_replace('_', ' ', $kunci)) . ' harus tanggal sah (YYYY-MM-DD).';
            return null;
        }
        return $v;
    };

    $email = trim((string) ($in['email'] ?? ''));
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $galat[] = 'Email tidak sah.';
        $email = '';
    }

    $sert = !empty($in['sertifikat_pendidik']) && $in['sertifikat_pendidik'] !== '0' ? 1 : 0;

    $prodi = $in['prodi'] ?? $in['prodi_id'] ?? [];
    $prodi = is_array($prodi) ? $prodi : [$prodi];
    $prodi = array_values(array_filter(array_map('intval', $prodi), fn(int $p) => $p > 0));
    if (!$prodi) {
        $galat[] = 'Pilih minimal satu prodi tempat mengajar.';
    }

    return [
        'data' => [
            'nidn_nidk_nup'       => $nidn,
            'nama'                => $nama,
            'jenis'               => $jenis,
            'jabatan_fungsional'  => $pilih('jabatan_fungsional', DOSEN_JABATAN),
            'gelar_akademik'      => ($g = trim((string) ($in['gelar_akademik'] ?? ''))) === '' ? null : mb_substr($g, 0, 120),
            'tanggal_lahir'       => $tgl('tanggal_lahir'),
            'sertifikat_pendidik' => $sert,
            'profesi'             => $pilih('profesi', DOSEN_PROFESI),
            'no_str'              => ($s = trim((string) ($in['no_str'] ?? ''))) === '' ? null : mb_substr($s, 0, 60),
            'tanggal_str'         => $tgl('tanggal_str'),
            'tahap_mengajar'      => $pilih('tahap_mengajar', DOSEN_TAHAP),
            'email'               => $email === '' ? null : mb_substr($email, 0, 160),
        ],
        'prodi' => $prodi,
        'galat' => $galat,
    ];
}

/** Simpan dosen (baru/ubah) + sinkron dosen_prodi. */
function dosen_simpan(array $in, ?int $id = null): array
{
    $v = dosen_validasi($in, $id);
    if ($v['galat']) {
        return ['ok' => false, 'pesan' => implode(' ', $v['galat']), 'id' => $id];
    }
    if ($id === null) {
        $id = transaksi(function () use ($v): int {
            $baru = sisip('dosen', $v['data']);
            dosen_prodi_set($baru, $v['prodi']);
            return $baru;
        });
        return ['ok' => true, 'pesan' => 'Dosen ditambahkan.', 'id' => $id];
    }
    if (!q1('SELECT id FROM dosen WHERE id = ?', [$id])) {
        return ['ok' => false, 'pesan' => 'Dosen tidak ditemukan.', 'id' => $id];
    }
    transaksi(function () use ($v, $id): void {
        ubah('dosen', $v['data'], 'id', $id);
        dosen_prodi_set((int) $id, $v['prodi']);
    });
    return ['ok' => true, 'pesan' => 'Data dosen diubah.', 'id' => $id];
}

/** Tambah satu baris riwayat pendidikan. */
function dosen_pendidikan_tambah(int $dosenId, array $in): array
{
    if (!q1('SELECT id FROM dosen WHERE id = ?', [$dosenId])) {
        return ['ok' => false, 'pesan' => 'Dosen tidak ditemukan.'];
    }
    $jenjang = trim((string) ($in['jenjang'] ?? ''));
    if (!in_array($jenjang, DOSEN_JENJANG, true)) {
        return ['ok' => false, 'pesan' => 'Jenjang harus salah satu: ' . implode(', ', DOSEN_JENJANG) . '.'];
    }
    $tahun = trim((string) ($in['tahun_lulus'] ?? ''));
    if ($tahun !== '' && (!preg_match('/^(19|20)\d{2}$/', $tahun) || (int) $tahun > (int) date('Y'))) {
        return ['ok' => false, 'pesan' => 'Tahun lulus pendidikan harus 4 angka dan tidak di masa depan.'];
    }
    $bidang = trim((string) ($in['bidang'] ?? ''));
    $asal   = trim((string) ($in['asal_pt'] ?? ''));
    if ($bidang === '' || $asal === '') {
        return ['ok' => false, 'pesan' => 'Bidang dan asal perguruan tinggi wajib diisi.'];
    }
    sisip('dosen_pendidikan', [
        'dosen_id'    => $dosenId,
        'jenjang'     => $jenjang,
        'bidang'      => mb_substr($bidang, 0, 120),
        'asal_pt'     => mb_substr($asal, 0, 160),
        'tahun_lulus' => $tahun === '' ? null : (int) $tahun,
    ]);
    return ['ok' => true, 'pesan' => 'Riwayat pendidikan ditambahkan.'];
}

function dosen_pendidikan_hapus(int $pendidikanId, int $dosenId): array
{
    $n = ex('DELETE FROM dosen_pendidikan WHERE id = ? AND dosen_id = ?', [$pendidikanId, $dosenId]);
    return $n > 0
        ? ['ok' => true, 'pesan' => 'Riwayat pendidikan dihapus.']
        : ['ok' => false, 'pesan' => 'Baris pendidikan tidak ditemukan.'];
}

/** Urutan kolom CSV dosen -> input form. Prodi dipisah `;` (id atau nama). */
function dosen_baris_csv(array $k): array
{
    return [
        'nidn_nidk_nup'      => $k[0] ?? '',
        'nama'               => $k[1] ?? '',
        'jenis'              => $k[2] ?? '',
        'jabatan_fungsional' => $k[3] ?? '',
        'gelar_akademik'     => $k[4] ?? '',
        'tanggal_lahir'      => $k[5] ?? '',
        'sertifikat_pendidik'=> $k[6] ?? '',
        'profesi'            => $k[7] ?? '',
        'no_str'             => $k[8] ?? '',
        'tanggal_str'        => $k[9] ?? '',
        'tahap_mengajar'     => $k[10] ?? '',
        'email'              => $k[11] ?? '',
    ];
}

/** "1;2" atau "Kebidanan;Keperawatan" -> [1,2]. */
function dosen_prodi_csv(string $teks): array
{
    $peta = dosen_prodi_peta();
    $balik = array_change_key_case(array_flip($peta), CASE_LOWER);
    $out = [];
    foreach (preg_split('/[;|]/', $teks) ?: [] as $bagian) {
        $bagian = trim($bagian);
        if ($bagian === '') {
            continue;
        }
        if (ctype_digit($bagian) && isset($peta[(int) $bagian])) {
            $out[] = (int) $bagian;
            continue;
        }
        $kunci = strtolower($bagian);
        if (isset($balik[$kunci])) {
            $out[] = (int) $balik[$kunci];
        }
    }
    return $out;
}

function dosen_csv_isi(string $csv): array
{
    $baris = csv_baris($csv);
    if ($baris && strtolower((string) ($baris[0]['kolom'][0] ?? '')) === 'nidn') {
        array_shift($baris);
    }
    return $baris;
}

function dosen_pratinjau(string $csv): array
{
    $out = [];
    foreach (dosen_csv_isi($csv) as $b) {
        $in = dosen_baris_csv($b['kolom']);
        $in['prodi'] = dosen_prodi_csv((string) ($b['kolom'][12] ?? ''));
        $v = dosen_validasi($in);
        $out[] = ['baris' => $b['baris'], 'kolom' => $b['kolom'], 'galat' => $v['galat']];
    }
    return $out;
}

function dosen_impor(string $csv): array
{
    $masuk = 0;
    $tolak = [];
    foreach (dosen_csv_isi($csv) as $b) {
        $in = dosen_baris_csv($b['kolom']);
        $in['prodi'] = dosen_prodi_csv((string) ($b['kolom'][12] ?? ''));
        $h = dosen_simpan($in);
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
if (defined('MASTER_TANPA_HTTP') || !str_starts_with($aksi, 'dosen')) {
    return;
}

$css = '<link rel="stylesheet" href="' . e(BASE_URL) . 'aset/css/master.css?v=1">';
$PRODI ??= null;
$prodiId  = (int) ($PRODI ?? qv('SELECT id FROM prodi ORDER BY id LIMIT 1', [], 0));
$prodiNm  = (string) qv('SELECT nama_ps FROM prodi WHERE id = ?', [$prodiId], '—');
$bolehUbah = boleh('ISI');
$petaProdi = dosen_prodi_peta();
$isi = '';      // berkas form/impor menambah ke sini setelah di-require

if ($aksi === 'dosen-simpan') {
    wajib('ISI');
    csrf_periksa();
    $id = (int) masuk_input('id');
    $_POST['prodi'] = $_POST['prodi'] ?? [];
    $h = dosen_simpan($_POST, $id > 0 ? $id : null);
    if (!$h['ok']) {
        $galat = $h['pesan'];
        $nilai = $_POST;
        $nilai['id'] = $id;
        require __DIR__ . '/dosen_form.php';
        return;
    }
    alihkan(BASE_URL . 'index.php?m=master&a=dosen-form&id=' . (int) $h['id'] . '&ok=' . rawurlencode($h['pesan']));
}

if ($aksi === 'dosen-pendidikan-tambah' || $aksi === 'dosen-pendidikan-hapus') {
    wajib('ISI');
    csrf_periksa();
    $dosenId = (int) masuk_input('dosen_id');
    $h = $aksi === 'dosen-pendidikan-tambah'
        ? dosen_pendidikan_tambah($dosenId, $_POST)
        : dosen_pendidikan_hapus((int) masuk_input('id'), $dosenId);
    alihkan(BASE_URL . 'index.php?m=master&a=dosen-form&id=' . $dosenId . '&ok=' . rawurlencode($h['pesan']));
}

if ($aksi === 'dosen-impor' || $aksi === 'dosen-impor-simpan') {
    wajib('IMPOR');
    $pratinjau = null;
    $hasil = null;
    $galat = '';
    if ($aksi === 'dosen-impor' && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        csrf_periksa();
        $f = $_FILES['berkas'] ?? null;
        if (!$f || ($f['error'] ?? 4) !== UPLOAD_ERR_OK) {
            $galat = 'Berkas tidak terunggah.';
        } elseif (($f['size'] ?? 0) > 2 * 1024 * 1024) {
            $galat = 'Berkas lebih dari 2 MB.';
        } elseif (!preg_match('/\.(csv|txt)$/i', (string) $f['name'])) {
            $galat = 'Berkas harus .csv atau .txt.';
        } else {
            sesi_mulai();
            $_SESSION['impor_dosen'] = (string) file_get_contents($f['tmp_name']);
            $pratinjau = dosen_pratinjau($_SESSION['impor_dosen']);
        }
    }
    if ($aksi === 'dosen-impor-simpan') {
        csrf_periksa();
        sesi_mulai();
        $csv = (string) ($_SESSION['impor_dosen'] ?? '');
        if ($csv === '') {
            $galat = 'Tidak ada berkas yang menunggu konfirmasi. Unggah ulang.';
        } else {
            $hasil = dosen_impor($csv);
            unset($_SESSION['impor_dosen']);
        }
    }
    require __DIR__ . '/dosen_impor.php';
    return;
}

if ($aksi === 'dosen-form') {
    $id = (int) masuk_input('id');
    $nilai = ['id' => 0, 'jenis' => 'Dosen Tetap', 'prodi' => [$prodiId]];
    $galat = '';
    if ($id > 0) {
        $d = q1('SELECT id, nidn_nidk_nup, nama, jenis, jabatan_fungsional, gelar_akademik,
                        tanggal_lahir, sertifikat_pendidik, profesi, no_str, tanggal_str,
                        tahap_mengajar, email FROM dosen WHERE id = ?', [$id]);
        if (!$d) {
            tolak(404, 'Dosen tidak ditemukan.');
        }
        $d['prodi'] = dosen_prodi_dari($id);
        $nilai = $d;
    }
    require __DIR__ . '/dosen_form.php';
    return;
}

/* daftar */
$cari = masuk_input('q');
$fJenis = masuk_input('jenis');
$fProdi = (int) masuk_input('prodi');

$syarat = ['1=1'];
$param = [];
if ($cari !== '') {
    $syarat[] = '(d.nama LIKE ? OR d.nidn_nidk_nup LIKE ?)';
    $param[] = '%' . $cari . '%';
    $param[] = '%' . $cari . '%';
}
if (in_array($fJenis, DOSEN_JENIS, true)) {
    $syarat[] = 'd.jenis = ?';
    $param[] = $fJenis;
}
if ($fProdi > 0) {
    $syarat[] = 'EXISTS (SELECT 1 FROM dosen_prodi x WHERE x.dosen_id = d.id AND x.prodi_id = ?)';
    $param[] = $fProdi;
}

// GROUP_CONCAT: satu baris per dosen walau mengajar di beberapa prodi.
// Subquery, bukan JOIN, supaya dosen 2 prodi tidak menggandakan baris.
$daftar = q(
    'SELECT d.id, d.nidn_nidk_nup, d.nama, d.jenis, d.jabatan_fungsional, d.profesi,
            d.sertifikat_pendidik, d.tahap_mengajar,
            (SELECT GROUP_CONCAT(p.nama_ps ORDER BY p.nama_ps SEPARATOR " · ")
             FROM dosen_prodi dp JOIN prodi p ON p.id = dp.prodi_id
             WHERE dp.dosen_id = d.id) AS prodi_nama,
            (SELECT GROUP_CONCAT(CONCAT(pd.jenjang, IFNULL(CONCAT(" ", pd.bidang), "")) ORDER BY pd.tahun_lulus DESC SEPARATOR "; ")
             FROM dosen_pendidikan pd WHERE pd.dosen_id = d.id) AS pendidikan
     FROM dosen d WHERE ' . implode(' AND ', $syarat) . '
     ORDER BY d.nama LIMIT 200',   // ponytail: 200 baris; paging kalau UPPS >200 dosen
    $param
);

$hitungJenis = [];
foreach (q('SELECT jenis, COUNT(*) n FROM dosen GROUP BY jenis') as $r) {
    $hitungJenis[$r['jenis']] = (int) $r['n'];
}
$totalDosen = array_sum($hitungJenis);

$isi = '';
$ok = masuk_input('ok');
if ($ok !== '') {
    $isi .= callout('ok', 'Tersimpan', e($ok));
}

$isi .= '<div class="hitung">';
foreach (DOSEN_JENIS as $j) {
    $isi .= sprintf('<a class="hit%s" href="%sindex.php?m=master&amp;a=dosen&amp;jenis=%s"><b>%d</b><span>%s</span></a>',
        $fJenis === $j ? ' on' : '', e(BASE_URL), e(urlencode($j)), $hitungJenis[$j] ?? 0, e($j));
}
$isi .= sprintf('<a class="hit%s" href="%sindex.php?m=master&amp;a=dosen"><b>%d</b><span>Semua</span></a>',
    $fJenis === '' ? ' on' : '', e(BASE_URL), $totalDosen);
$isi .= '</div>';

$opsiProdi = '<option value="0">Semua</option>';
foreach ($petaProdi as $pid => $pn) {
    $opsiProdi .= '<option value="' . (int) $pid . '"' . ($fProdi === $pid ? ' selected' : '') . '>' . e($pn) . '</option>';
}
$formSaring = '<form class="saring" method="get" action="' . e(BASE_URL) . 'index.php">'
    . '<input type="hidden" name="m" value="master"><input type="hidden" name="a" value="dosen">'
    . '<label>Cari nama / NIDN<input type="search" name="q" value="' . e($cari) . '"></label>'
    . '<label>Jenis<select name="jenis"><option value="">Semua</option>' . pilihan(DOSEN_JENIS, $fJenis) . '</select></label>'
    . '<label>Prodi<select name="prodi">' . $opsiProdi . '</select></label>'
    . '<button class="btn btn-primary" type="submit">Saring</button>'
    . '<a class="btn" href="' . e(BASE_URL) . 'index.php?m=master&amp;a=dosen">Atur ulang</a>'
    . '</form>';
$isi .= kartu('', $formSaring);

$kolom = [
    ['kunci' => 'nidn_nidk_nup', 'label' => 'NIDN / NIDK / NUP', 'kelas' => 'mono'],
    ['kunci' => 'nama', 'label' => 'Nama'],
    ['kunci' => 'jenis', 'label' => 'Jenis'],
    ['kunci' => 'jabatan_fungsional', 'label' => 'Jabatan'],
    ['kunci' => 'prodi_nama', 'label' => 'Prodi', 'html' => true],
    ['kunci' => 'pendidikan', 'label' => 'Pendidikan'],
    ['kunci' => 'tahap_mengajar', 'label' => 'Tahap'],
    ['kunci' => 'sertifikat_pendidik', 'label' => 'Serdos'],
    ['kunci' => 'aksi', 'label' => 'Aksi', 'html' => true],
];
$barisTabel = [];
foreach ($daftar as $d) {
    // Satu baris per orang; prodi berbeda dipisah " · " dan diberi badge jumlah.
    $prodiTeks = $d['prodi_nama'] === null ? '' : (string) $d['prodi_nama'];
    $jumlahProdi = $prodiTeks === '' ? 0 : substr_count($prodiTeks, ' · ') + 1;
    $barisTabel[] = [
        'nidn_nidk_nup'      => $d['nidn_nidk_nup'],
        'nama'               => $d['nama'],
        'jenis'              => $d['jenis'],
        'jabatan_fungsional' => $d['jabatan_fungsional'],
        'prodi_nama'         => e($prodiTeks)
            . ($jumlahProdi > 1 ? ' <span class="badge badge-terkunci">' . $jumlahProdi . ' prodi</span>' : ''),
        'pendidikan'         => $d['pendidikan'],
        'tahap_mengajar'     => $d['tahap_mengajar'],
        'sertifikat_pendidik'=> (int) $d['sertifikat_pendidik'] === 1 ? 'Ya' : 'Tidak',
        'aksi'               => '<a class="btn" href="' . e(BASE_URL) . 'index.php?m=master&amp;a=dosen-form&amp;id='
            . (int) $d['id'] . '">' . ($bolehUbah ? 'Ubah' : 'Lihat') . '</a>',
    ];
}

$isi .= kartu('Dosen & Tendik', tabel_html($kolom, $barisTabel), [
    'catatan' => count($daftar) . ' baris · satu orang satu baris walau mengajar di beberapa prodi',
]);

$aksiKanan = '';
if ($bolehUbah) {
    $aksiKanan .= '<a class="btn btn-primary" href="' . e(BASE_URL) . 'index.php?m=master&amp;a=dosen-form">Tambah</a>';
}
if (boleh('IMPOR')) {
    $aksiKanan .= '<a class="btn" href="' . e(BASE_URL) . 'index.php?m=master&amp;a=dosen-impor">Impor CSV</a>';
}
render('Dosen & Tendik', $css . $isi, [
    'aktif' => 'dosen',
    'sub'   => $totalDosen . ' orang terdaftar · ' . count($petaProdi) . ' prodi',
    'aksi'  => $aksiKanan,
]);
