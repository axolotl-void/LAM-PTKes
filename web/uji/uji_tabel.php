<?php
/**
 * Uji Data Dukung (38 tabel) — lewat jalur PRODUKSI yang masih hidup.
 *
 * KEPUTUSAN (b): modul `web/modul/tabel/` sudah TIDAK dipakai produksi.
 *   grep -rn 'modul/tabel' web/ | grep -v /uji/   -> tidak ada hasil
 *   web/router.php hanya melayani /api/* dan berkas statis dari app/;
 *   web/index.php (front controller modul) tidak pernah dirutekan lagi.
 *   Frontend asli `app/assets/js/pages/datadukung.js` bicara ke /api/tabel
 *   saja (app/assets/js/api.js: tabel/isi/kunci/buka).
 *
 * Uji lama me-require modul/tabel/proses.php + merender modul/tabel/index.php,
 * jadi ia mengukur kode mati: 3 assertion gagal karena halaman itu tidak
 * pernah dirender lagi. Uji ini dipindahkan ke jalur yang benar-benar dipakai:
 *
 *   web/system/rekap.php  -> status 38 tabel + saringan kriteria (baca)
 *   web/system/data.php   -> isi_tabel_api / kunci_tabel_api / buka_kunci_api
 *   web/system/auth.php   -> gerbang izin (tabel `izin`, bukan tebakan)
 *
 * Yang tetap diuji (inti sistem, tidak boleh hilang):
 *   1. tepat 38 tabel dukung, semuanya terbaca
 *   2. saringan K1..K8 benar; yang tidak punya tabel jujur kosong, bukan error
 *   3. simpan isi tabel: sel tersimpan pada kunci field Input yang benar
 *   4. kunci -> status terkunci, dan operator tidak bisa mengisi saat terkunci
 *   5. buka kunci oleh peran berhak berhasil
 *
 * Catatan: api.php bukan lapisan aturan, cuma routing. Yang diuji handler-nya,
 * karena di situlah aturannya. `php://input` tidak bisa dipalsukan dari CLI.
 *
 * Semua baris/sel/audit buatan uji DIHAPUS lagi di akhir (DELETE), supaya uji
 * lain tidak terganggu sisa data.
 *
 * Jalankan: LAMPTKES_ENV=lokal php web/uji/uji_tabel.php
 */
declare(strict_types=1);

ob_start();
putenv('LAMPTKES_ENV=lokal');
$_SERVER['REQUEST_METHOD'] = 'POST';
require_once __DIR__ . '/../system/view.php';
require_once __DIR__ . '/../system/rekap.php';
require_once __DIR__ . '/../system/data.php';

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
$per = periode_aktif($prodi);
cek('periode aktif ada', $per !== null);
$perId = (int) ($per['id'] ?? 0);

$op = q1('SELECT * FROM pengguna WHERE email = ?', ['siti@bbg.ac.id']);        // OPERATOR prodi 1
$vf = q1('SELECT * FROM pengguna WHERE email = ?', ['maimunah@bbg.ac.id']);    // VERIFIKATOR prodi 1
$admin = q1('SELECT * FROM pengguna WHERE email = ?', ['zulfikar@bbg.ac.id']); // ADMIN, tanpa prodi
cek('akun uji lengkap', $op && $vf && $admin);

$sesiOp = ['pengguna_id' => (int) $op['id'], 'prodi_id' => (int) $op['prodi_id']];
$sesiVf = ['pengguna_id' => (int) $vf['id'], 'prodi_id' => (int) $vf['prodi_id']];
$sesiAd = ['pengguna_id' => (int) $admin['id'], 'prodi_id' => $prodi];

/** Bersihkan jejak uji. `audit` append-only, jadi dipangkas per id, bukan TRUNCATE. */
$auditAwal = (int) qv('SELECT COALESCE(MAX(id),0) FROM audit', [], 0);
function bersih(array $kode, int $prodi, int $perId, int $auditAwal): void
{
    foreach ($kode as $k) {
        ex('DELETE FROM baris_tabel WHERE tabel_kode = ? AND prodi_id = ? AND periode_id = ?',
            [$k, $prodi, $perId]);   // sel ikut terhapus (FK ON DELETE CASCADE)
    }
    ex('DELETE FROM audit WHERE id > ?', [$auditAwal]);
}
$kodeUji = ['01', '02'];
bersih($kodeUji, $prodi, $perId, $auditAwal);

/**
 * Jalankan satu potongan kode sebagai satu "request" CLI: sesi disuntik.
 * Dipisah proses karena (a) tolak() memanggil exit, (b) pengguna() di-cache
 * static per proses sehingga ganti peran dalam satu proses tidak sah.
 */
function jalankan(array $sesi, string $kode): string
{
    $berkas = tempnam(sys_get_temp_dir(), 'ujitabel') . '.php';
    file_put_contents($berkas, '<?php
declare(strict_types=1);
ob_start();
putenv("LAMPTKES_ENV=lokal");
$_SERVER["REQUEST_METHOD"] = "POST";
require ' . var_export(__DIR__ . '/../system/view.php', true) . ';
require ' . var_export(__DIR__ . '/../system/data.php', true) . ';
sesi_mulai();
$_SESSION = ' . var_export($sesi, true) . ';
register_shutdown_function(fn() => fwrite(STDERR, "KODE=" . http_response_code()));
' . $kode . '
while (ob_get_level() > 0) { ob_end_flush(); }
');
    $keluar = (string) shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($berkas) . ' 2>&1');
    @unlink($berkas);
    return $keluar;
}

/** Ambil objek JSON dari keluaran subproses (abaikan KODE=... di stderr). */
function json_dari(string $keluaran): array
{
    if (preg_match('/\{.*\}/s', $keluaran, $m)) {
        $d = json_decode($m[0], true);
        return is_array($d) ? $d : [];
    }
    return [];
}

/* ── 1. tepat 38 tabel, semuanya terbaca ────────────────────────── */
$kodeDb = array_column(q('SELECT kode FROM tabel_dukung ORDER BY nomor'), 'kode');
cek('38 tabel dukung di skema', count($kodeDb) === 38, (string) count($kodeDb));
$harap = array_map(fn($n) => str_pad((string) $n, 2, '0', STR_PAD_LEFT), range(1, 38));
cek('kode 01..38 lengkap tanpa lubang', $kodeDb === $harap, implode(',', array_diff($harap, $kodeDb)));

$semua = rekap_semua_tabel($prodi, $perId);
cek('rekap_semua_tabel mengembalikan 38 baris', count($semua) === 38, (string) count($semua));

$sah = ['lengkap', 'terkunci', 'sebagian', 'kosong', 'hitung', 'na'];
$rusak = [];
foreach ($semua as $t) {
    if (!in_array($t['status'] ?? null, $sah, true)) {
        $rusak[] = $t['kode'] . '=' . ($t['status'] ?? 'null');
    }
}
cek('semua 38 tabel terbaca dengan status sah', $rusak === [], implode(',', $rusak));

// Field Input tiap tabel harus terhitung; 6 tabel tanpa Input berstatus 'hitung'.
$totalInput = (int) qv("SELECT COUNT(*) FROM spec_field WHERE asal = 'Input'", [], 0);
$jumlahTotal = array_sum(array_column($semua, 'total'));
cek('field Input 38 tabel = spec di DB', $jumlahTotal === $totalInput, "$jumlahTotal/$totalInput");
// 8, bukan 7: Tabel 21 (bimbingan TA) dihitung dari tabel `bimbingan`.
cek('tepat 8 tabel berstatus hitung',
    count(array_filter($semua, fn($t) => $t['status'] === 'hitung')) === 8);
cek('semua tabel berlaku untuk prodi bertahap profesi',
    count(array_filter($semua, fn($t) => $t['berlaku'])) === 38);

// 6 tabel khusus profesi: aturannya dibaca dari skema, bukan ditebak.
cek('tabel khusus profesi berlaku bila prodi punya tahap profesi', berlaku_untuk_prodi('30', $prodi) === true);
cek('tabel khusus profesi ditolak untuk prodi tanpa tahap profesi', berlaku_untuk_prodi('30', 999999) === false);
cek('tabel biasa tidak bergantung tahap profesi', berlaku_untuk_prodi('01', 999999) === true);

/* ── 2. saringan per kriteria K1..K8 ────────────────────────────── */
$rekapK = rekap_per_kriteria($prodi, $perId);
$kodeK = array_column($rekapK, 'kriteria');
cek('8 kriteria K1..K8 selalu dikembalikan', $kodeK === ['K1', 'K2', 'K3', 'K4', 'K5', 'K6', 'K7', 'K8'],
    implode(',', $kodeK));
cek('jumlah tabel per kriteria = 38', array_sum(array_column($rekapK, 'total')) === 38,
    (string) array_sum(array_column($rekapK, 'total')));

// K1 hanya dokumen: HARUS jujur kosong (total 0), bukan error / bukan hilang.
$k1 = $rekapK[0];
cek('K1 jujur kosong: total 0 tabel', $k1['kriteria'] === 'K1' && $k1['total'] === 0, json_encode($k1));
$tabelK1 = array_values(array_filter($semua, fn($t) => $t['kriteria'] === 'K1'));
cek('K1 tidak punya tabel (hasil saringan kosong, bukan galat)', $tabelK1 === []);

// Tiap kriteria dibandingkan dengan kebenaran DB, bukan angka yang diketik.
$hitungDb = [];
$salah = [];
foreach (q('SELECT kriteria, COUNT(*) n FROM tabel_dukung GROUP BY kriteria') as $r) {
    $hitungDb[$r['kriteria']] = (int) $r['n'];
}
foreach ($rekapK as $k) {
    if (($hitungDb[$k['kriteria']] ?? 0) !== $k['total']) {
        $salah[] = $k['kriteria'] . '=' . $k['total'] . '/' . ($hitungDb[$k['kriteria']] ?? 0);
    }
}
cek('tabel per kriteria cocok dengan DB', $salah === [], implode(',', $salah));

// Saringan yang dipakai UI: saring kode_kriteria, hasilnya harus sama dengan DB.
$salahKode = [];
foreach ($hitungDb as $krit => $n) {
    $kodeDariDb = array_column(q('SELECT kode FROM tabel_dukung WHERE kriteria = ? ORDER BY nomor', [$krit]), 'kode');
    $kodeDariRekap = array_column(array_values(array_filter($semua, fn($t) => $t['kriteria'] === $krit)), 'kode');
    if ($kodeDariDb !== $kodeDariRekap) {
        $salahKode[] = $krit;
    }
}
cek('saringan kriteria mengembalikan kode yang benar', $salahKode === [], implode(',', $salahKode));

// Kriteria tanpa tabel: saringan mengembalikan daftar kosong, tidak melempar.
$filterK1 = array_values(array_filter($semua, fn($t) => $t['kriteria'] === 'K1'));
cek('saringan kriteria kosong tidak error', is_array($filterK1) && $filterK1 === []);

/* ── 3. simpan isi tabel: sel di kunci field Input yang benar ───── */
$input01 = array_map('strval', array_column(
    q("SELECT kunci FROM spec_field WHERE tabel_kode = '01' AND asal = 'Input' ORDER BY urut"), 'kunci'));
cek('tabel 01 punya 3 field Input', count($input01) === 3, implode(',', $input01));

$h = json_dari(jalankan($sesiOp, 'echo json_encode(isi_tabel_api("01", 3, "uji"));'));
cek('isi 3 field tabel 01', ($h['field_terisi'] ?? -1) === 3 && ($h['field_total'] ?? -1) === 3, json_encode($h));
// data.php mengembalikan `status` sebagai string (bukan objek status_satu_tabel).
cek('status jadi lengkap', ($h['status'] ?? '') === 'lengkap', json_encode($h));

$kunciSel = array_column(q(
    'SELECT s.field_kunci FROM sel s JOIN baris_tabel b ON b.id = s.baris_id
     WHERE b.tabel_kode = ? AND b.prodi_id = ? AND b.periode_id = ? ORDER BY s.field_kunci',
    ['01', $prodi, $perId]), 'field_kunci');
$harapSel = $input01;
sort($harapSel);
cek('sel tersimpan pada kunci field Input yang benar', $kunciSel === $harapSel,
    implode(',', $kunciSel));
$bid = (int) qv('SELECT id FROM baris_tabel WHERE tabel_kode = ? AND prodi_id = ? AND periode_id = ? ORDER BY urut LIMIT 1',
    ['01', $prodi, $perId]);
cek('tidak ada sel di luar field Input (Master/Hitung tidak disimpan)',
    (int) qv('SELECT COUNT(*) FROM sel s JOIN baris_tabel b ON b.id = s.baris_id
              LEFT JOIN spec_field f ON f.tabel_kode = b.tabel_kode AND f.kunci = s.field_kunci
              WHERE b.tabel_kode = ? AND b.prodi_id = ? AND b.periode_id = ? AND f.asal <> "Input"',
        ['01', $prodi, $perId], 0) === 0);

// Klamp: mustahil "lengkap dengan 99/3".
$h = json_dari(jalankan($sesiOp, 'echo json_encode(isi_tabel_api("01", 99, "uji"));'));
cek('terisi diklamp ke jumlah field', ($h['field_terisi'] ?? -1) === 3, json_encode($h));

// Kosongkan: status kembali kosong, sel memang dihapus bukan disimpan "".
$h = json_dari(jalankan($sesiOp, 'echo json_encode(isi_tabel_api("01", 0, "uji"));'));
cek('isi 0 mengosongkan tabel', ($h['field_terisi'] ?? -1) === 0 && ($h['status'] ?? '') === 'kosong',
    json_encode($h));
cek('tidak ada sel bernilai string kosong',
    (int) qv('SELECT COUNT(*) FROM sel s JOIN baris_tabel b ON b.id = s.baris_id
              WHERE b.tabel_kode = ? AND b.prodi_id = ? AND b.periode_id = ? AND s.nilai_teks = ""',
        ['01', $prodi, $perId], 0) === 0);

$h = json_dari(jalankan($sesiOp, 'echo json_encode(isi_tabel_api("99", 1, "uji"));'));
cek('kode tabel tak dikenal ditolak jujur', str_contains((string) ($h['error'] ?? ''), 'tabel tidak ada'),
    json_encode($h));

/* ── 4. kunci: status terkunci, operator tidak bisa mengisi ─────── */
$h = json_dari(jalankan($sesiVf, 'echo json_encode(kunci_tabel_api("02", "uji"));'));
cek('kunci saat belum lengkap ditolak', str_contains((string) ($h['error'] ?? ''), 'belum lengkap'),
    json_encode($h));
cek('tabel 02 tetap tidak terkunci', (int) qv(
    'SELECT COUNT(*) FROM baris_tabel WHERE tabel_kode = ? AND prodi_id = ? AND periode_id = ? AND dikunci_oleh IS NOT NULL',
    ['02', $prodi, $perId], 0) === 0);

jalankan($sesiOp, 'echo json_encode(isi_tabel_api("01", 3, "uji"));');
$h = json_dari(jalankan($sesiVf, 'echo json_encode(kunci_tabel_api("01", "verifikator"));'));
cek('kunci tabel lengkap berhasil', ($h['kode'] ?? '') === '01' && ($h['dikunci_oleh'] ?? '') === 'verifikator',
    json_encode($h));
cek('status jadi terkunci', status_satu_tabel('01', $prodi, $perId)['status'] === 'terkunci');
cek('baris ditandai dikunci_oleh pengguna berhak',
    (int) qv('SELECT dikunci_oleh FROM baris_tabel WHERE id = ?', [$bid], 0) === (int) $vf['id']);

$h = json_dari(jalankan($sesiOp, 'echo json_encode(isi_tabel_api("01", 0, "uji"));'));
cek('operator tidak bisa mengisi saat terkunci', str_contains((string) ($h['error'] ?? ''), 'dikunci oleh'),
    json_encode($h));
cek('isi saat terkunci tidak mengubah data',
    (int) qv('SELECT COUNT(*) FROM sel s JOIN baris_tabel b ON b.id = s.baris_id
              WHERE b.tabel_kode = ? AND b.prodi_id = ? AND b.periode_id = ?', ['01', $prodi, $perId], 0) === 3
    && status_satu_tabel('01', $prodi, $perId)['status'] === 'terkunci');
cek('kunci tercatat di audit',
    (int) qv("SELECT COUNT(*) FROM audit WHERE aksi = 'kunci' AND entitas = 'tabel' AND entitas_id = '01'",
        [], 0) >= 1);

/* ── 5. buka kunci oleh peran yang berhak ───────────────────────── */
cek('operator tidak boleh BUKA_KUNCI', akses('BUKA_KUNCI', $op) === 0);
cek('verifikator tidak boleh BUKA_KUNCI', akses('BUKA_KUNCI', $vf) === 0);
cek('admin boleh BUKA_KUNCI', akses('BUKA_KUNCI', $admin) === 1);
cek('operator boleh ISI', akses('ISI', $op) === 1);
cek('verifikator tidak boleh ISI', akses('ISI', $vf) === 0);
cek('verifikator boleh VERIFIKASI', akses('VERIFIKASI', $vf) === 1);

// Gerbang dijalankan sebagai request terpisah: tolak() memanggil exit.
$out = jalankan($sesiOp, 'wajib("BUKA_KUNCI"); echo json_encode(["lolos" => true]);');
cek('wajib(BUKA_KUNCI) menolak operator 403',
    str_contains($out, 'tidak boleh: BUKA_KUNCI') && str_contains($out, 'KODE=403'), substr(trim($out), 0, 160));
$out = jalankan($sesiVf, 'wajib("ISI"); echo json_encode(["lolos" => true]);');
cek('wajib(ISI) menolak verifikator 403',
    str_contains($out, 'tidak boleh: ISI') && str_contains($out, 'KODE=403'), substr(trim($out), 0, 160));
$out = jalankan($sesiOp, 'wajib("ISI"); echo json_encode(["lolos" => true]);');
cek('wajib(ISI) meloloskan operator', str_contains($out, '"lolos":true'), substr(trim($out), 0, 160));

$h = json_dari(jalankan($sesiAd, 'echo json_encode(buka_kunci_api("01", "admin"));'));
cek('admin membuka kunci berhasil', ($h['terbuka'] ?? false) === true, json_encode($h));
cek('status kembali lengkap setelah dibuka', status_satu_tabel('01', $prodi, $perId)['status'] === 'lengkap');
cek('dikunci_oleh dikosongkan', (int) qv(
    'SELECT COUNT(*) FROM baris_tabel WHERE tabel_kode = ? AND prodi_id = ? AND periode_id = ? AND dikunci_oleh IS NOT NULL',
    ['01', $prodi, $perId], 0) === 0);

// Setelah dibuka, operator bisa mengisi lagi.
$h = json_dari(jalankan($sesiOp, 'echo json_encode(isi_tabel_api("01", 1, "uji"));'));
cek('setelah buka kunci operator bisa mengisi lagi',
    ($h['field_terisi'] ?? -1) === 1 && ($h['status'] ?? '') === 'sebagian', json_encode($h));

/* ── bersihkan ──────────────────────────────────────────────────── */
bersih($kodeUji, $prodi, $perId, $auditAwal);
cek('data uji bersih: 0 baris tabel 01/02',
    (int) qv('SELECT COUNT(*) FROM baris_tabel WHERE tabel_kode IN ("01","02") AND prodi_id = ? AND periode_id = ?',
        [$prodi, $perId], 0) === 0);
cek('data uji bersih: 0 sel yatim',
    (int) qv('SELECT COUNT(*) FROM sel s LEFT JOIN baris_tabel b ON b.id = s.baris_id WHERE b.id IS NULL', [], 0) === 0);
cek('data uji bersih: audit uji dipangkas', (int) qv('SELECT COUNT(*) FROM audit WHERE id > ?', [$auditAwal], 0) === 0);
cek('tabel 01/02 kembali kosong', status_satu_tabel('01', $prodi, $perId)['status'] === 'kosong'
    && status_satu_tabel('02', $prodi, $perId)['status'] === 'kosong');

while (ob_get_level() > 0) { ob_end_flush(); }
echo $gagal === 0 ? "\nSEMUA UJI LULUS\n" : "\n$gagal UJI GAGAL\n";
exit($gagal === 0 ? 0 : 1);
