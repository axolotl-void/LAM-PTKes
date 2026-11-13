<?php
/**
 * Uji modul Ekspor — tanpa HTTP.
 *
 * Yang dibuktikan, bukan diasumsikan:
 *   a) .xlsx keluaran adalah ZIP sah berisi bagian OOXML wajib
 *   b) SETIAP XML di dalamnya lolos simplexml_load_string (bukti Excel bisa buka)
 *   c) ekspor ditolak saat ada aturan blokir gagal, dengan alasannya
 *   d) nama sheet <= 31 karakter dan bebas : \ / ? * [ ]
 *   e) field asal='Tetap' tabel 05 ikut tertulis di sheet-nya
 *
 * Jalankan: php web/uji/uji_ekspor.php
 */
declare(strict_types=1);

ob_start();
putenv('LAMPTKES_ENV=lokal');

require_once __DIR__ . '/../system/view.php';
require_once __DIR__ . '/../system/xlsx.php';
require_once __DIR__ . '/../modul/ekspor/fungsi.php';

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
$per   = periode_aktif($prodi);
$perId = $per ? (int) $per['id'] : 0;
cek('periode aktif ada', $perId > 0, (string) $perId);

/* ── data uji ───────────────────────────────────────────────────────
   uq_baris = (periode_id, tabel_kode, urut), bukan per prodi. Pakai urut
   900+ supaya tidak bentrok dengan isian nyata, dan bersihkan sisa run
   sebelumnya di awal supaya uji bisa diulang. */
$bersihBaris = static function () use ($perId): void {
    ex('DELETE FROM baris_tabel WHERE periode_id = ? AND urut >= 900', [$perId]);
};
$bersihBaris();

$buat = static function (string $kode, int $urut, array $nilai) use ($prodi, $perId): int {
    $id = sisip('baris_tabel', ['prodi_id' => $prodi, 'periode_id' => $perId,
                                'tabel_kode' => $kode, 'urut' => $urut]);
    foreach ($nilai as $kunci => $v) {
        sisip('sel', ['baris_id' => $id, 'field_kunci' => $kunci, 'nilai_angka' => $v]);
    }
    return $id;
};
$buat('05', 900, ['jumlah_mahasiswa_per_angkatan_pada_ts_6_ts' => 41, 'jumlah_lulusan_s_d_ts' => 118]);
$buat('01', 900, ['praktikum_jam_pelaksanaan' => 250]);

$sheets = ekspor_sheets($prodi, $perId);
cek('sheet Ringkasan paling depan', ($sheets[0]['nama'] ?? '') === 'Ringkasan',
    (string) ($sheets[0]['nama'] ?? '?'));
cek('lebih dari satu sheet', count($sheets) > 1, (string) count($sheets));

/* ── (d) nama sheet sah ─────────────────────────────────────────── */
$namaBuruk = [];
foreach ($sheets as $s) {
    $n = (string) $s['nama'];
    if (mb_strlen($n) > 31 || mb_strlen($n) < 1 || preg_match('/[:\\\\\/?\*\[\]]/', $n) === 1) {
        $namaBuruk[] = $n;
    }
}
cek('(d) semua nama sheet <= 31 karakter & bebas : \\ / ? * [ ]',
    $namaBuruk === [], implode(' | ', $namaBuruk));
cek('(d) nama sheet tidak ganda',
    count(array_unique(array_column($sheets, 'nama'))) === count($sheets));
cek('(d) nama 40 karakter dipotong jadi 31',
    mb_strlen(xlsx_nama_sheet(str_repeat('A', 40))) === 31);
cek('(d) karakter terlarang dibuang',
    preg_match('/[:\\\\\/?\*\[\]]/', xlsx_nama_sheet('Tabel: 5/6 [a]?')) === 0,
    xlsx_nama_sheet('Tabel: 5/6 [a]?'));

/* ── (e) penanda Tetap tabel 05 ikut ────────────────────────────── */
$sheet05 = null;
foreach ($sheets as $s) {
    if (($s['kode'] ?? '') === '05') {
        $sheet05 = $s;
    }
}
cek('(e) sheet tabel 05 ada', $sheet05 !== null);
$kolomTetap = -1;
foreach ($sheet05['header'] ?? [] as $i => $h) {
    if (str_contains((string) $h, 'Penanda')) {
        $kolomTetap = $i;
    }
}
cek('(e) header memuat kolom Penanda', $kolomTetap >= 0, (string) $kolomTetap);
$selTetap = (string) ($sheet05['baris'][0][$kolomTetap] ?? '');
cek('(e) sel penanda berisi (a)–(g)',
    str_contains($selTetap, '(a)') && str_contains($selTetap, '(g)'), $selTetap);

// Tabel 06 tidak punya field Tetap di spec_field, tapi penandanya wajib.
$sheet06 = null;
foreach ($sheets as $s) {
    if (($s['kode'] ?? '') === '06') {
        $sheet06 = $s;
    }
}
cek('(e) tabel 06 tetap diekspor walau belum berisi', $sheet06 !== null);
cek('(e) tabel 06 menyertakan penanda', $sheet06 !== null
    && array_filter($sheet06['baris'], fn($r) => in_array(EKSPOR_PENANDA, array_map('strval', $r), true)) !== []);

// Tabel 01 punya field 'No' asal=Input; kolom nomor buatan tidak boleh
// menambah kolom "No" kedua (terlihat dobel di PDF sebelum diperbaiki).
$sheet01 = $sheets[array_search('01', array_column($sheets, 'kode'), true)] ?? null;
if ($sheet01 !== null) {
    $no = array_filter($sheet01['header'],
        fn($h) => in_array(mb_strtolower(trim((string) $h)), ['no', 'no.'], true));
    cek('kolom No tidak dobel di tabel 01', count($no) === 1, (string) count($no));
    cek('setiap baris sepanjang header',
        array_filter($sheet01['baris'], fn($r) => count($r) !== count($sheet01['header'])) === [],
        count($sheet01['header']) . ' kolom');
}

/* ── (a) berkas .xlsx sah ───────────────────────────────────────── */
$berkas = tempnam(sys_get_temp_dir(), 'ujixlsx') . '.xlsx';
cek('(a) xlsx_buat mengembalikan true', xlsx_buat($sheets, $berkas) === true);
cek('(a) berkas ada dan tidak kosong', is_file($berkas) && filesize($berkas) > 0,
    is_file($berkas) ? filesize($berkas) . ' byte' : 'tidak ada');

$zip = new ZipArchive();
cek('(a) berkas adalah ZIP sah', $zip->open($berkas) === true);
$isi = [];
for ($i = 0; $i < $zip->numFiles; $i++) {
    $isi[] = $zip->getNameIndex($i);
}
foreach (['[Content_Types].xml', '_rels/.rels', 'xl/workbook.xml',
          'xl/_rels/workbook.xml.rels', 'xl/styles.xml'] as $w) {
    cek('(a) berisi ' . $w, in_array($w, $isi, true));
}
$lembar = array_values(array_filter($isi,
    fn($n) => preg_match('#^xl/worksheets/sheet\d+\.xml$#', $n) === 1));
cek('(a) minimal satu xl/worksheets/sheetN.xml', $lembar !== [], (string) count($lembar));
cek('(a) jumlah sheet XML = jumlah sheet logis', count($lembar) === count($sheets),
    count($lembar) . ' vs ' . count($sheets));

/* ── (b) setiap XML lolos simplexml ─────────────────────────────── */
$xmlSalah = [];
$semuaXml = '';
foreach ($isi as $n) {
    if (!str_ends_with($n, '.xml') && !str_ends_with($n, '.rels')) {
        continue;
    }
    $teksXml = (string) $zip->getFromName($n);
    $semuaXml .= $teksXml;
    $sebelum = libxml_use_internal_errors(true);
    libxml_clear_errors();
    $doc = simplexml_load_string($teksXml);
    $err = libxml_get_errors();
    libxml_clear_errors();
    libxml_use_internal_errors($sebelum);
    if ($doc === false || $err !== []) {
        $xmlSalah[] = $n . ': ' . ($err[0]->message ?? 'gagal dimuat');
    }
}
cek('(b) semua XML lolos simplexml_load_string tanpa error', $xmlSalah === [],
    implode(' | ', $xmlSalah));

// Angka harus <v>, bukan inlineStr — kalau teks, Excel tidak bisa menjumlah.
cek('angka ditulis sebagai <v>', str_contains($semuaXml, '<v>250</v>'));
cek('teks ditulis inlineStr', str_contains($semuaXml, 'inlineStr'));

$teks = 'Kebidanan & "Keperawatan" <uji>';
cek('escape XML benar',
    xlsx_teks($teks) === 'Kebidanan &amp; &quot;Keperawatan&quot; &lt;uji&gt;', xlsx_teks($teks));
$tmp2 = tempnam(sys_get_temp_dir(), 'ujixlsx2') . '.xlsx';
xlsx_buat([['nama' => 'Uji', 'header' => ['Teks'], 'baris' => [[$teks]]]], $tmp2);
$z2 = new ZipArchive();
$z2->open($tmp2);
cek('teks ber-& tetap menghasilkan XML sah',
    simplexml_load_string((string) $z2->getFromName('xl/worksheets/sheet1.xml')) !== false);
$z2->close();
@unlink($tmp2);

$zip->close();
@unlink($berkas);

/* ── (c) blokir menolak ekspor ──────────────────────────────────── */
foreach (ekspor_blokir($prodi) as $a) {
    cek('alasan blokir punya kode+nama+pesan',
        $a['kode'] !== '' && $a['nama'] !== '' && $a['pesan'] !== '', json_encode($a));
    break;
}
// Data yang belum ada BUKAN pelanggaran: R04 belum bisa dihitung -> tidak memblokir.
cek('(c) aturan "belum ada data" tidak memblokir',
    array_filter(ekspor_blokir($prodi), fn($a) => $a['kode'] === 'R04') === []);

// Sekarang R04 (blokir) gagal: Tabel 10 terisi 999, master mahasiswa kosong.
$buat('10', 900, ['total_mahasiswa_aktif_reguler' => 999]);
$blokir = ekspor_blokir($prodi);
$kode = array_column($blokir, 'kode');
cek('(c) ekspor ditolak saat aturan blokir gagal', $blokir !== [], json_encode($kode));
cek('(c) alasan menyebut kode aturannya', in_array('R04', $kode, true), implode(',', $kode));
$pesanR04 = '';
foreach ($blokir as $b) {
    if ($b['kode'] === 'R04') {
        $pesanR04 = $b['pesan'];
    }
}
cek('(c) alasan berisi angka selisih, bukan "tidak bisa"',
    str_contains($pesanR04, '999'), $pesanR04);

// Gerbang harus dipanggil SEBELUM sheet disusun, di ketiga aksi.
$kodeModul = (string) file_get_contents(__DIR__ . '/../modul/ekspor/index.php');
cek('wajib(EKSPOR) ada di setiap aksi ekspor (' . substr_count($kodeModul, "wajib('EKSPOR')") . 'x)',
    substr_count($kodeModul, "wajib('EKSPOR')") >= 3);

/* ── akun berperan ganda ────────────────────────────────────────── */
// peran_kode punya FK ke tabel peran: peran palsu dibuat lalu dibuang lagi.
$pg = q1('SELECT id, peran_kode FROM pengguna WHERE peran_kode = ? LIMIT 1', ['OPERATOR']);
$peranAsli = (string) $pg['peran_kode'];
$peranUji  = 'Operator + Verifikator';
sisip('peran', ['kode' => $peranUji, 'nama' => 'Uji peran ganda']);
try {
    ex('UPDATE pengguna SET peran_kode = ? WHERE id = ?', [$peranUji, (int) $pg['id']]);
    $blokir2 = ekspor_blokir($prodi);
    $adaAkun = array_values(array_filter($blokir2, fn($a) => $a['kode'] === 'AKUN'));
    cek('akun berperan ganda memblokir ekspor', $adaAkun !== [],
        json_encode(array_column($blokir2, 'kode')));
    cek('alasan menyebut email akunnya',
        $adaAkun !== [] && str_contains($adaAkun[0]['nama'], '@'), $adaAkun[0]['nama'] ?? '');
} finally {
    ex('UPDATE pengguna SET peran_kode = ? WHERE id = ?', [$peranAsli, (int) $pg['id']]);
    ex('DELETE FROM peran WHERE kode = ?', [$peranUji]);
}
cek('peran ganda dibersihkan', ekspor_akun_ganda() === []);

/* ── gerbang izin ───────────────────────────────────────────────── */
$izinAsli = (int) qv('SELECT akses FROM izin WHERE peran_kode = ? AND aksi_kode = ?',
    ['VERIFIKATOR', 'EKSPOR'], 0);
ex('UPDATE izin SET akses = 0 WHERE peran_kode = ? AND aksi_kode = ?', ['VERIFIKATOR', 'EKSPOR']);
$aksesNol = akses('EKSPOR', ['peran_kode' => 'VERIFIKATOR']);
ex('UPDATE izin SET akses = ? WHERE peran_kode = ? AND aksi_kode = ?',
    [$izinAsli, 'VERIFIKATOR', 'EKSPOR']);
cek('peran tanpa izin EKSPOR dapat akses 0 (wajib() -> 403)', $aksesNol === 0, (string) $aksesNol);
cek('izin EKSPOR dipulihkan',
    (int) qv('SELECT akses FROM izin WHERE peran_kode = ? AND aksi_kode = ?',
        ['VERIFIKATOR', 'EKSPOR'], -1) === $izinAsli);

// Setelah data uji dibuang, ekspor boleh lagi.
$bersihBaris();
cek('(c) setelah dibersihkan, ekspor boleh lagi',
    array_filter(ekspor_blokir($prodi), fn($a) => $a['kode'] !== 'PRODI') === [],
    json_encode(ekspor_blokir($prodi)));

/* ── kasus batas ────────────────────────────────────────────────── */
cek('xlsx_buat menolak daftar sheet kosong',
    xlsx_buat([], tempnam(sys_get_temp_dir(), 'kosong') . '.xlsx') === false);
cek('xlsx_buat menolak sheet tanpa nama',
    xlsx_buat([['header' => ['A'], 'baris' => []]], tempnam(sys_get_temp_dir(), 'x') . '.xlsx') === false);
cek('tabel tanpa baris tidak diekspor (kecuali 05/06)',
    array_filter($sheets, fn($s) => str_starts_with((string) $s['nama'], 'T7 ')) === []);
cek('xlsx_angka: NIM nol depan tetap teks', xlsx_angka('0012345') === null);
cek('xlsx_angka: 250 jadi angka', xlsx_angka('250') === '250');
cek('xlsx_angka: 250.0000 dari kolom decimal jadi 250', xlsx_angka('250.0000') === '250');

while (ob_get_level() > 0) {
    ob_end_flush();
}
echo $gagal === 0 ? "\nSEMUA UJI LULUS\n" : "\n$gagal UJI GAGAL\n";
exit($gagal === 0 ? 0 : 1);
