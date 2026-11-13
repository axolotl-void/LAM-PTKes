<?php
declare(strict_types=1);

/**
 * Pembuat .xlsx dari nol — tanpa Composer, tanpa PhpSpreadsheet.
 *
 * Kenapa ditulis sendiri: cPanel tidak selalu punya Composer, sedangkan .xlsx
 * sebenarnya cuma arsip ZIP berisi XML. PHP punya ZipArchive, jadi yang
 * dibutuhkan hanya 6 bagian minimal OOXML di bawah. Library pihak ketiga di
 * sini berarti satu titik gagal yang tidak bisa diperbaiki di server kampus.
 *
 * Pemakaian:
 *   xlsx_buat([
 *     ['nama' => 'Tabel 01', 'header' => ['No', 'Nama'], 'baris' => [[1, 'Ani'], [2, 'Budi']]],
 *   ], '/tmp/keluaran.xlsx');
 *
 * Semua teks lewat xlsx_teks() (htmlspecialchars ENT_XML1). Angka ditulis
 * sebagai <v> supaya Excel menghitungnya, bukan sebagai teks rata kiri.
 */

const XLSX_NS  = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
const XLSX_REL = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

/**
 * Tulis $sheet ke $berkas. true = berkas jadi.
 * $sheet = [['nama'=>string, 'header'=>string[], 'baris'=>array<array>], ...]
 */
function xlsx_buat(array $sheet, string $berkas): bool
{
    $sheet = array_values(array_filter(
        $sheet,
        fn($s) => is_array($s) && isset($s['nama']) && is_array($s['header'] ?? null)
    ));
    if ($sheet === []) {
        return false;
    }

    $zip = new ZipArchive();
    if ($zip->open($berkas, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        return false;
    }

    $tipe   = '';
    $relLbr = '';
    $daftar = '';
    $dipakai = [];
    foreach ($sheet as $i => $s) {
        $n = $i + 1;
        $nama = xlsx_nama_sheet((string) $s['nama'], $dipakai);
        $dipakai[] = $nama;
        $tipe .= sprintf(
            '<Override PartName="/xl/worksheets/sheet%d.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>',
            $n
        );
        $relLbr .= sprintf('<Relationship Id="rId%d" Type="%s/worksheet" Target="worksheets/sheet%d.xml"/>', $n, XLSX_REL, $n);
        $daftar .= sprintf('<sheet name="%s" sheetId="%d" r:id="rId%d"/>', xlsx_teks($nama), $n, $n);

        $zip->addFromString("xl/worksheets/sheet{$n}.xml", xlsx_lembar($s));
    }

    $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        . '<Default Extension="xml" ContentType="application/xml"/>'
        . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
        . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
        . $tipe
        . '</Types>');

    $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="' . XLSX_REL . '/officeDocument" Target="xl/workbook.xml"/>'
        . '</Relationships>');

    $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<workbook xmlns="' . XLSX_NS . '" xmlns:r="' . XLSX_REL . '">'
        . '<sheets>' . $daftar . '</sheets></workbook>');

    $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . $relLbr
        . '<Relationship Id="rIdS" Type="' . XLSX_REL . '/styles" Target="styles.xml"/>'
        . '</Relationships>');

    // Gaya minimal: 0 = biasa, 1 = header tebal. fill gray125 wajib ada,
    // beberapa versi Excel menolak berkas tanpa dua fill bawaan.
    $zip->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<styleSheet xmlns="' . XLSX_NS . '">'
        . '<fonts count="2"><font><sz val="10"/><name val="Calibri"/></font>'
        . '<font><b/><sz val="10"/><name val="Calibri"/></font></fonts>'
        . '<fills count="2"><fill><patternFill patternType="none"/></fill>'
        . '<fill><patternFill patternType="gray125"/></fill></fills>'
        . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
        . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
        . '<cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
        . '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/></cellXfs>'
        . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
        . '</styleSheet>');

    return $zip->close();
}

/**
 * Nama sheet yang diterima Excel: maks 31 karakter, tanpa : \ / ? * [ ],
 * tidak diawali/diakhiri kutip, tidak boleh ganda.
 */
function xlsx_nama_sheet(string $nama, array $dipakai = []): string
{
    $n = str_replace([':', '\\', '/', '?', '*', '[', ']'], ' ', $nama);
    $n = trim(preg_replace('/\s+/u', ' ', $n) ?? '');
    $n = trim($n, "'");
    if ($n === '') {
        $n = 'Sheet';
    }
    $n = mb_substr($n, 0, 31);
    if (in_array($n, $dipakai, true)) {
        $akar = mb_substr($n, 0, 27);
        for ($i = 2; $i < 100; $i++) {
            $calon = $akar . ' (' . $i . ')';
            if (!in_array($calon, $dipakai, true)) {
                return $calon;
            }
        }
    }
    return $n;
}

/** Nomor kolom -> huruf Excel: 1=A, 27=AA. */
function xlsx_kolom(int $n): string
{
    $s = '';
    while ($n > 0) {
        $n--;
        $s = chr(65 + $n % 26) . $s;
        $n = intdiv($n, 26);
    }
    return $s;
}

/** Escape XML. Karakter kontrol yang tidak sah dibuang dulu. */
function xlsx_teks(string $s): string
{
    $s = preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}]/u', '', $s) ?? '';
    return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

/**
 * Nilai sel angka, atau null kalau bukan angka.
 * String dengan nol depan ("0012345") tetap teks — itu NIM, bukan bilangan.
 */
function xlsx_angka(mixed $v): ?string
{
    if (is_int($v)) {
        return (string) $v;
    }
    if (is_float($v)) {
        if (!is_finite($v)) {
            return null;
        }
        $s = rtrim(rtrim(sprintf('%.4F', $v), '0'), '.');
        return $s === '-0' ? '0' : $s;
    }
    if (is_string($v)) {
        $t = trim($v);
        if (preg_match('/^(?:0|-?[1-9]\d*)(?:\.\d+)?$/', $t) === 1) {
            // "250.0000" dari kolom decimal MySQL -> "250", bukan teks.
            if (str_contains($t, '.')) {
                $t = rtrim(rtrim($t, '0'), '.');
            }
            return $t;
        }
    }
    return null;
}

/** Satu sel <c>. Angka -> <v>, selain itu inlineStr. */
function xlsx_sel(string $ref, mixed $nilai, int $gaya = 0): string
{
    if ($nilai === null || $nilai === '') {
        return '';
    }
    $s = $gaya > 0 ? ' s="' . $gaya . '"' : '';
    $angka = xlsx_angka($nilai);
    if ($angka !== null) {
        return sprintf('<c r="%s"%s><v>%s</v></c>', $ref, $s, $angka);
    }
    return sprintf('<c r="%s"%s t="inlineStr"><is><t xml:space="preserve">%s</t></is></c>',
        $ref, $s, xlsx_teks((string) $nilai));
}

/** Isi xl/worksheets/sheetN.xml untuk satu sheet. */
function xlsx_lembar(array $s): string
{
    $header = array_values((array) $s['header']);
    $lebar  = max(1, count($header));

    $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<worksheet xmlns="' . XLSX_NS . '">';

    // Kolom pertama sempit (nomor), sisanya cukup untuk label panjang Tabel 5.
    if ($lebar > 1) {
        $xml .= '<cols><col min="1" max="1" width="6" customWidth="1"/>'
            . sprintf('<col min="2" max="%d" width="20" customWidth="1"/></cols>', $lebar);
    }

    $xml .= '<sheetData>';
    $baris = 1;
    $sel = [];
    foreach ($header as $i => $h) {
        $sel[] = xlsx_sel(xlsx_kolom($i + 1) . $baris, (string) $h, 1);
    }
    $xml .= '<row r="1">' . implode('', $sel) . '</row>';

    foreach ((array) $s['baris'] as $data) {
        $baris++;
        $sel = [];
        $kolom = 0;
        foreach ((array) $data as $v) {
            $kolom++;
            $sel[] = xlsx_sel(xlsx_kolom($kolom) . $baris, $v);
        }
        $xml .= '<row r="' . $baris . '">' . implode('', $sel) . '</row>';
    }

    return $xml . '</sheetData></worksheet>';
}
