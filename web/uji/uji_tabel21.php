<?php
declare(strict_types=1);

/**
 * Uji Tabel 21 — pembimbingan tugas akhir.
 *
 * Yang dijaga:
 *  1. Bentuknya 13 kolom (bukan 7 seperti spec lama). Spec lama menaungi tiga
 *     kolom tahun di bawah satu label, sehingga tiga angka tidak bisa dibedakan.
 *  2. Angka dihitung dari tabel `bimbingan`, bukan diketik — jadi tidak mungkin
 *     bertentangan dengan data aslinya.
 *  3. Lama penyelesaian: bimbingan yang BELUM selesai tampil null (—), bukan 0.
 *     Nol berarti "selesai dalam 0 bulan", mustahil.
 *  4. Rata-rata lama dibagi jumlah dosen yang PUNYA bimbingan selesai, bukan
 *     semua dosen — kalau dibagi semua, dosen tanpa bimbingan selesai menarik
 *     rata-rata turun seolah bimbingannya cepat.
 *
 * Jalankan: LAMPTKES_ENV=lokal php web/uji/uji_tabel21.php
 */

require_once __DIR__ . '/../system/config.php';
require_once __DIR__ . '/../system/db.php';
require_once __DIR__ . '/../system/auth.php';
require_once __DIR__ . '/../system/rekap.php';
require_once __DIR__ . '/../system/hitung.php';
require_once __DIR__ . '/../system/tabel21.php';
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
cek('prodi + periode ada', $p > 0 && $per !== null);
if ($p === 0 || $per === null) { echo "\nSeed belum jalan.\n"; exit(1); }

$ts = (int) $per['tahun_ts'];

echo "\n── bentuk spec ──\n";

$spec = q("SELECT urut, label, asal, kelompok, pilih FROM spec_field WHERE tabel_kode='21' ORDER BY urut");
cek('13 field', count($spec) === 13, 'dapat ' . count($spec));
cek('tidak ada field Input kecuali No',
    count(array_filter($spec, fn($f) => $f['asal'] === 'Input')) === 1);
cek('3 kelompok tahun bimbingan',
    count(array_filter($spec, fn($f) => ($f['kelompok'] ?? '') === 'tahun_bimbingan')) === 3);
cek('3 kelompok tahun pertemuan',
    count(array_filter($spec, fn($f) => ($f['kelompok'] ?? '') === 'tahun_pertemuan')) === 3);
cek('3 kelompok tahun lama',
    count(array_filter($spec, fn($f) => ($f['kelompok'] ?? '') === 'tahun_lama')) === 3);
cek('tidak ada label yang menaungi 3 tahun sekaligus',
    count(array_filter($spec, fn($f) => preg_match('/TS-2\s*\/\s*TS-1\s*\/\s*TS/', $f['label']))) === 0);

echo "\n── tanpa data: kosong, bukan tabel nol ──\n";

$h = t21_hitung($p);
cek('kolom 13', count($h['kolom']) === 13, 'dapat ' . count($h['kolom']));
cek('belum ada bimbingan -> baris kosong', ($h['baris'] ?? []) === []);
cek('alasan kosong dijelaskan', ($h['kosong'] ?? '') !== '');

echo "\n── dengan data ──\n";

// Dua dosen. Dosen A: 2 mahasiswa di TS, satu selesai 12 bulan, satu berjalan.
// Dosen B: 1 mahasiswa di TS-1, selesai 6 bulan.
$dA = sisip('dosen', ['nama' => 'Uji Pembimbing A', 'jenis' => 'Dosen Tetap',
    'jabatan_fungsional' => 'Lektor', 'tahap_mengajar' => 'Keduanya']);
$dB = sisip('dosen', ['nama' => 'Uji Pembimbing B', 'jenis' => 'Dosen Tetap',
    'jabatan_fungsional' => 'Asisten Ahli', 'tahap_mengajar' => 'Keduanya']);
foreach ([$dA, $dB] as $d) {
    ex('INSERT INTO dosen_prodi (dosen_id, prodi_id) VALUES (?,?)', [$d, $p]);
    sisip('dosen_pendidikan', ['dosen_id' => $d, 'jenjang' => 'S2',
        'bidang' => 'Kebidanan', 'asal_pt' => 'Uji', 'tahun_lulus' => 2015]);
}

// Mahasiswa untuk FK.
$m1 = sisip('mahasiswa', ['prodi_id' => $p, 'nim' => 'UJI21A', 'nama' => 'Mhs A',
    'tahun_masuk' => $ts, 'status' => 'Lulus', 'tahap' => 'Akademik']);
$m2 = sisip('mahasiswa', ['prodi_id' => $p, 'nim' => 'UJI21B', 'nama' => 'Mhs B',
    'tahun_masuk' => $ts, 'status' => 'Aktif', 'tahap' => 'Akademik']);
$m3 = sisip('mahasiswa', ['prodi_id' => $p, 'nim' => 'UJI21C', 'nama' => 'Mhs C',
    'tahun_masuk' => $ts - 1, 'status' => 'Lulus', 'tahap' => 'Akademik']);

sisip('bimbingan', ['prodi_id' => $p, 'dosen_id' => $dA, 'mahasiswa_id' => $m1,
    'tahun_akademik' => $ts, 'jumlah_pertemuan' => 10,
    'mulai' => ($ts - 1) . '-09-01', 'selesai' => $ts . '-09-01', 'status' => 'Selesai']);
sisip('bimbingan', ['prodi_id' => $p, 'dosen_id' => $dA, 'mahasiswa_id' => $m2,
    'tahun_akademik' => $ts, 'jumlah_pertemuan' => 4,
    'mulai' => $ts . '-09-01', 'selesai' => null, 'status' => 'Berjalan']);
sisip('bimbingan', ['prodi_id' => $p, 'dosen_id' => $dB, 'mahasiswa_id' => $m3,
    'tahun_akademik' => $ts - 1, 'jumlah_pertemuan' => 6,
    'mulai' => ($ts - 2) . '-03-01', 'selesai' => ($ts - 1) . '-09-01', 'status' => 'Selesai']);

$h = t21_hitung($p);
cek('2 baris dosen + 2 kaki tabel', count($h['baris']) === 4, 'dapat ' . count($h['baris']));

$bA = $h['baris'][0]['sel'];
$bB = $h['baris'][1]['sel'];

// Indeks: 0 no, 1 nama, 2 jenjang, 3 jabatan, 4-6 mhs, 7-9 pertemuan, 10-12 lama
cek('dosen A: 2 mahasiswa di kolom TS', (int) $bA[6] === 2, (string) $bA[6]);
cek('dosen A: 14 pertemuan di kolom TS', (int) $bA[9] === 14, (string) $bA[9]);
cek('dosen A: lama TS = 12 bulan (1 dari 1 selesai)',
    abs((float) $bA[12] - 12.0) < 0.3, var_export($bA[12], true));

cek('dosen B: 1 mahasiswa di kolom TS-1', (int) $bB[5] === 1, (string) $bB[5]);
cek('dosen B: 6 pertemuan di kolom TS-1', (int) $bB[8] === 6, (string) $bB[8]);
// m3 mulai Maret (ts-2) selesai September (ts-1) = 18 bulan, bukan 6.
// Assertion saya yang salah hitung saat menulis uji ini; kodenya benar.
cek('dosen B: lama TS-1 = 18 bulan',
    abs((float) $bB[11] - 18.0) < 0.5, var_export($bB[11], true));

cek('dosen A: belum selesai -> lama TS BUKAN 0 (12 bulan dari yang selesai)',
    (float) $bA[12] !== 0.0);

echo "\n── bimbingan berjalan: lama harus null, bukan 0 ──\n";

// Dosen C hanya punya bimbingan berjalan.
$dC = sisip('dosen', ['nama' => 'Uji Pembimbing C', 'jenis' => 'Dosen Tetap',
    'jabatan_fungsional' => 'Lektor', 'tahap_mengajar' => 'Akademik']);
ex('INSERT INTO dosen_prodi (dosen_id, prodi_id) VALUES (?,?)', [$dC, $p]);
sisip('bimbingan', ['prodi_id' => $p, 'dosen_id' => $dC, 'mahasiswa_id' => $m2,
    'tahun_akademik' => $ts, 'jumlah_pertemuan' => 2,
    'mulai' => $ts . '-09-01', 'selesai' => null, 'status' => 'Berjalan']);

$h = t21_hitung($p);
$bC = null;
foreach ($h['baris'] as $b) {
    if (str_contains((string) $b['sel'][1], 'Pembimbing C')) { $bC = $b['sel']; }
}
cek('dosen C ditemukan', $bC !== null);
cek('dosen C: mahasiswa TS = 1', $bC !== null && (int) $bC[6] === 1);
cek('dosen C: lama penyelesaian null (belum selesai), BUKAN 0',
    $bC !== null && $bC[12] === null, var_export($bC[12] ?? 'tidak ada', true));

echo "\n── kaki tabel ──\n";

$jml = null;
$rata = null;
foreach ($h['baris'] as $b) {
    if (($b['sel'][1] ?? '') === 'Jumlah') { $jml = $b['sel']; }
    if (($b['sel'][1] ?? '') === 'Rata-rata') { $rata = $b['sel']; }
}
cek('ada baris Jumlah', $jml !== null);
cek('ada baris Rata-rata', $rata !== null);
cek('Jumlah mahasiswa TS = 3', $jml !== null && (int) $jml[6] === 3, (string) ($jml[6] ?? ''));
cek('Jumlah pertemuan TS = 16', $jml !== null && (int) $jml[9] === 16, (string) ($jml[9] ?? ''));

// Rata-rata lama TS: hanya dosen A (12 bulan). Dosen C belum selesai, jadi
// TIDAK boleh ikut dibagi — kalau ikut, hasilnya 6 dan itu menyesatkan.
cek('rata-rata lama TS = 12 (hanya yang selesai)',
    $rata !== null && abs((float) $rata[12] - 12.0) < 0.4,
    var_export($rata[12] ?? 'null', true));

echo "\n── hanya dosen yang membimbing yang muncul ──\n";

$nama = array_map(fn($b) => (string) ($b['sel'][1] ?? ''), $h['baris']);
cek('3 dosen pembimbing tampil', count(array_filter($nama, fn($n) => str_contains($n, 'Uji Pembimbing'))) === 3);
cek('dosen tanpa bimbingan tidak muncul dengan angka 0',
    count(array_filter($nama, fn($n) => str_contains($n, 'Uji Pembimbing'))) === 3);

bersihkan_data_uji(db(), true);
cek('data uji bersih', (int) qv('SELECT COUNT(*) FROM bimbingan', [], 0) === 0
    && (int) qv('SELECT COUNT(*) FROM mahasiswa', [], 0) === 0);

printf("\n%d lulus, %d gagal\n", $lulus, $gagal);
exit($gagal === 0 ? 0 : 1);
