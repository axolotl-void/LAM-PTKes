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

/* ── modul yang punya muat() WAJIB terdaftar di `pemuat` ────────────────
 * Bug yang pernah lolos: `identitas.js` punya muat(), endpoint-nya membalas
 * 200, tapi `pemuat` di app.js tidak punya entri `identitas`. Hasilnya
 * halaman berhenti di "Memuat…" selamanya TANPA error di konsol — karena
 * tidak ada yang memanggil muat(). Tidak ada uji lain yang menangkap ini:
 * sintaksnya benar, API-nya benar, halamannya cuma diam.
 */
$app = file_get_contents($akar . '/app.js');
if ($app === false) {
    $gagal++;
    printf("  GAGAL app.js tidak terbaca\n");
} else {
    preg_match('/const pemuat = \{(.*?)\n\};/s', $app, $mp);
    $blok = $mp[1] ?? '';
    $terdaftar = [];
    foreach (preg_split('/\r?\n/', $blok) as $baris) {
        if (preg_match('/^\s*([a-zA-Z_][a-zA-Z0-9_]*)\s*:/', $baris, $mm)) {
            $terdaftar[] = $mm[1];
        }
    }

    $hilang = [];
    foreach (glob($akar . '/pages/*.js') as $file) {
        $nama = basename($file, '.js');
        $isi = (string) file_get_contents($file);
        if (preg_match('/export\s+async\s+function\s+muat\s*\(/', $isi)
            && !in_array($nama, $terdaftar, true)) {
            $hilang[] = $nama;
        }
    }

    if ($hilang === []) {
        printf("  ok   semua modul ber-muat() terdaftar di pemuat (%d entri)\n", count($terdaftar));
    } else {
        $gagal++;
        printf("  GAGAL modul punya muat() tapi tidak ada di pemuat app.js: %s\n",
            implode(', ', $hilang));
        printf("        halaman itu akan berhenti di \"Memuat…\" tanpa error.\n");
    }
}

printf("\n%d berkas, %d gagal\n", count($berkas), $gagal);
exit($gagal === 0 ? 0 : 1);
