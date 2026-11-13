<?php
declare(strict_types=1);

/**
 * Helper kecil yang dipakai lebih dari satu entitas master milik Agent 3
 * (mahasiswa, dosen). Hanya fungsi murni — tidak membaca request, tidak
 * mencetak, jadi bisa di-require dari uji tanpa HTTP.
 */

if (!function_exists('pilihan')) {
    /** <option> dari daftar nilai. */
    function pilihan(array $nilai, string $dipilih): string
    {
        $out = '';
        foreach ($nilai as $v) {
            $out .= '<option value="' . e($v) . '"' . ($v === $dipilih ? ' selected' : '') . '>'
                . e($v) . '</option>';
        }
        return $out;
    }
}

if (!function_exists('csv_baris')) {
    /**
     * Urai teks CSV jadi ['baris' => nomor fisik di berkas, 'kolom' => [...]].
     * Nomor baris fisik (bukan urutan data) yang dilaporkan ke operator, supaya
     * "baris 4 ditolak" cocok dengan yang terlihat di Excel.
     */
    function csv_baris(string $csv): array
    {
        $csv = preg_replace('/^\xEF\xBB\xBF/', '', str_replace(["\r\n", "\r"], "\n", $csv));
        $out = [];
        foreach (explode("\n", (string) $csv) as $i => $satu) {
            if (trim($satu) === '') {
                continue;
            }
            $out[] = ['baris' => $i + 1, 'kolom' => array_map('trim', str_getcsv($satu, ',', '"', '\\'))];
        }
        return $out;
    }
}
