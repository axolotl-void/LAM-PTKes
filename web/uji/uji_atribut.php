<?php
declare(strict_types=1);

/**
 * Uji atribut yang BENTROK antara dua fitur berbeda.
 *
 * BUG YANG DIJAGA: mengetik di sel isian tabel memunculkan dialog
 *   "Kunci T-praktikum_judul_modul? Setelah dikunci isian ditolak sampai
 *   Admin UPPS membuka."
 *
 * Penyebabnya: `data-kunci` dipakai dua hal yang tidak berhubungan —
 *   - tombol kunci TABEL  : data-kunci="01"          (halaman Data Dukung)
 *   - sel isian tabel     : data-kunci="nama_kolom"  (halaman isian)
 * Handler klik global memakai closest('[data-kunci]'), jadi elemen mana pun
 * yang punya atribut itu dianggap tombol kunci tabel. Nama kolom dibaca
 * sebagai nomor tabel, dan dialognya muncul saat orang mau mengisi data.
 *
 * `data-buka` kena masalah yang sama: accordion dokumen memakainya, dan
 * handler buka-kunci juga. Membuka grup dokumen mencoba membuka kunci "K1".
 *
 * Kenapa tidak ada uji lain yang menangkap: sintaks benar, endpoint benar,
 * render benar. Yang salah hanya ARTI sebuah atribut, dan arti tidak bisa
 * diperiksa `node --check`.
 *
 * Jalankan: LAMPTKES_ENV=lokal php web/uji/uji_atribut.php
 */

$akar = dirname(__DIR__, 2);
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

$js = function (string $berkas) use ($akar): string {
    $p = $akar . '/app/assets/js/' . $berkas;
    return is_file($p) ? (string) file_get_contents($p) : '';
};

echo "── data-kunci: dua arti, satu atribut ──\n";

$app = $js('app.js');
$tabelisi = $js('pages/tabelisi.js');
$datadukung = $js('pages/datadukung.js');

cek('app.js terbaca', $app !== '');

// Handler kunci HARUS memakai selector yang hanya cocok dengan tombol kunci
// tabel. closest('[data-kunci]') adalah bug aslinya.
preg_match("/const kunci = e\.target\.closest\(([^)]*)\)/", $app, $mk);
$selKunci = $mk[1] ?? '';
cek('handler kunci ditemukan', $selKunci !== '', 'regex tidak cocok');
cek('handler kunci TIDAK memakai [data-kunci] mentah',
    !str_contains($selKunci, "'[data-kunci]'"),
    'selector = ' . $selKunci);
cek('handler kunci memakai atribut khusus tombol tabel',
    str_contains($selKunci, 'data-kunci-tabel') || str_contains($selKunci, '.dd-kunci'),
    'selector = ' . $selKunci);

// Sel isian tetap boleh memakai data-kunci — itu nama kolom.
cek('sel isian masih membawa data-kunci (nama kolom)',
    str_contains($tabelisi, 'data-kunci='),
    'kalau ini hilang, payload isian kehilangan nama kolom');

echo "\n── data-buka: accordion vs buka kunci ──\n";

$dokumen = $js('pages/dokumen.js');
preg_match("/const buka = e\.target\.closest\(([^)]*)\)/", $app, $mb);
$selBuka = $mb[1] ?? '';
cek('handler buka kunci ditemukan', $selBuka !== '', 'regex tidak cocok');
cek('handler buka kunci TIDAK memakai [data-buka] mentah',
    !str_contains($selBuka, "'[data-buka]'"), 'selector = ' . $selBuka);
cek('accordion dokumen tetap memakai data-buka',
    str_contains($dokumen, 'data-buka='),
    'kalau ini hilang, grup dokumen tidak bisa dibuka');

echo "\n── teks dialog harus menyebut TABEL, bukan kolom ──\n";

// Dialog lama: "Kunci T-praktikum_judul_modul?" — menyebut nama kolom.
cek('dialog kunci menyebut "Tabel T-"',
    str_contains($app, 'Kunci Tabel T-'),
    'tanpa kata "Tabel", nomor tabel dan nama kolom tidak bisa dibedakan pembaca');

echo "\n── setiap atribut pemicu harus punya pasangan handler ──\n";

// Atribut yang dirender tapi tidak pernah ditangani = tombol mati.
// Atribut yang ditangani tapi tidak pernah dirender = kode mati.
$pasangan = [
    'data-kunci-tabel' => ['datadukung.js'],
    'data-kunci-buka' => ['datadukung.js'],
];
foreach ($pasangan as $atribut => $berkas) {
    $dirender = false;
    foreach ($berkas as $b) {
        if (str_contains($js('pages/' . $b), $atribut)) { $dirender = true; }
    }
    cek("{$atribut} dirender di halaman", $dirender);
    cek("{$atribut} ditangani di app.js", str_contains($app, $atribut));
}

echo "\n── tidak ada selector longgar yang memangsa atribut bersama ──\n";

// Pola paling berbahaya: closest('[data-x]') untuk atribut yang juga dipakai
// elemen lain. Ini cara bug ini lahir.
$bahaya = [];
foreach ([['data-kunci', $app], ['data-buka', $app]] as [$atr, $isi]) {
    if (preg_match("/closest\(\['\[" . $atr . "\]'\]\)/", $isi)) {
        $bahaya[] = $atr;
    }
}
cek('tidak ada closest([data-kunci]/[data-buka]) mentah', $bahaya === [],
    implode(',', $bahaya));

printf("\n%d lulus, %d gagal\n", $lulus, $gagal);
exit($gagal === 0 ? 0 : 1);
