<?php
declare(strict_types=1);

/**
 * Penegakan aturan kelompok field (√ pilih-satu).
 *
 * KENAPA DI SERVER, BUKAN HANYA DI HALAMAN: klien bisa dilewati. Siapa pun yang
 * punya sesi bisa POST /api/tabel/38/isi dengan dua kolom "Dalam Negeri" dan
 * "Luar Negeri" terisi sekaligus. Data seperti itu mustahil, dan asesor akan
 * menemukannya di sidang. Aturan harus di tempat yang tidak bisa dilangkahi.
 *
 * Sumber kebenaran: kolom `kelompok` + `pilih` di spec_field, yang diisi
 * db/aturan_kelompok.py.
 */

/**
 * Periksa satu baris isian.
 *
 * @param array $nilai  [kunci_field => nilai]
 * @param array $spec   baris spec_field untuk tabel ini
 * @return string|null  pesan galat, atau null kalau sah
 */
function periksa_kelompok(array $nilai, array $spec): ?string
{
    // Kumpulkan kelompok yang aturannya 'satu'.
    $grup = [];
    foreach ($spec as $f) {
        if (!empty($f['kelompok']) && ($f['pilih'] ?? '') === 'satu') {
            $grup[$f['kelompok']][] = $f;
        }
    }

    foreach ($grup as $nama => $fields) {
        $terisi = [];
        foreach ($fields as $f) {
            $v = $nilai[$f['kunci']] ?? null;
            if ($v !== null && $v !== '' && $v !== '0' && $v !== 0) {
                $terisi[] = $f['label'] ?: $f['kunci'];
            }
        }
        if (count($terisi) > 1) {
            $rapi = str_replace('_', ' ', (string) $nama);
            return "Kolom {$rapi} hanya boleh satu yang diisi, tapi "
                . count($terisi) . ' terisi (' . implode(', ', $terisi) . '). '
                . 'Satu baris tidak boleh punya dua nilai untuk pilihan yang sama.';
        }
    }
    return null;
}

/**
 * Ambil spec_field satu tabel, dengan kelompok/pilih ikut.
 * Dipakai periksa_kelompok() dan halaman isian.
 */
function spec_satu_tabel(string $kode): array
{
    return q('SELECT urut, label, kunci, tipe, asal, kelompok, pilih, keterangan
              FROM spec_field WHERE tabel_kode = ? ORDER BY urut', [$kode]);
}
