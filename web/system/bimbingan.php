<?php
declare(strict_types=1);

/**
 * Bimbingan tugas akhir — sumber angka Tabel 21.
 *
 * Tanpa halaman ini Tabel 21 SELALU kosong: `bimbingan` tidak punya form di
 * mana pun, jadi angka yang dihitung `tabel21.php` tidak akan pernah ada.
 * "Dihitung dari master" hanya masuk akal kalau ada jalan mengisi masternya.
 *
 * Dipisah dari api.php supaya aturannya satu tempat dan bisa diuji tanpa HTTP.
 * Pemanggil WAJIB sudah memeriksa `wajib('ISI')` / `wajib_masuk()`.
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/view.php';

const BMB_STATUS = ['Berjalan', 'Selesai', 'Gagal'];

/**
 * Daftar bimbingan satu prodi, terbaru di atas.
 *
 * Nama mahasiswa & dosen di-JOIN karena tabel ini akan dibaca operator yang
 * mengenali orangnya, bukan id-nya.
 */
function bimbingan_daftar(int $prodiId): array
{
    $rows = q(
        'SELECT b.*, d.nama AS dosen_nama, d.gelar_akademik,
                m.nama AS mhs_nama, m.nim
         FROM bimbingan b
         JOIN dosen d ON d.id = b.dosen_id
         LEFT JOIN mahasiswa m ON m.id = b.mahasiswa_id
         WHERE b.prodi_id = ?
         ORDER BY b.tahun_akademik DESC, d.nama, m.nama',
        [$prodiId]);

    foreach ($rows as &$r) {
        $r['id'] = (int) $r['id'];
        $r['tahun_akademik'] = (int) $r['tahun_akademik'];
        $r['dosen_id'] = (int) $r['dosen_id'];
        $r['mahasiswa_id'] = $r['mahasiswa_id'] === null ? null : (int) $r['mahasiswa_id'];
        $r['jumlah_pertemuan'] = $r['jumlah_pertemuan'] === null ? null : (int) $r['jumlah_pertemuan'];
        $r['dosen'] = trim($r['dosen_nama'] . ($r['gelar_akademik'] ? ', ' . $r['gelar_akademik'] : ''));
        $r['mahasiswa'] = $r['mhs_nama'] ?? null;

        // Lama bimbingan dalam bulan, dihitung bukan disimpan. Menyimpan angka
        // turunan berarti angka itu bisa basi saat tanggal diperbaiki.
        $r['lama_bulan'] = ($r['mulai'] !== null && $r['selesai'] !== null)
            ? round(bimbingan_hari($r['mulai'], $r['selesai']) / 30.44, 1)
            : null;
    }
    unset($r);

    return $rows;
}

/** Selisih hari dua tanggal ISO. Negatif kalau selesai sebelum mulai. */
function bimbingan_hari(?string $mulai, ?string $selesai): int
{
    if ($mulai === null || $selesai === null || $mulai === '' || $selesai === '') {
        return 0;
    }
    try {
        $a = new DateTimeImmutable($mulai);
        $b = new DateTimeImmutable($selesai);
    } catch (Throwable) {
        return 0;
    }
    return (int) $a->diff($b)->format('%r%a');
}

/**
 * Periksa satu set input bimbingan. Mengembalikan daftar galat (kosong = sah).
 *
 * Dikembalikan sebagai daftar, bukan dilempar sebagai exception: pemanggil bisa
 * menampilkan SEMUA yang salah sekaligus, tidak satu per satu.
 */
function bimbingan_periksa(array $in, int $prodiId, ?int $id = null): array
{
    $galat = [];

    $dosenId = (int) ($in['dosen_id'] ?? 0);
    if ($dosenId <= 0) {
        $galat[] = 'Dosen pembimbing wajib dipilih.';
    } elseif (!q1("SELECT id FROM dosen WHERE id = ? AND jenis <> 'Tendik'", [$dosenId])) {
        $galat[] = 'Dosen tidak ditemukan atau terdaftar sebagai Tenaga Kependidikan.';
    }

    $tahun = (int) ($in['tahun_akademik'] ?? 0);
    if ($tahun < 2000 || $tahun > 2100) {
        $galat[] = 'Tahun akademik wajib diisi (mis. 2025).';
    }

    $mhsId = $in['mahasiswa_id'] ?? null;
    $mhsId = ($mhsId === null || $mhsId === '') ? null : (int) $mhsId;
    if ($mhsId !== null && !q1("SELECT id FROM mahasiswa WHERE id = ? AND prodi_id = ? AND status = 'Aktif'",
            [$mhsId, $prodiId])) {
        $galat[] = 'Mahasiswa tidak ditemukan di prodi ini.';
    }

    $pertemuan = $in['jumlah_pertemuan'] ?? null;
    if ($pertemuan !== null && $pertemuan !== '') {
        $p = (int) $pertemuan;
        if ($p < 0 || $p > 500) {
            $galat[] = 'Jumlah pertemuan harus antara 0 dan 500.';
        }
    }

    $status = (string) ($in['status'] ?? 'Berjalan');
    if (!in_array($status, BMB_STATUS, true)) {
        $galat[] = 'Status harus salah satu dari: ' . implode(', ', BMB_STATUS) . '.';
    }

    $mulai = ($in['mulai'] ?? '') !== '' ? (string) $in['mulai'] : null;
    $selesai = ($in['selesai'] ?? '') !== '' ? (string) $in['selesai'] : null;

    foreach (['mulai' => $mulai, 'selesai' => $selesai] as $nama => $tgl) {
        if ($tgl !== null && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $tgl)) {
            $galat[] = "Tanggal $nama harus berbentuk YYYY-MM-DD.";
        }
    }
    if ($mulai !== null && $selesai !== null && $selesai < $mulai) {
        $galat[] = 'Tanggal selesai tidak boleh sebelum tanggal mulai.';
    }

    // Selesai tanpa tanggal selesai = angka "lama bimbingan" tidak bisa
    // dihitung, dan Tabel 21 akan menampilkan kosong. Lebih baik ditolak
    // sekarang daripada ketahuan saat ekspor.
    if ($status === 'Selesai' && $selesai === null) {
        $galat[] = 'Status "Selesai" menuntut tanggal selesai diisi.';
    }

    // Satu mahasiswa tidak boleh punya dua baris bimbingan untuk dosen dan
    // tahun yang sama — itu akan menghitung pertemuan dua kali.
    if ($mhsId !== null) {
        $bentrok = q1(
            'SELECT id FROM bimbingan
             WHERE prodi_id = ? AND dosen_id = ? AND mahasiswa_id = ? AND tahun_akademik = ?
               AND (? IS NULL OR id <> ?)',
            [$prodiId, $dosenId, $mhsId, $tahun, $id, $id]);
        if ($bentrok) {
            $galat[] = 'Mahasiswa ini sudah tercatat dibimbing dosen tersebut pada tahun yang sama.';
        }
    }

    return $galat;
}

/** Tambah satu baris bimbingan. Pemanggil sudah memeriksa izin & CSRF. */
function bimbingan_tambah(array $in, int $prodiId, int $oleh): array
{
    $galat = bimbingan_periksa($in, $prodiId);
    if ($galat !== []) {
        return ['ok' => false, 'galat' => $galat];
    }

    ex(
        'INSERT INTO bimbingan
           (prodi_id, dosen_id, mahasiswa_id, tahun_akademik, topik,
            jumlah_pertemuan, mulai, selesai, status)
         VALUES (?,?,?,?,?,?,?,?,?)',
        [
            $prodiId,
            (int) $in['dosen_id'],
            ($in['mahasiswa_id'] ?? '') === '' ? null : (int) $in['mahasiswa_id'],
            (int) $in['tahun_akademik'],
            trim((string) ($in['topik'] ?? '')) ?: null,
            ($in['jumlah_pertemuan'] ?? '') === '' ? null : (int) $in['jumlah_pertemuan'],
            ($in['mulai'] ?? '') ?: null,
            ($in['selesai'] ?? '') ?: null,
            $in['status'] ?? 'Berjalan',
        ]);

    return ['ok' => true, 'id' => (int) db()->lastInsertId()];
}

/** Ubah satu baris bimbingan. */
function bimbingan_ubah(int $id, array $in, int $prodiId, int $oleh): array
{
    $lama = q1('SELECT * FROM bimbingan WHERE id = ? AND prodi_id = ?', [$id, $prodiId]);
    if (!$lama) {
        return ['ok' => false, 'galat' => ['Bimbingan tidak ditemukan di prodi ini.']];
    }

    $galat = bimbingan_periksa($in, $prodiId, $id);
    if ($galat !== []) {
        return ['ok' => false, 'galat' => $galat];
    }

    ex(
        'UPDATE bimbingan SET dosen_id = ?, mahasiswa_id = ?, tahun_akademik = ?,
                topik = ?, jumlah_pertemuan = ?, mulai = ?, selesai = ?, status = ?
         WHERE id = ? AND prodi_id = ?',
        [
            (int) $in['dosen_id'],
            ($in['mahasiswa_id'] ?? '') === '' ? null : (int) $in['mahasiswa_id'],
            (int) $in['tahun_akademik'],
            trim((string) ($in['topik'] ?? '')) ?: null,
            ($in['jumlah_pertemuan'] ?? '') === '' ? null : (int) $in['jumlah_pertemuan'],
            ($in['mulai'] ?? '') ?: null,
            ($in['selesai'] ?? '') ?: null,
            $in['status'] ?? 'Berjalan',
            $id, $prodiId,
        ]);

    return ['ok' => true];
}

/**
 * Hapus satu baris bimbingan.
 *
 * Ini satu-satunya tabel yang benar-benar DELETE: `bimbingan` adalah catatan
 * proses, bukan dokumen yang dirujuk tabel lain. Tidak ada riwayat pengisian
 * yang bergantung padanya — kalau barisnya salah, membiarkannya aktif justru
 * membuat Tabel 21 salah.
 */
function bimbingan_hapus(int $id, int $prodiId): array
{
    $ada = q1('SELECT id FROM bimbingan WHERE id = ? AND prodi_id = ?', [$id, $prodiId]);
    if (!$ada) {
        return ['ok' => false, 'galat' => ['Bimbingan tidak ditemukan di prodi ini.']];
    }
    ex('DELETE FROM bimbingan WHERE id = ? AND prodi_id = ?', [$id, $prodiId]);
    return ['ok' => true];
}

/** Pilihan untuk dropdown: dosen & mahasiswa prodi ini. */
function bimbingan_pilihan(int $prodiId): array
{
    // Kolom identitas dosen bernama `nidn_nidk_nup` (satu kolom untuk tiga
    // jenis nomor), bukan `nidn`. `dosen` tidak punya kolom `aktif` — yang
    // membedakan hanya `jenis`; Tendik bukan pembimbing.
    $dosen = array_map(
        fn($r) => ['id' => (int) $r['id'],
                   'nama' => trim($r['nama'] . ($r['gelar_akademik'] ? ', ' . $r['gelar_akademik'] : '')),
                   'nidn' => $r['nidn_nidk_nup']],
        q("SELECT d.id, d.nama, d.gelar_akademik, d.nidn_nidk_nup
           FROM dosen d JOIN dosen_prodi dp ON dp.dosen_id = d.id
           WHERE dp.prodi_id = ? AND d.jenis <> 'Tendik' ORDER BY d.nama", [$prodiId]));

    // Mahasiswa: `tahun_masuk` dan `status`, bukan `angkatan`/`aktif`.
    $mhs = array_map(
        fn($r) => ['id' => (int) $r['id'], 'nama' => $r['nama'], 'nim' => $r['nim']],
        q("SELECT id, nama, nim FROM mahasiswa WHERE prodi_id = ? AND status = 'Aktif' ORDER BY nama",
          [$prodiId]));

    return ['dosen' => $dosen, 'mahasiswa' => $mhs];
}
