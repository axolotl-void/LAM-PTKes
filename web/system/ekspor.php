<?php
declare(strict_types=1);

/**
 * Ekspor XLSX untuk frontend (app/).
 *
 * KENAPA ADA DI SINI, BUKAN DI web/modul/ekspor/: modul itu bagian dari
 * halaman PHP lama yang sudah tidak dirutekan. Logikanya masih benar dan
 * diuji (uji_ekspor 46 uji), tapi pintunya mati. Berkas ini hanya menyambung:
 * mengambil data yang sama, lalu mengirim berkasnya lewat api.php.
 *
 * ATURAN: ekspor BLOKIR kalau konsistensi masih gagal. Alasannya bukan
 * kemewahan — file XLSX yang diserahkan ke asesor dengan angka tidak konsisten
 * antar tabel lebih berbahaya daripada tidak punya file sama sekali, karena
 * asesor bisa memakainya untuk menolak.
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/rekap.php';
require_once __DIR__ . '/xlsx.php';
require_once __DIR__ . '/../modul/ekspor/fungsi.php';

/**
 * Kirim berkas XLSX ke klien lalu berhenti.
 *
 * Dipanggil dari api.php; tidak kembali.
 */
function ekspor_xlsx_kirim(int $prodi, ?int $periode): never
{
    $blokir = ekspor_blokir($prodi);
    if ($blokir !== []) {
        // 409: permintaan sah tapi keadaannya belum siap. Pesannya menyebut
        // apa yang harus diperbaiki, bukan hanya "ditolak".
        json_keluar([
            'error' => 'Ekspor belum bisa: ' . count($blokir) . ' hal harus diperbaiki.',
            'blokir' => array_map(fn($b) => [
                'kode' => $b['kode'], 'nama' => $b['nama'], 'pesan' => $b['pesan'],
            ], $blokir),
        ], 409);
    }

    $nama = qv('SELECT nama_ps FROM prodi WHERE id = ?', [$prodi], 'prodi');
    $isi = ekspor_data($prodi, $periode);
    $sheets = ekspor_sheets($prodi, $periode);

    $tmp = tempnam(sys_get_temp_dir(), 'lamptkes-') . '.xlsx';
    if (!xlsx_buat($sheets, $tmp)) {
        @unlink($tmp);
        json_keluar(['error' => 'Gagal membuat berkas XLSX.'], 500);
    }

    $unduh = 'Data-Dukung-' . preg_replace('/[^A-Za-z0-9]+/', '-', $nama)
        . '-' . date('Ymd') . '.xlsx';

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $unduh . '"');
    header('Content-Length: ' . (string) filesize($tmp));
    header('Cache-Control: no-store');
    readfile($tmp);
    @unlink($tmp);
    exit;
}
