<?php
/**
 * Uji kerangka: komponen tampilan + dashboard + ringkasan.
 * Tanpa server HTTP — fungsi dan modul dipanggil langsung.
 *
 * ── KEPUTUSAN: ini uji REGRESI, bukan uji fitur produksi ─────────────────
 *
 * Sejak frontend disajikan dari `app/` (HTML/CSS/JS milik klien, byte-identik)
 * lewat `web/router.php`, satu-satunya pintu produksi adalah:
 *
 *   /api/…   -> web/api.php        (JSON saja, tidak pernah mencetak HTML)
 *   sisanya  -> berkas statis dari folder app/
 *
 * `web/index.php` dan seluruh berkas di `web/modul/` TIDAK dipanggil router
 * itu. Jadi `web/tampilan/kerangka/komponen.php` — yang di-require
 * web/index.php baris 52 dan oleh 20+ berkas modul — saat ini BUKAN kode
 * produksi.
 *
 * Uji ini SENGAJA DIPERTAHANKAN, bukan dihapus:
 *
 *  1. Komponen ini disimpan sebagai RUJUKAN MARKUP: kelas CSS-nya
 *     (`card`, `card-head`, `strip`, `tbl`, `callout`, `tbar`) memang harus
 *     sama dengan yang dipakai `app/assets/css/app.css`. Kalau markup di sini
 *     melenceng, rujukan desainnya ikut bohong.
 *  2. ESCAPING adalah jaring pengaman keamanan. `kartu()`, `tabel_html()`, dan
 *     `callout()` menelan data dari DB (nama mahasiswa, judul tabel, nama
 *     prodi). Kalau suatu hari ada yang menghidupkan kembali `web/index.php`,
 *     satu `<script>` di nama orang langsung jadi XSS. Assertion escaping di
 *     berkas ini TIDAK BOLEH dilemahkan dengan alasan apa pun.
 *  3. `web/modul/konsistensi/fungsi.php` masih dipakai `web/api.php`, jadi
 *     `web/modul/` belum mati seluruhnya — menghapus uji ini akan menghapus
 *     satu-satunya penjaga markup yang tersisa kalau modul dihidupkan lagi.
 *
 * Karena itu: uji ini menguji kode yang disimpan, bukan kode yang melayani
 * pengguna hari ini. Kalau `web/index.php` dihidupkan lagi, uji ini langsung
 * berlaku sebagai uji fungsional tanpa perubahan.
 *
 * Jalankan: LAMPTKES_ENV=lokal php web/uji/uji_kerangka.php
 */
declare(strict_types=1);

ob_start();                                  // uji mencetak sebelum sesi dimulai
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTPS'] = '';
putenv('LAMPTKES_ENV=lokal');

require_once __DIR__ . '/../system/layout.php';
require_once __DIR__ . '/../tampilan/kerangka/komponen.php';

$gagal = 0;
function cek(string $nama, bool $ok, string $detail = ''): void
{
    global $gagal;
    echo ($ok ? '  ok  ' : '  GAGAL ') . $nama . ($detail !== '' ? " — $detail" : '') . "\n";
    if (!$ok) {
        $gagal++;
    }
}

/* ── render satu modul ke string, seperti request nyata ──────────────── */
function render_modul(string $berkas, int $prodiId): string
{
    $U = q1('SELECT p.*, pr.nama_ps AS prodi_nama FROM pengguna p
             LEFT JOIN prodi pr ON pr.id = p.prodi_id WHERE p.prodi_id = ? LIMIT 1', [$prodiId]);
    $PRODI = $prodiId;
    $PRODI_NAMA = $U['prodi_nama'] ?? null;
    ob_start();
    require $berkas;
    return (string) ob_get_clean();
}

/* ── ambil angka dari satu butir strip berdasarkan label ───────────────
   Markup yang dicocokkan = markup yang benar-benar dihasilkan komponen.php
   dan yang punya gaya di app/assets/css/app.css:

     <div class="cell"><div class="val "><b class="num">3</b>
       <span class="strip-dari">/ 12</span> <span class="strip-satuan">tabel</span></div>
       <div class="lab">Selesai</div>…

   Versi lama mencari `.strip-label`/`.strip-angka` — dua kelas yang tidak
   pernah ada di app.css. Ujinya yang salah alamat, bukan markup-nya. */
function strip_nilai(string $html, string $label): ?array
{
    $pola = '~<div class="lab">' . preg_quote($label, '~') . '</div>~';
    if (!preg_match($pola, $html, $m, PREG_OFFSET_CAPTURE)) {
        return null;
    }
    // Angka & penyebut ada di .val MILIK SEL YANG SAMA, yaitu teks sebelum
    // label ini. Ambil potongan sejak .val terakhir sebelum label.
    $sebelum = substr($html, 0, (int) $m[0][1]);
    $pos = strrpos($sebelum, '<div class="val');
    if ($pos === false) {
        return null;
    }
    $potong = substr($sebelum, $pos);
    if (!preg_match('~<b class="num">([\d.]+)</b>~', $potong, $a)) {
        return null;
    }
    $dari = null;
    if (preg_match('~<span class="strip-dari">/ ([\d.]+)</span>~', $potong, $d)) {
        $dari = (int) str_replace('.', '', $d[1]);
    }
    return ['nilai' => (int) str_replace('.', '', $a[1]), 'dari' => $dari];
}

/* ── sesi: user prodi 1 ─────────────────────────────────────────────── */
sesi_mulai();
$_SESSION['pengguna_id'] = (int) qv('SELECT id FROM pengguna WHERE prodi_id = 1 LIMIT 1');
$_SESSION['prodi_id'] = 1;
$prodi = 1;
$per = periode_aktif($prodi);
cek('periode aktif prodi uji ada', $per !== null);
$perId = (int) ($per['id'] ?? 0);

/* ── 1. komponen dasar ──────────────────────────────────────────────── */
$k = kartu('Judul <b>mentah</b>', '<p>isi</p>', ['catatan' => 'catatan & co']);
cek('kartu meng-escape judul', !str_contains($k, '<b>mentah</b>') && str_contains($k, '&lt;b&gt;mentah&lt;/b&gt;'), $k);
cek('kartu meng-escape catatan', str_contains($k, 'catatan &amp; co'));
cek('kartu memakai class card', str_contains($k, 'class="card"') && str_contains($k, '<h2>Judul'));

$t = tabel_html(['nomor', ['kunci' => 'nama', 'label' => 'Nama']], [
    ['nomor' => 1, 'nama' => 'A <script>alert(1)</script>'],
    ['nomor' => 2, 'nama' => null],
]);
cek('tabel_html meng-escape sel', !str_contains($t, '<script>') && str_contains($t, '&lt;script&gt;'));
cek('tabel_html menandai sel kosong', str_contains($t, '<span class="muted">—</span>'));
cek('tabel_html membungkus .tbl-wrap', str_contains($t, 'class="tbl-wrap"'));
cek('tabel_html tanpa bungkus bila diminta',
    !str_contains(tabel_html(['a'], [['a' => 1]], ['tanpa_bungkus' => true]), 'tbl-wrap'));

$kosong = tabel_html(['a', 'b'], [], ['kosong' => 'Belum ada baris.']);
cek('tabel_html tanpa baris menampilkan teks kosong',
    str_contains($kosong, 'Belum ada baris.') && str_contains($kosong, 'colspan="2"'));

$tHtml = tabel_html([['kunci' => 's', 'html' => true]], [['s' => badge_status('lengkap')]]);
cek('tabel_html kolom html tidak meng-escape badge', str_contains($tHtml, 'badge-lengkap'));

// Tanda HTML eksplisit per sel, bukan per kolom. Bentuk `['html' => ...]`
// sebagai SATU baris dulu diperlakukan sebagai baris asosiatif dan selnya
// hilang tanpa suara — uji ini yang menahannya.
$tSelHtml = tabel_html(['Status'], [['html' => '<b>ok</b>']]);
cek('tabel_html sel [html] dipertahankan', str_contains($tSelHtml, '<b>ok</b>'), $tSelHtml);

// Penanda eksplisit TIDAK boleh jadi celah: tanpa 'html', array bersarang
// tetap di-escape, bukan dipercaya.
$tNested = tabel_html(['A'], [[['isi' => '<script>x</script>']]]);
cek('tabel_html: isi bersarang tanpa html tetap di-escape', !str_contains($tNested, '<script>'), $tNested);

// Kontrak halaman_galat: $pesan = HTML milik pemanggil. Yang diuji di sini
// BUKAN "escape semuanya" (pemanggil memang mengirim markup), tapi bahwa
// judul & kode tetap di-escape dan pemanggil yang menyisipkan data wajib
// memakai e() sendiri — lihat komentar di komponen.php.
$gEsc = halaman_galat(409, 'Kode <b>' . e('<script>x</script>') . '</b>');
cek('halaman_galat: data lewat e() pemanggil tidak jadi tag',
    !str_contains($gEsc, '<script>') && str_contains($gEsc, '&lt;script&gt;'), $gEsc);
$gJudul = halaman_galat(409, 'isi', '<script>x</script>');
cek('halaman_galat: judul di-escape', !str_contains($gJudul, '<script>'), $gJudul);

$s = strip_angka([['label' => 'Selesai', 'nilai' => 3, 'dari' => 12, 'satuan' => 'tabel']]);
cek('strip_angka menulis angka dan penyebut',
    str_contains($s, '<b class="num">3</b>') && str_contains($s, '/ 12') && str_contains($s, 'tabel'));
// Bar = besaran yang SAMA dengan angka: 3/12 = 25%.
cek('strip_angka: bar 25% untuk 3 dari 12', str_contains($s, 'width:25.0%'), $s);

$c = callout('warn', 'Judul <x>', 'Isi <b>boleh</b> html');
cek('callout membawa kata + ikon, bukan warna saja',
    str_contains($c, 'Perhatian:') && str_contains($c, '<svg') && str_contains($c, 'class="callout warn"'));
cek('callout meng-escape judul tapi tidak isi',
    str_contains($c, 'Judul &lt;x&gt;') && str_contains($c, '<b>boleh</b>'));

$g = halaman_galat(409, 'Pesan <i>galat</i>');
cek('halaman_galat: potongan isi berisi kode + pesan, bukan dokumen utuh',
    str_contains($g, '>409<') && str_contains($g, 'Pesan <i>galat</i>')
    && !str_contains($g, '<!DOCTYPE') && str_contains($g, 'galat-halaman'));

/* ── 2. dashboard memakai angka yang sama dengan rekap_ringkas() ─────── */
$r = rekap_ringkas($prodi, $perId);
$d = rekap_dokumen($prodi, $perId);
$htmlDash = render_modul(__DIR__ . '/../modul/dashboard/index.php', $prodi);

foreach ([
    'Tabel wajib' => [$r['wajib'], null],
    'Selesai' => [$r['selesai'], $r['wajib']],
    'Sebagian' => [$r['sebagian'], $r['wajib']],
    'Kosong' => [$r['kosong'], $r['wajib']],
    'Dokumen terunggah' => [$d['unggah'], $d['total']],
] as $label => [$angka, $dari]) {
    $got = strip_nilai($htmlDash, $label);
    cek("dashboard: strip '$label' = rekap_ringkas()",
        $got !== null && $got['nilai'] === $angka && $got['dari'] === $dari,
        'html ' . json_encode($got) . ' vs rekap ' . $angka . '/' . ($dari ?? '-'));
}

/* ── 3. keadaan kosong ditampilkan jujur, bukan deretan 0 ────────────── */
if ($r['selesai'] === 0 && $r['sebagian'] === 0 && $d['unggah'] === 0) {
    cek('dashboard: keadaan kosong punya penjelasan "belum ada data"',
        str_contains($htmlDash, 'Belum ada data diisi'));
} else {
    cek('dashboard: keadaan kosong dilewati (data sudah ada)', true);
}
// Tabel tertinggal tidak boleh lebih dari 5 baris isi.
preg_match('~Tabel paling tertinggal.*?</tbody>~s', $htmlDash, $m);
$nBaris = isset($m[0]) ? substr_count($m[0], '<tr>') - 1 : -1;   // -1 untuk <tr> header
cek('dashboard: maksimal 5 tabel tertinggal', $nBaris >= 0 && $nBaris <= 5, "dapat $nBaris");

/* ── 4. ringkasan: 8 kriteria, K1 total 0 ditampilkan apa adanya ─────── */
$htmlRing = render_modul(__DIR__ . '/../modul/ringkasan/index.php', $prodi);
$perKrit = rekap_kriteria($prodi, $perId);
cek('ringkasan: 8 kriteria', count($perKrit) === 8, 'dapat ' . count($perKrit));
cek('ringkasan: total tabel per kriteria = 38',
    array_sum(array_column($perKrit, 'total')) === 38,
    'dapat ' . array_sum(array_column($perKrit, 'total')));

$k1 = null;
foreach ($perKrit as $x) {
    if ($x['kriteria'] === 'K1') {
        $k1 = $x;
    }
}
cek('ringkasan: K1 ada dengan total 0 tabel', $k1 !== null && $k1['total'] === 0, json_encode($k1));
cek('ringkasan: K1 tampil di halaman', str_contains($htmlRing, '>K1<'));
cek('ringkasan: K1 diberi catatan "tidak ada tabel"',
    str_contains($htmlRing, 'tidak ada tabel'));
// Angka sama dengan rekap: kolom tabel lengkap tiap kriteria harus berasal dari rekap.
$cocok = true;
$rinci = '';
foreach ($perKrit as $x) {
    if ($x['total'] === 0) {
        continue;
    }
    $pola = '~<tr><td>' . preg_quote($x['kriteria'], '~') . '</td>.*?<span class="num">(\d+)</span>~s';
    if (!preg_match($pola, $htmlRing, $m) || (int) $m[1] !== $x['lengkap']) {
        $cocok = false;
        $rinci .= $x['kriteria'] . ' html=' . ($m[1] ?? '?') . ' rekap=' . $x['lengkap'] . '; ';
    }
}
cek('ringkasan: angka tabel lengkap per kriteria = rekap_kriteria()', $cocok, $rinci);

/* ── 5. tidak ada sisa keluaran rusak di HTML ────────────────────────── */
foreach (['dashboard' => $htmlDash, 'ringkasan' => $htmlRing] as $nama => $html) {
    $kotor = [];
    // '=>' SEMPAT ada di daftar ini dan selalu gagal. Penyebabnya bukan bug
    // PHP: layout.php memuat <script> berisi arrow function (`() => {`).
    // Itu JavaScript yang sah, bukan jejak array PHP yang bocor ke HTML.
    // Kalau array bocor, jejaknya 'Array' — dan itu tetap diperiksa.
    foreach (['undefined', 'NaN', 'Array', 'Warning:', 'Deprecated:', 'Fatal error'] as $jejak) {
        if (str_contains($html, $jejak)) {
            $kotor[] = $jejak;
        }
    }
    cek("$nama: tidak ada undefined/NaN/warning di HTML", $kotor === [], implode(', ', $kotor));
    cek("$nama: halaman utuh (</html>)", str_contains($html, '</html>'));
    // CSS yang dipasang layout.php = `assets/css/app.css` + `anim.css`, disalin
    // apa adanya dari desain asli. `aset/css/kerangka.css` tidak pernah ada di
    // repo (hanya disebut sekali di TUGAS-10-AGENT.md sebagai rencana), jadi
    // mencarinya = menguji berkas yang tidak pernah dibuat.
    cek("$nama: memuat css desain asli (app.css)", str_contains($html, 'assets/css/app.css'));
}

/* ── 6. kasus batas: prodi tanpa periode ─────────────────────────────── */
$prodiTanpaPeriode = (int) qv('SELECT id FROM prodi WHERE id NOT IN (SELECT prodi_id FROM periode WHERE aktif = 1) LIMIT 1', [], 0);
if ($prodiTanpaPeriode > 0) {
    $galat = render_modul(__DIR__ . '/../modul/dashboard/index.php', $prodiTanpaPeriode);
    cek('dashboard: prodi tanpa periode -> halaman galat, bukan nol',
        str_contains($galat, 'galat-halaman') && !str_contains($galat, 'strip-angka')
        && str_contains($galat, '</html>'));
} else {
    // Semua prodi punya periode: uji jalur galat langsung lewat fungsinya.
    cek('dashboard: jalur galat siap pakai',
        str_contains(halaman_galat(409, 'x'), 'galat-halaman'));
}

while (ob_get_level() > 0) {
    ob_end_flush();
}
echo $gagal === 0 ? "\nSEMUA UJI LULUS\n" : "\n$gagal UJI GAGAL\n";
exit($gagal === 0 ? 0 : 1);