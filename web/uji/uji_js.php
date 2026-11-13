<?php
declare(strict_types=1);

/**
 * Periksa sintaks semua modul JS frontend lewat node --check.
 *
 * Kenapa ada: tiga kali saya menulis string JS yang dibuka dengan kutip satu
 * tapi ditutup backtick. `node --check` tidak menangkapnya kalau berkas
 * dianggap CommonJS (template literal di dalam '...' baru gagal saat modul
 * diimpor sebagai ESM). Skrip ini menyalin tiap berkas ke .mjs dulu, baru
 * memeriksa — persis seperti browser memuatnya.
 *
 * Jalankan: php web/uji/uji_js.php
 */

$akar = dirname(__DIR__, 2) . '/app/assets/js';
$berkas = [];
foreach (['', '/pages'] as $sub) {
    foreach (glob($akar . $sub . '/*.js') ?: [] as $f) {
        $berkas[] = $f;
    }
}
sort($berkas);

if ($berkas === []) {
    fwrite(STDERR, "Tidak ada berkas JS ditemukan di $akar\n");
    exit(1);
}

$gagal = 0;
foreach ($berkas as $f) {
    $tmp = sys_get_temp_dir() . '/cek-' . basename($f, '.js') . '-' . substr(md5($f), 0, 6) . '.mjs';
    copy($f, $tmp);
    $keluar = [];
    exec('node --check ' . escapeshellarg($tmp) . ' 2>&1', $keluar, $kode);
    @unlink($tmp);

    $rel = str_replace($akar . '/', '', $f);

    if ($kode === 0) {
        printf("  ok   %s\n", $rel);
    } else {
        $gagal++;
        printf("  GAGAL %s\n", $rel);
        foreach (array_slice($keluar, 0, 4) as $baris) {
            printf("        %s\n", trim($baris));
        }
    }
}

printf("\n%d berkas, %d gagal\n", count($berkas), $gagal);
exit($gagal === 0 ? 0 : 1);
