"""Bangkitkan web/assets/ikon.php dari mockup/assets/js/icons.js.

Sumber kebenaran ikon ada di icons.js (dipakai JS dan digambar juga di FigCraft).
PHP butuh bentuk yang sama. Kalau disalin tangan, cepat atau lambat berbeda.

Jalankan: python3 db/gen_ikon.php.py  (lihat cara pakai di docstring bawah)
"""
import re
import sys

SRC = "/Users/yogiprasetyasadewa/Documents/03_Proyek/LAM-PTKES/mockup/assets/js/icons.js"
OUT = "/Users/yogiprasetyasadewa/Documents/03_Proyek/LAM-PTKES/web/assets/ikon.php"

teks = open(SRC).read()

# Ambil pasangan  nama: 'path'  di dalam objek ICONS.
pasangan = re.findall(r"(\w+)\s*:\s*'([^']+)'", teks)
if len(pasangan) < 10:
    sys.exit(f"hanya {len(pasangan)} ikon terbaca dari {SRC} — periksa format icons.js")

baris = []
for nama, path in pasangan:
    baris.append(f"    '{nama}' => '{path}',")

php = """<?php
declare(strict_types=1);

/**
 * Ikon stroke 16x16 — dipakai PHP.
 *
 * DIBANGKITKAN dari `mockup/assets/js/icons.js` oleh `db/gen_ikon.py`.
 * Jangan disunting tangan: ubah icons.js lalu jalankan generatornya, supaya
 * ikon di PHP dan di JS tidak pernah berbeda bentuk.
 */

const IKON = [
""" + "\n".join(baris) + """
];

/**
 * SVG ikon. Atribut sama persis dengan `icons.js` supaya ukuran, tebal garis,
 * dan posisi identik dengan tampilan lama.
 */
function ikon(string $nama, int $ukuran = 16): string
{
    $path = IKON[$nama] ?? '';
    if ($path === '') {
        return '';
    }
    return '<svg class="ico" width="' . $ukuran . '" height="' . $ukuran . '" viewBox="0 0 16 16"'
        . ' fill="none" stroke="currentColor" stroke-width="1.35"'
        . ' stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
        . '<path d="' . e($path) . '"/></svg>';
}
"""

open(OUT, "w").write(php)
print(f"{len(pasangan)} ikon -> {OUT}")
