<?php
declare(strict_types=1);

/**
 * Kerja sama (Tabel 38) — baca & tulis.
 *
 * Dipisah dari api.php supaya aturannya satu tempat dan bisa diuji tanpa HTTP.
 * Pemanggil WAJIB sudah memeriksa `wajib('ISI')` / `wajib_masuk()`.
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/view.php';

const MITRA_JENIS = ['Rumah Sakit', 'Rumah Sakit Jiwa', 'Puskesmas', 'Klinik',
                     'BPM', 'PMB', 'Industri', 'PT Mitra', 'Pemerintah', 'Lainnya'];
const MITRA_LINGKUP = ['Pendidikan', 'Penelitian', 'PkM', 'Ketiganya'];

/**
 * Daftar mitra + status MoU yang DIHITUNG dari tanggal, bukan disimpan.
 *
 * Kenapa dihitung: `status_dokumen` bisa jadi basi sehari setelah disimpan.
 * Yang menentukan hanya `tgl_berakhir` vs hari ini. Kolom status_dokumen tetap
 * ada untuk kasus "belum ada dokumen" yang tidak bisa disimpulkan dari tanggal.
 */
function mitra_daftar(?int $prodiId = null): array
{
    $upps = (int) qv('SELECT id FROM upps ORDER BY id LIMIT 1', [], 0);
    $rows = q('SELECT * FROM mitra WHERE aktif = 1 ORDER BY
                 CASE WHEN tgl_berakhir IS NULL THEN 1 ELSE 0 END,
                 tgl_berakhir ASC, nama ASC');
    $hariIni = new DateTimeImmutable('today');

    foreach ($rows as &$m) {
        $m['id'] = (int) $m['id'];
        $m['ada_laporan'] = (int) $m['ada_laporan'] === 1;

        // Status efektif: tanggal menang atas kolom. "belum ada" kalau memang
        // tidak ada nomor dokumen maupun tanggal.
        if ($m['tgl_berakhir'] === null && $m['no_dokumen'] === null) {
            $m['status'] = 'belum ada';
            $m['sisa_hari'] = null;
        } elseif ($m['tgl_berakhir'] === null) {
            $m['status'] = 'berlaku';
            $m['sisa_hari'] = null;   // tanpa tanggal = tidak bisa dihitung
        } else {
            $akhir = new DateTimeImmutable((string) $m['tgl_berakhir']);
            $sisa = (int) $hariIni->diff($akhir)->format('%r%a');
            $m['status'] = $sisa < 0 ? 'kedaluwarsa' : 'berlaku';
            $m['sisa_hari'] = $sisa;
        }

        $m['kegiatan'] = q('SELECT tahun, pendidikan, penelitian, pkm
                            FROM mitra_kegiatan WHERE mitra_id = ? ORDER BY tahun',
            [$m['id']]);
        $m['wahana'] = array_map('intval', array_column(
            q('SELECT wahana_id FROM mitra_wahana WHERE mitra_id = ?', [$m['id']]), 'wahana_id'));
        $m['tgl_berakhir_teks'] = $m['tgl_berakhir'] ? tgl((string) $m['tgl_berakhir']) : null;
        $m['tgl_mulai_teks'] = $m['tgl_mulai'] ? tgl((string) $m['tgl_mulai']) : null;
    }
    return $rows;
}

/** Validasi + simpan. $id = 0 berarti tambah baru. */
function mitra_simpan(array $d, int $id = 0): array
{
    $nama = trim((string) ($d['nama'] ?? ''));
    if ($nama === '') {
        return ['error' => 'Nama mitra wajib diisi.'];
    }
    if (mb_strlen($nama) > 160) {
        return ['error' => 'Nama mitra maksimal 160 karakter.'];
    }
    $jenis = in_array($d['jenis'] ?? '', MITRA_JENIS, true) ? $d['jenis'] : null;
    if ($jenis === null) {
        return ['error' => 'Jenis mitra tidak dikenal. Pilihan: ' . implode(', ', MITRA_JENIS)];
    }
    $lingkup = in_array($d['lingkup'] ?? '', MITRA_LINGKUP, true) ? $d['lingkup'] : 'Pendidikan';

    // Tanggal: dua-duanya boleh kosong, tapi kalau dua-duanya ada harus urut.
    $mulai = mitra_tanggal($d['tgl_mulai'] ?? null);
    $akhir = mitra_tanggal($d['tgl_berakhir'] ?? null);
    if ($mulai === false || $akhir === false) {
        return ['error' => 'Format tanggal harus YYYY-MM-DD.'];
    }
    if ($mulai !== null && $akhir !== null && $akhir < $mulai) {
        return ['error' => 'Tanggal berakhir tidak boleh lebih awal dari tanggal mulai.'];
    }

    $upps = (int) qv('SELECT id FROM upps ORDER BY id LIMIT 1', [], 0);
    $data = [
        'upps_id' => $upps,
        'nama' => $nama,
        'jenis' => $jenis,
        'lingkup' => $lingkup,
        'alamat' => mitra_teks($d['alamat'] ?? null, 255),
        'kota' => mitra_teks($d['kota'] ?? null, 80),
        'no_dokumen' => mitra_teks($d['no_dokumen'] ?? null, 120),
        'tgl_mulai' => $mulai,
        'tgl_berakhir' => $akhir,
        'status_dokumen' => 'berlaku',   // status efektif dihitung saat baca
        'ada_laporan' => !empty($d['ada_laporan']) ? 1 : 0,
        'catatan' => mitra_teks($d['catatan'] ?? null, 255),
    ];

    return transaksi(static function () use ($data, $d, $id) {
        if ($id > 0) {
            $lama = q1('SELECT * FROM mitra WHERE id = ?', [$id]);
            if (!$lama) {
                return ['error' => 'Mitra tidak ditemukan.'];
            }
            ubah('mitra', $data, 'id', $id);
            $idBaru = $id;
        } else {
            $idBaru = sisip('mitra', $data);
        }

        // Kegiatan per tahun: ganti seluruhnya, karena form mengirim set penuh.
        if (isset($d['kegiatan']) && is_array($d['kegiatan'])) {
            ex('DELETE FROM mitra_kegiatan WHERE mitra_id = ?', [$idBaru]);
            foreach ($d['kegiatan'] as $k) {
                $tahun = (int) ($k['tahun'] ?? 0);
                if ($tahun < 2000 || $tahun > 2100) {
                    continue;
                }
                sisip('mitra_kegiatan', [
                    'mitra_id' => $idBaru, 'tahun' => $tahun,
                    'pendidikan' => max(0, (int) ($k['pendidikan'] ?? 0)),
                    'penelitian' => max(0, (int) ($k['penelitian'] ?? 0)),
                    'pkm' => max(0, (int) ($k['pkm'] ?? 0)),
                ]);
            }
        }

        // Wahana yang dilayani.
        if (isset($d['wahana']) && is_array($d['wahana'])) {
            ex('DELETE FROM mitra_wahana WHERE mitra_id = ?', [$idBaru]);
            foreach ($d['wahana'] as $wid) {
                $wid = (int) $wid;
                if ($wid > 0) {
                    ex('INSERT IGNORE INTO mitra_wahana (mitra_id, wahana_id) VALUES (?,?)',
                        [$idBaru, $wid]);
                }
            }
        }

        ex('INSERT INTO audit (pengguna_id, prodi_id, aksi, entitas, entitas_id, nilai_baru)
            VALUES (?,?,?,?,?,?)',
            [pengguna()['id'] ?? null, prodi_aktif(), $id > 0 ? 'ubah' : 'tambah',
             'mitra', (string) $idBaru, json_encode($data, JSON_UNESCAPED_UNICODE)]);

        return ['ok' => true, 'id' => $idBaru];
    });
}

function mitra_hapus(int $id): array
{
    $m = q1('SELECT nama FROM mitra WHERE id = ?', [$id]);
    if (!$m) {
        return ['error' => 'Mitra tidak ditemukan.'];
    }
    // Nonaktifkan, bukan DELETE: Tabel 30/38 bisa merujuknya dan riwayat
    // pengisian harus tetap punya nama.
    ex('UPDATE mitra SET aktif = 0 WHERE id = ?', [$id]);
    ex('INSERT INTO audit (pengguna_id, prodi_id, aksi, entitas, entitas_id, nilai_lama)
        VALUES (?,?,?,?,?,?)',
        [pengguna()['id'] ?? null, prodi_aktif(), 'nonaktif', 'mitra', (string) $id, (string) $m['nama']]);
    return ['ok' => true, 'nonaktif' => $id];
}

/** Ringkasan untuk strip halaman Kerja Sama. Dihitung, bukan diketik. */
function mitra_ringkas(array $daftar): array
{
    $berlaku = 0;
    $kedaluwarsa = 0;
    $belum = 0;
    $tanpaLaporan = 0;
    $segera = 0;
    foreach ($daftar as $m) {
        if ($m['status'] === 'berlaku') {
            $berlaku++;
            // ≤ 180 hari dianggap perlu diperpanjang — asesor menanyakan ini.
            if ($m['sisa_hari'] !== null && $m['sisa_hari'] <= 180) {
                $segera++;
            }
        } elseif ($m['status'] === 'kedaluwarsa') {
            $kedaluwarsa++;
        } else {
            $belum++;
        }
        if (!$m['ada_laporan']) {
            $tanpaLaporan++;
        }
    }
    return [
        'total' => count($daftar),
        'berlaku' => $berlaku,
        'kedaluwarsa' => $kedaluwarsa,
        'belum' => $belum,
        'segera' => $segera,
        'tanpa_laporan' => $tanpaLaporan,
    ];
}

/** '' -> null, 'YYYY-MM-DD' -> string, bentuk lain -> false. */
function mitra_tanggal(mixed $v): string|null|false
{
    if ($v === null || $v === '') {
        return null;
    }
    $s = substr(trim((string) $v), 0, 10);
    $d = DateTimeImmutable::createFromFormat('Y-m-d', $s);
    if ($d === false || $d->format('Y-m-d') !== $s) {
        return false;
    }
    return $s;
}

function mitra_teks(mixed $v, int $maks): ?string
{
    $s = trim((string) ($v ?? ''));
    return $s === '' ? null : mb_substr($s, 0, $maks);
}
