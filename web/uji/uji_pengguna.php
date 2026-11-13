<?php
/**
 * Uji modul pengguna — tanpa HTTP, tanpa server.
 *
 * Yang dibuktikan di sini bukan "halaman tampil", tapi dua jaminan yang kalau
 * hilang membuat sistem terkunci: admin tidak bisa menonaktifkan dirinya
 * sendiri, dan jumlah Admin aktif tidak bisa jadi 0.
 *
 * Jalankan: php web/uji/uji_pengguna.php
 */
declare(strict_types=1);

ob_start();
putenv('LAMPTKES_ENV=lokal');
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTPS'] = '';

require_once __DIR__ . '/../system/view.php';
require_once __DIR__ . '/../modul/pengguna/logika.php';

$gagal = 0;
function cek(string $nama, bool $ok, string $detail = ''): void
{
    global $gagal;
    echo ($ok ? '  ok  ' : '  GAGAL ') . $nama . ($detail !== '' ? " — $detail" : '') . "\n";
    if (!$ok) {
        $gagal++;
    }
}

/** Jalankan kode di proses PHP terpisah — satu-satunya cara menguji tolak() yang exit. */
function subproses(string $kode): string
{
    $f = tempnam(sys_get_temp_dir(), 'ujipgn') . '.php';
    file_put_contents($f, "<?php\ndeclare(strict_types=1);\n" . $kode);
    $out = (string) shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($f) . ' 2>&1');
    @unlink($f);
    return $out;
}

$akar = realpath(__DIR__ . '/..');

/* ── data awal, dicatat supaya bisa dikembalikan persis ─────────── */
$awal = q('SELECT id, peran_kode, prodi_id, aktif FROM pengguna ORDER BY id');
$auditAwal = (int) qv('SELECT COALESCE(MAX(id),0) FROM audit', [], 0);

$admin = q1("SELECT * FROM pengguna WHERE email = 'zulfikar@bbg.ac.id'");
$op    = q1("SELECT * FROM pengguna WHERE email = 'siti@bbg.ac.id'");
cek('akun uji admin & operator ada', $admin !== null && $op !== null);
$adminId = (int) $admin['id'];
$opId    = (int) $op['id'];

try {
    /* ── (a) admin menonaktifkan diri sendiri DITOLAK ───────────── */
    $h = pengguna_ubah($adminId, ['peran_kode' => 'ADMIN', 'prodi_id' => '', 'aktif' => 0], $admin);
    $sesudah = q1('SELECT aktif FROM pengguna WHERE id = ?', [$adminId]);
    cek('(a) admin nonaktifkan diri sendiri ditolak', $h['ok'] === false, json_encode($h));
    cek('(a) pesan menyebut alasannya', $h['ok'] === false
        && str_contains($h['pesan'], 'akun sendiri'), (string) ($h['pesan'] ?? ''));
    cek('(a) akun admin tetap aktif di DB', (int) ($sesudah['aktif'] ?? 0) === 1);
    cek('(a) tidak ada baris audit dari penolakan',
        (int) qv('SELECT COUNT(*) FROM audit WHERE id > ?', [$auditAwal], 0) === 0);

    /* ── (b) admin aktif TERAKHIR tidak boleh hilang ────────────── */
    // Siapkan keadaan: hanya satu admin aktif (admin sementara), dan pelakunya
    // BUKAN admin itu — supaya yang diuji jaminan "jangan sampai 0", bukan
    // jaminan "jangan nonaktifkan diri sendiri".
    $tmpId = sisip('pengguna', [
        'email' => 'uji-admin-sementara@bbg.ac.id', 'nama' => 'Admin Sementara Uji',
        'sandi' => null, 'peran_kode' => 'ADMIN', 'prodi_id' => null, 'aktif' => 1,
    ]);
    ex('UPDATE pengguna SET aktif = 0 WHERE id = ?', [$adminId]);

    $h = pengguna_ubah($tmpId, ['peran_kode' => 'ADMIN', 'prodi_id' => '', 'aktif' => 0], $op);
    cek('(b) nonaktifkan admin aktif terakhir ditolak', $h['ok'] === false, json_encode($h));
    cek('(b) pesan menyebut Admin aktif jadi 0', $h['ok'] === false
        && str_contains($h['pesan'], 'Admin aktif jadi 0'), (string) ($h['pesan'] ?? ''));

    $h2 = pengguna_ubah($tmpId, ['peran_kode' => 'OPERATOR', 'prodi_id' => '', 'aktif' => 1], $op);
    cek('(b) turunkan peran admin terakhir juga ditolak', $h2['ok'] === false, json_encode($h2));
    cek('(b) admin sementara tetap ADMIN & aktif',
        q1('SELECT peran_kode, aktif FROM pengguna WHERE id = ?', [$tmpId])
        === ['peran_kode' => 'ADMIN', 'aktif' => 1]);
    cek('(b) admin aktif masih 1, bukan 0', admin_aktif() === 1, (string) admin_aktif());

    // Kembalikan admin asli lebih dulu supaya sisa uji memakai keadaan normal.
    ex('UPDATE pengguna SET aktif = 1 WHERE id = ?', [$adminId]);
    ex('DELETE FROM pengguna WHERE id = ?', [$tmpId]);
    cek('(b) admin aktif kembali 1 setelah bersih', admin_aktif() === 1, (string) admin_aktif());

    /* ── (c) operator membuka modul pengguna -> 403 ─────────────── */
    $out = subproses(
        'putenv("LAMPTKES_ENV=lokal");' .
        '$_SERVER["REQUEST_METHOD"]="GET"; $_SERVER["HTTPS"]="";' .
        'ob_start();' .
        'session_id("uji-pgn-op"); session_start(); $_SESSION["pengguna_id"]=' . $opId . ';' .
        '$_GET=["m"=>"pengguna","a"=>"index"];' .
        'chdir(' . var_export($akar, true) . ');' .
        'require "index.php";' .
        'ob_end_flush();'
    );
    cek('(c) operator ditolak 403 oleh wajib(KELOLA_PENGGUNA)',
        str_contains($out, 'Peran OPERATOR tidak boleh: KELOLA_PENGGUNA'), trim($out));
    cek('(c) halaman pengguna tidak dirender untuk operator',
        !str_contains($out, 'Matriks izin') && !str_contains($out, '<table'), 'ada markup tabel');

    /* ── (d) ubah peran menulis audit nilai lama & baru ─────────── */
    $h = pengguna_ubah($opId, [
        'peran_kode' => 'VERIFIKATOR', 'prodi_id' => (string) $op['prodi_id'], 'aktif' => 1,
    ], $admin);
    cek('(d) ubah peran berhasil', $h['ok'] === true, json_encode($h));

    $aud = q1("SELECT * FROM audit WHERE entitas = 'pengguna' AND entitas_id = ? AND field = 'peran_kode'
               AND id > ? ORDER BY id DESC LIMIT 1", [(string) $opId, $auditAwal]);
    cek('(d) baris audit peran_kode tertulis', $aud !== null, json_encode($aud));
    cek('(d) aksi = "ubah akun"', ($aud['aksi'] ?? '') === 'ubah akun', (string) ($aud['aksi'] ?? ''));
    cek('(d) nilai_lama = OPERATOR', ($aud['nilai_lama'] ?? '') === 'OPERATOR', (string) ($aud['nilai_lama'] ?? '-'));
    cek('(d) nilai_baru = VERIFIKATOR', ($aud['nilai_baru'] ?? '') === 'VERIFIKATOR', (string) ($aud['nilai_baru'] ?? '-'));
    cek('(d) pelaku tercatat admin', (int) ($aud['pengguna_id'] ?? 0) === $adminId);
    cek('(d) peran di DB benar-benar berubah',
        qv('SELECT peran_kode FROM pengguna WHERE id = ?', [$opId]) === 'VERIFIKATOR');

    // Ubah ke nilai yang sama -> tidak menulis audit palsu.
    $n0 = (int) qv('SELECT COUNT(*) FROM audit', [], 0);
    $h = pengguna_ubah($opId, ['peran_kode' => 'VERIFIKATOR', 'prodi_id' => (string) $op['prodi_id'], 'aktif' => 1], $admin);
    cek('(d) simpan tanpa perubahan tidak menulis audit',
        $h['ok'] === true && $h['berubah'] === 0 && (int) qv('SELECT COUNT(*) FROM audit', [], 0) === $n0,
        json_encode($h));

    cek('(d) prodi palsu ditolak', pengguna_ubah($opId,
        ['peran_kode' => 'OPERATOR', 'prodi_id' => '999999', 'aktif' => 1], $admin)['ok'] === false);
    cek('(d) peran palsu ditolak', pengguna_ubah($opId,
        ['peran_kode' => 'SUPERUSER', 'prodi_id' => '', 'aktif' => 1], $admin)['ok'] === false);

    /* ── (e) matriks izin: 24 sel, cocok dengan DB ──────────────── */
    cek('(e) matriks = 24 baris', count(izin_matriks()) === 24, (string) count(izin_matriks()));
    cek('(e) 4 peran x 6 aksi', count(peran_peta()) === 4 && count(AKSI) === 6,
        count(peran_peta()) . ' x ' . count(AKSI));

    $html = subproses(
        'putenv("LAMPTKES_ENV=lokal");' .
        '$_SERVER["REQUEST_METHOD"]="GET"; $_SERVER["HTTPS"]="";' .
        'ob_start();' .
        'session_id("uji-pgn-adm"); session_start(); $_SESSION["pengguna_id"]=' . $adminId . ';' .
        '$_GET=["m"=>"pengguna","a"=>"index"];' .
        'chdir(' . var_export($akar, true) . ');' .
        'require "index.php";' .
        'ob_end_flush();'
    );
    $n = preg_match_all('/<td class="iz iz-(\d)"/', $html, $m);
    cek('(e) halaman admin menampilkan 24 sel izin', $n === 24, (string) $n);

    $db = [];
    foreach (izin_matriks() as $r) {
        $db[] = (int) $r['akses'];
    }
    $tampil = array_map('intval', $m[1] ?? []);
    cek('(e) urutan & nilai sel = tabel izin', $tampil === $db,
        'tampil ' . implode('', $tampil) . ' vs DB ' . implode('', $db));
    $potong = '';
    if (preg_match('/Matriks izin<\/h2>.*?(?=<section class="card">|<\/main>)/s', $html, $mm)) {
        $potong = $mm[0];
    }
    cek('(e) blok matriks ditemukan', $potong !== '' && str_contains($potong, 'iz-0'));
    cek('(e) matriks read-only: tidak ada input/select/textarea di blok matriks',
        $potong !== '' && !preg_match('/<(input|select|textarea|button)\b/i', $potong),
        'ada kontrol di blok matriks');
    cek('(e) UI menyatakan matriks mengikat server',
        str_contains($html, 'mengikat server') && str_contains($html, 'wajib()'));
    cek('(e) legenda 0/1/2 ada',
        str_contains($html, 'Tidak boleh') && str_contains($html, 'Hanya lihat'));

    /* ── daftar & saringan ──────────────────────────────────────── */
    cek('daftar semua akun', count(pengguna_daftar()) === (int) qv('SELECT COUNT(*) FROM pengguna'));
    $adm = pengguna_daftar('ADMIN', '');
    cek('saringan peran ADMIN', count($adm) >= 1
        && count(array_filter($adm, fn($r) => $r['peran_kode'] !== 'ADMIN')) === 0);
    $non = pengguna_daftar('', '0');
    cek('saringan status nonaktif', count(array_filter($non, fn($r) => (int) $r['aktif'] !== 0)) === 0);
    cek('akun tanpa prodi tampil sebagai semua prodi',
        str_contains($html, 'semua prodi'), 'label prodi kosong tidak ada');
    cek('tombol nonaktifkan, bukan hapus',
        str_contains($html, 'Nonaktifkan') && !str_contains($html, '>Hapus<'));
} finally {
    /* ── kembalikan data ke kondisi awal ───────────────────────── */
    foreach ($awal as $r) {
        ex('UPDATE pengguna SET peran_kode = ?, prodi_id = ?, aktif = ? WHERE id = ?',
            [$r['peran_kode'], $r['prodi_id'], $r['aktif'], $r['id']]);
    }
    ex('DELETE FROM pengguna WHERE email = ?', ['uji-admin-sementara@bbg.ac.id']);
    ex('DELETE FROM audit WHERE id > ?', [$auditAwal]);
}

cek('data kembali ke kondisi awal',
    q('SELECT id, peran_kode, prodi_id, aktif FROM pengguna ORDER BY id') === $awal);
cek('audit kembali ke kondisi awal',
    (int) qv('SELECT COUNT(*) FROM audit WHERE id > ?', [$auditAwal], 0) === 0);
cek('jumlah admin aktif seperti semula',
    admin_aktif() === count(array_filter($awal, fn($r) => $r['peran_kode'] === 'ADMIN' && (int) $r['aktif'] === 1)));

while (ob_get_level() > 0) { ob_end_flush(); }
echo $gagal === 0 ? "\nSEMUA UJI LULUS\n" : "\n$gagal UJI GAGAL\n";
exit($gagal === 0 ? 0 : 1);
