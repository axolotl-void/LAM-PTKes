<?php
declare(strict_types=1);

/**
 * Uji bimbingan tugas akhir + sambungannya ke Tabel 21.
 *
 * Kenapa uji ini ada: Tabel 21 dulu SELALU kosong. `tabel21.php` benar, tapi
 * tidak ada jalan mengisi `bimbingan` — jadi "dihitung dari master" tidak
 * pernah punya master. Uji ini menjaga supaya lubang itu tidak terbuka lagi:
 * kalau formulir bimbingan hilang, Tabel 21 harus ketahuan kosong.
 *
 * Yang dijaga:
 *  1. Aturan tolak: dosen wajib, tahun wajib, selesai < mulai, "Selesai"
 *     tanpa tanggal selesai, satu mahasiswa dua kali di dosen+tahun sama.
 *  2. Lama bimbingan dihitung dari tanggal, bukan diketik.
 *  3. Tabel 21 benar-benar berubah setelah bimbingan ditambah — bukan hanya
 *     "endpoint mengembalikan 200".
 *  4. Mahasiswa prodi lain tidak bisa dipakai (kebocoran antar-prodi).
 */

require_once __DIR__ . '/../system/config.php';
require_once __DIR__ . '/../system/db.php';
require_once __DIR__ . '/../system/auth.php';
require_once __DIR__ . '/../system/rekap.php';
require_once __DIR__ . '/../system/hitung_peta.php';
require_once __DIR__ . '/../system/bimbingan.php';
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
$_SESSION['pengguna'] = ['id' => 1, 'peran_kode' => 'admin'];

$p1 = (int) qv('SELECT id FROM prodi ORDER BY id LIMIT 1', [], 0);
$p2 = (int) qv('SELECT id FROM prodi WHERE id <> ? ORDER BY id LIMIT 1', [$p1], 0);
$per = periode_aktif($p1);
cek('dua prodi + periode aktif ada', $p1 > 0 && $p2 > 0 && $per !== null);
if ($p1 === 0 || $p2 === 0 || $per === null) { echo "\nSeed belum jalan.\n"; exit(1); }

/* ── siapkan master: 1 dosen, 1 mahasiswa per prodi ───────────────── */
// Skema sebenarnya: dosen.nidn_nidk_nup (bukan `nidn`), tanpa kolom `aktif`;
// dosen_prodi tanpa `aktif`; mahasiswa memakai tahun_masuk + status enum.
function bmb_dosen(int $prodi, string $nama, string $jenis = 'Dosen Tetap'): int
{
    ex('INSERT INTO dosen (nama, nidn_nidk_nup, jenis) VALUES (?,?,?)',
       [$nama, substr(md5($nama . $prodi), 0, 8), $jenis]);
    $id = (int) db()->lastInsertId();
    ex('INSERT INTO dosen_prodi (dosen_id, prodi_id) VALUES (?,?)', [$id, $prodi]);
    return $id;
}

function bmb_mhs(int $prodi, string $nama, string $nim, string $status = 'Aktif'): int
{
    ex('INSERT INTO mahasiswa (prodi_id, nama, nim, tahun_masuk, status) VALUES (?,?,?,?,?)',
       [$prodi, $nama, $nim, 2022, $status]);
    return (int) db()->lastInsertId();
}

$d1 = bmb_dosen($p1, 'Uji Dosen Satu');
$d2 = bmb_dosen($p1, 'Uji Dosen Dua');
$dLain = bmb_dosen($p2, 'Uji Dosen Prodi Lain');
$m1 = bmb_mhs($p1, 'Uji Mahasiswa Satu', '9001');
$m2 = bmb_mhs($p1, 'Uji Mahasiswa Dua', '9002');
$mLain = bmb_mhs($p2, 'Uji Mahasiswa Prodi Lain', '9003');

if (!function_exists('bmb_dosen')) { /* sudah dideklarasikan di atas */ }

echo "── aturan tolak ──\n";

$dasar = ['dosen_id' => $d1, 'mahasiswa_id' => $m1, 'tahun_akademik' => 2025,
          'jumlah_pertemuan' => 8, 'mulai' => '2025-02-01', 'selesai' => '2025-08-01',
          'status' => 'Selesai'];

$g = bimbingan_periksa(array_merge($dasar, ['dosen_id' => 0]), $p1);
cek('dosen kosong ditolak', $g !== [], json_encode($g));

$g = bimbingan_periksa(array_merge($dasar, ['tahun_akademik' => 0]), $p1);
cek('tahun kosong ditolak', $g !== [], json_encode($g));
$g = bimbingan_periksa(array_merge($dasar, ['tahun_akademik' => 1800]), $p1);
cek('tahun 1800 ditolak', $g !== [], json_encode($g));

$g = bimbingan_periksa(array_merge($dasar, ['selesai' => '2025-01-01', 'mulai' => '2025-02-01']), $p1);
cek('selesai sebelum mulai ditolak', $g !== [], json_encode($g));

$g = bimbingan_periksa(array_merge($dasar, ['selesai' => '']), $p1);
cek('status Selesai tanpa tanggal selesai ditolak', $g !== [], json_encode($g));

$g = bimbingan_periksa(array_merge($dasar, ['status' => 'Entah']), $p1);
cek('status asing ditolak', $g !== [], json_encode($g));

// Prodi lain: mahasiswa prodi 2 tidak boleh masuk prodi 1.
$g = bimbingan_periksa(array_merge($dasar, ['mahasiswa_id' => $mLain]), $p1);
cek('mahasiswa prodi lain ditolak', $g !== [], json_encode($g));

// Dosen berjenis Tendik tidak boleh jadi pembimbing (skema tidak punya
// kolom `aktif` di tabel dosen; `jenis` yang membedakan).
$tendik = bmb_dosen($p1, 'Uji Tendik', 'Tendik');
$g = bimbingan_periksa(array_merge($dasar, ['dosen_id' => $tendik]), $p1);
cek('Tendik tidak boleh jadi pembimbing', $g !== [], json_encode($g));

// Mahasiswa berstatus Lulus tidak boleh ditambah bimbingan baru.
$mLulus = bmb_mhs($p1, 'Uji Mahasiswa Lulus', '9010', 'Lulus');
$g = bimbingan_periksa(array_merge($dasar, ['mahasiswa_id' => $mLulus]), $p1);
cek('mahasiswa non-aktif ditolak', $g !== [], json_encode($g));

// Data sah harus LOLOS, bukan hanya "yang salah ditolak".
$g = bimbingan_periksa($dasar, $p1);
cek('data sah diterima', $g === [], json_encode($g));

echo "\n── simpan ──\n";

$r = bimbingan_tambah($dasar, $p1, 1);
cek('tambah berhasil', ($r['ok'] ?? false) === true, json_encode($r));
$id1 = (int) ($r['id'] ?? 0);
cek('id dikembalikan', $id1 > 0);

// Duplikat dosen+mahasiswa+tahun harus ditolak (pertemuan bisa terhitung 2×).
$r2 = bimbingan_tambah($dasar, $p1, 1);
cek('duplikat dosen+mhs+tahun ditolak', ($r2['ok'] ?? true) === false, json_encode($r2));

// Tahun beda boleh.
$r3 = bimbingan_tambah(array_merge($dasar, ['tahun_akademik' => 2024, 'mulai' => '2024-02-01',
                                  'selesai' => '2024-08-01']), $p1, 1);
cek('tahun berbeda diterima', ($r3['ok'] ?? false) === true, json_encode($r3));

// Mahasiswa sama, dosen beda, tahun sama: boleh (pembimbing 2).
$r4 = bimbingan_tambah(array_merge($dasar, ['dosen_id' => $d2]), $p1, 1);
cek('dosen pembimbing kedua diterima', ($r4['ok'] ?? false) === true, json_encode($r4));

echo "\n── lama bimbingan dihitung ──\n";

$daftar = bimbingan_daftar($p1);
cek('daftar berisi 3 baris', count($daftar) === 3, 'dapat ' . count($daftar));

$b1 = null;
foreach ($daftar as $b) { if ((int) $b['id'] === $id1) { $b1 = $b; } }
cek('baris tersimpan bisa dibaca', $b1 !== null);

// 2025-02-01 → 2025-08-01 = 181 hari / 30.44 = 5.9 bulan
cek('lama bulan dihitung dari tanggal',
    $b1 !== null && abs((float) $b1['lama_bulan'] - 5.9) < 0.2,
    $b1 === null ? 'baris hilang' : (string) $b1['lama_bulan']);

cek('nama dosen di-JOIN, bukan id', $b1 !== null && str_contains((string) $b1['dosen'], 'Uji Dosen Satu'));
cek('nama mahasiswa di-JOIN', $b1 !== null && ($b1['mahasiswa'] ?? '') === 'Uji Mahasiswa Satu');

cek('hari: 181', bimbingan_hari('2025-02-01', '2025-08-01') === 181,
    (string) bimbingan_hari('2025-02-01', '2025-08-01'));
cek('hari: selesai sebelum mulai negatif',
    bimbingan_hari('2025-08-01', '2025-02-01') < 0);
cek('hari: tanggal kosong = 0', bimbingan_hari(null, '2025-08-01') === 0);

echo "\n── Tabel 21 benar-benar berubah ──\n";

$t21 = hitung_satu('21', $p1);
cek('Tabel 21 punya 13 kolom', count($t21['kolom'] ?? []) === 13, 'dapat ' . count($t21['kolom'] ?? []));
cek('Tabel 21 tidak lagi kosong', count($t21['baris'] ?? []) > 0, json_encode($t21['kosong'] ?? ''));

// Hanya dosen yang punya baris bimbingan di prodi ini yang tampil.
$dosenBimbing = array_map(
    fn($r) => (int) $r['dosen_id'],
    q('SELECT DISTINCT dosen_id FROM bimbingan WHERE prodi_id = ?', [$p1]));
// Kaki tabel ("Jumlah", "Rata-rata") bukan baris dosen — instrumen memang
// memintanya, tapi uji ini menghitung baris data.
$barisData = array_values(array_filter(
    $t21['baris'] ?? [],
    fn($b) => !in_array(trim((string) ($b['sel'][1] ?? '')), ['Jumlah', 'Rata-rata'], true)));
cek('Tabel 21 menampilkan tepat dosen yang membimbing',
    count($barisData) === count($dosenBimbing),
    count($barisData) . ' baris data vs ' . count($dosenBimbing) . ' dosen');
cek('Tabel 21 punya kaki Jumlah dan Rata-rata',
    count($t21['baris'] ?? []) - count($barisData) === 2,
    'kaki=' . (count($t21['baris'] ?? []) - count($barisData)));

// Dosen prodi lain tidak boleh muncul di Tabel 21 prodi ini.
$semuaTeks = json_encode($t21['baris'] ?? [], JSON_UNESCAPED_UNICODE);
cek('dosen prodi lain tidak bocor ke Tabel 21',
    !str_contains($semuaTeks, 'Prodi Lain'));

// Setelah bimbingan dihapus, Tabel 21 harus ikut berubah.
$r = bimbingan_hapus($id1, $p1);
cek('hapus berhasil', ($r['ok'] ?? false) === true, json_encode($r));
$t21b = hitung_satu('21', $p1);
// Setelah hapus, dosen yang tadinya membimbing (d1) tidak lagi muncul kalau
// tidak ada baris lain miliknya.
$masihAda = (int) qv('SELECT COUNT(*) FROM bimbingan WHERE dosen_id = ? AND prodi_id = ?',
    [$d1, $p1], 0);
$teks = json_encode($t21b['baris'] ?? [], JSON_UNESCAPED_UNICODE);
cek('Tabel 21 konsisten dengan isi bimbingan',
    $masihAda === 0 ? !str_contains($teks, 'Uji Dosen Satu') : str_contains($teks, 'Uji Dosen Satu'),
    'sisa=' . $masihAda . ' baris=' . count($t21b['baris'] ?? []));

$r = bimbingan_hapus($id1, $p1);
cek('hapus dua kali ditolak', ($r['ok'] ?? true) === false);

// Hapus baris prodi lain lewat prodi 1 harus gagal (kebocoran antar-prodi).
$lain = bimbingan_tambah(['dosen_id' => $dLain, 'mahasiswa_id' => $mLain,
    'tahun_akademik' => 2025, 'status' => 'Berjalan'], $p2, 1);
cek('bimbingan prodi 2 tersimpan', ($lain['ok'] ?? false) === true, json_encode($lain));
$r = bimbingan_hapus((int) ($lain['id'] ?? 0), $p1);
cek('tidak bisa hapus bimbingan prodi lain', ($r['ok'] ?? true) === false);

echo "\n── ubah ──\n";

$r = bimbingan_ubah($id1 + 9999, $dasar, $p1, 1);
cek('ubah id tak dikenal ditolak', ($r['ok'] ?? true) === false);

$sisa = q('SELECT id FROM bimbingan WHERE prodi_id = ? ORDER BY id LIMIT 1', [$p1]);
cek('ada baris untuk diubah', $sisa !== []);
if ($sisa !== []) {
    $sid = (int) $sisa[0]['id'];
    $r = bimbingan_ubah($sid, ['dosen_id' => $d1, 'mahasiswa_id' => $m2,
        'tahun_akademik' => 2023, 'jumlah_pertemuan' => 3,
        'mulai' => '2023-01-10', 'selesai' => '2023-04-10', 'status' => 'Selesai'], $p1, 1);
    cek('ubah berhasil', ($r['ok'] ?? false) === true, json_encode($r));
    $baru = q1('SELECT * FROM bimbingan WHERE id = ?', [$sid]);
    cek('tahun berubah', (int) $baru['tahun_akademik'] === 2023);
    cek('mahasiswa berubah', (int) $baru['mahasiswa_id'] === $m2);
    cek('pertemuan berubah', (int) $baru['jumlah_pertemuan'] === 3);
}

echo "\n── pilihan dropdown ──\n";

$pil = bimbingan_pilihan($p1);
cek('pilihan dosen tidak kosong', ($pil['dosen'] ?? []) !== []);
cek('pilihan mahasiswa tidak kosong', ($pil['mahasiswa'] ?? []) !== []);
cek('dosen prodi lain tidak masuk pilihan',
    !str_contains(json_encode($pil['dosen'], JSON_UNESCAPED_UNICODE), 'Prodi Lain'));

echo "\n── data uji bersih ──\n";

bersihkan_data_uji(db(), true);
$sisa = (int) qv('SELECT COUNT(*) FROM bimbingan', [], 0);
cek('bimbingan kosong setelah bersih', $sisa === 0, (string) $sisa);

printf("\n%d lulus, %d gagal\n", $lulus, $gagal);
exit($gagal === 0 ? 0 : 1);
