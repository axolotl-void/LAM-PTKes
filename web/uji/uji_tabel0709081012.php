<?php
declare(strict_types=1);

/**
 * Uji Tabel 07, 08, 09, 10, 12.
 *
 * Yang dijaga uji ini (semuanya pernah jadi bug nyata di proyek lain):
 *   1) Pembagian nol -> null, BUKAN 0 dan BUKAN INF/NaN. "0%" dan "tidak bisa
 *      dihitung" adalah dua klaim berbeda; asesor membedakannya.
 *   2) Persen FT = ft_lulus / ft_peserta × 100 — dihitung, tidak disimpan.
 *   3) Tabel 09 menghitung per prodi, hanya status Aktif.
 *   4) Tabel 10 "mahasiswa baru" hanya tahun_masuk yang cocok, apa pun
 *      statusnya sekarang; kolom Input (daya tampung dll.) tetap null.
 *   5) Tabel 12 rata-rata TERTIMBANG, bukan rata-rata 4 kategori.
 *
 * Jalankan: LAMPTKES_ENV=lokal php web/uji/uji_tabel0709081012.php
 */

require_once __DIR__ . '/../system/config.php';
require_once __DIR__ . '/../system/db.php';
require_once __DIR__ . '/../system/hitung.php';
require_once __DIR__ . '/../system/view.php';          // desimal(): null -> '—'
require_once __DIR__ . '/../system/tabel0709081012.php';
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

/** Cari nilai non-finite (INF/NAN) di mana pun dalam hasil hitung. */
function ada_non_finite(mixed $v): bool
{
    if (is_float($v)) {
        return !is_finite($v);
    }
    if (is_array($v)) {
        foreach ($v as $x) {
            if (ada_non_finite($x)) {
                return true;
            }
        }
    }
    return false;
}

/** Baris hasil berdasarkan kolom pertama (label tahun) — memudahkan pencarian. */
function baris_dengan(array $hasil, string $awalan): ?array
{
    foreach ($hasil['baris'] as $b) {
        if (str_starts_with((string) $b['sel'][0], $awalan)) {
            return $b['nilai'];
        }
    }
    return null;
}

function mhs(string $nim, int $prodi, int $tahun, string $jalur, string $status): int
{
    return sisip('mahasiswa', [
        'prodi_id' => $prodi, 'nim' => $nim, 'nama' => 'Uji ' . $nim,
        'tahun_masuk' => $tahun, 'jalur_masuk' => $jalur,
        'tahap' => 'Akademik', 'status' => $status,
    ]);
}

bersihkan_data_uji(db(), true);

$p1 = (int) qv('SELECT id FROM prodi WHERE aktif = 1 ORDER BY id LIMIT 1', [], 0);
$p2 = (int) qv('SELECT id FROM prodi WHERE aktif = 1 AND id <> ? ORDER BY id LIMIT 1', [$p1], 0);
$per1 = $p1 > 0 ? periode_prodi($p1) : null;

echo "── prasyarat ──\n";
cek('dua prodi aktif tersedia', $p1 > 0 && $p2 > 0, "p1=$p1 p2=$p2");
cek('periode aktif prodi 1 ada', $per1 !== null);

if ($per1 === null) {
    printf("\n%d lulus, %d gagal\n", $lulus, $gagal);
    exit(1);
}

$ts = (int) $per1['tahun_ts'];           // 2025

echo "\n── Tabel 07: bulan-bulanan hitung (CBT) ──\n";

// TS-2 sengaja TIDAK diisi: baris harus muncul dengan sel kosong, bukan nol.
sisip('uji_kompetensi', ['prodi_id' => $p1, 'jenis' => 'CBT', 'periode' => $ts - 1,
    'selesai_stase' => 12, 'peserta_total' => 14,
    'ft_peserta' => 10, 'ft_lulus' => 10, 'rt_peserta' => 4, 'rt_lulus' => 2]);
sisip('uji_kompetensi', ['prodi_id' => $p1, 'jenis' => 'CBT', 'periode' => $ts,
    'selesai_stase' => 25, 'peserta_total' => 20,
    'ft_peserta' => 20, 'ft_lulus' => 18, 'rt_peserta' => 0, 'rt_lulus' => 0]);
sisip('uji_kompetensi', ['prodi_id' => $p1, 'jenis' => 'OSCE', 'periode' => $ts,
    'selesai_stase' => 30, 'peserta_total' => 8,
    'ft_peserta' => 8, 'ft_lulus' => 6, 'rt_peserta' => 2, 'rt_lulus' => 1]);

$h7 = t07_hitung($p1, 'CBT');
cek('3 baris: TS-2, TS-1, TS', count($h7['baris']) === 3, 'dapat ' . count($h7['baris']));
$label7 = array_map(fn($b) => (string) $b['sel'][0], $h7['baris']);
cek('urutan label TS-2, TS-1, TS',
    str_starts_with($label7[0], 'TS-2') && str_starts_with($label7[1], 'TS-1')
    && str_starts_with($label7[2], 'TS ('),
    implode(' | ', $label7));
cek('jumlah kolom = jumlah sel tiap baris',
    count(array_filter($h7['baris'], fn($b) => count($b['sel']) === count($h7['kolom']))) === 3);

$tsRow = baris_dengan($h7, 'TS (');
$ts1Row = baris_dengan($h7, 'TS-1');
$ts2Row = baris_dengan($h7, 'TS-2');

// INTI 1: persen = lulus/peserta*100, tidak disimpan.
cek('FT % = 18/20*100 = 90', ($tsRow['ft_persen'] ?? null) === 90.0, var_export($tsRow['ft_persen'] ?? null, true));
cek('FT % bukan hasil pembagian terbalik', ($tsRow['ft_persen'] ?? null) !== 90.0 * 0 + 111.11);

// INTI 2: pembagi nol -> null, bukan 0 / INF / NaN.
cek('RT peserta 0 -> RT % null (bukan 0, bukan INF)',
    array_key_exists('rt_persen', $tsRow) && $tsRow['rt_persen'] === null,
    var_export($tsRow['rt_persen'] ?? 'kunci hilang', true));
cek('tidak ada INF/NaN di hasil Tabel 07', !ada_non_finite($h7));
cek('null tidak dicetak sebagai 0', desimal($tsRow['rt_persen']) === '—', desimal($tsRow['rt_persen']));
cek('0 asli tetap dicetak 0, bukan —', desimal(0) === '0,00', desimal(0));

// INTI 3: total lulusan = FT lulus + RT lulus; persen lulusan / peserta.
cek('total lulusan TS = 18 + 0 = 18', ($tsRow['total_lulusan'] ?? null) === 18, var_export($tsRow['total_lulusan'] ?? null, true));
cek('persen lulusan TS = 18/20*100 = 90', ($tsRow['persen_lulusan'] ?? null) === 90.0, var_export($tsRow['persen_lulusan'] ?? null, true));
cek('FT % TS-1 = 10/10*100 = 100', ($ts1Row['ft_persen'] ?? null) === 100.0, var_export($ts1Row['ft_persen'] ?? null, true));
cek('RT % TS-1 = 2/4*100 = 50', ($ts1Row['rt_persen'] ?? null) === 50.0, var_export($ts1Row['rt_persen'] ?? null, true));
cek('total lulusan TS-1 = 10 + 2 = 12', ($ts1Row['total_lulusan'] ?? null) === 12, var_export($ts1Row['total_lulusan'] ?? null, true));

echo "\n── Tabel 07: tahun kosong tetap kosong ──\n";
cek('TS-2 tanpa data: semua angka null, bukan 0',
    $ts2Row['ft_peserta'] === null && $ts2Row['ft_lulus'] === null
    && $ts2Row['ft_persen'] === null && $ts2Row['total_lulusan'] === null
    && $ts2Row['selesai_stase'] === null,
    json_encode($ts2Row));
cek('TS-2 tetap tampil sebagai baris (bukan dihilangkan)', $ts2Row !== null);

echo "\n── Tabel 08: OSCE, bukan campuran CBT ──\n";
$h8 = t08_hitung($p1);
$oRow = baris_dengan($h8, 'TS (');
cek('OSCE TS memakai peserta OSCE (8), bukan CBT (20)', ($oRow['peserta_total'] ?? null) === 8,
    var_export($oRow['peserta_total'] ?? null, true));
cek('OSCE FT % = 6/8*100 = 75', ($oRow['ft_persen'] ?? null) === 75.0, var_export($oRow['ft_persen'] ?? null, true));
cek('OSCE RT % = 1/2*100 = 50', ($oRow['rt_persen'] ?? null) === 50.0, var_export($oRow['rt_persen'] ?? null, true));
cek('OSCE TS-1 kosong (tidak ada baris OSCE 2024)',
    // `?? 'x'` salah di sini: null coalescing memperlakukan null sebagai tidak
    // ada, jadi null asli lolos jadi 'x'. Pakai array_key_exists.
    ($r = baris_dengan($h8, 'TS-1')) !== null
    && array_key_exists('ft_peserta', $r) && $r['ft_peserta'] === null);

echo "\n── Tabel 07 tanpa periode ──\n";
// Prodi tanpa periode tidak boleh mengarang tahun.
$pTanpa = (int) qv('SELECT id FROM prodi ORDER BY id LIMIT 1', [], 0);
$perSimpan = q('SELECT * FROM periode WHERE prodi_id = ?', [$pTanpa]);
ex('DELETE FROM periode WHERE prodi_id = ?', [$pTanpa]);
$h7np = t07_hitung($pTanpa);
cek('tanpa periode: label TS tidak diterjemahkan ke tahun',
    str_contains((string) $h7np['baris'][0]['sel'][0], '(') === false,
    (string) $h7np['baris'][0]['sel'][0]);
cek('tanpa periode: angka null semua', $h7np['baris'][2]['nilai']['ft_persen'] === null);
cek('tanpa periode: catatan menjelaskan sebabnya', str_contains($h7np['catatan'], 'Periode akreditasi'));
foreach ($perSimpan as $p) {
    ex('INSERT INTO periode (id, prodi_id, tahun_ts, tahun_ss, aktif) VALUES (?,?,?,?,?)',
        [$p['id'], $p['prodi_id'], $p['tahun_ts'], $p['tahun_ss'], $p['aktif']]);
}

echo "\n── Tabel 09: per prodi, hanya Aktif ──\n";
mhs('UJI0901', $p1, $ts, 'Reguler', 'Aktif');
mhs('UJI0902', $p1, $ts - 1, 'Reguler', 'Aktif');
mhs('UJI0903', $p1, $ts - 2, 'Reguler', 'Aktif');
mhs('UJI0904', $p1, $ts - 1, 'Transfer', 'Aktif');
mhs('UJI0905', $p1, $ts, 'Asing', 'Aktif');
mhs('UJI0906', $p1, $ts - 2, 'Reguler', 'Lulus');   // lulus: tidak dihitung
mhs('UJI0907', $p1, $ts, 'Reguler', 'Cuti');        // cuti: tidak dihitung
mhs('UJI0908', $p2, $ts, 'Reguler', 'Aktif');
mhs('UJI0909', $p2, $ts, 'Reguler', 'Aktif');

$h9 = t09_hitung();
$perProdi = [];
foreach ($h9['baris'] as $b) {
    $perProdi[$b['nilai']['program_studi']] = $b['nilai'];
}
$nama1 = (string) qv('SELECT nama_ps FROM prodi WHERE id = ?', [$p1]);
$nama2 = (string) qv('SELECT nama_ps FROM prodi WHERE id = ?', [$p2]);

cek('satu baris per prodi aktif (2)', count($h9['baris']) === 2, 'dapat ' . count($h9['baris']));
cek('prodi 1: reguler 3 (Lulus & Cuti tidak ikut)',
    ($perProdi[$nama1]['mahasiswa_reguler'] ?? -1) === 3, json_encode($perProdi[$nama1] ?? null));
cek('prodi 1: non reguler (Transfer) 1', ($perProdi[$nama1]['non_reguler'] ?? -1) === 1);
cek('prodi 1: asing 1', ($perProdi[$nama1]['asing'] ?? -1) === 1);
cek('prodi 2: reguler 2, transfer 0, asing 0',
    ($perProdi[$nama2]['mahasiswa_reguler'] ?? -1) === 2
    && ($perProdi[$nama2]['non_reguler'] ?? -1) === 0
    && ($perProdi[$nama2]['asing'] ?? -1) === 0, json_encode($perProdi[$nama2] ?? null));
cek('Tabel 09 tidak menerima prodiId (semua prodi UPPS)',
    (new ReflectionFunction('t09_hitung'))->getNumberOfParameters() === 0);

echo "\n── Tabel 10: mahasiswa baru hanya tahun_masuk yang cocok ──\n";
$h10 = t10_hitung($p1);
cek('5 baris TS-4..TS', count($h10['baris']) === 5, 'dapat ' . count($h10['baris']));
cek('urutan dimulai TS-4 dan berakhir TS',
    str_starts_with((string) $h10['baris'][0]['sel'][0], 'TS-4')
    && str_starts_with((string) $h10['baris'][4]['sel'][0], 'TS ('));

$rTs = baris_dengan($h10, 'TS (');
$rTs1 = baris_dengan($h10, 'TS-1');
$rTs2 = baris_dengan($h10, 'TS-2');
$rTs3 = baris_dengan($h10, 'TS-3');

// Aktif prodi 1: 0901 (Reg, 2025), 0902 (Reg, 2024), 0903 (Reg, 2023),
// 0904 (Trf, 2024), 0905 (Asing, 2025) -> 3 reguler, 1 transfer, 1 asing.
// "Mahasiswa baru" = tahun_masuk cocok, APA PUN status sekarang: 0907 (Reguler,
// 2025, kini Cuti) tetap pernah jadi mahasiswa baru 2025.
cek('TS baru reguler 2 (termasuk yang kini Cuti)', ($rTs['mahasiswa_baru_reguler'] ?? -1) === 2,
    json_encode($rTs));
cek('TS baru transfer 0', ($rTs['mahasiswa_baru_transfer'] ?? -1) === 0);
cek('TS baru asing 1', ($rTs['mahasiswa_baru_asing'] ?? -1) === 1);
cek('TS-1 baru reguler 1 & transfer 1', ($rTs1['mahasiswa_baru_reguler'] ?? -1) === 1
    && ($rTs1['mahasiswa_baru_transfer'] ?? -1) === 1, json_encode($rTs1));
cek('TS-1 TIDAK memuat mahasiswa baru tahun TS', ($rTs1['mahasiswa_baru_asing'] ?? -1) === 0);
cek('TS-2 baru reguler 2 (termasuk yang kini Lulus — pernah jadi maba)',
    ($rTs2['mahasiswa_baru_reguler'] ?? -1) === 2, json_encode($rTs2));
cek('TS-3 tanpa mahasiswa baru = 0, bukan null', ($rTs3['mahasiswa_baru_reguler'] ?? null) === 0,
    json_encode($rTs3));

cek('total aktif reguler 3 di setiap baris tahun',
    ($rTs['total_mahasiswa_aktif_reguler'] ?? -1) === 3
    && ($rTs3['total_mahasiswa_aktif_reguler'] ?? -1) === 3);
cek('total aktif transfer 1 & asing 1',
    ($rTs['total_mahasiswa_aktif_transfer'] ?? -1) === 1
    && ($rTs['total_mahasiswa_aktif_asing'] ?? -1) === 1);

// INTI: kolom Input tidak dikarang.
cek('daya tampung null (tidak ada masternya, tidak dikarang)',
    $rTs['daya_tampung'] === null && $rTs3['daya_tampung'] === null);
cek('calon ikut seleksi null', $rTs['calon_ikut_seleksi'] === null);
cek('calon lulus seleksi null', $rTs['calon_lulus_seleksi'] === null);
cek('daya tampung tampil — bukan 0', desimal($rTs['daya_tampung']) === '—');
cek('catatan menyebut pengisian manual', str_contains($h10['catatan'], 'DIISI MANUAL'));

echo "\n── Tabel 12: rata-rata TERTIMBANG ──\n";
sisip('survei', ['prodi_id' => $p1, 'jenis' => 'Mahasiswa', 'aspek' => 'Dosen',
    'periode' => $ts, 'skor_1' => 1, 'skor_2' => 2, 'skor_3' => 3, 'skor_4' => 4]);
sisip('survei', ['prodi_id' => $p1, 'jenis' => 'Mahasiswa', 'aspek' => 'Tenaga kependidikan',
    'periode' => $ts, 'skor_1' => 0, 'skor_2' => 0, 'skor_3' => 0, 'skor_4' => 10]);
sisip('survei', ['prodi_id' => $p1, 'jenis' => 'Mahasiswa', 'aspek' => 'Sarana',
    'periode' => $ts, 'skor_1' => 0, 'skor_2' => 0, 'skor_3' => 0, 'skor_4' => 0]);
// Pengguna Lulusan tidak boleh masuk Tabel 12.
sisip('survei', ['prodi_id' => $p1, 'jenis' => 'Pengguna Lulusan', 'aspek' => 'Lulusan',
    'periode' => $ts, 'skor_1' => 9, 'skor_2' => 0, 'skor_3' => 0, 'skor_4' => 0]);

$h12 = t12_hitung($p1);
$perAspek = [];
foreach ($h12['baris'] as $b) {
    $perAspek[$b['nilai']['aspek']] = $b['nilai'];
}

// (1*1 + 2*2 + 3*3 + 4*4) / 10 = 30/10 = 3,00 — sengaja dibuat 3,00 karena
// rata-rata TIDAK tertimbang dari 1,2,3,4 juga 2,5, jadi tertukar ketahuan.
cek('Dosen: (1·1+2·2+3·3+4·4)/10 = 3,00',
    abs(($perAspek['Dosen']['rata_tertimbang'] ?? 0) - 3.0) < 1e-9,
    var_export($perAspek['Dosen']['rata_tertimbang'] ?? null, true));
cek('bukan rata-rata 4 kategori (2,50)',
    ($perAspek['Dosen']['rata_tertimbang'] ?? null) !== 2.5);
cek('Tenaga kependidikan: (0+0+0+40)/10 = 4,00',
    abs(($perAspek['Tenaga kependidikan']['rata_tertimbang'] ?? 0) - 4.0) < 1e-9,
    var_export($perAspek['Tenaga kependidikan']['rata_tertimbang'] ?? null, true));
cek('total 0 responden -> null, bukan 0',
    array_key_exists('Sarana', $perAspek) && $perAspek['Sarana']['rata_tertimbang'] === null,
    var_export($perAspek['Sarana']['rata_tertimbang'] ?? 'kunci hilang', true));
cek('rata null dicetak — bukan 0,00', desimal($perAspek['Sarana']['rata_tertimbang']) === '—');
cek('hanya jenis Mahasiswa yang masuk', !isset($perAspek['Lulusan']),
    implode(',', array_keys($perAspek)));
cek('tidak ada INF/NaN di Tabel 12', !ada_non_finite($h12));

echo "\n── bentuk seragam untuk tampilan ──\n";
foreach (['07' => $h7, '08' => $h8, '09' => $h9, '10' => $h10, '12' => $h12] as $kode => $h) {
    $ok = isset($h['judul'], $h['kolom'], $h['baris'], $h['catatan'], $h['kosong'])
        && is_array($h['kolom']) && is_array($h['baris'])
        && count(array_filter($h['baris'], fn($b) => count($b['sel']) === count($h['kolom'])))
           === count($h['baris']);
    cek("tabel $kode: kolom/baris/catatan/kosong lengkap & sel sepanjang kolom", $ok);
}

// Hitungan tidak boleh menyimpan apa pun: jalan dua kali harus sama.
cek('t07 deterministik (dihitung, tidak disimpan)',
    array_column(t07_hitung($p1, 'CBT')['baris'], 'sel') === array_column($h7['baris'], 'sel'));

bersihkan_data_uji(db(), true);

cek('data uji bersih setelah uji',
    (int) qv('SELECT COUNT(*) FROM mahasiswa', [], 0) === 0
    && (int) qv('SELECT COUNT(*) FROM uji_kompetensi', [], 0) === 0
    && (int) qv('SELECT COUNT(*) FROM survei', [], 0) === 0);
cek('periode dipulihkan', (int) qv('SELECT COUNT(*) FROM periode WHERE prodi_id = ?', [$pTanpa], 0) === 1);

printf("\n%d lulus, %d gagal\n", $lulus, $gagal);
exit($gagal === 0 ? 0 : 1);
