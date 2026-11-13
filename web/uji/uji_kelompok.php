<?php
declare(strict_types=1);

/**
 * Uji aturan kelompok field (√ pilih-satu vs boleh-banyak).
 *
 * Kenapa ini perlu: instrumen memakai tanda √ untuk dua arti berbeda.
 * Tabel 22 kolom 6-8 = pilih satu tahun penelitian. Tabel 16 kolom 3-4 =
 * boleh dua-duanya (dosennnya mengajar akademik DAN profesi). Kalau aturan ini
 * salah, operator bisa menyimpan data yang mustahil dan tidak ada yang tahu.
 *
 * Jalankan: php web/uji/uji_kelompok.php [nama_db]
 * Butuh DB yang sudah dimigrasi 001-004.
 */

require_once __DIR__ . '/../system/db.php';

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

echo "── aturan kelompok ──\n";

// 1) Field yang MUSTAHIL dua-duanya harus bertanda 'satu'.
//    Tabel 38: satu instansi ada di satu negara. Ini yang paling jelas.
$a = q1("SELECT kelompok, pilih FROM spec_field
         WHERE tabel_kode='38' AND kunci LIKE '%dalam_negeri%'");
$b = q1("SELECT kelompok, pilih FROM spec_field
         WHERE tabel_kode='38' AND kunci LIKE '%luar_negeri%'");
cek('38 Dalam Negeri & Luar Negeri satu kelompok',
    $a && $b && $a['kelompok'] === $b['kelompok'] && $a['kelompok'] !== '');
cek('38 lokasi mitra = pilih satu',
    $a && $a['pilih'] === 'satu',
    'kalau banyak, satu mitra bisa tercatat di dua negara');

// 2) Tabel 22: satu penelitian terjadi di satu tahun.
$th = q("SELECT kunci, kelompok, pilih FROM spec_field
         WHERE tabel_kode='22' AND kelompok='tahun_penelitian'");
cek('22 punya 3 kolom tahun (TS-2, TS-1, TS)', count($th) === 3, 'dapat ' . count($th));
cek('22 tahun penelitian = pilih satu',
    $th !== [] && count(array_filter($th, fn($r) => $r['pilih'] === 'satu')) === count($th));

// 3) Tabel 16: dosen BOLEH mengajar akademik dan profesi sekaligus.
//    Instrumen catatan 5) menyebutnya eksplisit.
$tahap = q("SELECT kunci, pilih FROM spec_field
            WHERE tabel_kode='16' AND kelompok='tahap'");
cek('16 punya 2 kolom tahap', count($tahap) === 2, 'dapat ' . count($tahap));
cek('16 tahap = boleh banyak',
    $tahap !== [] && count(array_filter($tahap, fn($r) => $r['pilih'] === 'banyak')) === count($tahap),
    'instrumen catatan 5: dapat diberi tanda √ pada kedua-duanya');

// 4) Kelompok tidak boleh punya campuran 'satu' dan 'banyak' dalam satu
//    kelompok. Satu kelompok = satu aturan. Campuran berarti salah isi tabel.
$campur = q("SELECT kelompok, COUNT(DISTINCT pilih) n FROM spec_field
             WHERE kelompok <> '' GROUP BY kelompok HAVING n > 1");
cek('tidak ada kelompok bercampur satu/banyak', $campur === [],
    implode(', ', array_column($campur, 'kelompok')));

// 5) Setiap field berkelompok minimal 2 anggota. Kelompok berisi 1 field
//    tidak ada gunanya — tanda 'satu' pada satu-satunya pilihan tidak berarti.
$tunggal = q("SELECT kelompok, COUNT(*) n FROM spec_field
              WHERE kelompok <> '' GROUP BY kelompok HAVING n < 2");
cek('tidak ada kelompok berisi 1 field', $tunggal === [],
    implode(', ', array_column($tunggal, 'kelompok')));

printf("\n%d lulus, %d gagal\n", $lulus, $gagal);
exit($gagal === 0 ? 0 : 1);
