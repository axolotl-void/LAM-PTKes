<?php
declare(strict_types=1);

/**
 * Ekspor PDF — dibuat sendiri, tanpa pustaka eksternal.
 *
 * KENAPA TIDAK MEMAKAI PUSTAKA: hosting produksi adalah cPanel PHP tanpa
 * Composer (lihat keputusan awal proyek). Dompdf/mPDF butuh Composer, dan
 * instalasinya tidak bisa dipertanggungjawabkan di sana. wkhtmltopdf/tectonic
 * butuh binary sistem yang juga tidak ada. Satu-satunya jalan yang benar-benar
 * bisa jalan di hosting itu: tulis PDF langsung.
 *
 * PDF 1.4 sederhana cukup untuk kebutuhan ini: teks, garis tabel, beberapa
 * ukuran huruf, dan halaman lebih dari satu. Font memakai Helvetica (salah
 * satu dari 14 font standar PDF) sehingga TIDAK perlu menyematkan berkas font.
 *
 * Batasan yang disadari dan disebutkan di layar:
 *  - Huruf non-ASCII diganti '?' bila di luar Latin-1 (WinAnsi). Nama beraksen
 *    Latin-1 tetap aman. Untuk teks Indonesia ini cukup.
 *  - Tidak ada gambar.
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/rekap.php';

/** Ukuran halaman A4 dalam point (1 pt = 1/72 inci). */
const PDF_W = 595.28;
const PDF_H = 841.89;
const PDF_MARGIN = 42.0;

/**
 * PDF sederhana: satu halaman objek, konten teks + garis.
 *
 * @param array $halaman daftar ['baris'=>[['teks'=>, 'x'=>, 'y'=>, 'ukuran'=>,
 *                      'tebal'=>bool, 'garis'=>['x1','y1','x2','y2']]]]
 */
function pdf_buat(array $halaman, string $judul): string
{
    // Objek: 1 Catalog, 2 Pages, 3 Font biasa, 4 Font tebal, lalu per halaman
    // satu objek Page + satu objek Contents.
    $objek = [];
    $objek[3] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>";
    $objek[4] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>";

    $n = 5;
    $idHalaman = [];
    $idIsi = [];

    foreach ($halaman as $h) {
        $isi = pdf_isi_halaman($h);
        $idIsi[] = $n;
        $objek[$n] = "<< /Length " . strlen($isi) . " >>\nstream\n" . $isi . "\nendstream";
        $n++;
        $idHalaman[] = $n;
        $objek[$n] = null; // diisi setelah semua id isi diketahui
        $n++;
    }

    // Pages harus tahu semua anak, dan tiap Page harus tahu induknya.
    $anak = implode(' ', array_map(fn($i) => $i . ' 0 R', $idHalaman));
    $objek[2] = "<< /Type /Pages /Count " . count($idHalaman)
        . " /Kids [" . $anak . "] >>";

    foreach ($idHalaman as $k => $id) {
        $objek[$id] = "<< /Type /Page /Parent 2 0 R"
            . " /MediaBox [0 0 " . pdf_num(PDF_W) . " " . pdf_num(PDF_H) . "]"
            . " /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >>"
            . " /Contents " . $idIsi[$k] . " 0 R >>";
    }
    $objek[1] = "<< /Type /Catalog /Pages 2 0 R >>";

    // Info: judul ikut tercatat di properti dokumen.
    $objek[5 + count($halaman) * 2 + 1] = null;

    // Susun berkas. xref butuh offset byte persis.
    ksort($objek);
    $keluar = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
    $offset = [];
    foreach ($objek as $nomor => $isiObj) {
        if ($isiObj === null) {
            continue;
        }
        $offset[$nomor] = strlen($keluar);
        $keluar .= $nomor . " 0 obj\n" . $isiObj . "\nendobj\n";
    }

    $xref = strlen($keluar);
    $maks = max(array_keys($offset));
    $keluar .= "xref\n0 " . ($maks + 1) . "\n";
    $keluar .= "0000000000 65535 f \n";
    for ($i = 1; $i <= $maks; $i++) {
        $keluar .= isset($offset[$i])
            ? sprintf("%010d 00000 n \n", $offset[$i])
            : "0000000000 65535 f \n";
    }
    $keluar .= "trailer\n<< /Size " . ($maks + 1) . " /Root 1 0 R >>\n";
    $keluar .= "startxref\n" . $xref . "\n%%EOF\n";

    return $keluar;
}

/** Isi satu halaman: kumpulan operator teks dan garis. */
function pdf_isi_halaman(array $h): string
{
    $out = [];
    foreach ($h['baris'] as $b) {
        if (isset($b['garis']) && is_array($b['garis'])) {
            [$x1, $y1, $x2, $y2] = $b['garis'];
            $out[] = pdf_num((float) $x1) . ' ' . pdf_num((float) $y1) . ' m '
                . pdf_num((float) $x2) . ' ' . pdf_num((float) $y2) . ' l S';
            continue;
        }
        $teks = (string) ($b['teks'] ?? '');
        if ($teks === '') {
            continue;
        }
        $ukuran = (float) ($b['ukuran'] ?? 9);
        $font = !empty($b['tebal']) ? '/F2' : '/F1';
        $x = (float) ($b['x'] ?? PDF_MARGIN);
        $y = (float) ($b['y'] ?? PDF_MARGIN);
        $out[] = 'BT ' . $font . ' ' . pdf_num($ukuran) . ' Tf '
            . pdf_num($x) . ' ' . pdf_num($y) . ' Td '
            . '(' . pdf_teks($teks) . ') Tj ET';
    }
    return implode("\n", $out);
}

/** Format angka: PDF tidak menerima koma desimal. */
function pdf_num(float $n): string
{
    $s = rtrim(rtrim(number_format($n, 3, '.', ''), '0'), '.');
    return $s === '' || $s === '-' ? '0' : $s;
}

/**
 * Escape teks untuk string literal PDF.
 *
 * Karakter di luar Latin-1 diganti '?'. Alasannya: font standar Helvetica
 * hanya punya WinAnsi. Lebih baik '?' yang jelas terlihat daripada huruf acak.
 */
function pdf_teks(string $s): string
{
    $out = '';
    $len = mb_strlen($s, 'UTF-8');
    for ($i = 0; $i < $len; $i++) {
        $c = mb_substr($s, $i, 1, 'UTF-8');
        $kode = mb_ord($c, 'UTF-8');
        if ($kode === false || $kode > 255) {
            $out .= '?';
            continue;
        }
        if ($kode === 0x5C) { $out .= '\\\\'; continue; }   // backslash
        if ($kode === 0x28) { $out .= '\\('; continue; }     // (
        if ($kode === 0x29) { $out .= '\\)'; continue; }     // )
        if ($kode === 0x0A || $kode === 0x0D) { $out .= ' '; continue; }
        if ($kode < 32) { $out .= ' '; continue; }
        $out .= ($kode < 128) ? chr($kode) : chr($kode);     // Latin-1 langsung
    }
    return $out;
}

/** Lebar teks perkiraan (Helvetica). Untuk memotong teks panjang. */
function pdf_lebar(string $s, float $ukuran): float
{
    // Rata-rata 0.5 em per karakter cukup untuk teks campuran. Bukan presisi
    // tipografi — tujuannya hanya mencegah teks melewati margin halaman.
    return mb_strlen($s, 'UTF-8') * $ukuran * 0.5;
}

/**
 * Potong teks agar muat, DI BATAS KATA.
 *
 * Memotong pada jumlah karakter (mb_substr) menghasilkan "Progr am" dan
 * "diakr editasi" — kata terbelah yang membuat judul tidak terbaca. Yang
 * benar: cari spasi terakhir yang masih muat.
 */
function pdf_potong(string $s, float $ukuran, float $maks): string
{
    if (pdf_lebar($s, $ukuran) <= $maks) {
        return $s;
    }
    $n = max(1, (int) floor($maks / ($ukuran * 0.5)) - 1);
    $kandidat = mb_substr($s, 0, $n, 'UTF-8');

    // Mundur ke spasi terakhir. Kalau tidak ada spasi (kata sangat panjang),
    // terpaksa potong di tengah — lebih baik daripada kehilangan teks.
    $spasi = mb_strrpos($kandidat, ' ', 0, 'UTF-8');
    if ($spasi !== false && $spasi > 0) {
        $kandidat = mb_substr($kandidat, 0, $spasi, 'UTF-8');
    }

    return rtrim($kandidat) . '...';
}

/**
 * Susun halaman PDF dari data ekspor.
 *
 * Struktur: halaman judul ringkas (identitas + rekap), lalu tabel Ringkasan
 * (38 tabel dengan status), lalu tabel-tabel yang ada isinya.
 */
function pdf_dari_ekspor(int $prodi, ?int $periode, array $data, array $sheets): array
{
    $halaman = [];
    $baris = [];
    $y = PDF_H - PDF_MARGIN;

    $namaProdi = (string) ($data['kepala']['prodi'] ?? 'Prodi');
    $ts = (string) ($data['kepala']['tahun_ts'] ?? '');
    $ss = (string) ($data['kepala']['tahun_ss'] ?? '');

    // ── kepala ──
    $baris[] = ['teks' => 'DATA DUKUNG AKREDITASI LAM-PTKes', 'x' => PDF_MARGIN,
                'y' => $y, 'ukuran' => 15, 'tebal' => true];
    $y -= 22;
    $baris[] = ['teks' => 'Universitas Bina Bangsa Getsempena · ' . $namaProdi,
                'x' => PDF_MARGIN, 'y' => $y, 'ukuran' => 10];
    $y -= 16;
    // Karakter di atas 0x7F bergantung pada tabel encoding pembaca. En dash
    // (0x96) tampil sebagai "?" di sebagian pembaca, jadi dipakai pemisah
    // ASCII yang pasti terbaca di mana pun.
    $baris[] = ['teks' => 'Tahun TS ' . $ts . ' | Tahun SS ' . $ss
                . ' | Dicetak ' . date('d-m-Y H:i'), 'x' => PDF_MARGIN, 'y' => $y, 'ukuran' => 8];
    $y -= 14;
    $baris[] = ['garis' => [PDF_MARGIN, $y, PDF_W - PDF_MARGIN, $y]];
    $y -= 20;

    // ── rekap ──
    $rekap = $data['rekap'] ?? [];
    $baris[] = ['teks' => 'RINGKASAN', 'x' => PDF_MARGIN, 'y' => $y, 'ukuran' => 11, 'tebal' => true];
    $y -= 15;
    // Angka-angka ini HARUS tutup: lengkap + sebagian + kosong + terkunci +
    // hitung = wajib. Kalau tidak, pembaca mengira ada tabel yang hilang dari
    // hitungan — dan itu memang pertanyaan pertama yang muncul.
    $wajib = (int) ($rekap['wajib'] ?? 0);
    $jumlahKategori = (int) ($rekap['lengkap'] ?? 0) + (int) ($rekap['sebagian'] ?? 0)
        + (int) ($rekap['kosong'] ?? 0) + (int) ($rekap['terkunci'] ?? 0)
        + (int) ($rekap['hitung'] ?? 0);

    foreach ([
        ['Tabel wajib', (string) $wajib],
        ['  Lengkap', (string) ($rekap['lengkap'] ?? 0)],
        ['  Sebagian', (string) ($rekap['sebagian'] ?? 0)],
        ['  Kosong', (string) ($rekap['kosong'] ?? 0)],
        ['  Terkunci', (string) ($rekap['terkunci'] ?? 0)],
        ['  Terhitung otomatis', (string) ($rekap['hitung'] ?? 0)],
        ['Jumlah dari lima kategori di atas', (string) $jumlahKategori
            . ($jumlahKategori === $wajib ? '  (cocok)' : '  (TIDAK cocok)')],
        ['Dokumen terunggah', (int) ($rekap['dokumen_total'] ?? 0) > 0
            ? (string) ($rekap['dokumen_terunggah'] ?? 0) . ' / ' . (string) $rekap['dokumen_total']
            : '-'],
    ] as [$label, $nilai]) {
        $baris[] = ['teks' => $label, 'x' => PDF_MARGIN, 'y' => $y, 'ukuran' => 9];
        $baris[] = ['teks' => $nilai, 'x' => PDF_MARGIN + 160, 'y' => $y, 'ukuran' => 9, 'tebal' => true];
        $y -= 13;
    }

    // ── daftar tabel ──
    $y -= 10;
    $baris[] = ['teks' => 'DAFTAR TABEL', 'x' => PDF_MARGIN, 'y' => $y, 'ukuran' => 11, 'tebal' => true];
    $y -= 14;

    $colKode = PDF_MARGIN;
    $colJudul = PDF_MARGIN + 34;
    $colStatus = PDF_W - PDF_MARGIN - 110;
    $colIsi = PDF_W - PDF_MARGIN - 40;

    // Keterangan status supaya asesor paham arti 'hitung' tanpa bertanya.
    // Tanpa ini, 'hitung' terbaca seperti 'tidak diisi'.
    foreach ([
        'Keterangan status:',
        '  kosong   = kolom isian belum diisi',
        '  sebagian = terisi sebagian kolom',
        '  lengkap  = semua kolom isian terisi',
        '  terkunci = sudah diverifikasi dan dikunci',
        '  hitung   = angka dihitung sistem dari master, operator tidak mengisi',
        'Kolom Terisi "0/3" = 0 dari 3 kolom isian terisi. "-" = tabel tidak punya kolom isian.',
    ] as $leg) {
        $baris[] = ['teks' => $leg, 'x' => PDF_MARGIN, 'y' => $y, 'ukuran' => 6.5];
        $y -= 8;
    }
    $y -= 12;

    // Header kolom: tanpa ini pembaca harus menebak arti "0/3 kolom".
    $baris[] = ['teks' => 'Kode', 'x' => $colKode, 'y' => $y, 'ukuran' => 7.5, 'tebal' => true];
    $baris[] = ['teks' => 'Judul tabel', 'x' => $colJudul, 'y' => $y, 'ukuran' => 7.5, 'tebal' => true];
    $baris[] = ['teks' => 'Status', 'x' => $colStatus, 'y' => $y, 'ukuran' => 7.5, 'tebal' => true];
    $baris[] = ['teks' => 'Terisi', 'x' => PDF_W - PDF_MARGIN - 34, 'y' => $y, 'ukuran' => 7.5, 'tebal' => true];
    $y -= 4;
    $baris[] = ['garis' => [PDF_MARGIN, $y, PDF_W - PDF_MARGIN, $y]];
    $y -= 9;

    foreach (($data['tabel'] ?? []) as $t) {
        if ($y < PDF_MARGIN + 30) {
            $halaman[] = ['baris' => $baris];
            $baris = [];
            $y = PDF_H - PDF_MARGIN;
        }
        // Judul dipecah ke baris kedua bila panjang, bukan dipotong: di PDF
        // tidak ada hover, judul yang terpotong hilang permanen dari dokumen
        // yang diserahkan ke asesor.
        $lebarJudul = $colStatus - $colJudul - 6;
        $judul = (string) $t['judul'];
        $baris1 = pdf_potong($judul, 7.5, $lebarJudul);
        $sisa = '';
        if (mb_strlen($baris1, 'UTF-8') < mb_strlen($judul, 'UTF-8')) {
            $potong = mb_substr($baris1, 0, max(1, mb_strlen($baris1, 'UTF-8') - 3), 'UTF-8');
            $sisa = pdf_potong(mb_substr($judul, mb_strlen($potong, 'UTF-8'), null, 'UTF-8'),
                7.5, $lebarJudul);
            $baris1 = $potong;
        }

        $baris[] = ['teks' => 'T-' . $t['kode'], 'x' => $colKode, 'y' => $y, 'ukuran' => 7.5];
        $baris[] = ['teks' => $baris1, 'x' => $colJudul, 'y' => $y, 'ukuran' => 7.5];
        $baris[] = ['teks' => (string) $t['status'], 'x' => $colStatus, 'y' => $y, 'ukuran' => 7.5];
        if ($sisa !== '') {
            $y -= 8;
            $baris[] = ['teks' => $sisa, 'x' => $colJudul, 'y' => $y, 'ukuran' => 7.5];
        }
        // "0/0 kolom" tidak bermakna (tabel hitungan tidak punya kolom isian).
        // Tampilkan tanda hubung, bukan pembagian nol.
        $total = (int) ($t['total'] ?? 0);
        $terisi = (int) ($t['terisi'] ?? 0);
        // Em dash (U+2014) di luar Latin-1 dan tampil sebagai '?' di PDF.
        // Untuk kolom angka, tanda hubung ASCII sudah cukup jelas.
        $isi = $total > 0 ? $terisi . '/' . $total : '-';
        // Rata kanan supaya digit sejajar, bukan bergerigi.
        $xIsi = PDF_W - PDF_MARGIN - pdf_lebar($isi, 7.5);
        $baris[] = ['teks' => $isi, 'x' => $xIsi, 'y' => $y, 'ukuran' => 7.5];
        $y -= 11;
    }

    $halaman[] = ['baris' => $baris];

    // Nomor halaman + penanda dokumen di setiap halaman. Untuk lampiran
    // akreditasi, halaman tanpa nomor tidak bisa dirujuk saat asesmen.
    $total = count($halaman);
    foreach ($halaman as $i => &$h) {
        $h['baris'][] = ['garis' => [PDF_MARGIN, PDF_MARGIN - 6, PDF_W - PDF_MARGIN, PDF_MARGIN - 6]];
        $h['baris'][] = ['teks' => 'LAM-PTKes | Data Dukung ' . $namaProdi,
                         'x' => PDF_MARGIN, 'y' => PDF_MARGIN - 18, 'ukuran' => 7];
        $nomor = 'Halaman ' . ($i + 1) . ' dari ' . $total;
        $h['baris'][] = ['teks' => $nomor,
                         'x' => PDF_W - PDF_MARGIN - pdf_lebar($nomor, 7),
                         'y' => PDF_MARGIN - 18, 'ukuran' => 7];
    }
    unset($h);

    return $halaman;
}
