<?php
/**
 * Uji kerangka: komponen tampilan + dashboard + ringkasan.
 * Tanpa server HTTP — fungsi dan modul dipanggil langsung.
 *
 * Jalankan: php web/uji/uji_kerangka.php
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

/* ── ambil angka dari satu butir strip berdasarkan label ─────────────── */
function strip_nilai(string $html, string $label): ?array
{
    $pola = '~<div class="strip-label">' . preg_quote($label, '~') . '</div>\s*<div class="strip-angka">'
          . '<b class="num">([\d.]+)</b>(?:<span class="strip-dari">/ ([\d.]+)</span>)?~';
    if (!preg_match($pola, $html, $m)) {
        return null;
    }
    return [
        'nilai' => (int) str_replace('.', '', $m[1]),
        'dari'  => isset($m[2]) && $m[2] !== '' ? (int) str_replace('.', '', $m[2]) : null,
    ];
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
    foreach (['undefined', 'NaN', 'Array', 'Warning:', 'Deprecated:', 'Fatal error', '=>'] as $jejak) {
        if (str_contains($html, $jejak)) {
            $kotor[] = $jejak;
        }
    }
    cek("$nama: tidak ada undefined/NaN/warning di HTML", $kotor === [], implode(', ', $kotor));
    cek("$nama: halaman utuh (</html>)", str_contains($html, '</html>'));
    cek("$nama: memuat css kerangka", str_contains($html, 'aset/css/kerangka.css'));
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