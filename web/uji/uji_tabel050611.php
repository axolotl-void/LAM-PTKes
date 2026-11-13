<?php
declare(strict_types=1);

/**
 * Uji Tabel 05/06/11 — matriks angkatan × tahun akademik.
 *
 * YANG DIKUNCI UJI INI (semua pernah jadi sumber angka salah):
 *   1) jumlah & label kolom persis seperti instrumen (05: TS-6…TS, 06: TS-3…TS);
 *   2) jumlah semua sel satu baris = "Jumlah lulusan s.d. TS" baris itu;
 *   3) satu mahasiswa hanya masuk SATU TA walau satu TA mencakup dua tahun
 *      kalender — inilah yang paling gampang salah dan paling mahal;
 *   4) mahasiswa tanpa tahun_lulus tidak masuk sel mana pun, tapi DIHITUNG
 *      dan dilaporkan (tidak hilang diam-diam);
 *   5) angkatan di luar rentang kolom tidak dibuang, dan jumlahnya dilaporkan;
 *   6) daya tampung Tabel 11 dibaca dari isian; belum diisi = null, bukan 0.
 *
 * Jalankan: LAMPTKES_ENV=lokal php web/uji/uji_tabel050611.php
 */

require_once __DIR__ . '/../system/config.php';
require_once __DIR__ . '/../system/db.php';
require_once __DIR__ . '/../system/hitung.php';
require_once __DIR__ . '/../system/tabel050611.php';
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

/** Mahasiswa uji; tahun_lulus/tanggal_lulus null berarti belum/entah. */
function mhs_uji(int $prodiId, int $masuk, string $status, string $tahap = 'Akademik',
                 ?int $lulus = null, ?string $tgl = null, string $nim = ''): int
{
    static $n = 0;
    $n++;
    return sisip('mahasiswa', [
        'prodi_id' => $prodiId,
        'nim' => 'UJI' . $n . ($nim !== '' ? '_' . $nim : ''),
        'nama' => 'Uji ' . $n,
        'tahun_masuk' => $masuk,
        'tahap' => $tahap,
        'status' => $status,
        'tahun_lulus' => $lulus,
        'tanggal_lulus' => $tgl,
    ]);
}

/** Jumlah semua sel satu baris Tabel 05/06. */
function jumlah_sel(array $baris): int
{
    return array_sum($baris['per_ta']);
}

bersihkan_data_uji(db(), true);

$prodi = (int) qv('SELECT id FROM prodi WHERE aktif = 1 ORDER BY id LIMIT 1', [], 0);
$per = $prodi > 0 ? periode_prodi($prodi) : null;
$ts = $per !== null ? (int) $per['tahun_ts'] : 0;

echo "── prasyarat ──\n";
cek('ada prodi aktif', $prodi > 0);
cek('ada periode aktif', $ts > 0, "TS=$ts");
if ($prodi === 0 || $ts === 0) {
    echo "\nTidak bisa diuji tanpa prodi/periode.\n";
    exit(1);
}

echo "\n── bentuk kolom sesuai instrumen ──\n";

$h5 = t05_hitung($prodi);
$h6 = t06_hitung($prodi);
$h11 = t11_hitung($prodi);

cek('Tabel 05: 7 kolom', count($h5['kolom_ta']) === 7, 'dapat ' . count($h5['kolom_ta']));
cek('Tabel 05: label TS-6 … TS',
    $h5['kolom_ta'] === ['TS-6', 'TS-5', 'TS-4', 'TS-3', 'TS-2', 'TS-1', 'TS'],
    implode(',', $h5['kolom_ta']));
cek('Tabel 06: 4 kolom', count($h6['kolom_ta']) === 4, 'dapat ' . count($h6['kolom_ta']));
cek('Tabel 06: label TS-3 … TS',
    $h6['kolom_ta'] === ['TS-3', 'TS-2', 'TS-1', 'TS'], implode(',', $h6['kolom_ta']));
cek('Tabel 11: 3 kolom TS-2 … TS',
    $h11['kolom_ta'] === ['TS-2', 'TS-1', 'TS'], implode(',', $h11['kolom_ta']));
cek('kolom memakai periode, bukan tahun berjalan',
    $h5['kolom_ta'][6] === 'TS' && ta_ke_tahun('TS', $per) === $ts, "TS=$ts");
cek('kolom kosong: tiap TA -> satu kunci total_per_ta',
    array_keys($h5['total_per_ta']) === $h5['kolom_ta']);

echo "\n── master kosong: tabel kosong, bukan galat ──\n";
cek('Tabel 05 tanpa mahasiswa: 0 baris', $h5['baris'] === [], 'dapat ' . count($h5['baris']));
cek('Tabel 05 total per TA nol', array_sum($h5['total_per_ta']) === 0);
cek('Tabel 11 tetap 4 komponen walau master kosong', count($h11['baris']) === 4,
    'dapat ' . count($h11['baris']));

echo "\n── sel = angkatan × TA lulus ──\n";

// Angkatan 2020, lulus TA 2023 (gasal: September 2023) dan TA 2025 (genap: Maret 2026).
mhs_uji($prodi, 2020, 'Lulus', 'Akademik', 2023, '2023-09-10');
mhs_uji($prodi, 2020, 'Lulus', 'Akademik', 2026, '2026-03-05');
// Angkatan 2020 yang masih kuliah: muncul sebagai baris, sel semuanya nol.
mhs_uji($prodi, 2020, 'Aktif');

$h5 = t05_hitung($prodi);
$baris2020 = array_values(array_filter($h5['baris'], fn($b) => $b['angkatan'] === 2020));
cek('angkatan 2020 muncul satu baris (tidak dobel per TA)', count($baris2020) === 1,
    'dapat ' . count($baris2020));
$b = $baris2020[0] ?? ['per_ta' => [], 'lulus_sd_ts' => -1];
cek('sel (2020, TS-2=2023) = 1', ($b['per_ta']['TS-2'] ?? -1) === 1, (string) ($b['per_ta']['TS-2'] ?? 'x'));
cek('sel (2020, TS) = 1 (lulus Maret 2026 = TA 2025/2026)',
    ($b['per_ta']['TS'] ?? -1) === 1, (string) ($b['per_ta']['TS'] ?? 'x'));
cek('lulusan s.d. TS = 2', $b['lulus_sd_ts'] === 2, (string) $b['lulus_sd_ts']);
cek('jumlah semua sel baris = lulusan s.d. TS', jumlah_sel($b) === $b['lulus_sd_ts'],
    jumlah_sel($b) . ' vs ' . $b['lulus_sd_ts']);
cek('angkatan dengan 0 lulusan tetap jadi baris', count($h5['baris']) >= 1);

echo "\n── INTI: satu mahasiswa tidak boleh masuk dua TA ──\n";

// Lulus Februari 2024 = semester genap TA 2023/2024 -> TA 2023 (TS-2), BUKAN TA 2024.
mhs_uji($prodi, 2021, 'Lulus', 'Akademik', 2024, '2024-02-14');
// Lulus Agustus 2024 = semester gasal TA 2024/2025 -> TA 2024 (TS-1).
mhs_uji($prodi, 2021, 'Lulus', 'Akademik', 2024, '2024-08-20');

$h5 = t05_hitung($prodi);
$b = array_values(array_filter($h5['baris'], fn($x) => $x['angkatan'] === 2021))[0];
cek('lulus Feb 2024 masuk TS-2 (genap TA 2023/2024)', ($b['per_ta']['TS-2'] ?? 0) === 1,
    (string) ($b['per_ta']['TS-2'] ?? 'x'));
cek('lulus Agu 2024 masuk TS-1 (gasal TA 2024/2025)', ($b['per_ta']['TS-1'] ?? 0) === 1,
    (string) ($b['per_ta']['TS-1'] ?? 'x'));
cek('jumlah sel baris 2021 = 2', array_sum($b['per_ta']) === 2, 'jumlah sel ' . array_sum($b['per_ta']));
$kolomJumlah = [];
foreach ($h5['baris'] as $r) {
    foreach ($r['per_ta'] as $k => $v) {
        $kolomJumlah[$k] = ($kolomJumlah[$k] ?? 0) + $v;
    }
}
cek('total_per_ta = jumlah seluruh baris per kolom', $kolomJumlah === $h5['total_per_ta'],
    json_encode($kolomJumlah) . ' vs ' . json_encode($h5['total_per_ta']));

echo "\n── mahasiswa tanpa tahun_lulus: dilaporkan, tidak masuk sel ──\n";

$sebelum = t05_hitung($prodi);
mhs_uji($prodi, 2022, 'Lulus', 'Akademik', null, null);          // status Lulus, tahun kosong
$sesudah = t05_hitung($prodi);
cek('tanpa tahun_lulus tidak menambah sel mana pun',
    array_sum($sesudah['total_per_ta']) === array_sum($sebelum['total_per_ta']),
    array_sum($sebelum['total_per_ta']) . ' -> ' . array_sum($sesudah['total_per_ta']));
cek('tanpa tahun_lulus DIHITUNG di diabaikan.tanpa_tahun_lulus',
    $sesudah['diabaikan']['tanpa_tahun_lulus'] === 1,
    (string) $sesudah['diabaikan']['tanpa_tahun_lulus']);
cek('catatan menyebut jumlah yang diabaikan',
    str_contains($sesudah['catatan'], 'tanpa tahun_lulus'));

echo "\n── angkatan di luar rentang tidak hilang diam-diam ──\n";

$sebelum = t05_hitung($prodi);
// Angkatan 2001: jauh sebelum TS-6. Lulus TA 2024 -> tetap harus tampil.
mhs_uji($prodi, 2001, 'Lulus', 'Akademik', 2024, '2024-09-01');
$sesudah = t05_hitung($prodi);
$b = array_values(array_filter($sesudah['baris'], fn($x) => $x['angkatan'] === 2001));
cek('angkatan 2001 tetap punya baris', count($b) === 1, 'dapat ' . count($b));
cek('lulusannya tetap masuk kolom TS-1', ($b[0]['per_ta']['TS-1'] ?? 0) === 1);
cek('jumlah angkatan di luar rentang dilaporkan',
    $sesudah['luar_rentang'] === $sebelum['luar_rentang'] + 1,
    $sebelum['luar_rentang'] . ' -> ' . $sesudah['luar_rentang']);
cek('catatan menyebut baris di luar rentang', str_contains($sesudah['catatan'], 'di luar rentang'));

echo "\n── tahap: 05 hanya akademik, 06 hanya profesi ──\n";

$p6 = t06_hitung($prodi);
cek('Tabel 06 belum memuat mahasiswa akademik',
    array_sum($p6['total_per_ta']) === 0, (string) array_sum($p6['total_per_ta']));

mhs_uji($prodi, 2023, 'Lulus', 'Profesi', 2024, '2024-10-01');
mhs_uji($prodi, 2025, 'Aktif', 'Profesi');
$p6 = t06_hitung($prodi);
$h5 = t05_hitung($prodi);
cek('Tabel 06 memuat lulusan profesi (TS-1 = 1)',
    ($p6['total_per_ta']['TS-1'] ?? -1) === 1, json_encode($p6['total_per_ta']));
cek('Tabel 05 tidak terpengaruh mahasiswa profesi',
    array_sum($h5['total_per_ta']) === array_sum($sesudah['total_per_ta']),
    json_encode($h5['total_per_ta']));
cek('Tabel 06 baris angkatan = 2023 dan 2025',
    array_column($p6['baris'], 'angkatan') === [2023, 2025],
    implode(',', array_column($p6['baris'], 'angkatan')));
cek('Tabel 06 jumlah sel = lulusan s.d. TS per baris',
    count(array_filter($p6['baris'], fn($r) => jumlah_sel($r) !== $r['lulus_sd_ts'])) === 0);

echo "\n── Tabel 11 ──\n";

$h11 = t11_hitung($prodi);
$label = array_column($h11['baris'], 'label', 'no');
cek('Tabel 11 baris 1 = Daya tampung', ($label['1'] ?? '') === 'Daya tampung');
cek('Tabel 11 baris 2 = Lulus tahap akademik', ($label['2'] ?? '') === 'Lulus tahap akademik');
cek('nama baris 3 menyebut mahasiswa baru profesi',
    str_contains($label['3'] ?? '', 'Mahasiswa baru profesi'));
cek('nama baris 4 menyebut total aktif profesi',
    str_contains($label['4'] ?? '', 'Total aktif profesi'));

$daya = $h11['baris'][0]['per_ta'];
cek('daya tampung kosong -> null, bukan 0',
    array_values($daya) === [null, null, null], json_encode($daya));

// Tabel 11 hanya 3 kolom, Tabel 05 punya 7: yang dibandingkan irisan kolomnya.
$t05 = t05_hitung($prodi);
cek('lulus tahap akademik Tabel 11 = total_per_ta Tabel 05 (kolom sama)',
    $h11['baris'][1]['per_ta'] === array_intersect_key($t05['total_per_ta'],
        array_flip($h11['kolom_ta'])),
    json_encode($h11['baris'][1]['per_ta']) . ' vs ' . json_encode($t05['total_per_ta']));

$aktifProfesi = $h11['baris'][3]['per_ta'];
cek('aktif profesi TS = 1 (satu mahasiswa aktif angkatan 2025)',
    ($aktifProfesi['TS'] ?? -1) === 1, json_encode($aktifProfesi));
cek('lulusan profesi tidak lagi dihitung aktif pada TA lulusnya',
    ($aktifProfesi['TS-1'] ?? -1) === 0, json_encode($aktifProfesi));
cek('mahasiswa baru profesi 2025 = 1 di kolom TS',
    ($h11['baris'][2]['per_ta']['TS'] ?? -1) === 1,
    json_encode($h11['baris'][2]['per_ta']));

echo "\n── daya tampung dari isian Tabel 11 ──\n";

$perId = (int) qv('SELECT id FROM periode WHERE prodi_id = ? AND aktif = 1 ORDER BY tahun_ts DESC LIMIT 1',
    [$prodi], 0);
$bid = sisip('baris_tabel', ['prodi_id' => $prodi, 'periode_id' => $perId,
    'tabel_kode' => '11', 'urut' => 1]);
sisip('sel', ['baris_id' => $bid, 'field_kunci' => 'tahun_akademik', 'nilai_teks' => 'TS-1']);
sisip('sel', ['baris_id' => $bid, 'field_kunci' => 'daya_tampung', 'nilai_angka' => 40]);
$bid2 = sisip('baris_tabel', ['prodi_id' => $prodi, 'periode_id' => $perId,
    'tabel_kode' => '11', 'urut' => 2]);
// Ditulis sebagai "2023/2024" supaya terbukti label bebas diterjemahkan.
sisip('sel', ['baris_id' => $bid2, 'field_kunci' => 'tahun_akademik', 'nilai_teks' => '2023/2024']);
sisip('sel', ['baris_id' => $bid2, 'field_kunci' => 'daya_tampung', 'nilai_angka' => 30]);

$h11 = t11_hitung($prodi);
$daya = $h11['baris'][0]['per_ta'];
cek('daya tampung TS-1 = 40', ($daya['TS-1'] ?? null) === 40, json_encode($daya));
cek('daya tampung "2023/2024" terbaca sebagai TS-2 = 30',
    ($daya['TS-2'] ?? null) === 30, json_encode($daya));
// ?? tidak bisa dipakai: nilainya memang null, jadi ?? mengembalikan 'x'.
cek('kolom yang belum diisi tetap null',
    array_key_exists('TS', $daya) && $daya['TS'] === null, json_encode($daya));

echo "\n── prodi lain tidak tercampur ──\n";

$prodiLain = (int) qv('SELECT id FROM prodi WHERE aktif = 1 AND id <> ? ORDER BY id LIMIT 1', [$prodi], 0);
if ($prodiLain > 0) {
    mhs_uji($prodiLain, 2020, 'Lulus', 'Akademik', 2023, '2023-10-01');
    $lain = t05_hitung($prodiLain);
    $ini = t05_hitung($prodi);
    cek('prodi lain punya angka sendiri', array_sum($lain['total_per_ta']) === 1,
        (string) array_sum($lain['total_per_ta']));
    cek('angka prodi ini tidak berubah', array_sum($ini['total_per_ta']) === 5,
        (string) array_sum($ini['total_per_ta']));
} else {
    cek('prodi kedua tersedia', false, 'hanya satu prodi aktif');
}

echo "\n── tanpa periode: jangan mengarang tahun ──\n";

ex('UPDATE periode SET aktif = 0 WHERE prodi_id = ?', [$prodi]);
$kosong = t05_hitung($prodi);
cek('tanpa periode: tidak ada kolom', $kosong['kolom_ta'] === [], json_encode($kosong['kolom_ta']));
cek('tanpa periode: tidak ada baris dikarang', $kosong['baris'] === []);
cek('tanpa periode: catatan menjelaskan sebabnya', str_contains($kosong['catatan'], 'Periode'));
ex('UPDATE periode SET aktif = 1 WHERE prodi_id = ?', [$prodi]);
cek('periode dipulihkan, kolom kembali 7', count(t05_hitung($prodi)['kolom_ta']) === 7);

bersihkan_data_uji(db(), true);
cek('data uji bersih setelah uji', (int) qv('SELECT COUNT(*) FROM mahasiswa', [], 0) === 0);
cek('baris Tabel 11 uji ikut bersih', (int) qv('SELECT COUNT(*) FROM baris_tabel', [], 0) === 0);

printf("\n%d lulus, %d gagal\n", $lulus, $gagal);
exit($gagal === 0 ? 0 : 1);
