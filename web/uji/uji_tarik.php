<?php
declare(strict_types=1);

/**
 * Uji tarik data master ke tabel data dukung.
 *
 * Yang dijaga di sini bukan "fitur jalan", tapi tiga janji yang kalau dilanggar
 * merusak data tanpa suara:
 *
 *  1. IDEMPOTEN — tarik dua kali tidak menggandakan baris. Kalau tidak,
 *     jumlah dosen di Tabel 16 bisa dua kali lipat setelah operator menekan
 *     tombol dua kali, dan rasio dosen:mahasiswa di Tabel 15 jadi salah.
 *
 *  2. FIELD MASTER IKUT BERUBAH — kalau nama dosen diperbaiki di master,
 *     Tabel 16 harus ikut. Menahannya berarti data basi bersembunyi.
 *
 *  3. ISIAN MANUAL TIDAK HILANG — field asal='Input' milik operator tidak
 *     boleh disentuh tarikan. Ini kebalikan dari nomor 2, dan keduanya harus
 *     benar sekaligus; itulah yang mudah salah.
 *
 * Jalankan: LAMPTKES_ENV=lokal php web/uji/uji_tarik.php
 */

require_once __DIR__ . '/../system/config.php';
require_once __DIR__ . '/../system/db.php';
require_once __DIR__ . '/../system/auth.php';
require_once __DIR__ . '/../system/rekap.php';
require_once __DIR__ . '/../system/tarik.php';
require_once __DIR__ . '/bersih.php';

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

bersihkan_data_uji(db(), true);

$p = (int) qv('SELECT id FROM prodi WHERE aktif = 1 ORDER BY id LIMIT 1', [], 0);
$per = $p > 0 ? periode_aktif($p) : null;
cek('prodi + periode aktif ada', $p > 0 && $per !== null);

// Kalau tidak ada prodi/periode, uji selebihnya tidak bisa dijalankan —
// dan gagal karena sebab yang salah. Berhenti dengan pesan jelas.
if ($p === 0 || $per === null) {
    echo "\nTidak bisa melanjutkan: prodi/periode tidak ada. Jalankan db/migrasi/005_seed.sql.\n";
    exit(1);
}
$perId = (int) $per['id'];

echo "\n── peta tarikan ──\n";

$bisa = tabel_bisa_tarik();
sort($bisa);
cek('5 tabel punya peta tarikan', $bisa === ['16', '19', '29', '30', '31'], implode(',', $bisa));
cek('tabel_bisa_tarik mengembalikan STRING, bukan int',
    $bisa !== [] && is_string($bisa[0]),
    'array_keys() mengubah "16" jadi int 16; in_array ketat lalu selalu false');
cek('tabel tanpa peta ditolak jujur',
    isset(tarik_master('01', $p, $perId)['error']));

echo "\n── tarik + idempoten ──\n";

// Siapkan satu dosen tetap.
$dosenId = sisip('dosen', [
    'nama' => 'Bd. Uji Tarik',
    'jenis' => 'Dosen Tetap',
    'jabatan_fungsional' => 'Lektor',
    'nidn_nidk_nup' => '0011223344',
    'gelar_akademik' => 'S.ST., M.Keb',
]);
ex('INSERT INTO dosen_prodi (dosen_id, prodi_id) VALUES (?,?)', [$dosenId, $p]);

$h1 = tarik_master('16', $p, $perId);
cek('tarik pertama membuat baris', ($h1['dibuat'] ?? 0) >= 1, json_encode($h1));

$h2 = tarik_master('16', $p, $perId);
cek('tarik kedua TIDAK menambah baris', ($h2['dibuat'] ?? 0) === 0, json_encode($h2));

$n = (int) qv('SELECT COUNT(*) FROM baris_tabel WHERE tabel_kode = ? AND prodi_id = ? AND periode_id = ?',
    ['16', $p, $perId], 0);
cek('baris tabel 16 tidak berganda', $n === 1, 'dapat ' . $n);

echo "\n── NIDN tetap teks (nol depan tidak hilang) ──\n";

$nilai = qv("SELECT s.nilai_teks FROM sel s JOIN baris_tabel b ON b.id = s.baris_id
             WHERE b.tabel_kode = '16' AND b.sumber_id = ? AND s.field_kunci = 'nidn_atau_nidk'",
    [$dosenId], null);
cek('NIDN tersimpan sebagai teks', $nilai !== null, var_export($nilai, true));
cek('NIDN "0011223344" utuh (nol depan tidak hilang)', $nilai === '0011223344', var_export($nilai, true));

echo "\n── field master ikut berubah ──\n";

ubah('dosen', ['nama' => 'Bd. Uji Tarik DIUBAH'], 'id', $dosenId);
tarik_master('16', $p, $perId);
$nama = qv("SELECT s.nilai_teks FROM sel s JOIN baris_tabel b ON b.id = s.baris_id
            WHERE b.tabel_kode = '16' AND b.sumber_id = ? AND s.field_kunci = 'nama_dosen_tetap'",
    [$dosenId], '');
cek('nama di tabel ikut berubah setelah master diperbaiki',
    str_contains($nama, 'DIUBAH'), $nama);

echo "\n── isian manual tidak hilang ──\n";

// Field Input di tabel 16 = 'no'. Operator mengisinya manual.
$barisId = (int) qv('SELECT id FROM baris_tabel WHERE tabel_kode = ? AND sumber_id = ?',
    ['16', $dosenId], 0);
ex('INSERT INTO sel (baris_id, field_kunci, nilai_angka, nilai_teks) VALUES (?,?,?,?)
    ON DUPLICATE KEY UPDATE nilai_angka=VALUES(nilai_angka)',
    [$barisId, 'no', 7, null]);

tarik_master('16', $p, $perId);
$no = qv("SELECT nilai_angka FROM sel WHERE baris_id = ? AND field_kunci = 'no'", [$barisId], null);
cek('isian manual (no=7) selamat setelah tarik ulang',
    $no !== null && (int) $no === 7, var_export($no, true));

echo "\n── tabel master yang kosong ──\n";

// Tabel 30 ditarik dari `wahana`. Kalau master kosong, tarik harus
// mengembalikan 0 tanpa galat — bukan crash.
ex('DELETE FROM wahana WHERE prodi_id = ?', [$p]);
$h = tarik_master('30', $p, $perId);
cek('master kosong -> 0 baris, bukan galat', ($h['dibuat'] ?? -1) === 0, json_encode($h));

bersihkan_data_uji(db(), true);
$sisa = (int) qv('SELECT COUNT(*) FROM baris_tabel', [], 0);
cek('data uji bersih setelah uji', $sisa === 0, 'sisa ' . $sisa);

printf("\n%d lulus, %d gagal\n", $lulus, $gagal);
exit($gagal === 0 ? 0 : 1);
