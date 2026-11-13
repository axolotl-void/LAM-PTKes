<?php
/**
 * Uji modul Data Dukung — tanpa HTTP, panggil fungsi langsung.
 *
 * Bagian akhir memakai subproses karena gerbang izin memanggil tolak() yang
 * `exit`. Di dalam satu proses, satu uji 403 akan menghentikan seluruh uji.
 *
 * Jalankan: php web/uji/uji_tabel.php
 */
declare(strict_types=1);

ob_start();
putenv('LAMPTKES_ENV=lokal');
$_SERVER['REQUEST_METHOD'] = 'GET';
require_once __DIR__ . '/../modul/tabel/proses.php';

$gagal = 0;
function cek(string $nama, bool $ok, string $detail = ''): void
{
    global $gagal;
    echo ($ok ? '  ok  ' : '  GAGAL ') . $nama . ($detail !== '' ? " — $detail" : '') . "\n";
    if (!$ok) {
        $gagal++;
    }
}

$prodi = (int) qv('SELECT id FROM prodi ORDER BY id LIMIT 1');
$per = periode_aktif($prodi);
cek('periode aktif ada', $per !== null);
$perId = (int) ($per['id'] ?? 0);
$op = q1('SELECT * FROM pengguna WHERE email = ?', ['siti@bbg.ac.id']);
$vf = q1('SELECT * FROM pengguna WHERE email = ?', ['maimunah@bbg.ac.id']);
$admin = q1('SELECT * FROM pengguna WHERE email = ?', ['zulfikar@bbg.ac.id']);
cek('akun uji lengkap', $op && $vf && $admin);

/** Bersihkan semua jejak uji untuk tabel-tabel yang dipakai. */
function bersih(array $kode, int $prodi, int $perId): void
{
    foreach ($kode as $k) {
        ex('DELETE FROM baris_tabel WHERE tabel_kode = ? AND prodi_id = ? AND periode_id = ?',
            [$k, $prodi, $perId]);
    }
}

$kodeUji = ['01', '02'];
bersih($kodeUji, $prodi, $perId);

/* ── 1. spesifikasi: hanya field Input yang boleh disimpan ───────── */
$f01 = tabel_field('01');
$input01 = array_values(array_filter($f01, fn($f) => $f['asal'] === 'Input'));
$lain01 = array_values(array_filter($f01, fn($f) => $f['asal'] !== 'Input'));
cek('tabel 01 punya 3 field Input', count($input01) === 3, (string) count($input01));
cek('tabel 01 punya 9 field non-Input', count($lain01) === 9, (string) count($lain01));
cek('12 field tabel 01 = 3 + 9', count($f01) === 12, (string) count($f01));
$urut01 = array_map('intval', array_column($f01, 'urut'));
$urutUrut = $urut01;
sort($urutUrut);
cek('field urut menaik', $urut01 === $urutUrut, implode(',', $urut01));

/* ── 2. isi tabel 01 lengkap -> status 'lengkap' ─────────────────── */
$post = ['sel' => [1 => [
    'no' => '1',
    'praktikum_judul_modul' => 'Asuhan Persalinan Normal',
    'praktikum_jam_pelaksanaan' => '4',
    // Field non-Input sengaja dikirim: harus DIABAIKAN, bukan disimpan.
    'kode_dan_nama_mk' => 'BDN101',
    'rps' => 'ada',
]]];
$hasil = tabel_simpan('01', $prodi, $perId, $post);
$s = status_satu_tabel('01', $prodi, $perId);
cek('simpan 1 baris', $hasil['baris'] === 1, json_encode($hasil));
cek('3 sel tersimpan (field non-Input diabaikan)', $hasil['sel'] === 3, json_encode($hasil));
cek('status jadi lengkap', $s['status'] === 'lengkap', json_encode($s));
cek('terisi = total (3/3)', $s['terisi'] === 3 && $s['total'] === 3, "{$s['terisi']}/{$s['total']}");

$bid = (int) qv('SELECT id FROM baris_tabel WHERE tabel_kode = ? AND prodi_id = ? AND periode_id = ? AND urut = 1',
    ['01', $prodi, $perId]);
$kunciTersimpan = q('SELECT field_kunci FROM sel WHERE baris_id = ? ORDER BY field_kunci', [$bid]);
cek('hanya kunci field Input yang tersimpan',
    array_column($kunciTersimpan, 'field_kunci') === ['no', 'praktikum_jam_pelaksanaan', 'praktikum_judul_modul'],
    implode(',', array_column($kunciTersimpan, 'field_kunci')));

// tipe angka -> nilai_angka terisi, nilai_teks NULL
$angka = q1('SELECT nilai_angka, nilai_teks FROM sel WHERE baris_id = ? AND field_kunci = ?',
    [$bid, 'praktikum_jam_pelaksanaan']);
cek('field angka masuk nilai_angka', $angka !== null && (float) $angka['nilai_angka'] === 4.0
    && $angka['nilai_teks'] === null, json_encode($angka));

// tipe Teks -> nilai_teks, bukan nilai_angka
$teks = q1('SELECT nilai_angka, nilai_teks FROM sel WHERE baris_id = ? AND field_kunci = ?', [$bid, 'no']);
cek('field Teks masuk nilai_teks', $teks !== null && $teks['nilai_teks'] === '1'
    && $teks['nilai_angka'] === null, json_encode($teks));

/* ── 3. simpan ulang = update, bukan duplikat ───────────────────── */
tabel_simpan('01', $prodi, $perId, ['sel' => [1 => ['no' => '1b']]]);
cek('simpan ulang tidak menambah baris',
    (int) qv('SELECT COUNT(*) FROM baris_tabel WHERE tabel_kode = ? AND prodi_id = ? AND periode_id = ?',
        ['01', $prodi, $perId]) === 1);
cek('nilai diperbarui',
    qv('SELECT nilai_teks FROM sel WHERE baris_id = ? AND field_kunci = ?', [$bid, 'no']) === '1b');
cek('sel lain tidak hilang',
    (int) qv('SELECT COUNT(*) FROM sel WHERE baris_id = ?', [$bid]) === 3);

/* ── 4. sel dikosongkan -> DIHAPUS, bukan disimpan kosong ───────── */
tabel_simpan('01', $prodi, $perId, ['sel' => [1 => ['no' => '   ']]]);
cek('sel kosong dihapus dari tabel', qv('SELECT COUNT(*) FROM sel WHERE baris_id = ? AND field_kunci = ?',
    [$bid, 'no']) === 0);
cek('tidak ada sel bernilai string kosong',
    (int) qv('SELECT COUNT(*) FROM sel WHERE baris_id = ? AND nilai_teks = ""', [$bid]) === 0);
$s = status_satu_tabel('01', $prodi, $perId);
cek('hapus 1 sel -> sebagian', $s['status'] === 'sebagian' && $s['terisi'] === 2, json_encode($s));

/* ── 5. banyak baris (tabel 1 = 239 baris) ─────────────────────── */
$banyak = ['sel' => []];
for ($i = 2; $i <= 5; $i++) {
    $banyak['sel'][$i] = ['no' => (string) $i, 'praktikum_judul_modul' => 'Modul ' . $i];
}
$h = tabel_simpan('01', $prodi, $perId, $banyak);
cek('tambah 4 baris sekaligus', $h['baris'] === 4, json_encode($h));
cek('5 baris tersimpan',
    (int) qv('SELECT COUNT(*) FROM baris_tabel WHERE tabel_kode = ? AND prodi_id = ? AND periode_id = ?',
        ['01', $prodi, $perId]) === 5);
$barisDibaca = tabel_baris('01', $prodi, $perId);
cek('tabel_baris mengembalikan 5 baris urut', count($barisDibaca) === 5
    && array_column($barisDibaca, 'urut') === [1, 2, 3, 4, 5]);
cek('nilai ikut terbaca per baris',
    ($barisDibaca[1]['nilai']['praktikum_judul_modul']['nilai_teks'] ?? null) === 'Modul 2');

// hapus baris lewat daftar hapus[]
$h = tabel_simpan('01', $prodi, $perId, ['hapus' => ['5']]);
cek('hapus baris 5', $h['dibuang'] === 1 && count(tabel_baris('01', $prodi, $perId)) === 4, json_encode($h));
cek('sel baris terhapus ikut hilang (cascade)',
    (int) qv('SELECT COUNT(*) FROM sel s JOIN baris_tabel b ON b.id = s.baris_id
              WHERE b.tabel_kode = ? AND b.prodi_id = ? AND b.periode_id = ? AND b.urut = 5',
        ['01', $prodi, $perId]) === 0);

// hapus[] hanya menyebut baris yang dibuang -> baris lain tidak tersentuh
$h = tabel_simpan('01', $prodi, $perId, ['hapus' => ['0', 'x'], 'sel' => [4 => ['no' => '4']]]);
cek('hapus[] tanpa nomor sah tidak menghapus apa pun',
    $h['dibuang'] === 0 && count(tabel_baris('01', $prodi, $perId)) === 4);

/* ── 6. validasi tipe ───────────────────────────────────────────── */
$tolakAngka = null;
try {
    tabel_simpan('01', $prodi, $perId, ['sel' => [1 => ['praktikum_jam_pelaksanaan' => 'empat']]]);
} catch (TabelDitolak $x) {
    $tolakAngka = $x;
}
cek('angka salah ditolak 422', $tolakAngka !== null && $tolakAngka->kodeHttp === 422,
    $tolakAngka ? $tolakAngka->getMessage() : 'tidak melempar');
cek('angka salah tidak menyimpan apa pun',
    qv('SELECT nilai_angka FROM sel WHERE baris_id = ? AND field_kunci = ?',
        [$bid, 'praktikum_jam_pelaksanaan']) !== null);

// desimal koma diterima (operator Indonesia mengetik 3,5)
tabel_simpan('01', $prodi, $perId, ['sel' => [1 => ['praktikum_jam_pelaksanaan' => '3,5']]]);
cek('desimal koma diterima', (float) qv('SELECT nilai_angka FROM sel WHERE baris_id = ? AND field_kunci = ?',
    [$bid, 'praktikum_jam_pelaksanaan']) === 3.5);

$tgl = tabel_tgl_ke_angka('2025-11-13');
cek('tanggal -> YYYYMMDD angka', $tgl === 20251113, (string) $tgl);
cek('YYYYMMDD -> tanggal kembali', tabel_angka_ke_tgl('20251113') === '2025-11-13');
cek('tanggal mustahil ditolak', (function (): bool {
    try { tabel_tgl_ke_angka('2025-02-30'); return false; } catch (TabelDitolak $x) { return $x->kodeHttp === 422; }
})());

// tanggal disimpan di nilai_angka lewat simpan
$fTanggal = qv("SELECT tabel_kode FROM spec_field WHERE tipe = 'tanggal' AND asal = 'Input' LIMIT 1");
if ($fTanggal !== null) {
    $ff = q1("SELECT kunci FROM spec_field WHERE tabel_kode = ? AND tipe = 'tanggal' AND asal = 'Input' LIMIT 1",
        [$fTanggal]);
    tabel_simpan((string) $fTanggal, $prodi, $perId, ['sel' => [1 => [$ff['kunci'] => '2024-03-01']]]);
    cek('tanggal tersimpan sebagai angka di nilai_angka',
        (float) qv('SELECT s.nilai_angka FROM sel s JOIN baris_tabel b ON b.id = s.baris_id
                    WHERE b.tabel_kode = ? AND b.prodi_id = ? AND b.periode_id = ? AND s.field_kunci = ?',
            [$fTanggal, $prodi, $perId, $ff['kunci']]) === 20240301.0, 'tabel ' . $fTanggal);
    bersih([(string) $fTanggal], $prodi, $perId);
}

/* ── 7. tabel 02 (hanya 1 field Input) tetap aturan yang sama ───── */
$h = tabel_simpan('02', $prodi, $perId, ['sel' => [1 => ['no' => '1', 'jumlah_mahasiswa' => '999']]]);
cek('Hitung tidak tersimpan di tabel 02', $h['sel'] === 1
    && qv('SELECT COUNT(*) FROM sel s JOIN baris_tabel b ON b.id = s.baris_id
           WHERE b.tabel_kode = ? AND b.prodi_id = ? AND b.periode_id = ? AND s.field_kunci = ?',
        ['02', $prodi, $perId, 'jumlah_mahasiswa']) === 0, json_encode($h));

/* ── 8. kunci ───────────────────────────────────────────────────── */
// isi semua field Input dulu supaya lengkap
$semua = [];
foreach ($input01 as $f) {
    $semua[$f['kunci']] = $f['tipe'] === 'angka' ? '2' : 'x';
}
foreach ([1, 2, 3, 4] as $u) {
    tabel_simpan('01', $prodi, $perId, ['sel' => [$u => $semua]]);
}
cek('lengkap sebelum dikunci', status_satu_tabel('01', $prodi, $perId)['status'] === 'lengkap');

$n = tabel_kunci('01', $prodi, $perId, (int) $admin['id']);
cek('kunci menulis semua baris', $n === 4, (string) $n);
cek('status jadi terkunci', status_satu_tabel('01', $prodi, $perId)['status'] === 'terkunci');

$ditolak = null;
try {
    tabel_simpan('01', $prodi, $perId, ['sel' => [1 => ['no' => '99']]]);
} catch (TabelDitolak $x) {
    $ditolak = $x;
}
cek('tabel terkunci menolak isi (403)', $ditolak !== null && $ditolak->kodeHttp === 403,
    $ditolak ? $ditolak->getMessage() : 'tidak melempar');
cek('isi saat terkunci tidak berubah',
    qv('SELECT nilai_teks FROM sel WHERE baris_id = ? AND field_kunci = ?', [$bid, 'no']) === 'x');

// kunci tabel yang belum lengkap -> 409, bukan diam-diam terkunci
bersih(['02'], $prodi, $perId);
$ditolak = null;
try {
    tabel_kunci('02', $prodi, $perId, (int) $admin['id']);
} catch (TabelDitolak $x) {
    $ditolak = $x;
}
cek('kunci saat belum lengkap ditolak 409', $ditolak !== null && $ditolak->kodeHttp === 409,
    $ditolak ? $ditolak->getMessage() : 'tidak melempar');

tabel_buka('01', $prodi, $perId);
cek('buka kunci mengembalikan status sebagian/lengkap',
    in_array(status_satu_tabel('01', $prodi, $perId)['status'], ['sebagian', 'lengkap'], true));

/* ── 9. tabel tak dikenal & tidak berlaku ───────────────────────── */
$ditolak = null;
try { tabel_info('99'); } catch (TabelDitolak $x) { $ditolak = $x; }
cek('kode tabel tak dikenal -> 404', $ditolak !== null && $ditolak->kodeHttp === 404);

ex('UPDATE prodi SET punya_tahap_profesi = 0 WHERE id = ?', [$prodi]);
$ditolak = null;
try {
    tabel_simpan('30', $prodi, $perId, ['sel' => [1 => ['no' => '1']]]);
} catch (TabelDitolak $x) {
    $ditolak = $x;
}
cek('tabel khusus profesi ditolak bila prodi tanpa tahap profesi',
    $ditolak !== null && $ditolak->kodeHttp === 409, $ditolak ? $ditolak->getMessage() : 'tidak melempar');
ex('UPDATE prodi SET punya_tahap_profesi = 1 WHERE id = ?', [$prodi]);

/* ── 10. gerbang izin (tabel `izin`, bukan tebakan) ─────────────── */
cek('operator boleh ISI', akses('ISI', $op) === 1);
cek('verifikator tidak boleh ISI', akses('ISI', $vf) === 0);
cek('verifikator boleh VERIFIKASI', akses('VERIFIKASI', $vf) === 1);
cek('operator tidak boleh BUKA_KUNCI', akses('BUKA_KUNCI', $op) === 0);
cek('admin boleh BUKA_KUNCI', akses('BUKA_KUNCI', $admin) === 1);

/* ── 11. gerbang di modul, lewat subproses (tolak() = exit) ─────── */
/**
 * Jalankan modul sebagai satu request CLI: sesi disuntik, POST disiapkan.
 * Dipisah proses karena wajib() memanggil tolak() yang meng-exit proses.
 */
function jalankan(array $sesi, array $get, array $post): string
{
    $berkas = tempnam(sys_get_temp_dir(), 'ujitabel') . '.php';
    file_put_contents($berkas, '<?php
declare(strict_types=1);
ob_start();
putenv("LAMPTKES_ENV=lokal");
$_SERVER["REQUEST_METHOD"] = ' . var_export($post ? 'POST' : 'GET', true) . ';
require "' . __DIR__ . '/../system/view.php";
sesi_mulai();
$_SESSION = ' . var_export($sesi, true) . ';
$_GET = ' . var_export($get, true) . ';
$_POST = ' . var_export($post, true) . ';
require "' . __DIR__ . '/../modul/tabel/index.php";
while (ob_get_level() > 0) { ob_end_flush(); }
');
    $keluar = shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($berkas) . ' 2>&1');
    @unlink($berkas);
    return (string) $keluar;
}

$csrf = 'ujicsrftoken0123456789abcdef';

// verifikator mencoba mengisi -> 403
$out = jalankan(
    ['pengguna_id' => (int) $vf['id'], 'prodi_id' => (int) $vf['prodi_id'], 'csrf' => $csrf],
    ['m' => 'tabel', 'a' => 'simpan'],
    ['_csrf' => $csrf, 't' => '01', 'sel' => [1 => ['no' => '7']]]
);
cek('verifikator ditolak saat isi (403 di modul)', str_contains($out, 'tidak boleh: ISI'), substr(trim($out), 0, 160));

// operator tanpa token CSRF -> 419
$out = jalankan(
    ['pengguna_id' => (int) $op['id'], 'prodi_id' => (int) $op['prodi_id'], 'csrf' => $csrf],
    ['m' => 'tabel', 'a' => 'simpan'],
    ['_csrf' => 'salah', 't' => '01', 'sel' => [1 => ['no' => '7']]]
);
cek('CSRF salah ditolak 419', str_contains($out, 'Token form kedaluwarsa'), substr(trim($out), 0, 160));

// operator mencoba kunci -> 403
$out = jalankan(
    ['pengguna_id' => (int) $op['id'], 'prodi_id' => (int) $op['prodi_id'], 'csrf' => $csrf],
    ['m' => 'tabel', 'a' => 'kunci', 't' => '01'],
    ['_csrf' => $csrf, 't' => '01']
);
cek('operator tidak boleh mengunci (403)', str_contains($out, 'tidak boleh: VERIFIKASI'), substr(trim($out), 0, 160));

// operator mengisi saat baris terkunci -> ditolak. Penolakan lewat alihkan()
// (header + exit), dan header tidak terbaca di CLI — jadi bukti diambil dari
// datanya: tidak ada sel yang berubah.
tabel_kunci('01', $prodi, $perId, (int) $admin['id']);
jalankan(
    ['pengguna_id' => (int) $op['id'], 'prodi_id' => (int) $op['prodi_id'], 'csrf' => $csrf],
    ['m' => 'tabel', 'a' => 'simpan'],
    ['_csrf' => $csrf, 't' => '01', 'sel' => [1 => ['no' => '8']]]
);
cek('simpan lewat modul saat terkunci tidak mengubah data',
    qv('SELECT nilai_teks FROM sel WHERE baris_id = ? AND field_kunci = ?', [$bid, 'no']) === 'x'
    && count(tabel_baris('01', $prodi, $perId)) === 4);
tabel_buka('01', $prodi, $perId);

// halaman daftar & form benar-benar merender (bukan fatal error)
$out = jalankan(
    ['pengguna_id' => (int) $op['id'], 'prodi_id' => (int) $op['prodi_id'], 'csrf' => $csrf],
    ['m' => 'tabel', 'a' => 'index'],
    []
);
cek('daftar 38 tabel merender', str_contains($out, '38 tabel instrumen')
    && substr_count($out, 'class="tf-nomor num"') === 38, 'jumlah baris: ' . substr_count($out, 'class="tf-nomor num"'));

$out = jalankan(
    ['pengguna_id' => (int) $op['id'], 'prodi_id' => (int) $op['prodi_id'], 'csrf' => $csrf],
    ['m' => 'tabel', 'a' => 'index', 'k' => 'K1'],
    []
);
cek('saringan K1 jujur: tidak punya tabel', str_contains($out, 'tidak punya tabel data dukung'));

// form kosong: 1 baris draf + 1 template, 3 field Input per baris
bersih(['01'], $prodi, $perId);
$out = jalankan(
    ['pengguna_id' => (int) $op['id'], 'prodi_id' => (int) $op['prodi_id'], 'csrf' => $csrf],
    ['m' => 'tabel', 'a' => 'lihat', 't' => '01'],
    []
);
$nKontrol = substr_count($out, 'class="tf-input"');
$nBaris = substr_count($out, '<fieldset class="tf-baris');
// 9 field Master tabel 01 muncul sekali di rincian field + sekali per baris
$nMaster = substr_count($out, 'tf-sumber tf-master');
cek('form tabel 01: 3 kontrol isian per baris', $nBaris > 0 && $nKontrol === 3 * $nBaris,
    "kontrol $nKontrol, baris $nBaris");
cek('field non-Input tampil read-only dengan label sumber', $nMaster === 9 * ($nBaris + 1),
    "master $nMaster, baris $nBaris");
cek('template baris baru tersedia', str_contains($out, 'id="tfTemplate"'));

/* ── 12. tabel panjang: 239 baris tidak boleh dirender sekaligus ── */
// max_input_vars PHP memotong POST diam-diam (~1000 field). Tabel 38 punya 9
// field Input, jadi 239 baris x 9 = 2151 field: tanpa batas, isian terakhir
// hilang tanpa pesan. Halaman harus dipecah.
$banyak = ['sel' => []];
for ($i = 1; $i <= 120; $i++) {
    $banyak['sel'][$i] = ['no' => (string) $i];
}
tabel_simpan('01', $prodi, $perId, $banyak);
cek('120 baris tersimpan', count(tabel_baris('01', $prodi, $perId)) === 120);

$out = jalankan(
    ['pengguna_id' => (int) $op['id'], 'prodi_id' => (int) $op['prodi_id'], 'csrf' => $csrf],
    ['m' => 'tabel', 'a' => 'lihat', 't' => '01'],
    []
);
// template baris baru juga berupa <fieldset class="tf-baris" — jangan dihitung
$nBarisHal1 = substr_count($out, '<fieldset class="tf-baris') - 1;
cek('halaman 1 hanya 50 baris', $nBarisHal1 === TABEL_PER_HAL, (string) $nBarisHal1);
cek('ada navigasi halaman (120/50 = 3)', str_contains($out, 'halaman 1 dari 3')
    && substr_count($out, '&amp;hal=') === 3);

$out = jalankan(
    ['pengguna_id' => (int) $op['id'], 'prodi_id' => (int) $op['prodi_id'], 'csrf' => $csrf],
    ['m' => 'tabel', 'a' => 'lihat', 't' => '01', 'hal' => 3],
    []
);
cek('halaman 3 berisi 20 baris sisa', substr_count($out, '<fieldset class="tf-baris') - 1 === 20,
    (string) (substr_count($out, '<fieldset class="tf-baris') - 1));
cek('hal di luar jangkauan dijepit, bukan halaman kosong',
    str_contains($out, 'halaman 3 dari 3'));
$out = jalankan(
    ['pengguna_id' => (int) $op['id'], 'prodi_id' => (int) $op['prodi_id'], 'csrf' => $csrf],
    ['m' => 'tabel', 'a' => 'lihat', 't' => '01', 'hal' => 999],
    []
);
cek('hal=999 jatuh ke halaman terakhir', str_contains($out, 'halaman 3 dari 3'));

// baris di halaman 3 bisa disimpan tanpa menyentuh baris halaman 1
ex('DELETE FROM sel WHERE baris_id = (SELECT id FROM (SELECT id FROM baris_tabel WHERE tabel_kode = ? AND prodi_id = ? AND periode_id = ? AND urut = 101) x)', ['01', $prodi, $perId]);
tabel_simpan('01', $prodi, $perId, ['sel' => [101 => ['no' => '101b']]]);
cek('simpan 1 baris halaman 3 tidak mengubah baris lain',
    qv('SELECT nilai_teks FROM sel WHERE baris_id = (SELECT id FROM baris_tabel WHERE tabel_kode = ? AND prodi_id = ? AND periode_id = ? AND urut = 101) AND field_kunci = ?',
        ['01', $prodi, $perId, 'no']) === '101b'
    && count(tabel_baris('01', $prodi, $perId)) === 120);
bersih(['01'], $prodi, $perId);
cek('JS tambah baris dimuat', str_contains($out, 'aset/js/tabel.js'));

// tabel 05: semua field non-Input -> peringatan, bukan form kosong tanpa penjelasan
$out = jalankan(
    ['pengguna_id' => (int) $op['id'], 'prodi_id' => (int) $op['prodi_id'], 'csrf' => $csrf],
    ['m' => 'tabel', 'a' => 'lihat', 't' => '05'],
    []
);
cek('tabel tanpa field Input memberi penjelasan', str_contains($out, 'Tidak ada field isian'));

/* ── bersihkan ──────────────────────────────────────────────────── */
bersih(['01', '02'], $prodi, $perId);
cek('data uji bersih: 0 baris tabel 01/02',
    (int) qv('SELECT COUNT(*) FROM baris_tabel WHERE tabel_kode IN ("01","02") AND prodi_id = ? AND periode_id = ?',
        [$prodi, $perId]) === 0);
cek('data uji bersih: 0 sel yatim',
    (int) qv('SELECT COUNT(*) FROM sel s LEFT JOIN baris_tabel b ON b.id = s.baris_id WHERE b.id IS NULL') === 0);
// Agent lain mengisi tabel lain di DB yang sama; yang diperiksa hanya tabel
// milik uji ini.
cek('tabel 01/02 kembali kosong', status_satu_tabel('01', $prodi, $perId)['status'] === 'kosong'
    && status_satu_tabel('02', $prodi, $perId)['status'] === 'kosong');

while (ob_get_level() > 0) { ob_end_flush(); }
echo $gagal === 0 ? "\nSEMUA UJI LULUS\n" : "\n$gagal UJI GAGAL\n";
exit($gagal === 0 ? 0 : 1);
