<?php
declare(strict_types=1);

/**
 * Uji master mahasiswa & dosen — TANPA HTTP.
 *
 * Yang dibuktikan (kontrak Agent 3):
 *   a) NIM ganda ditolak, pesannya menyebut NIM tersebut
 *   b) IPK 5,0 ditolak
 *   c) dosen 2 prodi tetap 1 baris di tabel dosen
 *   d) impor 3 baris (1 salah) -> 2 masuk, 1 dilaporkan dengan nomor barisnya
 *
 * Jalankan: php web/uji/uji_master.php
 */

ob_start();
putenv('LAMPTKES_ENV=lokal');
// MASTER_TANPA_HTTP mematikan bagian routing di modul; hanya fungsi yang dimuat.
define('MASTER_TANPA_HTTP', true);
require_once __DIR__ . '/../system/view.php';
require_once __DIR__ . '/../modul/master/mahasiswa.php';
require_once __DIR__ . '/../modul/master/dosen.php';

$gagal = 0;
function cek(string $nama, bool $ok, string $detail = ''): void
{
    global $gagal;
    echo ($ok ? '  ok  ' : '  GAGAL ') . $nama . ($detail !== '' ? " — $detail" : '') . "\n";
    if (!$ok) { $gagal++; }
}

$prodi = (int) qv('SELECT id FROM prodi ORDER BY id LIMIT 1');
$prodi2 = (int) qv('SELECT id FROM prodi ORDER BY id LIMIT 1 OFFSET 1');
$prodiNm = (string) qv('SELECT nama_ps FROM prodi WHERE id = ?', [$prodi]);
cek('dua prodi tersedia untuk uji lintas prodi', $prodi2 > 0, "prodi1=$prodi prodi2=$prodi2");

// Bersihkan sisa uji sebelumnya supaya uji bisa dijalankan berulang.
function bersih(string $prefix): void
{
    foreach (q('SELECT id FROM mahasiswa WHERE nim LIKE ?', [$prefix . '%']) as $r) {
        ex('DELETE FROM mahasiswa WHERE id = ?', [$r['id']]);
    }
    foreach (q('SELECT id FROM dosen WHERE nidn_nidk_nup LIKE ?', [$prefix . '%']) as $r) {
        ex('DELETE FROM dosen_pendidikan WHERE dosen_id = ?', [$r['id']]);
        ex('DELETE FROM dosen_prodi WHERE dosen_id = ?', [$r['id']]);
        ex('DELETE FROM dosen WHERE id = ?', [$r['id']]);
    }
}
bersih('UJI');

/* ── mahasiswa dasar ─────────────────────────────────────────────── */
$mhsA = [
    'nim' => 'UJI001', 'nama' => 'Siti Uji Aminah', 'tahun_masuk' => '2023',
    'jalur_masuk' => 'Reguler', 'tahap' => 'Akademik', 'status' => 'Aktif',
    'ipk' => '3,45', 'tanggal_lulus' => '', 'tahun_lulus' => '',
];
$h = mhs_simpan($mhsA, $prodi);
cek('tambah mahasiswa sah', $h['ok'], $h['pesan']);
cek('IPK koma "3,45" diterima dan tersimpan 3.45',
    $h['ok'] && (string) qv('SELECT ipk FROM mahasiswa WHERE id = ?', [$h['id']]) === '3.45',
    (string) qv('SELECT ipk FROM mahasiswa WHERE id = ?', [$h['id']], 'null'));

/* a) NIM ganda */
$h2 = mhs_simpan(['nim' => 'UJI001', 'nama' => 'Orang Lain', 'tahun_masuk' => '2024'], $prodi);
cek('(a) NIM ganda ditolak', !$h2['ok'], $h2['pesan']);
cek('(a) pesan menyebut NIM yang bentrok', str_contains($h2['pesan'], 'UJI001'), $h2['pesan']);
cek('(a) hanya 1 baris dengan NIM itu',
    (int) qv('SELECT COUNT(*) FROM mahasiswa WHERE nim = ?', ['UJI001'], 0) === 1);

// NIM sendiri tidak dianggap bentrok saat mengubah baris yang sama
$h3 = mhs_simpan(['nim' => 'UJI001', 'nama' => 'Siti Uji Aminah, S.Tr.Keb.',
                  'tahun_masuk' => '2023', 'status' => 'Aktif', 'ipk' => '3.45'], $prodi, $h['id']);
cek('ubah baris sendiri tidak dianggap NIM ganda', $h3['ok'], $h3['pesan']);

/* b) IPK 5,0 */
$h4 = mhs_simpan(['nim' => 'UJI002', 'nama' => 'IPK Salah', 'tahun_masuk' => '2023', 'ipk' => '5,0'], $prodi);
cek('(b) IPK 5,0 ditolak', !$h4['ok'], $h4['pesan']);
cek('(b) pesan menyebut rentang 0–4', str_contains($h4['pesan'], '0–4'), $h4['pesan']);
cek('(b) baris tidak tersimpan', (int) qv('SELECT COUNT(*) FROM mahasiswa WHERE nim = ?', ['UJI002'], 0) === 0);
foreach (['-0,5' => 'IPK negatif ditolak', '4,01' => 'IPK 4,01 ditolak', 'abc' => 'IPK bukan angka ditolak'] as $v => $nama) {
    $hv = mhs_simpan(['nim' => 'UJI0IPK', 'nama' => 'X', 'tahun_masuk' => '2023', 'ipk' => $v], $prodi);
    cek($nama, !$hv['ok'], $hv['pesan']);
}
$h4b = mhs_simpan(['nim' => 'UJI0IPK', 'nama' => 'X', 'tahun_masuk' => '2023', 'ipk' => '4,0'], $prodi);
cek('IPK tepat 4,00 diterima (batas inklusif)', $h4b['ok'], $h4b['pesan']);
if ($h4b['ok']) { ex('DELETE FROM mahasiswa WHERE id = ?', [$h4b['id']]); }

/* validasi lain yang mudah salah */
cek('NIM kosong ditolak', !mhs_simpan(['nim' => '', 'nama' => 'X', 'tahun_masuk' => '2023'], $prodi)['ok']);
cek('status Lulus tanpa tanggal lulus ditolak',
    !mhs_simpan(['nim' => 'UJI003', 'nama' => 'X', 'tahun_masuk' => '2020', 'status' => 'Lulus'], $prodi)['ok']);
$hL = mhs_simpan(['nim' => 'UJI003', 'nama' => 'Lulus Uji', 'tahun_masuk' => '2020',
                  'status' => 'Lulus', 'tanggal_lulus' => '2024-08-31', 'ipk' => '3,90'], $prodi);
cek('Lulus + tanggal lulus diterima, tahun_lulus terisi dari tanggal',
    $hL['ok'] && (int) qv('SELECT tahun_lulus FROM mahasiswa WHERE id = ?', [$hL['id']]) === 2024,
    (string) qv('SELECT tahun_lulus FROM mahasiswa WHERE id = ?', [$hL['id']], 'null'));
cek('tanggal tidak sah ditolak',
    !mhs_simpan(['nim' => 'UJI004', 'nama' => 'X', 'tahun_masuk' => '2023', 'tanggal_lulus' => '2023-02-31'], $prodi)['ok']);

/* nonaktif, bukan DELETE */
$hn = mhs_nonaktif((int) $h['id'], $prodi);
cek('nonaktifkan berhasil', $hn['ok'], $hn['pesan']);
cek('baris masih ada setelah nonaktif (bukan DELETE)',
    (int) qv('SELECT COUNT(*) FROM mahasiswa WHERE id = ?', [$h['id']], 0) === 1);
cek('status jadi DO', (string) qv('SELECT status FROM mahasiswa WHERE id = ?', [$h['id']]) === 'DO');
cek('nonaktif dua kali ditolak dengan pesan jelas', !mhs_nonaktif((int) $h['id'], $prodi)['ok']);

/* hitungan per status: semua kunci ada walau nol */
$hit = mhs_hitung($prodi);
cek('hitungan punya keempat status', array_keys($hit) === MHS_STATUS, json_encode($hit));
cek('hitungan DO mencerminkan 1 baris nonaktif', $hit['DO'] >= 1, json_encode($hit));
cek('hitungan = COUNT(*) tabel untuk prodi',
    array_sum($hit) === (int) qv('SELECT COUNT(*) FROM mahasiswa WHERE prodi_id = ?', [$prodi], 0),
    json_encode($hit));

/* ── c) dosen lintas prodi = satu baris ──────────────────────────── */
$dosen = [
    'nidn_nidk_nup' => 'UJI0123456789', 'nama' => 'Siti Rahmah, S.ST., M.Keb.',
    'jenis' => 'Dosen Tetap', 'jabatan_fungsional' => 'Lektor',
    'gelar_akademik' => 'S.ST., M.Keb.', 'tanggal_lahir' => '1985-04-02',
    'sertifikat_pendidik' => '1', 'profesi' => 'Bidan', 'no_str' => '123456',
    'tanggal_str' => '2024-01-31', 'tahap_mengajar' => 'Keduanya',
    'email' => 'uji.siti@bbg.ac.id', 'prodi' => [$prodi, $prodi2],
];
$hd = dosen_simpan($dosen);
cek('tambah dosen 2 prodi berhasil', $hd['ok'], $hd['pesan']);
$did = (int) ($hd['id'] ?? 0);
cek('(c) tabel dosen berisi TEPAT 1 baris untuk NIDN itu',
    (int) qv('SELECT COUNT(*) FROM dosen WHERE nidn_nidk_nup = ?', ['UJI0123456789'], 0) === 1,
    (string) qv('SELECT COUNT(*) FROM dosen WHERE nidn_nidk_nup = ?', ['UJI0123456789'], 0));
cek('(c) dosen_prodi berisi 2 baris (Kebidanan + Keperawatan)',
    (int) qv('SELECT COUNT(*) FROM dosen_prodi WHERE dosen_id = ?', [$did], 0) === 2,
    (string) qv('SELECT COUNT(*) FROM dosen_prodi WHERE dosen_id = ?', [$did], 0));
$pr = dosen_prodi_dari($did);
sort($pr);
cek('(c) prodi yang tersimpan = yang dicentang', $pr === [$prodi, $prodi2], json_encode($pr));

// Simpan ulang dengan 1 prodi saja -> tetap 1 baris dosen, dosen_prodi turun jadi 1
$ubah = $dosen;
$ubah['prodi'] = [$prodi];
$hu = dosen_simpan($ubah, $did);
cek('ubah prodi jadi 1 berhasil', $hu['ok'], $hu['pesan']);
cek('(c) masih 1 baris dosen setelah diubah', (int) qv('SELECT COUNT(*) FROM dosen WHERE id = ?', [$did], 0) === 1);
cek('(c) dosen_prodi turun jadi 1, bukan menambah baris dosen',
    (int) qv('SELECT COUNT(*) FROM dosen_prodi WHERE dosen_id = ?', [$did], 0) === 1,
    (string) qv('SELECT COUNT(*) FROM dosen_prodi WHERE dosen_id = ?', [$did], 0));

// Kembalikan ke 2 prodi: daftar dosen harus tetap 1 baris untuk orang ini
dosen_simpan($dosen, $did);
$barisDaftar = q('SELECT d.id FROM dosen d
                  WHERE EXISTS (SELECT 1 FROM dosen_prodi x WHERE x.dosen_id = d.id AND x.prodi_id = ?)
                    AND EXISTS (SELECT 1 FROM dosen_prodi x WHERE x.dosen_id = d.id AND x.prodi_id = ?)
                    AND d.nidn_nidk_nup = ?', [$prodi, $prodi2, 'UJI0123456789']);
cek('(c) query daftar lintas-prodi mengembalikan 1 baris', count($barisDaftar) === 1,
    'dapat ' . count($barisDaftar));

/* riwayat pendidikan banyak baris */
$p1 = dosen_pendidikan_tambah($did, ['jenjang' => 'S1', 'bidang' => 'Kebidanan',
    'asal_pt' => 'UBBG', 'tahun_lulus' => '2008']);
$p2 = dosen_pendidikan_tambah($did, ['jenjang' => 'S2', 'bidang' => 'Kesehatan Masyarakat',
    'asal_pt' => 'UI', 'tahun_lulus' => '2015']);
cek('tambah 2 riwayat pendidikan', $p1['ok'] && $p2['ok'], ($p1['pesan'] ?? '') . ' ' . ($p2['pesan'] ?? ''));
$didik = dosen_pendidikan_dari($did);
cek('riwayat pendidikan tersimpan 2 baris urut tahun terbaru',
    count($didik) === 2 && $didik[0]['jenjang'] === 'S2', json_encode($didik));
cek('jenjang ngawur ditolak',
    !dosen_pendidikan_tambah($did, ['jenjang' => 'SMA', 'bidang' => 'x', 'asal_pt' => 'y'])['ok']);
cek('tahun lulus masa depan ditolak',
    !dosen_pendidikan_tambah($did, ['jenjang' => 'S2', 'bidang' => 'x', 'asal_pt' => 'y',
        'tahun_lulus' => (string) ((int) date('Y') + 3)])['ok']);
$hapusP = dosen_pendidikan_hapus((int) $didik[0]['id'], $did);
cek('hapus 1 riwayat pendidikan', $hapusP['ok'] && count(dosen_pendidikan_dari($did)) === 1, $hapusP['pesan']);
cek('hapus pendidikan dosen lain tidak menghapus apa pun',
    !dosen_pendidikan_hapus((int) $didik[0]['id'], 999999)['ok']);

/* NIDN ganda untuk dosen */
$hd2 = dosen_simpan(['nidn_nidk_nup' => 'UJI0123456789', 'nama' => 'Duplikat', 'jenis' => 'Tendik',
    'prodi' => [$prodi]]);
cek('NIDN ganda ditolak dan menyebut NIDN-nya',
    !$hd2['ok'] && str_contains($hd2['pesan'], 'UJI0123456789'), $hd2['pesan']);
cek('dosen tanpa prodi ditolak',
    !dosen_simpan(['nidn_nidk_nup' => 'UJI999', 'nama' => 'Tanpa Prodi', 'jenis' => 'Tendik', 'prodi' => []])['ok']);

/* ── d) impor: 3 baris, 1 salah -> 2 masuk, 1 dilaporkan ──────────── */
$csv = "nim,nama,tahun_masuk,jalur_masuk,tahap,status,ipk,tanggal_lulus,tahun_lulus\n"
     . "UJI101,Impor Satu,2022,Reguler,Akademik,Aktif,3.10,,\n"      // baris 2 (fisik)
     . "UJI102,Impor Dua,2022,Transfer,Akademik,Aktif,9.99,,\n"      // baris 3 (fisik) — IPK salah
     . "UJI103,Impor Tiga,2021,Reguler,Akademik,Cuti,2.75,,";        // baris 4 (fisik)
$hasil = mhs_impor($csv, $prodi);
cek('(d) impor 3 baris: 2 masuk', $hasil['masuk'] === 2, 'masuk ' . $hasil['masuk']);
cek('(d) impor 3 baris: 1 ditolak', count($hasil['tolak']) === 1, json_encode($hasil['tolak']));
cek('(d) yang ditolak dilaporkan dengan nomor barisnya',
    ($hasil['tolak'][0]['baris'] ?? 0) === 3, json_encode($hasil['tolak']));
cek('(d) alasan penolakan menyebut IPK/rentang',
    str_contains((string) ($hasil['tolak'][0]['pesan'] ?? ''), 'IPK'), (string) ($hasil['tolak'][0]['pesan'] ?? ''));
cek('(d) 2 baris benar benar-benar ada di database',
    (int) qv('SELECT COUNT(*) FROM mahasiswa WHERE nim IN ("UJI101","UJI103")', [], 0) === 2);
cek('(d) baris salah tidak masuk',
    (int) qv('SELECT COUNT(*) FROM mahasiswa WHERE nim = ?', ['UJI102'], 0) === 0);
cek('(d) satu baris salah tidak membatalkan yang lain',
    (int) qv('SELECT COUNT(*) FROM mahasiswa WHERE nim IN ("UJI101","UJI103")', [], 0) === 2);

/* pratinjau memakai validator yang sama -> tidak bisa beda pendapat dgn simpan */
$csvPrat = "nim,nama,tahun_masuk,jalur_masuk,tahap,status,ipk,tanggal_lulus,tahun_lulus\n"
     . "UJI701,Prat Satu,2022,Reguler,Akademik,Aktif,3.10,,\n"
     . "UJI702,Prat Dua,2022,Transfer,Akademik,Aktif,9.99,,\n"
     . "UJI703,Prat Tiga,2021,Reguler,Akademik,Cuti,2.75,,";
$prat = mhs_pratinjau($csvPrat, $prodi);
cek('pratinjau menandai baris 3 saja sebagai salah',
    count($prat) === 3 && $prat[0]['galat'] === [] && $prat[2]['galat'] === []
    && count(array_filter($prat, fn($r) => $r['galat'])) === 1, json_encode(array_column($prat, 'galat')));
cek('pratinjau memakai nomor baris fisik berkas',
    array_column($prat, 'baris') === [2, 3, 4], json_encode(array_column($prat, 'baris')));

// NIM ganda di dalam berkas impor: baris kedua ditolak, bukan seluruh berkas
$csvDobel = "UJI201,Ganda Satu,2022,Reguler,Akademik,Aktif,3.0,,\n"
          . "UJI201,Ganda Dua,2022,Reguler,Akademik,Aktif,3.1,,\n";
$hd3 = mhs_impor($csvDobel, $prodi);
cek('impor NIM dobel: 1 masuk, 1 ditolak, pesan sebut NIM',
    $hd3['masuk'] === 1 && count($hd3['tolak']) === 1
    && str_contains($hd3['tolak'][0]['pesan'], 'UJI201'), json_encode($hd3));

// header tidak dianggap data
$csvTanpaHeader = "UJI301,Tanpa Header,2022,Reguler,Akademik,Aktif,3.0,,\n";
cek('CSV tanpa header tetap terbaca', mhs_impor($csvTanpaHeader, $prodi)['masuk'] === 1);

/* impor dosen: kolom prodi dipisah ";" -> tetap 1 baris dosen */
$csvD = "nidn,nama,jenis,jabatan_fungsional,gelar_akademik,tanggal_lahir,sertifikat_pendidik,profesi,no_str,tanggal_str,tahap_mengajar,email,prodi\n"
      . "UJI500,Nur Aisyah,Dosen Tetap,Asisten Ahli,S.Kep.,1990-09-14,0,Perawat,,,Akademik,nur@bbg.ac.id,{$prodiNm};"
      . ($prodi2 > 0 ? (string) qv('SELECT nama_ps FROM prodi WHERE id = ?', [$prodi2]) : '') . "\n"
      . "UJI501,Tanpa Prodi,Dosen Tetap,,,,,,,,,,NamaProdiSalah\n";
$hasilD = dosen_impor($csvD);
cek('impor dosen: 1 masuk, 1 ditolak (prodi tidak dikenal)',
    $hasilD['masuk'] === 1 && count($hasilD['tolak']) === 1, json_encode($hasilD));
$idImpor = (int) qv('SELECT id FROM dosen WHERE nidn_nidk_nup = ?', ['UJI500'], 0);
cek('impor dosen lintas prodi: 1 baris dosen, 2 baris dosen_prodi',
    $idImpor > 0
    && (int) qv('SELECT COUNT(*) FROM dosen WHERE nidn_nidk_nup = ?', ['UJI500'], 0) === 1
    && (int) qv('SELECT COUNT(*) FROM dosen_prodi WHERE dosen_id = ?', [$idImpor], 0) === 2,
    "id=$idImpor dp=" . (string) qv('SELECT COUNT(*) FROM dosen_prodi WHERE dosen_id = ?', [$idImpor], 0));
cek('baris dosen yang ditolak melaporkan nomor baris 3',
    ($hasilD['tolak'][0]['baris'] ?? 0) === 3, json_encode($hasilD['tolak']));

/* ── gerbang izin: matriks izin dibaca tabel, bukan request ───────── */
$operator = q1('SELECT * FROM pengguna WHERE peran_kode = "OPERATOR" LIMIT 1');
$pimpinan = q1('SELECT * FROM pengguna WHERE peran_kode = "PIMPINAN" LIMIT 1');
$verif    = q1('SELECT * FROM pengguna WHERE peran_kode = "VERIFIKATOR" LIMIT 1');
cek('OPERATOR boleh ISI', $operator !== null && akses('ISI', $operator) === 1);
cek('VERIFIKATOR tidak boleh ISI (0)', $verif !== null && akses('ISI', $verif) === 0);
cek('PIMPINAN hanya lihat ISI (2)', $pimpinan !== null && akses('ISI', $pimpinan) === 2);
cek('PIMPINAN tidak boleh IMPOR (2, bukan 1)', $pimpinan !== null && akses('IMPOR', $pimpinan) === 2);
cek('OPERATOR boleh IMPOR', $operator !== null && akses('IMPOR', $operator) === 1);

/* ── bersihkan data uji ──────────────────────────────────────────── */
foreach (q('SELECT id FROM mahasiswa WHERE nim LIKE ?', ['UJI%']) as $r) {
    ex('DELETE FROM mahasiswa WHERE id = ?', [$r['id']]);
}
foreach (q('SELECT id FROM dosen WHERE nidn_nidk_nup LIKE ?', ['UJI%']) as $r) {
    ex('DELETE FROM dosen_pendidikan WHERE dosen_id = ?', [$r['id']]);
    ex('DELETE FROM dosen_prodi WHERE dosen_id = ?', [$r['id']]);
    ex('DELETE FROM dosen WHERE id = ?', [$r['id']]);
}
$sisaMhs = (int) qv('SELECT COUNT(*) FROM mahasiswa WHERE nim LIKE "UJI%"', [], 0);
$sisaDsn = (int) qv('SELECT COUNT(*) FROM dosen WHERE nidn_nidk_nup LIKE "UJI%"', [], 0);
cek('data uji mahasiswa bersih', $sisaMhs === 0, (string) $sisaMhs);
cek('data uji dosen bersih', $sisaDsn === 0, (string) $sisaDsn);
cek('tidak ada dosen_prodi yatim', (int) qv(
    'SELECT COUNT(*) FROM dosen_prodi dp LEFT JOIN dosen d ON d.id = dp.dosen_id WHERE d.id IS NULL', [], 0) === 0);
cek('tidak ada dosen_pendidikan yatim', (int) qv(
    'SELECT COUNT(*) FROM dosen_pendidikan p LEFT JOIN dosen d ON d.id = p.dosen_id WHERE d.id IS NULL', [], 0) === 0);

while (ob_get_level() > 0) { ob_end_flush(); }
echo $gagal === 0 ? "\nSEMUA UJI LULUS\n" : "\n$gagal UJI GAGAL\n";
exit($gagal === 0 ? 0 : 1);
