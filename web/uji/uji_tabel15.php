<?php
declare(strict_types=1);

/**
 * Uji Tabel 15 — matriks dosen tetap × prodi.
 *
 * INI UJI YANG PALING MUDAH "DIBETULKAN" SALAH. Catatan instrumen:
 *   1) Satu dosen tetap bisa terdaftar di lebih dari satu prodi.
 *   2) Kolom Total menghitung setiap dosen SATU KALI.
 *
 * Akibatnya jumlah kolom prodi BOLEH melebihi Total. Orang yang melihat
 * angka itu tanpa membaca catatan akan menganggapnya bug lalu memaksa
 * keduanya sama — dan rumusnya jadi salah. Uji ini mengunci perilaku yang
 * benar, termasuk membuktikan bahwa selisih itu DISENGAJA.
 *
 * Jalankan: LAMPTKES_ENV=lokal php web/uji/uji_tabel15.php
 */

require_once __DIR__ . '/../system/config.php';
require_once __DIR__ . '/../system/db.php';
require_once __DIR__ . '/../system/auth.php';
require_once __DIR__ . '/../system/rekap.php';
require_once __DIR__ . '/../system/tabel15.php';
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

/** Tambah dosen tetap + pendidikan, kembalikan id. */
function dosen_uji(string $nama, array $prodi, ?string $jabatan, ?string $jenjang): int
{
    $id = sisip('dosen', [
        'nama' => $nama,
        'jenis' => 'Dosen Tetap',
        'jabatan_fungsional' => $jabatan,
        'nidn_nidk_nup' => '9' . substr((string) crc32($nama), 0, 9),
        'tahap_mengajar' => 'Keduanya',
    ]);
    foreach ($prodi as $p) {
        ex('INSERT IGNORE INTO dosen_prodi (dosen_id, prodi_id) VALUES (?,?)', [$id, $p]);
    }
    if ($jenjang !== null) {
        sisip('dosen_pendidikan', ['dosen_id' => $id, 'jenjang' => $jenjang,
            'bidang' => 'Kebidanan', 'asal_pt' => 'Uji', 'tahun_lulus' => 2015]);
    }
    return $id;
}

bersihkan_data_uji(db(), true);

echo "── bentuk spec ──\n";

$spec = q("SELECT urut, label, asal FROM spec_field WHERE tabel_kode = '15' ORDER BY urut");
cek('tabel 15 punya 4 field', count($spec) === 4, 'dapat ' . count($spec));
cek('tidak ada field Input (seluruhnya dihitung)',
    count(array_filter($spec, fn($f) => $f['asal'] === 'Input')) === 0);
cek('tabel 15 ditandai varian matriks',
    qv("SELECT varian FROM tabel_dukung WHERE kode = '15'", [], '') === 'matriks');
cek('tabel 15 terdaftar sebagai matriks', in_array('15', tabel_matriks(), true));

echo "\n── 8 baris tetap sesuai instrumen ──\n";

$h = t15_hitung();
$semua = array_column($h['baris'], 'hal', 'no');
$harapA = ['A1' => 'Belum Memiliki', 'A2' => 'Asisten Ahli', 'A3' => 'Lektor',
           'A4' => 'Lektor Kepala', 'A5' => 'Guru Besar'];
$harapB = ['B1' => 'Profesi', 'B2' => 'Sp-1/DLP/S-2', 'B3' => 'Sp-2/S-3'];
cek('5 baris jabatan fungsional', count(array_intersect_key($semua, $harapA)) === 5);
cek('3 baris pendidikan tertinggi', count(array_intersect_key($semua, $harapB)) === 3);
foreach ($harapA + $harapB as $no => $label) {
    if (($semua[$no] ?? null) !== $label) {
        cek("baris $no = $label", false, 'dapat ' . ($semua[$no] ?? 'tidak ada'));
    }
}
cek('label semua baris cocok instrumen', array_intersect_key($semua, $harapA + $harapB) == $harapA + $harapB);

echo "\n── hitung dasar ──\n";

$p1 = (int) qv('SELECT id FROM prodi WHERE aktif = 1 ORDER BY id LIMIT 1', [], 0);
$p2 = (int) qv('SELECT id FROM prodi WHERE aktif = 1 AND id <> ? ORDER BY id LIMIT 1', [$p1], 0);
cek('dua prodi aktif tersedia', $p1 > 0 && $p2 > 0);

$idSatu = dosen_uji('Uji Satu Prodi', [$p1], 'Lektor', 'S2');
$h = t15_hitung();
$lektor = array_values(array_filter($h['baris'], fn($b) => $b['no'] === 'A3'))[0];
cek('Lektor terhitung 1', $lektor['total'] === 1, 'dapat ' . $lektor['total']);
cek('Total UPPS = 1 dosen', $h['total_upps'] === 1, 'dapat ' . $h['total_upps']);

$sp1 = array_values(array_filter($h['baris'], fn($b) => $b['no'] === 'B2'))[0];
cek('S2 masuk baris Sp-1/DLP/S-2', $sp1['total'] === 1, 'dapat ' . $sp1['total']);

echo "\n── INTI: dosen lintas prodi ──\n";

$idDua = dosen_uji('Uji Dua Prodi', [$p1, $p2], 'Lektor Kepala', 'S3');
$h = t15_hitung();
$lk = array_values(array_filter($h['baris'], fn($b) => $b['no'] === 'A4'))[0];

cek('Lektor Kepala total = 1 (dihitung SEKALI)', $lk['total'] === 1, 'dapat ' . $lk['total']);
cek('Lektor Kepala muncul di prodi 1', ($lk['per_prodi'][$p1] ?? 0) === 1);
cek('Lektor Kepala muncul di prodi 2', ($lk['per_prodi'][$p2] ?? 0) === 1);

$jumlahKolom = array_sum($lk['per_prodi']);
cek('jumlah kolom prodi (2) BOLEH melebihi total (1) — catatan instrumen 1 & 2',
    $jumlahKolom === 2 && $lk['total'] === 1,
    "kolom=$jumlahKolom total={$lk['total']}");

cek('Total UPPS = 2 orang, bukan 3 penugasan',
    $h['total_upps'] === 2, 'dapat ' . $h['total_upps']);

echo "\n── pendidikan tertinggi, bukan semua jenjang ──\n";

// Dosen dengan S1 DAN S3: hanya S3 yang dihitung (pendidikan TERTINGGI).
// Diukur sebagai SELISIH, bukan angka mutlak: dosen dari langkah sebelumnya
// juga punya S3, jadi mengharap "1" mutlak akan gagal padahal kodenya benar
// (dan itu pernah terjadi — assertion-nya yang salah, bukan kodenya).
$sebelum = t15_hitung();
$b3Sebelum = array_values(array_filter($sebelum['baris'], fn($b) => $b['no'] === 'B3'))[0]['total'];
$b2Sebelum = array_values(array_filter($sebelum['baris'], fn($b) => $b['no'] === 'B2'))[0]['total'];

$idByk = sisip('dosen', ['nama' => 'Uji Banyak Jenjang', 'jenis' => 'Dosen Tetap',
    'jabatan_fungsional' => 'Asisten Ahli', 'tahap_mengajar' => 'Keduanya']);
ex('INSERT INTO dosen_prodi (dosen_id, prodi_id) VALUES (?,?)', [$idByk, $p1]);
foreach ([['S1', 2010], ['S2', 2014], ['S3', 2019]] as [$j, $th]) {
    sisip('dosen_pendidikan', ['dosen_id' => $idByk, 'jenjang' => $j,
        'bidang' => 'Kebidanan', 'asal_pt' => 'Uji', 'tahun_lulus' => $th]);
}
$h = t15_hitung();
$b1 = array_values(array_filter($h['baris'], fn($b) => $b['no'] === 'B1'))[0];
$b2 = array_values(array_filter($h['baris'], fn($b) => $b['no'] === 'B2'))[0];
$b3 = array_values(array_filter($h['baris'], fn($b) => $b['no'] === 'B3'))[0];
cek('S3 menambah baris B3 tepat 1', $b3['total'] === $b3Sebelum + 1,
    "sebelum $b3Sebelum, sesudah {$b3['total']}");
cek('S2 yang lebih rendah TIDAK ikut tercatat (tidak dobel)',
    $b2['total'] === $b2Sebelum, "sebelum $b2Sebelum, sesudah {$b2['total']}");
cek('S1 tidak muncul di baris B mana pun', $b1['total'] === 0, 'dapat ' . $b1['total']);

echo "\n── hanya dosen TETAP ──\n";

$idTT = sisip('dosen', ['nama' => 'Uji Tidak Tetap', 'jenis' => 'Dosen Tidak Tetap',
    'jabatan_fungsional' => 'Lektor', 'tahap_mengajar' => 'Akademik']);
ex('INSERT INTO dosen_prodi (dosen_id, prodi_id) VALUES (?,?)', [$idTT, $p1]);
$h = t15_hitung();
$lektor = array_values(array_filter($h['baris'], fn($b) => $b['no'] === 'A3'))[0];
cek('dosen tidak tetap TIDAK dihitung di Tabel 15', $lektor['total'] === 1, 'dapat ' . $lektor['total']);

echo "\n── jabatan kosong tetap dihitung ──\n";

$idKosong = dosen_uji('Uji Tanpa Jabatan', [$p1], null, null);
$h = t15_hitung();
$a1 = array_values(array_filter($h['baris'], fn($b) => $b['no'] === 'A1'))[0];
cek('jabatan kosong masuk "Belum Memiliki", bukan dibuang', $a1['total'] === 1, 'dapat ' . $a1['total']);

bersihkan_data_uji(db(), true);
cek('data uji bersih setelah uji', (int) qv('SELECT COUNT(*) FROM dosen', [], 0) === 0);

printf("\n%d lulus, %d gagal\n", $lulus, $gagal);
exit($gagal === 0 ? 0 : 1);
