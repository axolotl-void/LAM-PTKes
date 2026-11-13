<?php
declare(strict_types=1);

/**
 * Identitas UPPS & Prodi (F-01), Periode Akreditasi (F-02), Keuangan (F-17).
 *
 * Ketiganya bukan daftar, jadi tidak cocok dengan koleksi generik:
 *   - Identitas  = tepat SATU baris (UPPS + prodi). Tidak ada "tambah".
 *   - Periode    = satu baris aktif per prodi; tahun TS dipakai semua tabel.
 *   - Keuangan   = dua tabel (penerimaan & penggunaan), ditampilkan berdampingan.
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/view.php';

/* ── identitas ───────────────────────────────────────────────────── */

function identitas_baca(): array
{
    $u = q1('SELECT * FROM upps ORDER BY id LIMIT 1') ?: [];
    $p = q1('SELECT * FROM prodi WHERE aktif = 1 ORDER BY id LIMIT 1') ?: [];
    return [
        'upps' => $u,
        'prodi' => $p,
        'daftar_prodi' => q('SELECT id, nama_ps, jenjang, aktif FROM prodi ORDER BY id'),
    ];
}

function identitas_simpan(array $b): array
{
    $uppsId = (int) ($b['upps_id'] ?? 0);

    $wajib = ['nama_pt' => 'Nama perguruan tinggi', 'nama_upps' => 'Nama UPPS'];
    foreach ($wajib as $k => $label) {
        if (trim((string) ($b[$k] ?? '')) === '') {
            return ['error' => $label . ' wajib diisi.'];
        }
    }

    $email = trim((string) ($b['email'] ?? ''));
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['error' => 'Format email tidak sah.'];
    }

    $dataU = [
        'nama_pt' => mb_substr(trim((string) $b['nama_pt']), 0, 160),
        'nama_upps' => mb_substr(trim((string) $b['nama_upps']), 0, 160),
        'alamat' => teks_atau_null($b['alamat'] ?? null, 255),
        'kota' => teks_atau_null($b['kota'] ?? null, 80),
        'telepon' => teks_atau_null($b['telepon'] ?? null, 40),
        'email' => $email === '' ? null : $email,
        'website' => teks_atau_null($b['website'] ?? null, 120),
        'narahubung' => teks_atau_null($b['narahubung'] ?? null, 120),
    ];

    // Prodi: hanya field yang boleh diubah lewat halaman ini. Akreditasi dan
    // jenjang tidak — itu datang dari data resmi, bukan diketik ulang di sini.
    $dataP = [];
    $prodiId = (int) ($b['prodi_id'] ?? 0);
    if ($prodiId > 0) {
        if (isset($b['nama_ps'])) {
            $nama = trim((string) $b['nama_ps']);
            if ($nama === '') {
                return ['error' => 'Nama program studi wajib diisi.'];
            }
            $dataP['nama_ps'] = mb_substr($nama, 0, 120);
        }
        if (isset($b['punya_tahap_profesi'])) {
            $dataP['punya_tahap_profesi'] = !empty($b['punya_tahap_profesi']) ? 1 : 0;
        }
        if (isset($b['lama_studi_semester'])) {
            $n = (int) $b['lama_studi_semester'];
            if ($n < 1 || $n > 20) {
                return ['error' => 'Lama studi harus 1–20 semester.'];
            }
            $dataP['lama_studi_semester'] = $n;
        }
    }

    transaksi(static function () use ($uppsId, $dataU, $prodiId, $dataP) {
        if ($uppsId > 0) {
            ubah('upps', $dataU, 'id', $uppsId);
        } else {
            $uppsId = sisip('upps', $dataU);
        }
        if ($prodiId > 0 && $dataP !== []) {
            ubah('prodi', $dataP, 'id', $prodiId);
        }
        ex('INSERT INTO audit (pengguna_id, prodi_id, aksi, entitas, entitas_id, nilai_baru)
            VALUES (?,?,?,?,?,?)',
            [pengguna()['id'] ?? null, prodi_aktif(), 'ubah', 'identitas', (string) $uppsId,
             json_encode(['upps' => $dataU, 'prodi' => $dataP], JSON_UNESCAPED_UNICODE)]);
    });

    return ['ok' => true];
}

/* ── periode ─────────────────────────────────────────────────────── */

function periode_daftar(): array
{
    return q('SELECT p.*, pr.nama_ps FROM periode p JOIN prodi pr ON pr.id = p.prodi_id
              ORDER BY p.aktif DESC, p.tahun_ts DESC, pr.id');
}

function periode_simpan(array $b, int $id = 0): array
{
    $prodiId = (int) ($b['prodi_id'] ?? 0);
    if ($prodiId <= 0) {
        return ['error' => 'Program studi wajib dipilih.'];
    }
    if (!q1('SELECT id FROM prodi WHERE id = ?', [$prodiId])) {
        return ['error' => 'Program studi tidak dikenal.'];
    }

    $ts = (int) ($b['tahun_ts'] ?? 0);
    if ($ts < 1990 || $ts > 2100) {
        return ['error' => 'Tahun TS harus 1990–2100.'];
    }
    $ss = $b['tahun_ss'] ?? null;
    if ($ss !== null && $ss !== '') {
        $ss = (int) $ss;
        if ($ss < 1990 || $ss > 2100) {
            return ['error' => 'Tahun SS harus 1990–2100.'];
        }
        // TS = tahun berjalan, SS = tahun sebelumnya. Terbalik = salah isi.
        if ($ss >= $ts) {
            return ['error' => 'Tahun SS harus SEBELUM tahun TS (' . $ts . ').'];
        }
    } else {
        $ss = null;
    }

    $tgl = null;
    if (!empty($b['tgl_pengajuan'])) {
        $t = DateTimeImmutable::createFromFormat('Y-m-d', substr((string) $b['tgl_pengajuan'], 0, 10));
        if ($t === false || $t->format('Y-m-d') !== substr((string) $b['tgl_pengajuan'], 0, 10)) {
            return ['error' => 'Tanggal pengajuan harus tanggal yang sah.'];
        }
        $tgl = $t->format('Y-m-d');
    }

    $data = [
        'prodi_id' => $prodiId,
        'tahun_ts' => $ts,
        'tahun_ss' => $ss,
        'tgl_pengajuan' => $tgl,
        'catatan' => teks_atau_null($b['catatan'] ?? null, 255),
        'aktif' => !empty($b['aktif']) ? 1 : 0,
    ];

    // Satu periode aktif per prodi. Kalau dua aktif, tabel mana pun tidak tahu
    // TS yang mana dipakai — angka antar tabel jadi tidak bisa dibandingkan.
    if ($data['aktif'] === 1 && $id === 0) {
        ex('UPDATE periode SET aktif = 0 WHERE prodi_id = ?', [$prodiId]);
    }

    return transaksi(static function () use ($data, $b, $id) {
        if ($id > 0) {
            if (!q1('SELECT id FROM periode WHERE id = ?', [$id])) {
                return ['error' => 'Periode tidak ditemukan.'];
            }
            ubah('periode', $data, 'id', $id);
        } else {
            $id = sisip('periode', $data);
        }
        // Tahun TS adalah rujukan semua tabel: kalau periode ini aktif, pastikan
        // prodi lain tidak ikut memakainya secara keliru.
        ex('INSERT INTO audit (pengguna_id, prodi_id, aksi, entitas, entitas_id, nilai_baru)
            VALUES (?,?,?,?,?,?)',
            [pengguna()['id'] ?? null, prodi_aktif(), 'ubah', 'periode', (string) $id,
             json_encode($data, JSON_UNESCAPED_UNICODE)]);
        return ['ok' => true, 'id' => $id];
    });
}

function periode_hapus(int $id): array
{
    $p = q1('SELECT * FROM periode WHERE id = ?', [$id]);
    if (!$p) {
        return ['error' => 'Periode tidak ditemukan.'];
    }
    if ((int) $p['aktif'] === 1) {
        return ['error' => 'Periode yang sedang aktif tidak bisa dihapus. Nonaktifkan dulu '
            . '(pilih periode lain sebagai aktif) supaya tidak ada tabel yang kehilangan rujukan.'];
    }
    ex('DELETE FROM periode WHERE id = ?', [$id]);
    ex('INSERT INTO audit (pengguna_id, prodi_id, aksi, entitas, entitas_id, nilai_lama)
        VALUES (?,?,?,?,?,?)',
        [pengguna()['id'] ?? null, prodi_aktif(), 'hapus', 'periode', (string) $id,
         json_encode($p, JSON_UNESCAPED_UNICODE)]);
    return ['ok' => true];
}

/* ── keuangan ────────────────────────────────────────────────────── */

function keuangan_baca(?int $prodiId = null): array
{
    $pid = $prodiId ?: prodi_aktif();
    $terima = q('SELECT * FROM keuangan_terima WHERE prodi_id = ? ORDER BY tahun, sumber', [$pid]);
    $guna = q('SELECT * FROM keuangan_guna WHERE prodi_id = ? ORDER BY tahun, jenis', [$pid]);

    // Total dijumlahkan di sini, tidak disimpan. Kalau disimpan, cepat atau
    // lambat angkanya tidak sama dengan rinciannya dan tidak ada yang tahu
    // mana yang benar.
    $totalTerima = [];
    foreach ($terima as $t) {
        $th = (int) $t['tahun'];
        $totalTerima[$th] = ($totalTerima[$th] ?? 0) + (float) $t['jumlah_juta'];
    }
    $totalGuna = [];
    foreach ($guna as $t) {
        $th = (int) $t['tahun'];
        $totalGuna[$th] = ($totalGuna[$th] ?? 0) + (float) $t['jumlah_juta'];
    }

    return [
        'terima' => $terima,
        'guna' => $guna,
        'total_terima' => $totalTerima,
        'total_guna' => $totalGuna,
        'prodi_id' => $pid,
    ];
}

function keuangan_simpan(array $b, string $jenis): array
{
    $tabel = $jenis === 'guna' ? 'keuangan_guna' : 'keuangan_terima';
    $kol = $jenis === 'guna' ? 'jenis' : 'sumber';

    $prodiId = (int) ($b['prodi_id'] ?? 0);
    if ($prodiId <= 0) {
        return ['error' => 'Program studi wajib dipilih.'];
    }
    $tahun = (int) ($b['tahun'] ?? 0);
    if ($tahun < 1990 || $tahun > 2100) {
        return ['error' => 'Tahun harus 1990–2100.'];
    }
    $label = trim((string) ($b[$kol] ?? ''));
    if ($label === '') {
        return ['error' => ($jenis === 'guna' ? 'Jenis penggunaan' : 'Sumber penerimaan') . ' wajib diisi.'];
    }

    $jumlah = $b['jumlah_juta'] ?? null;
    if ($jumlah === null || $jumlah === '' || !is_numeric($jumlah)) {
        return ['error' => 'Jumlah (juta rupiah) harus angka.'];
    }
    $jumlah = round((float) $jumlah, 2);
    if ($jumlah < 0) {
        return ['error' => 'Jumlah tidak boleh negatif.'];
    }
    if ($jumlah > 100000000) {
        return ['error' => 'Jumlah terlalu besar — satuan yang diminta JUTA rupiah.'];
    }

    $data = [
        'prodi_id' => $prodiId,
        'tahun' => $tahun,
        $kol => mb_substr($label, 0, 120),
        'jumlah_juta' => $jumlah,
    ];

    $id = (int) ($b['id'] ?? 0);
    if ($id > 0) {
        if (!q1('SELECT id FROM ' . $tabel . ' WHERE id = ?', [$id])) {
            return ['error' => 'Data tidak ditemukan.'];
        }
        ubah($tabel, $data, 'id', $id);
    } else {
        // Baris ganda untuk prodi+tahun+label yang sama akan membuat total
        // terhitung dua kali. Tawarkan sebagai pembaruan, bukan tolak mentah.
        $ada = q1('SELECT id FROM ' . $tabel . ' WHERE prodi_id = ? AND tahun = ? AND ' . $kol . ' = ?',
            [$prodiId, $tahun, $data[$kol]]);
        if ($ada) {
            $id = (int) $ada['id'];
            ubah($tabel, $data, 'id', $id);
        } else {
            $id = sisip($tabel, $data);
        }
    }

    ex('INSERT INTO audit (pengguna_id, prodi_id, aksi, entitas, entitas_id, nilai_baru)
        VALUES (?,?,?,?,?,?)',
        [pengguna()['id'] ?? null, prodi_aktif(), 'ubah', $tabel, (string) $id,
         json_encode($data, JSON_UNESCAPED_UNICODE)]);

    return ['ok' => true, 'id' => $id];
}

function keuangan_hapus(string $jenis, int $id): array
{
    $tabel = $jenis === 'guna' ? 'keuangan_guna' : 'keuangan_terima';
    $row = q1('SELECT * FROM ' . $tabel . ' WHERE id = ?', [$id]);
    if (!$row) {
        return ['error' => 'Data tidak ditemukan.'];
    }
    ex('DELETE FROM ' . $tabel . ' WHERE id = ?', [$id]);
    ex('INSERT INTO audit (pengguna_id, prodi_id, aksi, entitas, entitas_id, nilai_lama)
        VALUES (?,?,?,?,?,?)',
        [pengguna()['id'] ?? null, prodi_aktif(), 'hapus', $tabel, (string) $id,
         json_encode($row, JSON_UNESCAPED_UNICODE)]);
    return ['ok' => true];
}

function teks_atau_null(mixed $v, int $maks): ?string
{
    $s = trim((string) ($v ?? ''));
    return $s === '' ? null : mb_substr($s, 0, $maks);
}
