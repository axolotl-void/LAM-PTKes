<?php
declare(strict_types=1);

/**
 * Uji PDF ekspor — dibuat tanpa pustaka eksternal.
 *
 * KENAPA TULIS SENDIRI: hosting produksi cPanel tanpa Composer, jadi
 * dompdf/mPDF tidak bisa dipasang; wkhtmltopdf/tectonic juga tidak ada.
 * PDF ditulis langsung (PDF 1.4, font standar Helvetica).
 *
 * Yang dijaga uji ini — semuanya pernah rusak sungguhan:
 *  1. Struktur berkas sah: diawali %PDF, diakhiri %%EOF, punya xref dan
 *     jumlah objek yang cocok. PDF yang "kelihatan PDF" tapi xref-nya salah
 *     akan ditolak pembaca.
 *  2. Karakter di luar Latin-1 TIDAK muncul sebagai '?' di tempat yang
 *     seharusnya berisi angka/tanda hubung. Em dash dan elipsis Unicode
 *     pernah tampil '?' dan merusak tabel.
 *  3. Ringkasan HARUS tutup: lengkap+sebagian+kosong+terkunci+hitung = wajib.
 *  4. Judul tabel memakai nama asli, bukan "01. Tabel 01".
 *
 * Jalankan: LAMPTKES_ENV=lokal php web/uji/uji_pdf.php
 */

require_once __DIR__ . '/../system/config.php';
require_once __DIR__ . '/../system/db.php';
require_once __DIR__ . '/../system/auth.php';
require_once __DIR__ . '/../system/rekap.php';
require_once __DIR__ . '/../system/pdf.php';
require_once __DIR__ . '/../modul/ekspor/fungsi.php';
require_once __DIR__ . '/bersih.php';

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

bersihkan_data_uji(db(), true);

$p = (int) qv('SELECT id FROM prodi WHERE aktif = 1 ORDER BY id LIMIT 1', [], 0);
$per = $p > 0 ? periode_aktif($p) : null;
cek('prodi + periode ada', $p > 0 && $per !== null);
if ($p === 0 || $per === null) { echo "\nSeed belum jalan.\n"; exit(1); }

echo "── escape teks ──\n";

cek('kurung dibuka di-escape', pdf_teks('a(b') === 'a\\(b');
cek('kurung ditutup di-escape', pdf_teks('a)b') === 'a\\)b');
cek('backslash di-escape', pdf_teks('a\\b') === 'a\\\\b');
cek('newline jadi spasi', pdf_teks("a\nb") === 'a b');

// Inilah bug yang pernah terjadi: em dash / elipsis tampil '?' di PDF.
cek('em dash (U+2014) jadi ?, bukan huruf acak', pdf_teks('a—b') === 'a?b');
cek('elipsis (U+2026) jadi ?, bukan huruf acak', pdf_teks('a…b') === 'a?b');
cek('huruf Latin-1 (é) tetap utuh', pdf_teks('café') === 'caf' . chr(0xE9));
cek('teks ASCII utuh', pdf_teks('Laporan kegiatan 2025') === 'Laporan kegiatan 2025');

echo "\n── format angka PDF ──\n";

cek('bilangan bulat tanpa .0', pdf_num(42.0) === '42');
cek('desimal memakai titik', pdf_num(3.5) === '3.5');
cek('nol', pdf_num(0.0) === '0');

echo "\n── struktur berkas ──\n";

$halaman = pdf_dari_ekspor($p, (int) $per['id'],
    ['kepala' => ['prodi' => 'Kebidanan', 'tahun_ts' => '2025', 'tahun_ss' => '2020'],
     'rekap' => rekap_ringkas($p, (int) $per['id']),
     'tabel' => []],
    []);
$pdf = pdf_buat($halaman, 'Data Dukung');

cek('diawali %PDF-1.4', str_starts_with($pdf, '%PDF-1.4'));
cek('diakhiri %%EOF', str_contains(rtrim($pdf), '%%EOF'));
cek('punya objek Catalog', str_contains($pdf, '/Type /Catalog'));
cek('punya objek Pages', str_contains($pdf, '/Type /Pages'));
cek('punya font Helvetica', str_contains($pdf, '/BaseFont /Helvetica'));
cek('punya tabel xref', str_contains($pdf, 'xref'));
cek('punya startxref', str_contains($pdf, 'startxref'));

// Offset xref harus menunjuk ke awal objek. Kalau tidak, pembaca PDF menolak
// berkas — tapi PHP tidak akan mengeluh apa pun.
preg_match('/startxref\s+(\d+)/', $pdf, $m);
$offset = (int) ($m[1] ?? 0);
cek('startxref menunjuk posisi xref yang benar',
    substr($pdf, $offset, 4) === 'xref', 'dapat ' . substr($pdf, $offset, 8));

// Setiap entri xref harus menunjuk ke "<n> 0 obj".
preg_match('/xref\s+0 (\d+)\s+(.*?)trailer/s', $pdf, $mx);
$jumlah = (int) ($mx[1] ?? 0);
$entri = preg_split('/\r?\n/', trim($mx[2] ?? ''));
$salah = 0;
$cekEntri = 0;
foreach ($entri as $i => $e) {
    if ($i === 0 || !preg_match('/^(\d{10}) 00000 n/', $e, $me)) {
        continue;
    }
    $cekEntri++;
    $pos = (int) $me[1];
    if (!preg_match('/^\d+ 0 obj/', substr($pdf, $pos, 24))) {
        $salah++;
    }
}
cek('ada entri xref yang diperiksa', $cekEntri > 3, 'diperiksa ' . $cekEntri);
cek('semua offset xref menunjuk ke objek yang benar', $salah === 0,
    $salah . ' dari ' . $cekEntri . ' salah');
cek('jumlah objek di trailer wajar', $jumlah > 4, 'Size ' . $jumlah);

echo "\n── isi halaman ──\n";

cek('ada operator teks', str_contains($pdf, 'Tj'));
cek('ada nomor halaman', str_contains($pdf, 'Halaman 1 dari'));

$teksIsi = '';
foreach ($halaman as $h) {
    foreach ($h['baris'] as $b) {
        if (isset($b['teks'])) { $teksIsi .= ' ' . $b['teks']; }
    }
}
cek('judul dokumen ada', str_contains($teksIsi, 'DATA DUKUNG'));
cek('nama prodi ada', str_contains($teksIsi, 'Kebidanan'));

echo "\n── ringkasan harus tutup ──\n";

$r = rekap_ringkas($p, (int) $per['id']);
$jml = (int) $r['lengkap'] + (int) $r['sebagian'] + (int) $r['kosong']
    + (int) $r['terkunci'] + (int) $r['hitung'];
cek('lima kategori = wajib', $jml === (int) $r['wajib'],
    $jml . ' vs ' . $r['wajib']);
cek('PDF mencantumkan penutup kategori',
    str_contains($teksIsi, 'Jumlah dari lima kategori')
    || str_contains($teksIsi, 'cocok'));

echo "\n── judul tabel memakai nama asli ──\n";

$judul = q("SELECT kode, judul FROM tabel_dukung ORDER BY kode");
$palsu = array_filter($judul, fn($j) => (bool) preg_match('/^\d{2}\. Tabel \d{2}$/', $j['judul']));
cek('tidak ada judul placeholder "01. Tabel 01"', $palsu === [],
    implode(', ', array_column($palsu, 'kode')));
cek('38 tabel punya judul', count($judul) === 38, 'dapat ' . count($judul));

// Judul panjang harus muat kolom; pdf_potong dipakai untuk itu.
$panjang = str_repeat('Judul sangat panjang ', 10);
$potong = pdf_potong($panjang, 7.5, 200);
cek('judul panjang dipotong', mb_strlen($potong, 'UTF-8') < mb_strlen($panjang, 'UTF-8'));
cek('potongan memakai titik ASCII, bukan elipsis Unicode',
    str_ends_with($potong, '...'), json_encode(mb_substr($potong, -6)));

// '?' hanya boleh muncul dari karakter yang memang tak bisa dirender,
// bukan dari tanda hubung yang seharusnya '-' atau em dash yang salah pakai.
cek('tanda hubung ASCII tetap ASCII', pdf_teks('a-b') === 'a-b');

printf("\n%d lulus, %d gagal\n", $lulus, $gagal);
exit($gagal === 0 ? 0 : 1);
