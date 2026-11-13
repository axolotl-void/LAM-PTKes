<?php
declare(strict_types=1);

/**
 * Logika modul pengguna — dipisah dari index.php supaya bisa diuji tanpa HTTP.
 *
 * Dua jaminan yang tidak boleh hilang:
 *   1. Admin tidak bisa menonaktifkan akunnya sendiri.
 *   2. Jumlah Admin aktif tidak boleh jadi 0 lewat perubahan apa pun.
 * Keduanya diperiksa di sini, bukan di tampilan: tombol yang disembunyikan
 * bukan pengaman, request langsung tetap harus ditolak.
 */

require_once __DIR__ . '/../../system/view.php';

/** Semua peran yang sah, dari DB. Urutan tetap supaya UI stabil. */
function peran_peta(): array
{
    static $p = null;
    if ($p !== null) {
        return $p;
    }
    $p = [];
    foreach (q('SELECT kode, nama FROM peran ORDER BY kode') as $r) {
        $p[(string) $r['kode']] = (string) $r['nama'];
    }
    return $p;
}

function peran_label(string $kode): string
{
    return peran_peta()[$kode] ?? $kode;
}

/** Jumlah Admin yang aktif. Dipakai gerbang "jangan sampai 0". */
function admin_aktif(): int
{
    return (int) qv("SELECT COUNT(*) FROM pengguna WHERE peran_kode = 'ADMIN' AND aktif = 1", [], 0);
}

/** Daftar akun + nama prodi. Saringan opsional peran dan status. */
function pengguna_daftar(string $peran = '', string $status = ''): array
{
    $where = [];
    $param = [];
    if ($peran !== '') {
        $where[] = 'p.peran_kode = ?';
        $param[] = $peran;
    }
    if ($status !== '') {
        $where[] = 'p.aktif = ?';
        $param[] = (int) $status;
    }
    $sql = 'SELECT p.id, p.nama, p.email, p.peran_kode, p.prodi_id, p.aktif, p.masuk_terakhir,
                   pr.nama_ps
            FROM pengguna p LEFT JOIN prodi pr ON pr.id = p.prodi_id'
        . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
        . ' ORDER BY p.aktif DESC, p.peran_kode, p.nama';
    return q($sql, $param);
}

function pengguna_ambil(int $id): ?array
{
    return q1('SELECT id, nama, email, peran_kode, prodi_id, aktif, masuk_terakhir
               FROM pengguna WHERE id = ?', [$id]);
}

function prodi_pilihan(): array
{
    return q('SELECT id, nama_ps, jenjang FROM prodi ORDER BY nama_ps');
}

/**
 * Ubah satu akun. Mengembalikan ['ok'=>bool, 'pesan'=>string, 'berubah'=>int].
 * $m = ['peran_kode'=>?, 'prodi_id'=>? ('' = semua prodi), 'aktif'=>0|1]
 */
function pengguna_ubah(int $id, array $m, array $pelaku): array
{
    $lama = pengguna_ambil($id);
    if (!$lama) {
        return ['ok' => false, 'pesan' => 'Akun tidak ditemukan.', 'berubah' => 0];
    }

    $peran = (string) ($m['peran_kode'] ?? '');
    if (!array_key_exists($peran, peran_peta())) {
        return ['ok' => false, 'pesan' => 'Peran tidak dikenal: ' . $peran, 'berubah' => 0];
    }

    $prodiMasuk = $m['prodi_id'] ?? '';
    $prodi = ($prodiMasuk === '' || $prodiMasuk === null) ? null : (int) $prodiMasuk;
    if ($prodi !== null && !q1('SELECT id FROM prodi WHERE id = ?', [$prodi])) {
        return ['ok' => false, 'pesan' => 'Prodi tidak ditemukan.', 'berubah' => 0];
    }

    $aktif = ((int) ($m['aktif'] ?? 1)) === 1 ? 1 : 0;

    // Jaminan 1: akun sendiri tidak boleh dinonaktifkan. Tanpa ini, satu klik
    // salah dan admin yang sedang bekerja kehilangan jalan masuknya sendiri.
    if ($id === (int) $pelaku['id'] && $aktif === 0) {
        return ['ok' => false, 'berubah' => 0,
            'pesan' => 'Tidak bisa menonaktifkan akun sendiri. Setelah nonaktif, akun ini '
                     . 'tidak bisa masuk lagi, jadi tidak ada yang bisa mengaktifkannya kembali '
                     . 'dari dalam sistem.'];
    }

    // Jaminan 2: Admin aktif terakhir tidak boleh hilang — baik dengan
    // menonaktifkan maupun dengan menurunkan perannya.
    $tetapAdminAktif = ($peran === 'ADMIN' && $aktif === 1);
    if ($lama['peran_kode'] === 'ADMIN' && (int) $lama['aktif'] === 1 && !$tetapAdminAktif
        && admin_aktif() <= 1) {
        return ['ok' => false, 'berubah' => 0,
            'pesan' => 'Ditolak: perubahan ini membuat jumlah Admin aktif jadi 0. '
                     . 'Sistem akan terkunci — tidak ada lagi akun yang boleh mengelola pengguna. '
                     . 'Angkat Admin lain dulu.'];
    }

    $baru = [
        'peran_kode' => $peran,
        'prodi_id'   => $prodi,
        'aktif'      => $aktif,
    ];
    $beda = [];
    foreach ($baru as $kol => $nilai) {
        $sebelum = $lama[$kol];
        $sebelum = $sebelum === null ? null : (is_int($sebelum) ? $sebelum : (string) $sebelum);
        if ((string) $sebelum !== (string) $nilai || ($sebelum === null) !== ($nilai === null)) {
            $beda[$kol] = [$sebelum, $nilai];
        }
    }
    if (!$beda) {
        return ['ok' => true, 'pesan' => 'Tidak ada perubahan.', 'berubah' => 0];
    }

    // Satu baris audit per field yang berubah — riwayat harus bisa dibaca
    // sebagai "dari apa ke apa", bukan satu blob yang harus diurai.
    transaksi(function () use ($id, $baru, $beda, $pelaku) {
        ubah('pengguna', $baru, 'id', $id);
        foreach ($beda as $kol => [$sebelum, $sesudah]) {
            sisip('audit', [
                'pengguna_id' => (int) $pelaku['id'],
                'prodi_id'    => $pelaku['prodi_id'] !== null ? (int) $pelaku['prodi_id'] : null,
                'aksi'        => 'ubah akun',
                'entitas'     => 'pengguna',
                'entitas_id'  => (string) $id,
                'field'       => $kol,
                'nilai_lama'  => $sebelum === null ? null : (string) $sebelum,
                'nilai_baru'  => $sesudah === null ? null : (string) $sesudah,
            ]);
        }
    });

    return ['ok' => true, 'pesan' => sprintf('Akun %s diperbarui (%d field).',
        $lama['nama'], count($beda)), 'berubah' => count($beda)];
}

/** Matriks izin 24 baris, dibaca dari tabel izin. Tidak ada nilai yang diketik. */
function izin_matriks(): array
{
    $baris = q('SELECT peran_kode, aksi_kode, akses FROM izin');
    $urutAksi = array_flip(AKSI);
    $urutPeran = array_flip(array_keys(peran_peta()));
    usort($baris, function ($a, $b) use ($urutAksi, $urutPeran) {
        return [$urutPeran[$a['peran_kode']] ?? 99, $urutAksi[$a['aksi_kode']] ?? 99]
           <=> [$urutPeran[$b['peran_kode']] ?? 99, $urutAksi[$b['aksi_kode']] ?? 99];
    });
    return $baris;
}

const ARTI_AKSES = [
    0 => ['Tidak boleh', 'server menolak dengan 403'],
    1 => ['Boleh', 'boleh mengubah data'],
    2 => ['Hanya lihat', 'boleh membaca, setiap tulis ditolak 403'],
];

/**
 * HTML matriks izin. Read-only memang, tapi yang membuatnya mengikat adalah
 * `akses()` — fungsi yang sama dipakai setiap gerbang wajib() di server.
 */
function pengguna_matriks_html(): string
{
    $m = [];
    foreach (izin_matriks() as $b) {
        $m[(string) $b['peran_kode']][(string) $b['aksi_kode']] = (int) $b['akses'];
    }
    $h = '<div class="tbl-wrap"><table class="tbl tbl-izin"><thead><tr><th>Peran</th>';
    foreach (AKSI as $a) {
        $h .= '<th class="r">' . e($a) . '</th>';
    }
    $h .= '</tr></thead><tbody>';
    foreach (peran_peta() as $kode => $nama) {
        $h .= '<tr><td class="strong">' . e($nama) . '</td>';
        foreach (AKSI as $a) {
            $v = $m[$kode][$a] ?? 0;
            [$arti] = ARTI_AKSES[$v] ?? ARTI_AKSES[0];
            $h .= sprintf('<td class="iz iz-%d">%d <span class="iz-arti">%s</span></td>',
                $v, $v, e($arti));
        }
        $h .= '</tr>';
    }
    return $h . '</tbody></table></div>';
}

/** Riwayat perubahan akun dari tabel audit, terbaru dulu. */
function audit_akun(int $batas = 10): array
{
    return q('SELECT a.id, a.waktu, a.entitas_id, a.field, a.nilai_lama, a.nilai_baru,
                     u.nama AS pelaku, t.nama AS sasaran
              FROM audit a
              LEFT JOIN pengguna u ON u.id = a.pengguna_id
              LEFT JOIN pengguna t ON t.id = CAST(a.entitas_id AS UNSIGNED)
              WHERE a.entitas = ? ORDER BY a.id DESC LIMIT ' . max(1, $batas),
        ['pengguna']);
}
