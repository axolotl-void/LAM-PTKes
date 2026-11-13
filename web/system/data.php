<?php
declare(strict_types=1);

/**
 * Operasi tulis yang dipakai API. Dipisah dari api.php supaya bisa diuji
 * tanpa HTTP, dan supaya aturannya satu tempat.
 *
 * Semua fungsi di sini MENGASUMSIKAN gerbang izin sudah diperiksa pemanggil
 * (`wajib()`). Jangan panggil langsung dari halaman tanpa gerbang.
 */

require_once __DIR__ . '/db.php';
// data.php memanggil periode_aktif()/status_satu_tabel()/berlaku_untuk_prodi()
// yang tinggal di rekap.php. Dulu aman hanya karena api.php memuatnya lebih
// dulu — di luar jalur itu fatal error. Ketergantungan ditulis di sini.
require_once __DIR__ . '/rekap.php';

/**
 * Isi field satu tabel lewat angka ringkas (dipakai UI prototipe: {terisi:n}).
 * Membuat baris kalau belum ada, lalu mengisi n field Input pertama.
 * Klamp 0..total — mustahil ada baris "lengkap" dengan 3/6.
 */
function isi_tabel_api(string $kode, int $terisi, string $oleh): array
{
    $p = prodi_aktif();
    $per = $p ? periode_aktif($p) : null;
    if (!$p || !$per) {
        return ['error' => 'Prodi/periode belum ditetapkan.'];
    }
    $info = q1('SELECT * FROM tabel_dukung WHERE kode = ?', [$kode]);
    if (!$info) {
        return ['error' => 'tabel tidak ada'];
    }
    if (!berlaku_untuk_prodi($kode, $p)) {
        return ['error' => 'tabel khusus tahap profesi, tidak berlaku untuk prodi ini'];
    }
    $field = q("SELECT kunci FROM spec_field WHERE tabel_kode = ? AND asal = 'Input' ORDER BY urut", [$kode]);
    $total = count($field);

    $baris = q1('SELECT * FROM baris_tabel WHERE prodi_id = ? AND periode_id = ? AND tabel_kode = ? ORDER BY urut LIMIT 1',
        [$p, (int) $per['id'], $kode]);
    if ($baris && $baris['dikunci_oleh'] !== null) {
        return ['error' => 'dikunci oleh ' . $baris['dikunci_oleh']];
    }

    $n = max(0, min($terisi, $total));

    return transaksi(static function () use ($baris, $p, $per, $kode, $field, $n, $total, $oleh) {
        $id = $baris ? (int) $baris['id'] : sisip('baris_tabel', [
            'prodi_id' => $p, 'periode_id' => (int) $per['id'], 'tabel_kode' => $kode, 'urut' => 1,
        ]);
        ex('DELETE FROM sel WHERE baris_id = ?', [$id]);
        for ($i = 0; $i < $n; $i++) {
            sisip('sel', ['baris_id' => $id, 'field_kunci' => $field[$i]['kunci'], 'nilai_teks' => '1']);
        }
        ex('INSERT INTO audit (pengguna_id, prodi_id, aksi, entitas, entitas_id, field, nilai_baru)
            VALUES (?,?,?,?,?,?,?)',
            [pengguna()['id'] ?? null, $p, 'isi', 'tabel', $kode, 'field_terisi', (string) $n]);
        return [
            'kode' => $kode,
            'judul' => qv('SELECT judul FROM tabel_dukung WHERE kode = ?', [$kode], ''),
            'field_terisi' => $n,
            'field_total' => $total,
            'berlaku' => 1,
            'status' => status_satu_tabel($kode, $p, (int) $per['id'])['status'],
        ];
    });
}

function kunci_tabel_api(string $kode, string $oleh): array
{
    $p = prodi_aktif();
    $per = $p ? periode_aktif($p) : null;
    if (!$p || !$per) {
        return ['error' => 'Prodi/periode belum ditetapkan.'];
    }
    $s = status_satu_tabel($kode, $p, (int) $per['id']);
    if ($s['status'] !== 'lengkap') {
        return ['error' => 'belum lengkap: ' . $s['terisi'] . '/' . $s['total']];
    }
    ex('UPDATE baris_tabel SET dikunci_oleh = ?, dikunci_pada = NOW()
        WHERE prodi_id = ? AND periode_id = ? AND tabel_kode = ?',
        [pengguna()['id'] ?? null, $p, (int) $per['id'], $kode]);
    ex('INSERT INTO audit (pengguna_id, prodi_id, aksi, entitas, entitas_id)
        VALUES (?,?,?,?,?)', [pengguna()['id'] ?? null, $p, 'kunci', 'tabel', $kode]);
    return ['kode' => $kode, 'dikunci_oleh' => $oleh];
}

function buka_kunci_api(string $kode, string $oleh): array
{
    $p = prodi_aktif();
    $per = $p ? periode_aktif($p) : null;
    if (!$p || !$per) {
        return ['error' => 'Prodi/periode belum ditetapkan.'];
    }
    ex('UPDATE baris_tabel SET dikunci_oleh = NULL, dikunci_pada = NULL
        WHERE prodi_id = ? AND periode_id = ? AND tabel_kode = ?',
        [$p, (int) $per['id'], $kode]);
    ex('INSERT INTO audit (pengguna_id, prodi_id, aksi, entitas, entitas_id)
        VALUES (?,?,?,?,?)', [pengguna()['id'] ?? null, $p, 'buka kunci', 'tabel', $kode]);
    return ['kode' => $kode, 'terbuka' => true];
}

/**
 * Daftar akun. Tanpa kolom sandi — jangan pernah bocor ke JSON.
 *
 * Bentuk keluaran MENGIKUTI kontrak `app/assets/js/pages/pengguna.js`:
 *   status = 'aktif' | 'nonaktif' | 'konflik'   (bukan boolean `aktif`)
 *   prodi  = nama prodi atau 'Semua prodi'
 *   aktif_terakhir = teks siap tampil
 * API menyesuaikan frontend, bukan sebaliknya.
 */
function daftar_pengguna(): array
{
    $rows = q('SELECT p.id, p.email, p.nama, p.peran_kode AS peran, p.aktif,
                      p.masuk_terakhir, pr.nama_ps AS prodi
               FROM pengguna p LEFT JOIN prodi pr ON pr.id = p.prodi_id
               ORDER BY p.id');
    foreach ($rows as &$r) {
        $r['id'] = (int) $r['id'];
        $r['status'] = (int) $r['aktif'] === 1 ? 'aktif' : 'nonaktif';
        $r['prodi'] = $r['prodi'] ?? 'Semua prodi';
        $r['aktif_terakhir'] = $r['masuk_terakhir'] !== null
            ? tgl((string) $r['masuk_terakhir']) : '—';
        unset($r['aktif'], $r['masuk_terakhir']);
    }
    return $rows;
}

function ringkasan_akun(): array
{
    $rows = q('SELECT peran_kode, aktif FROM pengguna');
    $aktif = count(array_filter($rows, fn($r) => (int) $r['aktif'] === 1));
    return [
        'total' => count($rows),
        'aktif_tanpa_konflik' => $aktif,
        'konflik' => 0,   // peran ganda mustahil di skema
        'nonaktif' => count($rows) - $aktif,
        'peran' => (int) qv('SELECT COUNT(*) FROM peran', [], 0),
    ];
}
