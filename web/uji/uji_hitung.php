<?php
declare(strict_types=1);

/**
 * Uji fondasi perhitungan: terjemahan label tahun akademik.
 *
 * KENAPA PALING PENTING: hampir semua tabel memakai "TS", "TS-1", "SS-5".
 * Kalau terjemahannya salah atau tidak konsisten (ada yang pakai tahun
 * kalender, ada yang pakai tahun_ts), dua tabel melaporkan angka berbeda untuk
 * tahun yang sama dan asesor menanyakannya. Uji ini mengunci rujukan tunggal:
 * tabel `periode`, tidak pernah date('Y').
 *
 * Jalankan: LAMPTKES_ENV=lokal php web/uji/uji_hitung.php
 */

require_once __DIR__ . '/../system/config.php';
require_once __DIR__ . '/../system/db.php';
require_once __DIR__ . '/../system/hitung.php';

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

$per = ['tahun_ts' => 2025, 'tahun_ss' => 2020];

echo "── terjemahan label ──\n";

cek('TS -> tahun_ts', ta_ke_tahun('TS', $per) === 2025, (string) ta_ke_tahun('TS', $per));
cek('TS-1 -> tahun_ts-1', ta_ke_tahun('TS-1', $per) === 2024);
cek('TS-7 -> tahun_ts-7', ta_ke_tahun('TS-7', $per) === 2018);
cek('SS -> tahun_ss', ta_ke_tahun('SS', $per) === 2020);
cek('SS-5 -> tahun_ss-5', ta_ke_tahun('SS-5', $per) === 2015);
cek('spasi & en-dash diterima', ta_ke_tahun('TS - 2', $per) === 2023 && ta_ke_tahun('TS–2', $per) === 2023);
cek('"2024/2025" -> 2024', ta_ke_tahun('2024/2025', $per) === 2024);
cek('"2024" -> 2024', ta_ke_tahun('2024', $per) === 2024);

// Yang tidak dikenal HARUS null, bukan tahun berjalan. Diam-diam memakai
// tahun berjalan menghasilkan angka yang kelihatan benar tapi salah.
cek('label tak dikenal -> null (bukan tahun berjalan)', ta_ke_tahun('XX', $per) === null);
cek('label kosong -> null', ta_ke_tahun('', $per) === null);

echo "\n── tanpa periode: jangan mengarang ──\n";

cek('TS tanpa periode -> null', ta_ke_tahun('TS', []) === null);
cek('TS-3 tanpa periode -> null', ta_ke_tahun('TS-3', []) === null);
cek('tahun langsung tetap jalan tanpa periode', ta_ke_tahun('2024', []) === 2024);

echo "\n── arah balik (tahun -> label) ──\n";

cek('2025 -> TS', tahun_ke_ta(2025, $per) === 'TS');
cek('2024 -> TS-1', tahun_ke_ta(2024, $per) === 'TS-1');
cek('2020 -> SS', tahun_ke_ta(2020, $per) === 'SS');
cek('2015 -> SS-5', tahun_ke_ta(2015, $per) === 'SS-5');

echo "\n── rentang ──\n";

cek('rentang TS x3 = TS-2,TS-1,TS', rentang_ta('TS', 3) === ['TS-2', 'TS-1', 'TS']);
cek('rentang TS x7 punya 7 elemen', count(rentang_ta('TS', 7)) === 7);
cek('rentang berakhir di TS', end(rentang_ta('TS', 7)) === 'TS');

echo "\n── tahun akademik vs tahun kalender ──\n";

// Instrumen memakai tahun akademik; mahasiswa.tahun_lulus menyimpan tahun
// kalender. Lulus pada TA 2025 bisa berarti kalender 2025 atau 2026.
[$awal, $akhir] = ta_ke_rentang_kalender(2025);
cek('TA 2025 mencakup kalender 2025..2026', $awal === 2025 && $akhir === 2026);

echo "\n── dari DB nyata ──\n";

$p = (int) qv('SELECT id FROM prodi WHERE aktif = 1 ORDER BY id LIMIT 1', [], 0);
$perDb = $p > 0 ? periode_prodi($p) : null;
cek('periode_prodi mengembalikan periode aktif', $perDb !== null);
if ($perDb !== null) {
    cek('tahun_ts periode terbaca', (int) $perDb['tahun_ts'] > 2000, (string) $perDb['tahun_ts']);
    cek('TS dari DB cocok dengan tahun_ts',
        ta_ke_tahun('TS', $perDb) === (int) $perDb['tahun_ts']);
}

// Fungsi dasar harus mengembalikan bentuk yang bisa dipakai, bukan galat,
// walau master masih kosong.
$angkatan = $p > 0 ? mhs_per_angkatan($p) : [];
cek('mhs_per_angkatan mengembalikan array', is_array($angkatan));
$lulusan = $p > 0 ? lulusan_per_tahun($p) : [];
cek('lulusan_per_tahun mengembalikan array', is_array($lulusan));
cek('lulusan_per_tahun tidak menyisakan kunci internal _ipk',
    array_reduce($lulusan, fn($c, $v) => $c || array_key_exists('_ipk', $v), false) === false);

printf("\n%d lulus, %d gagal\n", $lulus, $gagal);
exit($gagal === 0 ? 0 : 1);
