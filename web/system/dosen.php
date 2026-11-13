<?php
declare(strict_types=1);

/**
 * Dosen & Tendik — baca & tulis.
 *
 * ATURAN YANG MENENTUKAN BENTUK DATA: satu orang = SATU baris, walaupun
 * mengajar di dua prodi. Prodi disimpan di `dosen_prodi` (many-to-many).
 * Prototipe lama menduplikasi baris per prodi, sehingga "jumlah dosen"
 * terhitung dobel dan rasio dosen:mahasiswa di Tabel 15 salah. Rasio itu
 * dipakai asesor, jadi tidak boleh salah.
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/rekap.php';
require_once __DIR__ . '/view.php';

const DOSEN_JENIS = ['Dosen Tetap', 'Dosen Tidak Tetap', 'Tendik'];
const DOSEN_JABATAN = ['Belum Memiliki', 'Asisten Ahli', 'Lektor', 'Lektor Kepala', 'Guru Besar'];
const DOSEN_PROFESI = ['Bidan', 'Perawat', 'Ners'];
const DOSEN_TAHAP = ['Akademik', 'Profesi', 'Keduanya'];
const DOSEN_JENJANG = ['D3', 'D4', 'S1', 'Profesi', 'S2', 'S3'];

/**
 * Daftar dosen + prodi yang diajar + hitungan SKS/keaktifan.
 * $saring: ['jenis'=>, 'prodi'=>, 'cari'=>]
 */
function dosen_daftar(array $saring = []): array
{
    $where = ['1=1'];
    $param = [];

    if (!empty($saring['jenis']) && in_array($saring['jenis'], DOSEN_JENIS, true)) {
        $where[] = 'd.jenis = ?';
        $param[] = $saring['jenis'];
    }
    if (!empty($saring['prodi'])) {
        $where[] = 'EXISTS (SELECT 1 FROM dosen_prodi x WHERE x.dosen_id = d.id AND x.prodi_id = ?)';
        $param[] = (int) $saring['prodi'];
    }
    if (!empty($saring['cari'])) {
        $where[] = '(d.nama LIKE ? OR d.nidn_nidk_nup LIKE ?)';
        $cari = '%' . $saring['cari'] . '%';
        $param[] = $cari;
        $param[] = $cari;
    }

    $rows = q('SELECT d.id, d.nidn_nidk_nup, d.nama, d.jenis, d.jabatan_fungsional,
                      d.gelar_akademik, d.tanggal_lahir, d.sertifikat_pendidik,
                      d.profesi, d.no_str, d.tanggal_str, d.tahap_mengajar, d.email,
                      d.dibuat, d.diubah
               FROM dosen d
               WHERE ' . implode(' AND ', $where) . '
               ORDER BY FIELD(d.jenis, "Dosen Tetap", "Dosen Tidak Tetap", "Tendik"),
                        d.nama', $param);

    if ($rows === []) {
        return [];
    }

    $ids = array_column($rows, 'id');
    $in = implode(',', array_fill(0, count($ids), '?'));

    // Prodi per dosen — satu query, bukan N+1.
    $prodi = [];
    foreach (q("SELECT dp.dosen_id, p.id, p.nama_ps
                FROM dosen_prodi dp JOIN prodi p ON p.id = dp.prodi_id
                WHERE dp.dosen_id IN ($in) ORDER BY p.id", $ids) as $r) {
        $prodi[(int) $r['dosen_id']][] = ['id' => (int) $r['id'], 'nama' => $r['nama_ps']];
    }

    $pendidikan = [];
    foreach (q("SELECT * FROM dosen_pendidikan WHERE dosen_id IN ($in)
                ORDER BY tahun_lulus DESC, id", $ids) as $r) {
        $pendidikan[(int) $r['dosen_id']][] = [
            'id' => (int) $r['id'], 'jenjang' => $r['jenjang'], 'bidang' => $r['bidang'],
            'asal_pt' => $r['asal_pt'], 'tahun_lulus' => $r['tahun_lulus'] !== null ? (int) $r['tahun_lulus'] : null,
        ];
    }

    $hariIni = new DateTimeImmutable('today');
    foreach ($rows as &$d) {
        $d['id'] = (int) $d['id'];
        $d['sertifikat_pendidik'] = (int) $d['sertifikat_pendidik'] === 1;
        $d['prodi'] = $prodi[$d['id']] ?? [];
        $d['pendidikan'] = $pendidikan[$d['id']] ?? [];
        $d['nama_lengkap'] = trim(
            ($d['gelar_akademik'] ? '' : '') . $d['nama']
            . ($d['gelar_akademik'] ? ', ' . $d['gelar_akademik'] : '')
        );

        // STR: dokumen profesi yang punya masa berlaku. Kalau lewat, dosen
        // masih berhak mengajar tapi tidak bisa jadi pembimbing klinik —
        // asesor menanyakan ini di Tabel 16.
        if ($d['tanggal_str'] === null) {
            $d['status_str'] = 'tidak ada';
            $d['sisa_str'] = null;
        } else {
            $akhir = new DateTimeImmutable((string) $d['tanggal_str']);
            $sisa = (int) $hariIni->diff($akhir)->format('%r%a');
            $d['status_str'] = $sisa < 0 ? 'kedaluwarsa' : 'berlaku';
            $d['sisa_str'] = $sisa;
        }
        $d['tanggal_str_teks'] = $d['tanggal_str'] ? tgl((string) $d['tanggal_str']) : null;
    }
    return $rows;
}

/** Ringkasan untuk strip. Dihitung, bukan diketik. */
function dosen_ringkas(array $daftar): array
{
    $h = ['Dosen Tetap' => 0, 'Dosen Tidak Tetap' => 0, 'Tendik' => 0];
    $sertifikat = 0;
    $strKedaluwarsa = 0;
    $tanpaNidn = 0;
    foreach ($daftar as $d) {
        $h[$d['jenis']] = ($h[$d['jenis']] ?? 0) + 1;
        if ($d['sertifikat_pendidik']) {
            $sertifikat++;
        }
        if ($d['status_str'] === 'kedaluwarsa') {
            $strKedaluwarsa++;
        }
        if (trim((string) $d['nidn_nidk_nup']) === '') {
            $tanpaNidn++;
        }
    }
    return [
        'total' => count($daftar),
        'tetap' => $h['Dosen Tetap'],
        'tidak_tetap' => $h['Dosen Tidak Tetap'],
        'tendik' => $h['Tendik'],
        'sertifikat' => $sertifikat,
        'str_kedaluwarsa' => $strKedaluwarsa,
        'tanpa_nidn' => $tanpaNidn,
    ];
}

/** Validasi + simpan. $id = 0 berarti tambah. */
function dosen_simpan(array $d, int $id = 0): array
{
    $nama = trim((string) ($d['nama'] ?? ''));
    if ($nama === '') {
        return ['error' => 'Nama wajib diisi.'];
    }
    if (mb_strlen($nama) > 160) {
        return ['error' => 'Nama maksimal 160 karakter.'];
    }

    $jenis = in_array($d['jenis'] ?? '', DOSEN_JENIS, true) ? $d['jenis'] : null;
    if ($jenis === null) {
        return ['error' => 'Jenis tidak dikenal. Pilihan: ' . implode(', ', DOSEN_JENIS)];
    }

    $nidn = trim((string) ($d['nidn_nidk_nup'] ?? ''));
    if ($nidn !== '' && mb_strlen($nidn) > 32) {
        return ['error' => 'NIDN/NIDK/NUP maksimal 32 karakter.'];
    }
    if ($nidn !== '') {
        // NIDN unik — dua orang tidak boleh punya NIDN sama.
        $bentrok = q1('SELECT id, nama FROM dosen WHERE nidn_nidk_nup = ? AND id <> ?',
            [$nidn, $id]);
        if ($bentrok) {
            return ['error' => 'NIDN ' . $nidn . ' sudah dipakai: ' . $bentrok['nama']];
        }
    }

    $jabatan = $d['jabatan_fungsional'] ?? null;
    if (!in_array($jabatan, DOSEN_JABATAN, true)) {
        $jabatan = null;
    }
    $profesi = $d['profesi'] ?? null;
    if (!in_array($profesi, DOSEN_PROFESI, true)) {
        $profesi = null;
    }
    $tahap = $d['tahap_mengajar'] ?? null;
    if (!in_array($tahap, DOSEN_TAHAP, true)) {
        $tahap = null;
    }

    $lahir = dosen_tanggal($d['tanggal_lahir'] ?? null);
    $str = dosen_tanggal($d['tanggal_str'] ?? null);
    if ($lahir === false) {
        return ['error' => 'Tanggal lahir harus format YYYY-MM-DD.'];
    }
    if ($str === false) {
        return ['error' => 'Tanggal STR harus format YYYY-MM-DD.'];
    }
    // STR berlaku 5 tahun dan diperpanjang, jadi tanggal jauh di depan itu
    // NORMAL. Yang perlu ditahan hanya salah ketik tahun (mis. 2206).
    // Batas 15 tahun: STR terpanjang yang sah masih di bawah itu.
    if ($str !== null && $str > (new DateTimeImmutable('+15 years'))->format('Y-m-d')) {
        return ['error' => 'Tanggal STR terlalu jauh ke depan — periksa tahunnya.'];
    }

    $email = trim((string) ($d['email'] ?? ''));
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['error' => 'Format email tidak sah.'];
    }

    $prodiIds = array_values(array_filter(array_map('intval', (array) ($d['prodi'] ?? [])), fn($x) => $x > 0));

    $data = [
        'nidn_nidk_nup' => $nidn === '' ? null : $nidn,
        'nama' => $nama,
        'jenis' => $jenis,
        'jabatan_fungsional' => $jabatan,
        'gelar_akademik' => dosen_teks($d['gelar_akademik'] ?? null, 120),
        'tanggal_lahir' => $lahir,
        'sertifikat_pendidik' => !empty($d['sertifikat_pendidik']) ? 1 : 0,
        'profesi' => $profesi,
        'no_str' => dosen_teks($d['no_str'] ?? null, 60),
        'tanggal_str' => $str,
        'tahap_mengajar' => $tahap,
        'email' => $email === '' ? null : $email,
    ];

    return transaksi(static function () use ($data, $d, $id, $prodiIds) {
        if ($id > 0) {
            if (!q1('SELECT id FROM dosen WHERE id = ?', [$id])) {
                return ['error' => 'Dosen tidak ditemukan.'];
            }
            ubah('dosen', $data, 'id', $id);
            $idBaru = $id;
        } else {
            $idBaru = sisip('dosen', $data);
        }

        // Prodi: ganti set penuh. Satu orang tetap satu baris dosen.
        ex('DELETE FROM dosen_prodi WHERE dosen_id = ?', [$idBaru]);
        foreach ($prodiIds as $pid) {
            ex('INSERT IGNORE INTO dosen_prodi (dosen_id, prodi_id) VALUES (?,?)', [$idBaru, $pid]);
        }

        // Riwayat pendidikan: ganti set penuh, hanya baris yang berisi.
        if (isset($d['pendidikan']) && is_array($d['pendidikan'])) {
            ex('DELETE FROM dosen_pendidikan WHERE dosen_id = ?', [$idBaru]);
            foreach ($d['pendidikan'] as $p) {
                $jenjang = trim((string) ($p['jenjang'] ?? ''));
                if ($jenjang === '') {
                    continue;
                }
                $tahun = (int) ($p['tahun_lulus'] ?? 0);
                sisip('dosen_pendidikan', [
                    'dosen_id' => $idBaru,
                    'jenjang' => mb_substr($jenjang, 0, 40),
                    'bidang' => dosen_teks($p['bidang'] ?? null, 120),
                    'asal_pt' => dosen_teks($p['asal_pt'] ?? null, 160),
                    'tahun_lulus' => ($tahun >= 1950 && $tahun <= 2100) ? $tahun : null,
                ]);
            }
        }

        ex('INSERT INTO audit (pengguna_id, prodi_id, aksi, entitas, entitas_id, nilai_baru)
            VALUES (?,?,?,?,?,?)',
            [pengguna()['id'] ?? null, prodi_aktif(), $id > 0 ? 'ubah' : 'tambah',
             'dosen', (string) $idBaru, json_encode($data, JSON_UNESCAPED_UNICODE)]);

        return ['ok' => true, 'id' => $idBaru];
    });
}

/**
 * Hapus = nonaktifkan? Tabel dosen tidak punya kolom `aktif`.
 * Menambah kolom lebih jujur daripada memaksa NULL-kan NIDN.
 */
function dosen_nonaktifkan(int $id): array
{
    $d = q1('SELECT nama FROM dosen WHERE id = ?', [$id]);
    if (!$d) {
        return ['error' => 'Dosen tidak ditemukan.'];
    }
    // Jadikan Tendik? Tidak. Yang benar: hapus hanya kalau tidak dipakai.
    $dipakai = (int) qv('SELECT COUNT(*) FROM dosen_pendidikan WHERE dosen_id = ?', [$id], 0)
        + (int) qv('SELECT COUNT(*) FROM stase WHERE pembimbing_id = ?', [$id], 0);
    if ($dipakai > 0) {
        return ['error' => 'Tidak bisa dihapus: masih dipakai ' . $dipakai
            . ' catatan (riwayat pendidikan / pembimbing stase). Ubah namanya kalau memang salah orang.'];
    }
    ex('DELETE FROM dosen_prodi WHERE dosen_id = ?', [$id]);
    ex('DELETE FROM dosen WHERE id = ?', [$id]);
    ex('INSERT INTO audit (pengguna_id, prodi_id, aksi, entitas, entitas_id, nilai_lama)
        VALUES (?,?,?,?,?,?)',
        [pengguna()['id'] ?? null, prodi_aktif(), 'hapus', 'dosen', (string) $id, (string) $d['nama']]);
    return ['ok' => true, 'hapus' => $id];
}

function dosen_tanggal(mixed $v): string|null|false
{
    if ($v === null || $v === '') {
        return null;
    }
    $s = substr(trim((string) $v), 0, 10);
    $t = DateTimeImmutable::createFromFormat('Y-m-d', $s);
    if ($t === false || $t->format('Y-m-d') !== $s) {
        return false;
    }
    return $s;
}

function dosen_teks(mixed $v, int $maks): ?string
{
    $s = trim((string) ($v ?? ''));
    return $s === '' ? null : mb_substr($s, 0, $maks);
}
