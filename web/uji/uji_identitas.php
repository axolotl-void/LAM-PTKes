<?php
/**
 * Uji modul Identitas (F-01) + Periode (F-02). Tanpa server HTTP.
 *
 * Yang dibuktikan:
 *  (1) tahun absolut disimpan, label dihitung — ganti tahun_ts menggeser SEMUA
 *      label TS tapi jumlah baris_tabel dan sel TIDAK berubah
 *  (2) punya_tahap_profesi = 0 membuat tabel 30 tidak berlaku, wajib 38 -> 32
 *  (3) label_ts / label_ss berformat benar untuk beberapa nilai
 *
 * Jalankan: php web/uji/uji_identitas.php
 * Data dikembalikan ke kondisi awal di akhir, termasuk kalau ada uji gagal.
 */
declare(strict_types=1);

ob_start();
putenv('LAMPTKES_ENV=lokal');
require_once __DIR__ . '/../system/layout.php';
require_once __DIR__ . '/../modul/identitas/bantu.php';

$gagal = 0;
function cek(string $nama, bool $ok, string $detail = ''): void
{
    global $gagal;
    echo ($ok ? '  ok  ' : '  GAGAL ') . $nama . ($detail !== '' ? " — $detail" : '') . "\n";
    if (!$ok) {
        $gagal++;
    }
}

$prodi = (int) qv('SELECT id FROM prodi ORDER BY id LIMIT 1');
$awalProdi = q1('SELECT jenjang, punya_tahap_profesi, nama_profesi_lulusan FROM prodi WHERE id = ?', [$prodi]);
$awalPeriode = q1('SELECT id, tahun_ts, tahun_ss, aktif FROM periode WHERE prodi_id = ? ORDER BY tahun_ts DESC LIMIT 1', [$prodi]);

/*
 * Seluruh uji yang MENGUBAH data berjalan di satu transaksi yang di-rollback.
 * Dua alasan:
 *   1. data harus kembali ke kondisi awal apa pun yang terjadi (termasuk fatal
 *      error di tengah uji) — DELETE manual bisa terlewat, ROLLBACK tidak;
 *   2. agent lain bekerja paralel di DB yang sama; snapshot transaksi membuat
 *      hitungan COUNT(*) uji ini tidak ikut bergoyang karena tulisan mereka.
 */
db()->beginTransaction();
$selesai = false;
register_shutdown_function(function () use (&$selesai): void {
    if (!$selesai && db()->inTransaction()) {
        db()->rollBack();
    }
});


/* ── (3) format label ───────────────────────────────────────────── */
cek("label_ts(2025,1) = 'TS-1 (2024/2025)'", label_ts(2025, 1) === 'TS-1 (2024/2025)', label_ts(2025, 1));
cek("label_ts(2025,0) = 'TS-0 (2025/2026)'", label_ts(2025, 0) === 'TS-0 (2025/2026)', label_ts(2025, 0));
cek("label_ts(2025,6) = 'TS-6 (2019/2020)'", label_ts(2025, 6) === 'TS-6 (2019/2020)', label_ts(2025, 6));
cek("label_ts(2026,2) = 'TS-2 (2024/2025)'", label_ts(2026, 2) === 'TS-2 (2024/2025)', label_ts(2026, 2));
cek("label_ss(2020,1) = 'SS-1 (2020/2021)'", label_ss(2020, 1) === 'SS-1 (2020/2021)', label_ss(2020, 1));
cek("label_ss(2020,16) = 'SS-16 (2035/2036)'", label_ss(2020, 16) === 'SS-16 (2035/2036)', label_ss(2020, 16));
cek('label TS tidak pernah sama untuk mundur berbeda',
    label_ts(2025, 0) !== label_ts(2025, 1) && label_ts(2025, 6) !== label_ts(2025, 5));

/* ── hitungan wajib dari tabel_dukung, bukan angka yang diketik ──── */
$W1 = wajib_tabel($prodi);
cek('jumlah tabel dibaca dari tabel_dukung', $W1['total'] === (int) qv('SELECT COUNT(*) FROM tabel_dukung'),
    (string) $W1['total']);
cek('6 tabel khusus profesi = 4,6,7,8,11,30',
    $W1['khusus'] === [4, 6, 7, 8, 11, 30], implode(',', $W1['khusus']));
cek('prodi berprofesi: wajib = semua tabel', (int) $awalProdi['punya_tahap_profesi'] === 1 && $W1['wajib'] === 38,
    (string) $W1['wajib']);

/* ── (2) tanpa tahap profesi: tabel 30 na, wajib 32 ─────────────── */
ex('UPDATE prodi SET punya_tahap_profesi = 0 WHERE id = ?', [$prodi]);
$W2 = wajib_tabel($prodi);
cek('punya_tahap_profesi=0 -> wajib 32', $W2['wajib'] === 32, (string) $W2['wajib']);
cek('selisih wajib = jumlah tabel khusus', $W1['wajib'] - $W2['wajib'] === count($W2['khusus']));

$perAktif = periode_aktif($prodi);
$s30 = status_satu_tabel('30', $prodi, (int) $perAktif['id']);
cek('tabel 30 jadi tidak berlaku', $s30['status'] === 'na', json_encode($s30));
$s05 = status_satu_tabel('05', $prodi, (int) $perAktif['id']);
cek('tabel 05 tetap berlaku', berlaku_untuk_prodi('05', $prodi) === true && $s05['status'] !== 'na',
    json_encode($s05));
foreach ([4, 6, 7, 8, 11, 30] as $nomor) {
    $kode = sprintf('%02d', $nomor);
    cek("tabel $kode tidak berlaku tanpa profesi", berlaku_untuk_prodi($kode, $prodi) === false);
}
// Wajib 38 lagi setelah dinyalakan — bukti status mengikuti data, bukan disimpan.
ex('UPDATE prodi SET punya_tahap_profesi = 1 WHERE id = ?', [$prodi]);
cek('dinyalakan lagi -> wajib 38', wajib_tabel($prodi)['wajib'] === 38);

/* ── (1) ganti tahun: label bergeser, data tetap ────────────────── */
$perId = (int) $perAktif['id'];
$ts0 = (int) $perAktif['tahun_ts'];

// Isi data contoh supaya "tidak berubah" punya sesuatu untuk dibuktikan.
// urut = MAX+1: agent lain bisa saja sedang mengisi tabel/prodi/periode yang sama,
// dan uq_baris (periode,tabel,urut) akan menolak nomor yang sudah dipakai.
$urut = (int) qv('SELECT COALESCE(MAX(urut), 0) + 1 FROM baris_tabel WHERE periode_id = ? AND tabel_kode = ?',
    [$perId, '05'], 1);
$bid = sisip('baris_tabel', ['prodi_id' => $prodi, 'periode_id' => $perId, 'tabel_kode' => '05', 'urut' => $urut]);
$field = q("SELECT kunci FROM spec_field WHERE tabel_kode = '05' AND asal = 'Input'");
if (!$field) {
    $field = q("SELECT kunci FROM spec_field WHERE tabel_kode = '01' AND asal = 'Input' LIMIT 2");
}
foreach ($field as $i => $f) {
    sisip('sel', ['baris_id' => $bid, 'field_kunci' => $f['kunci'],
                  'nilai_angka' => $i === 0 ? 12 : 34, 'nilai_teks' => 'x']);
}

$hitungBaris = fn(): int => (int) qv('SELECT COUNT(*) FROM baris_tabel WHERE prodi_id = ?', [$prodi], 0);
$hitungSel   = fn(): int => (int) qv('SELECT COUNT(*) FROM sel s JOIN baris_tabel b ON b.id = s.baris_id
                                      WHERE b.prodi_id = ?', [$prodi], 0);

$labelLama = [];
foreach (range(0, 6) as $n) {
    $labelLama[$n] = label_ts($ts0, $n);
}
$barisSebelum = $hitungBaris();
$selSebelum = $hitungSel();
cek('data contoh tersimpan', $barisSebelum > 0 && $selSebelum > 0, "$barisSebelum baris / $selSebelum sel");

$tsBaru = $ts0 + 1;
ex('UPDATE periode SET tahun_ts = ? WHERE id = ?', [$tsBaru, $perId]);
$perBaru = q1('SELECT tahun_ts FROM periode WHERE id = ?', [$perId]);
cek("tahun_ts berubah $ts0 -> $tsBaru", (int) $perBaru['tahun_ts'] === $tsBaru);

$tsSekarang = (int) qv('SELECT tahun_ts FROM periode WHERE prodi_id = ? AND aktif = 1
                        ORDER BY tahun_ts DESC LIMIT 1', [$prodi], 0);
$bergeser = 0;
foreach (range(0, 6) as $n) {
    if (label_ts($tsSekarang, $n) !== $labelLama[$n]) {
        $bergeser++;
    }
}
cek('7 label TS-0..TS-6 semuanya bergeser', $bergeser === 7, "$bergeser/7 berubah");

$barisSesudah = $hitungBaris();
$selSesudah = $hitungSel();
cek('COUNT(*) baris_tabel tidak berubah', $barisSesudah === $barisSebelum, "$barisSebelum -> $barisSesudah");
cek('COUNT(*) sel tidak berubah', $selSesudah === $selSebelum, "$selSebelum -> $selSesudah");

// Nilai sel ikut tidak berubah — bukan cuma jumlahnya.
$nilai = qv('SELECT nilai_angka FROM sel WHERE baris_id = ? AND field_kunci = ?',
    [$bid, $field[0]['kunci']], null);
cek('nilai sel tetap 12 setelah tahun diganti', (float) $nilai === 12.0, (string) $nilai);

// Data contoh tidak perlu dibersihkan manual: transaksi di-rollback di akhir.
cek('tahun_ts dikembalikan di dalam transaksi',
    (int) qv('SELECT tahun_ts FROM periode WHERE id = ?', [$perId]) === $tsBaru);

/* ── peringatan akreditasi: hitung sisa hari ─────────────────────── */
cek('sisa_hari kosong -> null', sisa_hari(null) === null);
cek('sisa_hari tanggal tidak sah -> null', sisa_hari('bukan-tanggal') === null);
cek('sisa_hari hari ini = 0', sisa_hari(date('Y-m-d')) === 0, (string) sisa_hari(date('Y-m-d')));
$kemarin = date('Y-m-d', strtotime('-1 day'));
$dekat = date('Y-m-d', strtotime('+30 days'));
$jauh = date('Y-m-d', strtotime('+3 years'));
cek('lewat -> jenis bad', (pesan_akreditasi($kemarin)['jenis'] ?? '') === 'bad');
cek('30 hari -> jenis warn', (pesan_akreditasi($dekat)['jenis'] ?? '') === 'warn');
cek('3 tahun -> tidak ada peringatan', pesan_akreditasi($jauh) === null);
cek('pesan menyebut jumlah hari', str_contains((string) (pesan_akreditasi($dekat)['pesan'] ?? ''), '30 hari'),
    (string) (pesan_akreditasi($dekat)['pesan'] ?? ''));

/* ── gerbang izin: Pimpinan lihat, Operator isi, Verifikator tidak ── */
$idPeran = fn(string $k) => (int) qv('SELECT id FROM pengguna WHERE peran_kode = ? LIMIT 1', [$k]);
$coba = function (string $peran) use ($idPeran): array {
    $u = q1('SELECT * FROM pengguna WHERE id = ?', [$idPeran($peran)]);
    return ['akses' => akses('ISI', $u), 'boleh' => akses('ISI', $u) === 1,
            'hanya_lihat' => akses('ISI', $u) === 2];
};
cek('ADMIN boleh ISI', $coba('ADMIN')['boleh'] === true);
cek('OPERATOR boleh ISI', $coba('OPERATOR')['boleh'] === true);
cek('PIMPINAN hanya lihat', $coba('PIMPINAN')['hanya_lihat'] === true && $coba('PIMPINAN')['boleh'] === false);
cek('VERIFIKATOR tidak boleh ISI', $coba('VERIFIKATOR')['akses'] === 0);

/* ── halaman benar-benar dirender (tanpa HTTP) ──────────────────── */
sesi_mulai();
$_SESSION['pengguna_id'] = $idPeran('ADMIN');
$_SESSION['prodi_id'] = $prodi;
$_SESSION['csrf'] = 'uji-csrf';

$render = function (string $berkas) use ($prodi): array {
    $_GET = ['m' => 'identitas', 'p' => (string) $prodi];
    $PRODI = $prodi;
    ob_start();
    require $berkas;
    return [ob_get_clean(), error_get_last()];
};
[$htmlId, $errId] = $render(__DIR__ . '/../modul/identitas/index.php');
$bersih = fn(string $h): bool => !str_contains($h, 'Warning:') && !str_contains($h, 'Fatal error')
    && !str_contains($h, 'Notice:');
cek('halaman identitas dirender tanpa galat PHP', $bersih($htmlId), json_encode($errId['message'] ?? ''));
cek('halaman identitas memuat blok konsekuensi', str_contains($htmlId, 'tabel wajib'));
cek('halaman identitas memuat tabel 30 di daftar khusus', str_contains($htmlId, '11 · 30'));
cek('halaman identitas punya token CSRF', str_contains($htmlId, 'name="_csrf"'));

$_GET = ['m' => 'periode', 'p' => (string) $prodi];
ob_start();
require __DIR__ . '/../modul/periode/index.php';
$htmlPer = ob_get_clean();
cek('halaman periode dirender tanpa galat PHP', $bersih($htmlPer));
cek('halaman periode memuat pratinjau TS-0..TS-6',
    str_contains($htmlPer, 'TS-0 (') && str_contains($htmlPer, 'TS-6 ('));
cek('halaman periode memuat pratinjau SS-1..SS-16',
    str_contains($htmlPer, 'SS-1 (') && str_contains($htmlPer, 'SS-16 ('));
cek('halaman periode menyatakan tahun absolut', str_contains($htmlPer, 'tidak mengubah data'));

/* ── jalur simpan: isian salah ditolak, tidak menulis apa pun ────── */
// Aksi simpan yang GAGAL validasi tidak mengalihkan halaman, jadi bisa dipanggil
// langsung. Gerbang wajib('ISI') / csrf_periksa() sudah diuji lewat akses() di atas;
// keduanya memanggil tolak() yang berhenti dengan exit, jadi tidak dijalankan di sini.
$jenjangAwal = (string) qv('SELECT jenjang FROM prodi WHERE id = ?', [$prodi]);
$_SESSION['pengguna_id'] = $idPeran('ADMIN');
$_POST = [
    '_csrf' => 'uji-csrf',
    'nama_pt' => 'Universitas Uji', 'nama_upps' => 'Fakultas Uji', 'narahubung' => '0812',
    'nama_ps' => 'Prodi Uji', 'jenjang' => 'Sarjana', 'bidang' => 'Uji',
    'punya_tahap_profesi' => '0', 'lama_studi_semester' => '99',
    'tgl_akreditasi' => '2022-01-01', 'tgl_berakhir_akreditasi' => '2021-01-01',
    'nama_profesi_lulusan' => 'Bidan',
];
$_GET = ['m' => 'identitas', 'a' => 'simpan', 'p' => (string) $prodi];
ob_start();
require __DIR__ . '/../modul/identitas/index.php';
$htmlSalah = ob_get_clean();
cek('simpan dengan isian salah tidak menulis apa pun',
    (string) qv('SELECT jenjang FROM prodi WHERE id = ?', [$prodi]) === $jenjangAwal, $jenjangAwal);
cek('galat lama studi ditampilkan', str_contains($htmlSalah, 'Lama studi harus 1'));
cek('galat tanggal berakhir ditampilkan', str_contains($htmlSalah, 'tidak boleh sebelum'));
cek('galat profesi tanpa tahap profesi ditampilkan', str_contains($htmlSalah, 'hanya untuk prodi bertahap profesi'));
cek('isian salah tetap tampil di form, tidak dikosongkan',
    str_contains($htmlSalah, 'value="Prodi Uji"'));
$_POST = [];

/* ── rollback: data kembali ke kondisi awal, tanpa sisa ──────────── */
$_SESSION = [];
db()->rollBack();
$selesai = true;

cek('kondisi awal pulih: punya_tahap_profesi', (int) qv('SELECT punya_tahap_profesi FROM prodi WHERE id = ?', [$prodi])
    === (int) $awalProdi['punya_tahap_profesi']);
cek("kondisi awal pulih: tahun_ts {$awalPeriode['tahun_ts']}",
    (int) qv('SELECT tahun_ts FROM periode WHERE id = ?', [(int) $awalPeriode['id']])
    === (int) $awalPeriode['tahun_ts']);
cek('tidak ada baris uji tertinggal',
    (int) qv('SELECT COUNT(*) FROM baris_tabel WHERE id = ?', [$bid], 0) === 0);
cek('tidak ada sel uji tertinggal',
    (int) qv('SELECT COUNT(*) FROM sel WHERE baris_id = ?', [$bid], 0) === 0);

while (ob_get_level() > 0) {
    ob_end_flush();
}
echo $gagal === 0 ? "\nSEMUA UJI LULUS\n" : "\n$gagal UJI GAGAL\n";
exit($gagal === 0 ? 0 : 1);
