<?php
declare(strict_types=1);

/**
 * Logika modul Data Dukung — dipisah dari index.php supaya uji bisa memanggil
 * fungsi langsung tanpa HTTP (syarat KONTRAK-SWARM §6).
 *
 * Aturan yang dijaga di sini, bukan di tampilan:
 *  - hanya field `asal = 'Input'` yang ditulis ke `sel`. Master/Hitung/Tetap
 *    tidak pernah disimpan: nilainya berasal dari master atau dihitung sistem,
 *    jadi menyimpannya berarti menyimpan salinan yang cepat atau lambat basi.
 *  - sel yang dikosongkan DIHAPUS, bukan disimpan sebagai string kosong.
 *    String kosong akan terhitung "terisi" oleh status_satu_tabel() dan tabel
 *    akan tampak lengkap padahal isinya kosong.
 *  - baris terkunci menolak semua tulisan, termasuk dari ADMIN. Membuka kunci
 *    adalah aksi tersendiri (BUKA_KUNCI) yang tercatat.
 */

require_once __DIR__ . '/../../system/view.php';
require_once __DIR__ . '/../../system/rekap.php';

/** Ditolak dengan kode HTTP. Ditangkap index.php lalu diteruskan ke tolak(). */
final class TabelDitolak extends RuntimeException
{
    public function __construct(public readonly int $kodeHttp, string $pesan)
    {
        parent::__construct($pesan);
    }
}

/** Tipe yang disimpan di nilai_angka. Sisanya nilai_teks. */
const TABEL_TIPE_ANGKA = ['angka', 'desimal', 'tahun akademik', 'persen', 'tanggal'];

/**
 * Baris per halaman form. Batas ini ada karena `max_input_vars` PHP (default
 * 1000) memotong POST diam-diam: 239 baris x 3 field = 717 sel + field lain
 * sudah mendekati batas, dan tabel 38 punya 9 field Input (239 x 9 = 2151).
 * 50 baris x 9 field = 450 sel, aman dengan margin.
 */
const TABEL_PER_HAL = 50;

function tabel_info(string $kode): array
{
    $r = q1('SELECT kode, nomor, judul, kriteria, khusus_profesi FROM tabel_dukung WHERE kode = ?', [$kode]);
    if (!$r) {
        throw new TabelDitolak(404, 'Tabel tidak dikenal: ' . $kode);
    }
    return $r;
}

/** Field satu tabel, urut sesuai instrumen. */
function tabel_field(string $kode): array
{
    return q('SELECT urut, label, kunci, tipe, asal, keterangan
              FROM spec_field WHERE tabel_kode = ? ORDER BY urut', [$kode]);
}

function tabel_kolom(string $tipe): string
{
    return in_array($tipe, TABEL_TIPE_ANGKA, true) ? 'angka' : 'teks';
}

/**
 * Tanggal disimpan di nilai_angka sebagai YYYYMMDD (bilangan bulat 8 digit).
 * Skema `sel` hanya punya DECIMAL(18,4) dan VARCHAR(500), dan kontrak
 * (KONTRAK-SWARM §3) menetapkan tanggal -> nilai_angka. YYYYMMDD utuh sampai
 * 9999-12-31, urut secara numerik, dan bisa dibalik tanpa kehilangan hari.
 */
function tabel_tgl_ke_angka(string $iso): int
{
    $d = DateTime::createFromFormat('!Y-m-d', $iso);
    if (!$d || $d->format('Y-m-d') !== $iso) {
        throw new TabelDitolak(422, 'Tanggal tidak sah: ' . $iso);
    }
    return (int) $d->format('Ymd');
}

function tabel_angka_ke_tgl(?string $angka): string
{
    if ($angka === null || $angka === '') {
        return '';
    }
    $n = (int) $angka;
    if ($n < 10000101) {
        return '';
    }
    $iso = sprintf('%04d-%02d-%02d', intdiv($n, 10000), intdiv($n % 10000, 100), $n % 100);
    return DateTime::createFromFormat('!Y-m-d', $iso) ? $iso : '';
}

/** Baris + nilainya untuk satu tabel/prodi/periode. */
function tabel_baris(string $tabelKode, int $prodiId, int $periodeId): array
{
    $baris = q(
        'SELECT b.id, b.urut, b.dikunci_oleh, b.dikunci_pada, p.nama AS pengunci
         FROM baris_tabel b LEFT JOIN pengguna p ON p.id = b.dikunci_oleh
         WHERE b.tabel_kode = ? AND b.prodi_id = ? AND b.periode_id = ?
         ORDER BY b.urut',
        [$tabelKode, $prodiId, $periodeId]);

    if (!$baris) {
        return [];
    }

    $id = array_column($baris, 'id');
    $isi = [];
    foreach (q('SELECT s.baris_id, s.field_kunci, s.nilai_angka, s.nilai_teks
                FROM sel s JOIN baris_tabel b ON b.id = s.baris_id
                WHERE b.tabel_kode = ? AND b.prodi_id = ? AND b.periode_id = ?
                  AND b.id IN (' . implode(',', array_fill(0, count($id), '?')) . ')',
                 [$tabelKode, $prodiId, $periodeId, ...$id]) as $s) {
        $isi[(int) $s['baris_id']][$s['field_kunci']] = $s;
    }

    foreach ($baris as &$b) {
        $b['id'] = (int) $b['id'];
        $b['urut'] = (int) $b['urut'];
        $b['dikunci'] = $b['dikunci_oleh'] !== null;
        $b['nilai'] = $isi[$b['id']] ?? [];
    }
    return $baris;
}

/** Apakah ada baris tabel ini yang terkunci. */
function tabel_terkunci(string $tabelKode, int $prodiId, int $periodeId): bool
{
    return (int) qv(
        'SELECT COUNT(*) FROM baris_tabel
         WHERE tabel_kode = ? AND prodi_id = ? AND periode_id = ? AND dikunci_oleh IS NOT NULL',
        [$tabelKode, $prodiId, $periodeId], 0) > 0;
}

/**
 * Simpan isian satu tabel. $post berisi sel[urut][kunci] dan hapus[urut].
 * Semua tulisan satu transaksi: gagal satu baris = tidak ada yang tersimpan.
 */
function tabel_simpan(string $tabelKode, int $prodiId, int $periodeId, array $post): array
{
    $info = tabel_info($tabelKode);
    if (!berlaku_untuk_prodi($tabelKode, $prodiId)) {
        throw new TabelDitolak(409, 'Tabel ' . $info['nomor'] . ' tidak berlaku untuk prodi ini.');
    }
    if (tabel_terkunci($tabelKode, $prodiId, $periodeId)) {
        throw new TabelDitolak(403, 'Tabel sudah dikunci. Minta Admin membuka kunci dulu.');
    }

    $field = [];
    foreach (tabel_field($tabelKode) as $f) {
        if ($f['asal'] === 'Input') {
            $field[$f['kunci']] = $f;
        }
    }
    if (!$field) {
        throw new TabelDitolak(409, 'Tabel ini tidak punya field yang bisa diisi operator.');
    }

    $sel = is_array($post['sel'] ?? null) ? $post['sel'] : [];
    // hapus[] = daftar nomor urut baris yang dibuang. Daftar, bukan peta
    // urut=>tanda: satu baris form tidak perlu mengirim field untuk setiap
    // baris yang TIDAK dihapus (tabel 239 baris akan menembus max_input_vars).
    $hapus = array_filter((array) ($post['hapus'] ?? []), 'is_scalar');

    // Validasi semua nilai DULU: satu angka salah tidak boleh membatalkan
    // separuh isian yang sudah benar, dan tidak boleh tersimpan separuh.
    $bersih = [];
    foreach ($sel as $urut => $nilaiBaris) {
        $urut = (int) $urut;
        if ($urut < 1 || !is_array($nilaiBaris)) {
            continue;
        }
        foreach ($nilaiBaris as $kunci => $v) {
            if (!isset($field[$kunci]) || !is_string($v)) {
                continue;                       // field non-Input: abaikan, jangan simpan
            }
            $v = trim($v);
            if ($v === '') {
                $bersih[$urut][$kunci] = null;  // kosong = hapus sel
                continue;
            }
            $tipe = $field[$kunci]['tipe'];
            if (tabel_kolom($tipe) === 'angka') {
                if ($tipe === 'tanggal') {
                    $bersih[$urut][$kunci] = (string) tabel_tgl_ke_angka($v);
                } else {
                    $n = str_replace([' ', ','], ['', '.'], $v);
                    if (!is_numeric($n)) {
                        throw new TabelDitolak(422, sprintf(
                            'Baris %d, "%s": "%s" bukan angka.', $urut, $field[$kunci]['label'], $v));
                    }
                    $bersih[$urut][$kunci] = (string) (float) $n;
                }
            } else {
                if (mb_strlen($v) > 500) {
                    throw new TabelDitolak(422, sprintf(
                        'Baris %d, "%s": teks lebih dari 500 karakter.', $urut, $field[$kunci]['label']));
                }
                $bersih[$urut][$kunci] = $v;
            }
        }
    }

    return transaksi(function () use ($tabelKode, $prodiId, $periodeId, $bersih, $hapus, $field): array {
        $dipakai = 0;
        $ditulis = 0;
        $dibuang = 0;

        foreach ($hapus as $urut) {
            $urut = (int) $urut;
            if ($urut < 1) {
                continue;
            }
            $id = qv('SELECT id FROM baris_tabel WHERE tabel_kode = ? AND prodi_id = ?
                      AND periode_id = ? AND urut = ?', [$tabelKode, $prodiId, $periodeId, $urut]);
            if ($id !== null) {
                ex('DELETE FROM baris_tabel WHERE id = ?', [(int) $id]); // sel ikut terhapus (FK cascade)
                $dibuang++;
            }
            unset($bersih[$urut]);
        }

        foreach ($bersih as $urut => $nilai) {
            $id = qv('SELECT id FROM baris_tabel WHERE tabel_kode = ? AND prodi_id = ?
                      AND periode_id = ? AND urut = ?', [$tabelKode, $prodiId, $periodeId, $urut]);
            if ($id === null) {
                $id = sisip('baris_tabel', [
                    'prodi_id' => $prodiId, 'periode_id' => $periodeId,
                    'tabel_kode' => $tabelKode, 'urut' => $urut,
                ]);
            }
            $dipakai++;
            foreach ($nilai as $kunci => $v) {
                if ($v === null) {
                    $dibuang += ex('DELETE FROM sel WHERE baris_id = ? AND field_kunci = ?', [$id, $kunci]);
                    continue;
                }
                $kolom = tabel_kolom($field[$kunci]['tipe']);
                ex('INSERT INTO sel (baris_id, field_kunci, nilai_angka, nilai_teks)
                    VALUES (?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE nilai_angka = VALUES(nilai_angka),
                                            nilai_teks = VALUES(nilai_teks)',
                    [$id, $kunci,
                     $kolom === 'angka' ? $v : null,
                     $kolom === 'teks' ? $v : null]);
                $ditulis++;
            }
        }

        return ['baris' => $dipakai, 'sel' => $ditulis, 'dibuang' => $dibuang];
    });
}

/** Kunci tabel. Hanya kalau sudah lengkap — mengunci tabel setengah jadi
 *  membuat verifikator memeriksa data yang masih akan berubah. */
function tabel_kunci(string $tabelKode, int $prodiId, int $periodeId, int $oleh): int
{
    $info = tabel_info($tabelKode);
    $s = status_satu_tabel($tabelKode, $prodiId, $periodeId);
    if ($s['status'] !== 'lengkap') {
        throw new TabelDitolak(409, sprintf(
            'Tabel %s belum lengkap (%d/%d field). Kunci hanya untuk tabel lengkap.',
            $info['nomor'], $s['terisi'], $s['total']));
    }
    return ex('UPDATE baris_tabel SET dikunci_oleh = ?, dikunci_pada = NOW()
               WHERE tabel_kode = ? AND prodi_id = ? AND periode_id = ?',
        [$oleh, $tabelKode, $prodiId, $periodeId]);
}

function tabel_buka(string $tabelKode, int $prodiId, int $periodeId): int
{
    return ex('UPDATE baris_tabel SET dikunci_oleh = NULL, dikunci_pada = NULL
               WHERE tabel_kode = ? AND prodi_id = ? AND periode_id = ?',
        [$tabelKode, $prodiId, $periodeId]);
}
