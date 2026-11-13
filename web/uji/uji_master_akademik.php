<?php
declare(strict_types=1);

/**
 * Uji master akademik: MK, wahana, lab, pustaka.
 *
 * Tanpa HTTP: mk.php berhenti sebelum mencetak HTML kalau $modul bukan
 * 'master', jadi fungsinya bisa dipanggil langsung di sini.
 *
 * Jalankan: php web/uji/uji_master_akademik.php
 */

// Tangkap keluaran yang tidak sengaja tercetak (warning/notice) supaya
// "ok" tetap bisa dibaca mesin.
ob_start();
putenv('LAMPTKES_ENV=lokal');

require_once __DIR__ . '/../modul/master/mk.php';
require_once __DIR__ . '/../modul/master/wahana.php';
require_once __DIR__ . '/../modul/master/lab.php';
require_once __DIR__ . '/../modul/master/pustaka.php';

$gagal = 0;
function cek(string $nama, bool $ok, string $detail = ''): void
{
    global $gagal;
    echo ($ok ? '  ok    ' : '  GAGAL ') . $nama . ($detail !== '' ? " — $detail" : '') . "\n";
    if (!$ok) {
        $gagal++;
    }
}

$P1 = (int) qv('SELECT id FROM prodi ORDER BY id LIMIT 1');
$P2 = (int) qv('SELECT id FROM prodi WHERE id <> ? ORDER BY id LIMIT 1', [$P1]);
cek('dua prodi tersedia untuk uji lintas prodi', $P1 > 0 && $P2 > 0, "P1=$P1 P2=$P2");

/** Bersihkan selalu, termasuk kalau uji gagal di tengah. */
$bersih = ['mata_kuliah' => [], 'wahana' => [], 'laboratorium' => [], 'pustaka' => []];
register_shutdown_function(static function () use (&$bersih): void {
    foreach ($bersih as $tabel => $ids) {
        foreach ($ids as $id) {
            if ($id > 0) {
                ex("DELETE FROM $tabel WHERE id = ?", [$id]);
            }
        }
    }
});

$kolomMk = 'id, kode_mk, nama_mk, sks_kuliah, sks_praktik, tahap';

/* ── (a) total SKS per tahap dihitung dari baris ─────────────────── */
$mk = [
    ['kode_mk' => 'UJI101', 'nama_mk' => 'Uji Akademik A', 'sks_kuliah' => 3, 'sks_praktik' => 1, 'tahap' => 'Akademik',
     'aspek_sikap' => 1, 'ada_rps' => 1, 'cpl' => 'CPL-1'],
    ['kode_mk' => 'UJI102', 'nama_mk' => 'Uji Akademik B', 'sks_kuliah' => 2, 'sks_praktik' => 0, 'tahap' => 'Akademik',
     'aspek_pengetahuan' => 1],
    ['kode_mk' => 'UJI201', 'nama_mk' => 'Uji Profesi',   'sks_kuliah' => 0, 'sks_praktik' => 2, 'tahap' => 'Profesi',
     'aspek_ket_khusus' => 1],
];
$mkId = [];
foreach ($mk as $i => $d) {
    $h = mka_simpan_mk($d, $P1);
    cek('simpan MK ' . $d['kode_mk'], $h['ok'], $h['galat']);
    $mkId[$i] = $h['id'];
    $bersih['mata_kuliah'][] = $h['id'];
}

$baris = q("SELECT $kolomMk FROM mata_kuliah WHERE prodi_id = ?", [$P1]);
$tot = mka_total_sks($baris);
cek('(a) Akademik: 2 MK, 6 SKS (3+1 + 2+0)',
    $tot['Akademik']['mk'] === 2 && $tot['Akademik']['total'] === 6, json_encode($tot['Akademik']));
cek('(a) Profesi: 1 MK, 2 SKS praktik',
    $tot['Profesi']['mk'] === 1 && $tot['Profesi']['total'] === 2 && $tot['Profesi']['praktik'] === 2,
    json_encode($tot['Profesi']));
cek('(a) total = jumlah baris, bukan konstanta',
    $tot['Akademik']['total'] + $tot['Profesi']['total'] === 8, (string) ($tot['Akademik']['total'] + $tot['Profesi']['total']));

// Bukti bukan konstanta: ubah satu baris, total wajib ikut berubah.
mka_simpan_mk(['kode_mk' => 'UJI101', 'nama_mk' => 'Uji Akademik A', 'sks_kuliah' => 4,
    'sks_praktik' => 1, 'tahap' => 'Akademik'], $P1, $mkId[0]);
$tot2 = mka_total_sks(q("SELECT $kolomMk FROM mata_kuliah WHERE prodi_id = ?", [$P1]));
cek('(a) ubah SKS 3->4 menaikkan total Akademik 6->7', $tot2['Akademik']['total'] === 7,
    json_encode($tot2['Akademik']));

// Hapus satu baris -> total turun. Angka yang diketik tidak akan ikut.
ex('DELETE FROM mata_kuliah WHERE id = ?', [$mkId[1]]);
$tot3 = mka_total_sks(q("SELECT $kolomMk FROM mata_kuliah WHERE prodi_id = ?", [$P1]));
cek('(a) hapus baris B menurunkan total Akademik 7->5', $tot3['Akademik']['total'] === 5,
    json_encode($tot3['Akademik']));
cek('(a) baris kosong -> semua tahap 0',
    mka_total_sks([])['Akademik']['total'] === 0 && mka_total_sks([])['Profesi']['mk'] === 0);

/* ── (c) kode_mk ganda: ditolak satu prodi, boleh lintas prodi ───── */
$dobel = mka_simpan_mk(['kode_mk' => 'uji101', 'nama_mk' => 'Duplikat beda huruf',
    'sks_kuliah' => 2, 'tahap' => 'Akademik'], $P1);
cek('(c) kode ganda dalam satu prodi ditolak', !$dobel['ok'], $dobel['galat']);
cek('(c) pesan menyebut kodenya', str_contains($dobel['galat'], 'UJI101'), $dobel['galat']);

$lintas = mka_simpan_mk(['kode_mk' => 'UJI101', 'nama_mk' => 'MK prodi lain',
    'sks_kuliah' => 3, 'tahap' => 'Akademik'], $P2);
cek('(c) kode sama di prodi BERBEDA boleh', $lintas['ok'], $lintas['galat']);
$bersih['mata_kuliah'][] = $lintas['id'];

// Kode dinormalkan: spasi + huruf kecil jadi kode yang sama.
$spasi = mka_simpan_mk(['kode_mk' => '  uji101 ', 'nama_mk' => 'Spasi', 'sks_kuliah' => 2,
    'tahap' => 'Akademik'], $P1);
cek('(c) "  uji101 " dinormalkan lalu tetap ditolak', !$spasi['ok'], $spasi['galat']);

// Batas: SKS 0 semua ditolak, MK prodi lain tidak bisa diubah dari sini.
$nol = mka_simpan_mk(['kode_mk' => 'UJI999', 'nama_mk' => 'Tanpa SKS',
    'sks_kuliah' => 0, 'sks_praktik' => 0], $P1);
cek('(batas) SKS 0+0 ditolak', !$nol['ok'], $nol['galat']);
$curang = mka_simpan_mk(['kode_mk' => 'UJI101', 'nama_mk' => 'Curang',
    'sks_kuliah' => 2, 'tahap' => 'Akademik'], $P2, $mkId[0]);
cek('(batas) ubah MK milik prodi lain ditolak', !$curang['ok'], $curang['galat']);

/* ── (b) wahana kedaluwarsa muncul di daftar perhatian ───────────── */
$kemarin = date('Y-m-d', strtotime('-1 day'));
$besok   = date('Y-m-d', strtotime('+1 day'));
$hNanti  = date('Y-m-d', strtotime('+' . (MKA_WAHANA_SEGERA + 10) . ' days'));

$w = mka_simpan_wahana(['nama' => 'RS Uji Kedaluwarsa', 'kualifikasi' => 'Tipe B',
    'variasi_kasus' => '12 kasus', 'jumlah_pembimbing' => 3,
    'dokumen_kerja_sama' => 'MoU 001/UJI/2023', 'berlaku_sampai' => $kemarin], $P1);
cek('simpan wahana kedaluwarsa', $w['ok'], $w['galat']);
$bersih['wahana'][] = $w['id'];

$w2 = mka_simpan_wahana(['nama' => 'RS Uji Masih Jauh', 'jumlah_pembimbing' => 2,
    'dokumen_kerja_sama' => 'MoU 002/UJI/2026', 'berlaku_sampai' => $hNanti], $P1);
cek('simpan wahana masih berlaku', $w2['ok'], $w2['galat']);
$bersih['wahana'][] = $w2['id'];

$w3 = mka_simpan_wahana(['nama' => 'RS Uji Tanpa Tanggal', 'jumlah_pembimbing' => 1], $P1);
cek('simpan wahana tanpa tanggal MoU', $w3['ok'], $w3['galat']);
$bersih['wahana'][] = $w3['id'];

$kolomW = 'id, nama, kualifikasi, variasi_kasus, jumlah_pembimbing, dokumen_kerja_sama, berlaku_sampai';
$semuaW = q("SELECT $kolomW FROM wahana WHERE prodi_id = ? ORDER BY nama", [$P1]);
cek('(b) wahana kedaluwarsa TIDAK disembunyikan dari daftar',
    count(array_filter($semuaW, static fn($b) => $b['nama'] === 'RS Uji Kedaluwarsa')) === 1,
    count($semuaW) . ' baris');

$perhatian = mka_wahana_perhatian($semuaW);
$namaPerhatian = array_column($perhatian, 'nama');
cek('(b) wahana kedaluwarsa muncul di daftar perhatian',
    in_array('RS Uji Kedaluwarsa', $namaPerhatian, true), json_encode($namaPerhatian));
cek('(b) wahana yang masih jauh TIDAK masuk perhatian',
    !in_array('RS Uji Masih Jauh', $namaPerhatian, true), json_encode($namaPerhatian));
cek('(b) wahana bertanggal kosong tidak dihitung sebagai kedaluwarsa',
    !in_array('RS Uji Tanpa Tanggal', $namaPerhatian, true));

$satu = null;
foreach ($perhatian as $b) {
    if ($b['nama'] === 'RS Uji Kedaluwarsa') {
        $satu = $b;
    }
}
cek('(b) status kedaluwarsa + sisa -1 hari',
    $satu !== null && $satu['_status'] === 'kedaluwarsa' && $satu['_sisa'] === -1,
    json_encode($satu['_status'] ?? null) . ' sisa=' . json_encode($satu['_sisa'] ?? null));
cek('(b) tanggal kedaluwarsa ikut tampil, bukan disembunyikan',
    $satu !== null && tgl((string) $satu['berlaku_sampai']) !== '—'
        && str_contains($satu['_label'], '1'),
    ($satu['_label'] ?? '') . ' / ' . tgl((string) ($satu['berlaku_sampai'] ?? '')));

// Batas tanggal: hari ini = berakhir hari ini (sisa 0), bukan kedaluwarsa.
$hariIni = date('Y-m-d');
cek('(batas) berlaku_sampai hari ini -> sisa 0, masih berlaku',
    mka_wahana_status($hariIni)['sisa'] === 0 && mka_wahana_status($hariIni)['status'] === 'segera',
    json_encode(mka_wahana_status($hariIni)));
cek('(batas) tanggal ngawur tidak bikin fatal',
    mka_wahana_status('bukan-tanggal')['status'] === 'kosong', json_encode(mka_wahana_status('bukan-tanggal')));
cek('(batas) tanggal kosong -> status kosong', mka_wahana_status(null)['status'] === 'kosong');
$tglSalah = mka_simpan_wahana(['nama' => 'RS Uji Tanggal Salah', 'berlaku_sampai' => '2026-02-30'], $P1);
cek('(batas) tanggal mustahil ditolak', !$tglSalah['ok'], $tglSalah['galat']);
$kembarW = mka_simpan_wahana(['nama' => 'RS Uji Kedaluwarsa', 'berlaku_sampai' => $besok], $P1);
cek('(batas) nama wahana kembar di satu prodi ditolak', !$kembarW['ok'], $kembarW['galat']);

/* ── (d) total judul & copy pustaka = jumlah baris ───────────────── */
$pust = [
    ['jenis' => 'Buku',    'judul' => 'Buku Uji A',  'jumlah_judul' => 5, 'jumlah_copy' => 12, 'tahun' => 2020, 'nomor' => '978-000'],
    ['jenis' => 'Buku',    'judul' => 'Buku Uji B',  'jumlah_judul' => 3, 'jumlah_copy' => 4,  'tahun' => 2021],
    ['jenis' => 'Jurnal',  'judul' => 'Jurnal Uji C', 'jumlah_judul' => 7, 'jumlah_copy' => 7, 'tahun' => 2023, 'nomor' => 'e-ISSN 000'],
];
foreach ($pust as $d) {
    $h = mka_simpan_pustaka($d, $P1);
    cek('simpan pustaka ' . $d['judul'], $h['ok'], $h['galat']);
    $bersih['pustaka'][] = $h['id'];
}

$kolomP = 'id, jenis, judul, jumlah_judul, jumlah_copy, tahun, nomor';
$semuaP = q("SELECT $kolomP FROM pustaka WHERE prodi_id = ?", [$P1]);
$rp = mka_rekap_pustaka($semuaP);
$jmlBaris = count($semuaP);
cek('(d) 3 baris pustaka tersimpan', $jmlBaris === 3, "dapat $jmlBaris");
cek('(d) total judul = 15', $rp['judul'] === 15, (string) $rp['judul']);
cek('(d) total copy = 23', $rp['copy'] === 23, (string) $rp['copy']);
cek('(d) per jenis: Buku 2 baris/8 judul/16 copy',
    $rp['per_jenis']['Buku'] === ['judul' => 8, 'copy' => 16, 'baris' => 2],
    json_encode($rp['per_jenis']['Buku']));
cek('(d) per jenis: Jurnal 1 baris/7 judul/7 copy',
    $rp['per_jenis']['Jurnal'] === ['judul' => 7, 'copy' => 7, 'baris' => 1],
    json_encode($rp['per_jenis']['Jurnal']));
cek('(d) jenis tanpa baris tetap 0, bukan hilang',
    isset($rp['per_jenis']['Prosiding']) && $rp['per_jenis']['Prosiding']['judul'] === 0);

// Angka dihitung ulang dari baris, bukan nilai yang tersimpan di suatu tempat.
ex('DELETE FROM pustaka WHERE id = ?', [$bersih['pustaka'][0]]);
$rp2 = mka_rekap_pustaka(q("SELECT $kolomP FROM pustaka WHERE prodi_id = ?", [$P1]));
cek('(d) hapus 1 baris (5 judul/12 copy) -> 10 judul, 11 copy',
    $rp2['judul'] === 10 && $rp2['copy'] === 11, json_encode([$rp2['judul'], $rp2['copy']]));
cek('(d) pustaka kosong -> 0/0', mka_rekap_pustaka([])['judul'] === 0 && mka_rekap_pustaka([])['copy'] === 0);

$tanpaJudul = mka_simpan_pustaka(['jenis' => 'Buku', 'judul' => 'Copy tanpa judul',
    'jumlah_judul' => 0, 'jumlah_copy' => 3], $P1);
cek('(batas) copy > 0 dengan judul 0 ditolak', !$tanpaJudul['ok'], $tanpaJudul['galat']);
$jenisSalah = mka_simpan_pustaka(['jenis' => 'Majalah', 'judul' => 'X', 'jumlah_judul' => 1], $P1);
cek('(batas) jenis di luar ENUM ditolak', !$jenisSalah['ok'], $jenisSalah['galat']);
$tahunAneh = mka_simpan_pustaka(['jenis' => 'Buku', 'judul' => 'Y', 'jumlah_judul' => 1, 'tahun' => 1200], $P1);
cek('(batas) tahun 1200 ditolak', !$tahunAneh['ok'], $tahunAneh['galat']);

/* ── laboratorium: unit & kondisi dijumlah dari baris ────────────── */
$lab = [
    ['nama_lab' => 'Lab Uji Kebidanan', 'alat' => 'Bedah set', 'jumlah_unit' => 4,
     'kepemilikan' => 'SD', 'kondisi' => 'Baik', 'ada_logbook' => 1, 'jam_pakai_per_minggu' => 20],
    ['nama_lab' => 'Lab Uji Kebidanan', 'alat' => 'USG phantom', 'jumlah_unit' => 2,
     'kepemilikan' => 'SW', 'kondisi' => 'Rusak Ringan', 'jam_pakai_per_minggu' => 10],
    ['nama_lab' => 'Lab Uji Kebidanan', 'alat' => 'Timbangan bayi', 'jumlah_unit' => 1,
     'kepemilikan' => 'SD', 'kondisi' => 'Rusak Berat'],
];
foreach ($lab as $d) {
    $h = mka_simpan_lab($d, $P1);
    cek('simpan alat ' . $d['alat'], $h['ok'], $h['galat']);
    $bersih['laboratorium'][] = $h['id'];
}
$semuaL = q('SELECT id, nama_lab, alat, jumlah_unit, kepemilikan, kondisi, ada_logbook, jam_pakai_per_minggu
             FROM laboratorium WHERE prodi_id = ?', [$P1]);
$rl = mka_rekap_lab($semuaL);
cek('lab: 1 lab, 7 unit', $rl['jumlah_lab'] === 1 && $rl['unit'] === 7, json_encode([$rl['jumlah_lab'], $rl['unit']]));
cek('lab: kondisi baik 4, rusak ringan 2, rusak berat 1',
    $rl['baik'] === 4 && $rl['rusak_ringan'] === 2 && $rl['rusak_berat'] === 1, json_encode($rl));
cek('lab: kepemilikan SD 5 unit, SW 2 unit', $rl['SD'] === 5 && $rl['SW'] === 2, json_encode([$rl['SD'], $rl['SW']]));
cek('lab: 1 dari 3 baris ada logbook', $rl['logbook'] === 1, (string) $rl['logbook']);
cek('lab kosong -> 0 lab', mka_rekap_lab([])['jumlah_lab'] === 0);
$jamAneh = mka_simpan_lab(['nama_lab' => 'Lab Uji', 'alat' => 'Alat', 'jam_pakai_per_minggu' => 200], $P1);
cek('(batas) jam pakai 200/minggu ditolak', !$jamAneh['ok'], $jamAneh['galat']);
$unitNol = mka_simpan_lab(['nama_lab' => 'Lab Uji', 'alat' => 'Alat', 'jumlah_unit' => 0], $P1);
cek('(batas) jumlah unit 0 ditolak', !$unitNol['ok'], $unitNol['galat']);

/* ── gerbang izin: peran yang boleh dan tidak ────────────────────── */
$peran = static function (string $email): ?array {
    return q1('SELECT id, peran_kode, prodi_id FROM pengguna WHERE email = ?', [$email]);
};
$siti = $peran('siti@bbg.ac.id');
$maimunah = $peran('maimunah@bbg.ac.id');
$admin = $peran('zulfikar@bbg.ac.id');
cek('akun uji OPERATOR, VERIFIKATOR, ADMIN ada', $siti && $maimunah && $admin);

// masuk_sebagai() menulis sesi, tapi pengguna() menyimpan cache sekali per
// proses — jadi peran diuji lewat akses($aksi, $u) dengan pengguna eksplisit.
masuk_sebagai((int) $siti['id'], (int) $siti['prodi_id']);
cek('gerbang: OPERATOR boleh ISI (menyimpan MK)', akses('ISI', $siti) === 1, 'akses=' . akses('ISI', $siti));

cek('gerbang: VERIFIKATOR tidak boleh ISI', akses('ISI', $maimunah) === 0, 'akses=' . akses('ISI', $maimunah));
cek('gerbang: PIMPINAN hanya lihat ISI, tidak menulis',
    akses('ISI', $peran('zahriah@bbg.ac.id')) === 2, 'akses=' . akses('ISI', $peran('zahriah@bbg.ac.id')));

// wajib() memanggil tolak() yang exit; dibuktikan di proses terpisah supaya
// exit-nya tidak mematikan uji ini. view.php dimuat karena tolak() ada di sana.
$skrip = tempnam(sys_get_temp_dir(), 'uji_gerbang_') . '.php';
file_put_contents($skrip, '<?php
require ' . var_export(__DIR__ . '/../system/view.php', true) . ';
$u = q1("SELECT id, prodi_id FROM pengguna WHERE email = ?", ["maimunah@bbg.ac.id"]);
masuk_sebagai((int) $u["id"], (int) $u["prodi_id"]);
wajib("ISI");
echo "LOLOS";
');
$keluaran = [];
$kode = 0;
exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($skrip) . ' 2>&1', $keluaran, $kode);
@unlink($skrip);
$teks = implode(' ', $keluaran);
cek('gerbang: wajib(ISI) menghentikan VERIFIKATOR (403, bukan diam-diam lolos)',
    $kode !== 0 || !str_contains($teks, 'LOLOS'), "exit=$kode out=$teks");
cek('gerbang: pesan penolakan menyebut aksinya', str_contains($teks, 'ISI'), $teks);

// Di CLI $PRODI kosong (tidak lewat front controller), jadi ini menguji cabang
// ADMIN: prodi dipilih dari ?p= dan prodi tak dikenal jatuh ke prodi pertama.
// Tanpa itu mka_prodi() bisa mengembalikan 0 dan semua query jadi kosong diam-diam.
$_GET['p'] = (string) $P2;
cek('gerbang: prodi dipilih lewat ?p=', mka_prodi() === $P2, 'dapat ' . mka_prodi());
$_GET['p'] = '999999';
cek('(batas) prodi tak dikenal jatuh ke prodi pertama, bukan 0', mka_prodi() === $P1, 'dapat ' . mka_prodi());
unset($_GET['p']);

/* ── bersih ──────────────────────────────────────────────────────── */
foreach ($bersih as $tabel => $ids) {
    foreach ($ids as $id) {
        if ($id > 0) {
            ex("DELETE FROM $tabel WHERE id = ?", [$id]);
        }
    }
}
$sisa = [
    'mata_kuliah'  => (int) qv('SELECT COUNT(*) FROM mata_kuliah WHERE kode_mk LIKE ? OR kode_mk LIKE ?', ['UJI%', 'uji%']),
    'wahana'       => (int) qv('SELECT COUNT(*) FROM wahana WHERE nama LIKE ?', ['%Uji%']),
    'laboratorium' => (int) qv('SELECT COUNT(*) FROM laboratorium WHERE nama_lab LIKE ?', ['%Uji%']),
    'pustaka'      => (int) qv('SELECT COUNT(*) FROM pustaka WHERE judul LIKE ?', ['%Uji%']),
];
cek('data uji bersih', array_sum($sisa) === 0, json_encode($sisa));

while (ob_get_level() > 0) {
    ob_end_flush();
}
echo $gagal === 0 ? "\nSEMUA UJI LULUS\n" : "\n$gagal UJI GAGAL\n";
exit($gagal === 0 ? 0 : 1);
