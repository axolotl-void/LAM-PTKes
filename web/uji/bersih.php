<?php
declare(strict_types=1);

/**
 * Jalankan semua uji dari keadaan BERSIH, dan bersihkan sesudahnya.
 *
 * KENAPA: dua kali uji jadi gagal bukan karena kodenya salah, tapi karena uji
 * lain meninggalkan baris data (1 mahasiswa, 1 mata kuliah, 1 pustaka, ...).
 * Uji yang bergantung pada "tabel kosong" lalu menghitung 4 alih-alih 3.
 * Uji yang hasilnya bergantung pada sisa uji sebelumnya tidak membuktikan apa
 * pun — ia cuma mengukur urutan.
 *
 * Jadi: sebelum dan sesudah, semua tabel yang diisi operator dikosongkan.
 * Yang TIDAK dihapus: master hasil migrasi (prodi, kriteria, tabel_dukung,
 * spec_field, dokumen_spec, konsistensi_aturan, pengguna, peran, izin).
 * Itu bagian dari skema, bukan data uji.
 *
 * Jalankan: bash web/uji/jalankan_semua.sh
 */

/** Tabel yang isinya dibuat uji/operator, bukan hasil migrasi. */
const TABEL_UJI = [
    'sel', 'baris_tabel',              // isi 38 tabel data dukung
    'mahasiswa', 'dosen', 'dosen_prodi', 'dosen_pendidikan',
    'mata_kuliah', 'laboratorium', 'pustaka',
    'uji_kompetensi', 'survei', 'tracer', 'stase',
    'mitra', 'mitra_wahana', 'mitra_kegiatan',
    'keuangan_terima', 'keuangan_guna',
    'audit', 'sesi', 'dokumen_unggah',
    // `periode` SENGAJA tidak dihapus: isinya datang dari 005_seed.sql
    // (TS 2025/2026 untuk dua prodi), dan hampir semua uji butuh periode
    // aktif. Menghapusnya membuat uji gagal karena sebab yang salah.
];

function bersihkan_data_uji(PDO $db, bool $diam = false): void
{
    // Matikan FK check sementara: urutan hapus antar tabel bertaut tidak
    // selalu bisa dijamin, dan yang penting hasilnya kosong, bukan urutannya.
    $db->exec('SET FOREIGN_KEY_CHECKS = 0');
    foreach (TABEL_UJI as $t) {
        try {
            $db->exec('DELETE FROM ' . $t);
        } catch (PDOException $e) {
            // Tabel mungkin belum ada di DB ini (migrasi sebagian). Bukan
            // kegagalan: yang penting tabel yang ada sudah kosong.
            if (!$diam) {
                fwrite(STDERR, "  (lewati $t: " . $e->getMessage() . ")\n");
            }
        }
    }
    $db->exec('SET FOREIGN_KEY_CHECKS = 1');
}

if (PHP_SAPI === 'cli' && realpath($argv[0] ?? '') === realpath(__FILE__)) {
    require_once __DIR__ . '/../system/config.php';
    require_once __DIR__ . '/../system/db.php';
    bersihkan_data_uji(db());
    echo "DB bersih.\n";
}
