<?php
declare(strict_types=1);

/**
 * Uji tombol keluar — dari HTML sampai sesi mati.
 *
 * BUG YANG DIJAGA: tombol "Keluar" tampil rapi, diklik, dan TIDAK TERJADI
 * APA-APA. Penyebabnya bukan server (POST /api/keluar sudah benar sejak awal),
 * tapi selektor di app.js mencari `[data-keluar]` sementara index.html — desain
 * asli Rahmat — memberi `id="btnKeluar"`. Tidak ada error di konsol, tidak ada
 * pesan: tombolnya hanya diam.
 *
 * Kenapa tidak ada uji lain yang menangkapnya: `node --check` hanya memeriksa
 * sintaks, `uji_sso`/`uji_pengguna` menguji server, dan render halaman tetap
 * 100% benar. Yang salah cuma satu atribut yang tidak pernah bertemu.
 *
 * Yang dijaga uji ini:
 *  1. Tombol di index.html ada dan punya id yang dicari app.js.
 *  2. app.js mengenali SELURUH tombol keluar yang ada di HTML — bukan hanya
 *     yang kebetulan cocok dengan selector lama.
 *  3. Server: /api/keluar mematikan sesi; sesudahnya /api/saya bilang null.
 *  4. Gagal jaringan TIDAK boleh membuat UI berpura-pura sudah keluar.
 */

require_once __DIR__ . '/../system/config.php';
require_once __DIR__ . '/../system/db.php';
require_once __DIR__ . '/../system/auth.php';

$gagal = 0;
$lulus = 0;

function cek(string $nama, bool $ok, string $ket = ''): void
{
    global $gagal, $lulus;
    if ($ok) {
        $lulus++;
        printf("  ok    %s\n", $nama);
    } else {
        $gagal++;
        printf("  GAGAL %s%s\n", $nama, $ket !== '' ? " — $ket" : '');
    }
}

/** Permintaan HTTP dengan cookie jar sendiri. Mengembalikan isi + kode. */
function http_minta(string $metode, string $path, ?array $isi, string $cookie): array
{
    $kh = [];
    $curl = curl_init('http://127.0.0.1:8005' . $path);
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_COOKIEJAR => $cookie,
        CURLOPT_COOKIEFILE => $cookie,
        CURLOPT_CUSTOMREQUEST => $metode,
        CURLOPT_TIMEOUT => 5,
    ]);
    if ($isi !== null) {
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($isi));
        curl_setopt($curl, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    }
    $badan = curl_exec($curl);
    $kode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    return ['isi' => (string) $badan, 'kode' => $kode];
}

$akar = dirname(__DIR__, 2);
$html = (string) file_get_contents($akar . '/app/index.html');
$app = (string) file_get_contents($akar . '/app/assets/js/app.js');

echo "── tombol di HTML ──\n";

// Semua elemen yang secara makna adalah tombol keluar.
preg_match_all('/<button[^>]*>[^<]*Keluar[^<]*<\/button>/i', $html, $mb);
$tombol = $mb[0] ?? [];
cek('ada tombol "Keluar" di index.html', $tombol !== [], json_encode($tombol));

$id = [];
foreach ($tombol as $t) {
    if (preg_match('/id="([^"]+)"/', $t, $m)) { $id[] = $m[1]; }
}
cek('tombol keluar punya id', $id !== [], json_encode($tombol));

// Inilah bug-nya: app.js mencari atribut yang tidak ada di HTML.
$htmlPunyaDataKeluar = str_contains($html, 'data-keluar');
$appCariDataKeluar = str_contains($app, "[data-keluar]");
// Cari selector di dalam closest(...) pada handler keluar — bukan sekadar
// "#btnKeluar ada di file ini". Versi longgar lolos padahal tombolnya mati.
preg_match("/const keluar = e\.target\.closest\(([^)]*)\)/", $app, $ms);
$selector = $ms[1] ?? '';
$appCariId = false;
foreach ($id as $i) {
    if (str_contains($selector, '#' . $i)) { $appCariId = true; }
}

cek('app.js mengenali id tombol yang ada di HTML', $appCariId,
    'id=' . implode(',', $id) . ' · app.js cari #' . implode(',#', $id) . ' = '
    . var_export($appCariId, true));
// app.js boleh tetap menyebut [data-keluar] sebagai cadangan, ASAL id dari
// HTML ikut ditangkap. Yang dilarang: HANYA mengandalkan atribut yang tidak
// ada di HTML — itulah bug aslinya.
cek('selector handler keluar menangkap id dari HTML', $appCariId,
    'selector = closest(' . $selector . ') · id di HTML = ' . implode(',', $id));

echo "\n── penanganan galat ──\n";

// Server mati: api.keluar() mengembalikan null. UI harus jujur.
preg_match('/const keluar = e\.target\.closest\(.*?\n    \}/s', $app, $mh);
$blok = $mh[0] ?? '';
cek('blok handler keluar ditemukan', $blok !== '');
cek('periksa hasil null (galat jaringan)', str_contains($blok, 'r === null')
    || str_contains($blok, 'r === undefined'),
    'tanpa ini UI berpura-pura sudah keluar padahal sesi server masih hidup');
cek('menyembunyikan tombol keluar setelah sesi hilang',
    str_contains($blok, "setAttribute('hidden'") || str_contains($blok, 'setAttribute("hidden"'),
    'tombol keluar milik sesi; tanpa sesi ia tidak punya arti');

echo "\n── server: sesi benar-benar mati ──\n";

$email = (string) qv('SELECT email FROM pengguna WHERE aktif = 1 ORDER BY id LIMIT 1', [], '');
cek('ada akun aktif untuk diuji', $email !== '');

$pengguna = q1('SELECT * FROM pengguna WHERE email = ? AND aktif = 1', [$email]);
if ($pengguna) {
    // Sesi dibuat lewat jalur yang sama dengan login sungguhan.
    $_SESSION = [];
    $_SESSION['pengguna'] = [
        'id' => (int) $pengguna['id'],
        'nama' => $pengguna['nama'],
        'email' => $pengguna['email'],
        'peran' => $pengguna['peran_kode'],
        'prodi' => $pengguna['prodi_id'] === null ? null : (int) $pengguna['prodi_id'],
    ];
    // Diuji lewat HTTP sungguhan, bukan memanggil keluar() langsung:
    // pengguna() meng-cache hasil per request, jadi memanggil keluar() di
    // proses yang sama tidak membuktikan apa pun. Yang harus dibuktikan:
    // COOKIE LAMA tidak bisa dipakai lagi.
    $sandi = 'demo1234';
    if (!password_verify($sandi, (string) $pengguna['sandi'])) {
        // Sandi seed berbeda di DB ini; uji HTTP dilewati dengan jujur
        // ketimbang memaksa sandi yang tidak diketahui.
        cek('sandi seed dikenal (uji HTTP bisa jalan)', false,
            'sandi akun "' . $email . '" bukan sandi seed; set ulang lewat 005_seed.sql');
    } else {
        $cookie = tempnam(sys_get_temp_dir(), 'ck');
        // 1) masuk, simpan cookie
        $masuk = http_minta('POST', '/api/masuk',
            ['nama' => $email, 'sandi' => $sandi, 'prodi' => 'Kebidanan'], $cookie);
        $jMasuk = json_decode($masuk['isi'], true) ?: [];
        cek('masuk lewat HTTP berhasil', ($jMasuk['sesi']['email'] ?? '') === $email,
            substr($masuk['isi'], 0, 120));

        // 2) /api/saya dengan cookie -> sesi hidup
        $saya = http_minta('GET', '/api/saya', null, $cookie);
        cek('sesi hidup sebelum keluar',
            (json_decode($saya['isi'], true)['sesi'] ?? null) !== null);

        // 3) POST /api/keluar dengan cookie
        $out = http_minta('POST', '/api/keluar', [], $cookie);
        cek('POST /api/keluar membalas keluar:true',
            (json_decode($out['isi'], true)['keluar'] ?? false) === true,
            substr($out['isi'], 0, 80));

        // 4) /api/saya DENGAN COOKIE LAMA -> harus null. Inilah inti ujinya:
        //    server harus mencabut sesi, bukan hanya UI yang pura-pura keluar.
        $lagi = http_minta('GET', '/api/saya', null, $cookie);
        $jLagi = json_decode($lagi['isi'], true) ?: [];
        cek('cookie lama tidak bisa dipakai setelah keluar',
            array_key_exists('sesi', $jLagi) && $jLagi['sesi'] === null,
            substr($lagi['isi'], 0, 100));

        @unlink($cookie);
    }
}

echo "\n── bagian yang tidak boleh hilang ──\n";

// Kalau suatu saat tombolnya diganti jadi <a>, handler harus tetap menangkap.
cek('handler memakai closest() bukan perbandingan langsung',
    str_contains($blok, 'closest('),
    'klik pada ikon/teks di dalam tombol harus tetap kena');
cek('tombol dinonaktifkan selama permintaan',
    str_contains($blok, 'disabled = true'),
    'mencegah klik ganda yang mengirim dua POST');

printf("\n%d lulus, %d gagal\n", $lulus, $gagal);
exit($gagal === 0 ? 0 : 1);
