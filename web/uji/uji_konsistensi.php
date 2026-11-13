<?php
declare(strict_types=1);

/**
 * Uji modul konsistensi. Tanpa HTTP: panggil fungsi langsung.
 *
 * Yang dijaga uji ini adalah bug prototipe lama: aturan berstatus 'lulus'
 * selalu menampilkan Selisih 0, termasuk saat membandingkan 7 vs 6. Karena itu
 * uji di bawah memeriksa selisih BUKAN nol pada data yang memang berbeda,
 * memeriksa 'belum' (bukan 'lulus') untuk aturan tanpa data, dan memeriksa
 * bahwa selisih R06 lahir dari data — bukan konstanta.
 *
 * Data uji dibuat di prodi/periode sendiri supaya tidak bertabrakan dengan
 * agent lain, lalu dihapus di akhir.
 *
 * Jalankan: php web/uji/uji_konsistensi.php
 */

ob_start();
putenv('LAMPTKES_ENV=lokal');
require_once __DIR__ . '/../modul/konsistensi/fungsi.php';

$gagal = 0;
function cek(string $nama, bool $ok, string $detail = ''): void
{
    global $gagal;
    echo ($ok ? '  ok  ' : '  GAGAL ') . $nama . ($detail !== '' ? " — $detail" : '') . "\n";
    if (!$ok) {
        $gagal++;
    }
}

/** Cari hasil satu aturan. */
function aturan(array $hasil, string $kode): array
{
    foreach ($hasil as $h) {
        if ($h['kode'] === $kode) {
            return $h;
        }
    }
    return [];
}

/** Tambah baris_tabel + sel sekaligus. Balik baris_id. */
function isi_sel(string $tabel, int $prodi, int $periode, array $sel, int $urut = 1): int
{
    $id = sisip('baris_tabel', [
        'prodi_id' => $prodi, 'periode_id' => $periode, 'tabel_kode' => $tabel, 'urut' => $urut,
    ]);
    foreach ($sel as $kunci => $nilai) {
        $angka = is_numeric($nilai) ? $nilai : null;
        ex('INSERT INTO sel (baris_id, field_kunci, nilai_angka, nilai_teks) VALUES (?,?,?,?)',
            [$id, $kunci, $angka, (string) $nilai]);
    }
    return $id;
}

/* ── siapkan prodi + periode sendiri ─────────────────────────────── */
$upps = (int) qv('SELECT id FROM upps ORDER BY id LIMIT 1');
$prodi = sisip('prodi', [
    'upps_id' => $upps, 'nama_ps' => 'ZZ Uji Konsistensi', 'jenjang' => 'Sarjana',
    'bidang' => 'Uji', 'punya_tahap_profesi' => 1, 'aktif' => 1,
]);
$periode = sisip('periode', ['prodi_id' => $prodi, 'tahun_ts' => 2025, 'tahun_ss' => 2021, 'aktif' => 1]);

$bersihkan = static function () use ($prodi, $periode): void {
    ex('DELETE s FROM sel s JOIN baris_tabel b ON b.id = s.baris_id WHERE b.prodi_id = ?', [$prodi]);
    ex('DELETE FROM baris_tabel WHERE prodi_id = ?', [$prodi]);
    ex('DELETE FROM mahasiswa WHERE prodi_id = ?', [$prodi]);
    ex('DELETE FROM mata_kuliah WHERE prodi_id = ?', [$prodi]);
    ex('DELETE FROM wahana WHERE prodi_id = ?', [$prodi]);
    ex('DELETE FROM periode WHERE id = ?', [$periode]);
    ex('DELETE FROM prodi WHERE id = ?', [$prodi]);
};

try {
    /* ── (d) 10 aturan, angka tutup ──────────────────────────────── */
    $semua = kn_aturan();
    cek('10 aturan aktif terbaca', count($semua) === 10, 'dapat ' . count($semua));

    $hasil = konsistensi_jalankan($prodi, $periode);
    cek('10 hasil evaluasi', count($hasil) === 10, 'dapat ' . count($hasil));

    $r = konsistensi_ringkas($hasil);
    cek('(d) lulus+gagal+belum = total aturan', $r['lulus'] + $r['gagal'] + $r['belum'] === $r['total'],
        json_encode($r));
    cek('(d) total = 10', $r['total'] === 10, json_encode($r));

    /* ── (a) tanpa data = 'belum', selisih null ──────────────────── */
    cek('semua aturan menyebut tabel yang harus diperbaiki',
        array_filter($hasil, fn($h) => $h['perbaikan'] === '') === [],
        implode(',', array_column(array_filter($hasil, fn($h) => $h['perbaikan'] === ''), 'kode')));
    foreach (['R01', 'R02', 'R03', 'R05', 'R06'] as $kode) {
        $h = aturan($hasil, $kode);
        cek("(a) $kode tanpa data -> belum", ($h['status'] ?? '') === 'belum', ($h['status'] ?? '?'));
        cek("(a) $kode selisih null, bukan 0", array_key_exists('selisih', $h) && $h['selisih'] === null,
            array_key_exists('selisih', $h) ? 'null' : 'kunci hilang');
    }
    $h = aturan($hasil, 'R09');
    cek("(a) R09 tanpa mata ajar -> belum", $h['status'] === 'belum', $h['status']);
    $h = aturan($hasil, 'R08');
    cek("(a) R08 tanpa wahana -> belum", $h['status'] === 'belum', $h['status']);

    /* ── (b) R10 gagal saat tabel khusus profesi belum lengkap ───── */
    $h = aturan($hasil, 'R10');
    $khusus = kn_tabel_profesi();
    cek('R10: 6 tabel khusus profesi dari DB', count($khusus) === 6, implode(',', $khusus));
    cek('(b) R10 gagal saat belum lengkap', $h['status'] === 'gagal', $h['status']);
    cek('(b) R10 a=0 dari ' . count($khusus) . ' tabel', $h['a'] === 0.0 && $h['b'] === (float) count($khusus),
        json_encode([$h['a'], $h['b']]));
    cek('(b) R10 selisih dihitung, bukan 0', $h['selisih'] === -(float) count($khusus), var_export($h['selisih'], true));
    cek('(b) R10 menyebut tabel yang harus diperbaiki', $h['perbaikan'] !== '', $h['perbaikan']);

    /* ── R04: 7 vs 6 harus berselisih 1, bukan 0 ────────────────── */
    isi_sel('10', $prodi, $periode, ['total_mahasiswa_aktif_reguler' => 7]);
    for ($i = 1; $i <= 6; $i++) {
        sisip('mahasiswa', ['prodi_id' => $prodi, 'nim' => 'UJI' . $i, 'nama' => 'Mhs Uji ' . $i,
            'tahun_masuk' => 2024, 'jalur_masuk' => 'Reguler', 'tahap' => 'Akademik', 'status' => 'Aktif']);
    }
    $h = aturan(konsistensi_jalankan($prodi, $periode), 'R04');
    cek('R04 7 vs 6 -> gagal', $h['status'] === 'gagal', $h['status']);
    cek('R04 7 vs 6 -> selisih 1 (bukan 0)', $h['selisih'] === 1.0, var_export($h['selisih'], true));
    cek('R04 a=7 b=6 dari data', $h['a'] === 7.0 && $h['b'] === 6.0, json_encode([$h['a'], $h['b']]));

    // satu mahasiswa lagi -> cocok -> lulus dan selisih 0 KARENA cocok
    sisip('mahasiswa', ['prodi_id' => $prodi, 'nim' => 'UJI7', 'nama' => 'Mhs Uji 7',
        'tahun_masuk' => 2024, 'jalur_masuk' => 'Reguler', 'tahap' => 'Akademik', 'status' => 'Aktif']);
    $h = aturan(konsistensi_jalankan($prodi, $periode), 'R04');
    cek('R04 7 vs 7 -> lulus', $h['status'] === 'lulus', $h['status']);
    cek('R04 7 vs 7 -> selisih 0 karena memang cocok', $h['selisih'] === 0.0, var_export($h['selisih'], true));

    /* ── (e) R06: selisih dari data, bukan konstanta ─────────────── */
    // Tabel 34 = satu baris per jenis penggunaan, tiap sel memuat 3 angka
    // (TS-2 / TS-1 / TS). 100% dihitung per tahun dari jumlah antar baris.
    $b1 = isi_sel('34', $prodi, $periode, ['persentase_ts_2_ts_1_ts' => '60 / 55 / 50']);
    $b2 = isi_sel('34', $prodi, $periode, ['persentase_ts_2_ts_1_ts' => '40 / 45 / 50'], 2);
    $h = aturan(konsistensi_jalankan($prodi, $periode), 'R06');
    cek('(e) R06 jumlah antar baris per tahun = 100', $h['a'] === 100.0, var_export($h['a'], true));
    cek('(e) R06 100% -> lulus', $h['status'] === 'lulus', $h['status']);
    cek('(e) R06 100% -> selisih 0 karena cocok', $h['selisih'] === 0.0, var_export($h['selisih'], true));

    // satu tahun melenceng (TS jadi 50+40=90) -> harus gagal, bukan tetap lulus
    ex('UPDATE sel SET nilai_teks = ? WHERE baris_id = ? AND field_kunci = ?',
        ['40 / 45 / 40', $b2, 'persentase_ts_2_ts_1_ts']);
    $h = aturan(konsistensi_jalankan($prodi, $periode), 'R06');
    cek('(e) R06 satu tahun 90% -> gagal', $h['status'] === 'gagal', $h['status']);
    cek('(e) R06 selisih -10 dihitung dari data', $h['selisih'] === -10.0, var_export($h['selisih'], true));
    cek('(e) R06 menyebut tahun yang melenceng', str_contains($h['label_a'], 'TS'), $h['label_a']);

    /* ── (c) konsistensi_blokir() daftar aturan blokir yang gagal ── */
    $blokir = konsistensi_blokir($prodi, $periode);
    $kodeBlokir = array_column($blokir, 'kode');
    cek('(c) R06 gagal masuk daftar blokir', in_array('R06', $kodeBlokir, true), implode(',', $kodeBlokir));
    cek('(c) hanya tingkat blokir yang masuk',
        array_filter($blokir, fn($h) => $h['tingkat'] !== 'blokir') === [], 'ada non-blokir');
    cek('(c) semua yang masuk berstatus gagal',
        array_filter($blokir, fn($h) => $h['status'] !== 'gagal') === [], 'ada non-gagal');
    cek('(c) R02 (peringatan) tidak memblokir', !in_array('R02', $kodeBlokir, true), implode(',', $kodeBlokir));

    // perbaiki R06 -> R06 keluar dari daftar blokir
    ex('UPDATE sel SET nilai_teks = ? WHERE baris_id = ? AND field_kunci = ?',
        ['40 / 45 / 50', $b2, 'persentase_ts_2_ts_1_ts']);
    $kodeBlokir = array_column(konsistensi_blokir($prodi, $periode), 'kode');
    cek('(c) R06 lulus -> tidak memblokir lagi', !in_array('R06', $kodeBlokir, true), implode(',', $kodeBlokir));
    // R10 bertingkat 'wajib' (bukan blokir): gagal TIDAK mematikan ekspor,
    // tapi tetap wajib tampil sebagai gagal di halaman. Uji batas ini penting
    // supaya 'wajib' tidak diam-diam diperlakukan sama dengan 'blokir'.
    cek('(c) R10 gagal tapi tingkat wajib -> tidak memblokir',
        !in_array('R10', $kodeBlokir, true), implode(',', $kodeBlokir));
    cek('(c) R10 tetap berstatus gagal di daftar aturan',
        aturan(konsistensi_jalankan($prodi, $periode), 'R10')['status'] === 'gagal');

    /* ── R08: wahana vs dokumen kerja sama ──────────────────────── */
    sisip('wahana', ['prodi_id' => $prodi, 'nama' => 'RS Uji A', 'dokumen_kerja_sama' => 'MoU-001.pdf']);
    sisip('wahana', ['prodi_id' => $prodi, 'nama' => 'RS Uji B', 'dokumen_kerja_sama' => null]);
    $h = aturan(konsistensi_jalankan($prodi, $periode), 'R08');
    cek('R08 2 wahana vs 1 dokumen -> gagal', $h['status'] === 'gagal', $h['status']);
    cek('R08 selisih 1 dihitung', $h['selisih'] === 1.0, var_export($h['selisih'], true));
    cek('R08 perbaikan menunjuk tabel', $h['perbaikan'] !== '', $h['perbaikan']);

    /* ── R09: mata ajar vs master MK ────────────────────────────── */
    sisip('mata_kuliah', ['prodi_id' => $prodi, 'kode_mk' => 'BDN101', 'nama_mk' => 'Asuhan Kehamilan']);
    isi_sel('16', $prodi, $periode, ['mata_ajar_yang_diampu' => 'Asuhan Kehamilan']);
    isi_sel('16', $prodi, $periode, ['mata_ajar_yang_diampu' => 'MK Tidak Ada'], 2);
    $h = aturan(konsistensi_jalankan($prodi, $periode), 'R09');
    cek('R09 2 mata ajar vs 1 cocok -> gagal', $h['status'] === 'gagal', $h['status']);
    cek('R09 selisih 1 dihitung', $h['selisih'] === 1.0, var_export($h['selisih'], true));

    /* ── R02: beda 7 vs 6 pada aturan lain tetap jujur ──────────── */
    isi_sel('03', $prodi, $periode, ['jumlah_lulusan_reguler' => 7]);
    isi_sel('05', $prodi, $periode, ['jumlah_lulusan_s_d_ts' => 6]);
    $h = aturan(konsistensi_jalankan($prodi, $periode), 'R02');
    cek('R02 7 vs 6 -> gagal, selisih 1', $h['status'] === 'gagal' && $h['selisih'] === 1.0,
        json_encode([$h['status'], $h['selisih']]));

    /* ── R10 lulus setelah semua tabel khusus profesi lengkap ───── */
    foreach ($khusus as $kode) {
        $fields = q('SELECT kunci FROM spec_field WHERE tabel_kode = ? AND asal = ?', [$kode, 'Input']);
        $sel = [];
        foreach ($fields as $f) {
            $sel[(string) $f['kunci']] = '1';
        }
        isi_sel($kode, $prodi, $periode, $sel);
    }
    $hasil = konsistensi_jalankan($prodi, $periode);
    $h = aturan($hasil, 'R10');
    cek('R10 lengkap -> lulus', $h['status'] === 'lulus', $h['status']);
    cek('R10 lengkap -> a=b=6, selisih 0 karena cocok',
        $h['a'] === 6.0 && $h['b'] === 6.0 && $h['selisih'] === 0.0, json_encode([$h['a'], $h['b'], $h['selisih']]));
    cek('R10 lulus -> tidak lagi memblokir', !in_array('R10', array_column(konsistensi_blokir($prodi, $periode), 'kode'), true));

    /* ── angka tutup setelah data terisi ────────────────────────── */
    $r = konsistensi_ringkas($hasil);
    cek('(d) angka tutup tetap = 10 setelah data terisi',
        $r['lulus'] + $r['gagal'] + $r['belum'] === 10, json_encode($r));
    cek('(d) hitungan cocok dengan isi daftar',
        $r['gagal'] === count(array_filter($hasil, fn($h) => $h['status'] === 'gagal'))
        && $r['lulus'] === count(array_filter($hasil, fn($h) => $h['status'] === 'lulus'))
        && $r['belum'] === count(array_filter($hasil, fn($h) => $h['status'] === 'belum')), json_encode($r));

    /* ── kasus batas: periode null ──────────────────────────────── */
    $hasilNull = konsistensi_jalankan($prodi, null);
    cek('periode null -> semua belum', count(array_filter($hasilNull, fn($h) => $h['status'] === 'belum')) === 10,
        json_encode(array_column($hasilNull, 'status')));
    cek('periode null -> semua selisih null',
        array_filter($hasilNull, fn($h) => $h['selisih'] !== null) === []);
    cek('periode null -> blokir kosong (tidak ada yang dihitung)',
        konsistensi_blokir() === [], 'harusnya kosong saat prodi belum dipilih');
} finally {
    $bersihkan();
}

// Bukti bersih: tidak ada sisa data uji.
$sisa = (int) qv('SELECT COUNT(*) FROM prodi WHERE nama_ps = ?', ['ZZ Uji Konsistensi'], 0);
cek('data uji dibersihkan', $sisa === 0, "sisa $sisa");
cek('tidak ada baris_tabel yatim',
    (int) qv('SELECT COUNT(*) FROM baris_tabel b LEFT JOIN prodi p ON p.id = b.prodi_id WHERE p.id IS NULL', [], 0) === 0);

echo $gagal === 0 ? "\nSEMUA UJI LULUS\n" : "\n$gagal UJI GAGAL\n";
exit($gagal === 0 ? 0 : 1);
