<?php
declare(strict_types=1);

/**
 * Logika aturan konsistensi lintas tabel. Tanpa HTML, tanpa sesi — bisa
 * di-require modul lain (ekspor) tanpa menjalankan halaman.
 *
 * Tiga pantangan yang lahir dari bug prototipe lama:
 *  1. selisih SELALU hasil pengurangan a - b, tidak pernah diketik;
 *  2. kalau tidak bisa dihitung, selisih null (tampil "—"), BUKAN 0;
 *  3. aturan tanpa data berstatus 'belum', BUKAN 'lulus'.
 * Prototipe lama menampilkan "lulus, selisih 0" untuk 7 vs 6 — pembaca
 * mengira tidak ada masalah padahal ada.
 */

require_once __DIR__ . '/../../system/rekap.php';

const KN_STATUS = ['lulus' => 'Lulus', 'gagal' => 'Gagal', 'belum' => 'Belum ada data'];

/** 10 aturan aktif, urut kode. */
function kn_aturan(): array
{
    return q('SELECT kode, nama, tabel_a, tabel_b, tingkat, catatan
              FROM konsistensi_aturan WHERE aktif = 1 ORDER BY kode');
}

/**
 * Semua angka dari sel satu kolom. Satu sel bisa memuat tiga angka
 * ("35 / 30 / 35") karena kolom TS-2/TS-1/TS dipadatkan jadi satu field —
 * karena itu hasilnya daftar per posisi, bukan satu nilai. Memotong teks
 * dengan CAST() akan diam-diam membuang angka kedua dan ketiga.
 */
function kn_angka_sel(string $tabel, string $kunci, int $prodi, ?int $periode): array
{
    if ($periode === null) {
        return [];
    }
    $baris = q(
        'SELECT s.nilai_angka, s.nilai_teks
         FROM sel s JOIN baris_tabel b ON b.id = s.baris_id
         WHERE b.tabel_kode = ? AND b.prodi_id = ? AND b.periode_id = ? AND s.field_kunci = ?
         ORDER BY b.urut',
        [$tabel, $prodi, $periode, $kunci]
    );
    $out = [];
    foreach ($baris as $r) {
        if ($r['nilai_angka'] !== null) {
            $out[] = [(float) $r['nilai_angka']];
            continue;
        }
        preg_match_all('/-?\d+(?:[.,]\d+)?/', (string) $r['nilai_teks'], $m);
        $pos = array_map(static fn($v) => (float) str_replace(',', '.', $v), $m[0]);
        if ($pos !== []) {
            $out[] = $pos;
        }
    }
    return $out;
}

/**
 * Jumlah satu kolom. null = belum ada satu pun sel terisi (jangan jadi 0).
 * 0 = memang diisi 0. Bila satu sel memuat beberapa angka, semuanya dijumlah.
 */
function kn_jumlah(string $tabel, array $kunci, int $prodi, ?int $periode): ?float
{
    $jumlah = 0.0;
    $ada = false;
    foreach ($kunci as $k) {
        foreach (kn_angka_sel($tabel, $k, $prodi, $periode) as $pos) {
            $jumlah += array_sum($pos);
            $ada = true;
        }
    }
    return $ada ? $jumlah : null;
}

/**
 * Jumlah persen per posisi tahun: [TS-2, TS-1, TS]. Tabel 34 punya satu baris
 * per jenis penggunaan, jadi persen 100% adalah jumlah ANTAR BARIS per tahun,
 * bukan jumlah tiga angka di satu baris.
 */
function kn_persen_tahun(string $tabel, string $kunci, int $prodi, ?int $periode): array
{
    $per = [];
    foreach (kn_angka_sel($tabel, $kunci, $prodi, $periode) as $pos) {
        foreach ($pos as $i => $v) {
            $per[$i] = ($per[$i] ?? 0.0) + $v;
        }
    }
    return $per;
}

/** Kode tabel khusus profesi (dari DB, bukan diketik). */
function kn_tabel_profesi(): array
{
    return array_column(q('SELECT kode FROM tabel_dukung WHERE khusus_profesi = 1 ORDER BY nomor'), 'kode');
}

/** Evaluasi satu aturan. Selalu mengembalikan kode+nama+tingkat asli. */
function kn_evaluasi(array $r, int $prodi, ?int $periode): array
{
    $h = $r + [
        'a' => null, 'b' => null, 'selisih' => null, 'status' => 'belum',
        'perbaikan' => '', 'alasan' => '', 'label_a' => '', 'label_b' => '', 'satuan' => '',
    ];
    if ($periode === null) {
        $h['alasan'] = 'periode akreditasi belum ada';
        return $h;
    }

    $selesai = static function (array $h, ?float $a, ?float $b, string $perbaikan, string $satuan = ''): array {
        $h['a'] = $a;
        $h['b'] = $b;
        $h['satuan'] = $satuan;
        if ($a === null || $b === null) {
            $h['status'] = 'belum';              // JANGAN diganti 'lulus'
            $h['selisih'] = null;                // JANGAN diganti 0
            // Tabel yang harus diisi tetap disebut: "belum ada data" pun butuh
            // tahu tabel mana yang harus dilengkapi.
            $h['perbaikan'] = $perbaikan;
            return $h;
        }
        $h['selisih'] = $a - $b;                 // selalu pengurangan
        $h['status'] = abs($h['selisih']) < 0.00005 ? 'lulus' : 'gagal';
        $h['perbaikan'] = $h['status'] === 'lulus' ? '' : $perbaikan;
        return $h;
    };

    switch ($r['kode']) {
        case 'R01':
            $a = kn_jumlah('15', ['total_di_upps'], $prodi, $periode);
            $b = (float) qv('SELECT COUNT(*) FROM dosen_prodi dp JOIN dosen d ON d.id = dp.dosen_id
                             WHERE dp.prodi_id = ? AND d.jenis = ?', [$prodi, 'Dosen Tetap'], 0);
            $h['label_a'] = 'Tabel 15 · total dosen di UPPS';
            $h['label_b'] = 'master dosen tetap prodi';
            return $selesai($h, $a, $a === null ? null : $b, '15 atau 16', 'orang');

        case 'R02':
            $a = kn_jumlah('03', ['jumlah_lulusan_reguler', 'jumlah_lulusan_transfer', 'jumlah_lulusan_asing'], $prodi, $periode);
            $b = kn_jumlah('05', ['jumlah_lulusan_s_d_ts'], $prodi, $periode);
            $h['label_a'] = 'Tabel 03 · jumlah lulusan';
            $h['label_b'] = 'Tabel 05 · akumulasi lulusan s.d. TS';
            return $selesai($h, $a, $b, '03 atau 05', 'orang');

        case 'R03':
            $a = kn_jumlah('09', ['mahasiswa_reguler', 'non_reguler', 'asing'], $prodi, $periode);
            $b = kn_jumlah('10', ['total_mahasiswa_aktif_reguler', 'total_mahasiswa_aktif_transfer', 'total_mahasiswa_aktif_asing'], $prodi, $periode);
            $h['label_a'] = 'Tabel 09 · total mahasiswa';
            $h['label_b'] = 'Tabel 10 · jumlah seluruh prodi';
            return $selesai($h, $a, $b, '09 atau 10', 'orang');

        case 'R04':
            $a = kn_jumlah('10', ['total_mahasiswa_aktif_reguler', 'total_mahasiswa_aktif_transfer', 'total_mahasiswa_aktif_asing'], $prodi, $periode);
            $b = $a === null ? null : (float) qv('SELECT COUNT(*) FROM mahasiswa WHERE prodi_id = ? AND status = ?',
                [$prodi, 'Aktif'], 0);
            $h['label_a'] = 'Tabel 10 · mahasiswa aktif';
            $h['label_b'] = 'master mahasiswa aktif';
            return $selesai($h, $a, $b, '10 atau master mahasiswa', 'orang');

        case 'R05':
            $a = kn_jumlah('33', ['jumlah_dana_ts_2_ts_1_ts_juta_rp'], $prodi, $periode);
            $b = kn_jumlah('34', ['jumlah_dana_ts_2_ts_1_ts_juta_rp'], $prodi, $periode);
            $h['label_a'] = 'Tabel 33 · penerimaan dana (juta Rp)';
            $h['label_b'] = 'Tabel 34 · sumber dana mahasiswa (juta Rp)';
            return $selesai($h, $a, $b, '33 atau 34', 'juta Rp');

        case 'R06':
            $tahun = kn_persen_tahun('34', 'persentase_ts_2_ts_1_ts', $prodi, $periode);
            $h['perbaikan'] = '34';
            $h['label_a'] = 'Tabel 34 · jumlah persen per tahun';
            $h['label_b'] = 'target 100% per tahun';
            $h['satuan'] = '%';
            if ($tahun === []) {
                $h['status'] = 'belum';
                $h['alasan'] = 'kolom persentase Tabel 34 belum terisi';
                return $h;
            }
            // Aturan menuntut 100% DI SETIAP TAHUN. Yang dilaporkan adalah tahun
            // paling melenceng supaya selisih 0 hanya muncul kalau semua tahun
            // memang 100 — bukan karena tahun pertama kebetulan pas.
            $label = ['TS-2', 'TS-1', 'TS'];
            $buruk = 0;
            $beda = 0.0;
            foreach ($tahun as $i => $v) {
                if (abs($v - 100.0) > abs($beda)) {
                    $beda = $v - 100.0;
                    $buruk = $i;
                }
            }
            $h['label_a'] .= ' (' . ($label[$buruk] ?? ('tahun ' . ($buruk + 1))) . ')';
            return $selesai($h, $tahun[$buruk], 100.0, '34', '%');

        case 'R07':
            $a = (float) qv('SELECT COUNT(*) FROM baris_tabel WHERE tabel_kode = ? AND prodi_id = ? AND periode_id = ?',
                ['17', $prodi, $periode], 0);
            $b = (float) qv('SELECT COUNT(*) FROM dosen_prodi dp JOIN dosen d ON d.id = dp.dosen_id
                             WHERE dp.prodi_id = ? AND d.jenis = ?', [$prodi, 'Dosen Tetap'], 0);
            $h['label_a'] = 'Tabel 17 · jumlah baris dosen';
            $h['label_b'] = 'master dosen tetap prodi';
            $h['perbaikan'] = '17 atau 16/19';
            if ($a === 0.0) {
                $h['alasan'] = 'Tabel 17 belum diisi';
                return $h;                        // a/b tetap null -> belum, selisih "—"
            }
            if ($a > $b) {
                return $selesai($h, $a, $b, '17 atau 16/19', 'orang');
            }
            // Nama dosen Tabel 17 berasal dari field Master yang tidak disimpan
            // di `sel`, jadi rujukan per baris tidak bisa ditelusuri. Jangan
            // mengaku lulus hanya karena jumlahnya cukup.
            $h['alasan'] = 'rujukan dosen per baris tidak tersimpan (field Master); hanya bisa dicek bila baris > master';
            return $h;

        case 'R08':
            $a = (float) qv('SELECT COUNT(*) FROM wahana WHERE prodi_id = ?', [$prodi], 0);
            $b = (float) qv('SELECT COUNT(*) FROM wahana WHERE prodi_id = ?
                             AND dokumen_kerja_sama IS NOT NULL AND dokumen_kerja_sama <> ?', [$prodi, ''], 0);
            $h['label_a'] = 'Tabel 30 · wahana praktik';
            $h['label_b'] = 'wahana ber-dokumen kerja sama';
            $h['perbaikan'] = '30 atau 38';
            if ($a === 0.0) {
                $h['alasan'] = 'belum ada wahana terdaftar';
                return $h;
            }
            return $selesai($h, $a, $b, '30 atau 38', 'wahana');

        case 'R09':
            $mk = q('SELECT kode_mk, nama_mk FROM mata_kuliah WHERE prodi_id = ?', [$prodi]);
            $kamus = [];
            foreach ($mk as $m) {
                $kamus[mb_strtolower(trim((string) $m['kode_mk']))] = true;
                $kamus[mb_strtolower(trim((string) $m['nama_mk']))] = true;
            }
            $sel = q(
                "SELECT s.nilai_teks FROM sel s JOIN baris_tabel b ON b.id = s.baris_id
                 WHERE b.tabel_kode IN ('16','19') AND b.prodi_id = ? AND b.periode_id = ?
                   AND s.field_kunci = ? AND s.nilai_teks IS NOT NULL AND s.nilai_teks <> ''",
                [$prodi, $periode, 'mata_ajar_yang_diampu']
            );
            $h['label_a'] = 'mata ajar tersimpan (Tabel 16/19)';
            $h['label_b'] = 'yang cocok dengan master MK';
            $h['perbaikan'] = '16/19 atau master MK';
            if ($sel === []) {
                $adaBaris = (int) qv('SELECT COUNT(*) FROM baris_tabel WHERE tabel_kode IN (?,?) AND prodi_id = ? AND periode_id = ?',
                    ['16', '19', $prodi, $periode], 0);
                $h['alasan'] = $adaBaris === 0
                    ? 'Tabel 16/19 belum diisi'
                    : 'mata ajar berasal dari field Master, tidak disimpan sebagai nilai';
                return $h;
            }
            $cocok = 0;
            foreach ($sel as $s) {
                if (isset($kamus[mb_strtolower(trim((string) $s['nilai_teks']))])) {
                    $cocok++;
                }
            }
            return $selesai($h, (float) count($sel), (float) $cocok, '16/19 atau master MK', 'mata ajar');

        case 'R10':
            $khusus = kn_tabel_profesi();
            $punya = (int) qv('SELECT punya_tahap_profesi FROM prodi WHERE id = ?', [$prodi], 0) === 1;
            $h['label_a'] = 'tabel khusus profesi lengkap';
            $h['label_b'] = 'tabel khusus profesi wajib';
            if (!$punya) {
                $h['a'] = 0.0;
                $h['b'] = 0.0;
                $h['selisih'] = 0.0;
                $h['status'] = 'lulus';
                $h['satuan'] = 'tabel';
                $h['alasan'] = 'tidak berlaku: prodi tanpa tahap profesi';
                return $h;
            }
            $lengkap = 0;
            $kurang = [];
            foreach ($khusus as $kode) {
                $s = status_satu_tabel($kode, $prodi, $periode);
                if (in_array($s['status'], ['lengkap', 'terkunci'], true)) {
                    $lengkap++;
                } else {
                    $kurang[] = $kode;
                }
            }
            $hasil = $selesai($h, (float) $lengkap, (float) count($khusus), implode(',', $kurang), 'tabel');
            if ($hasil['status'] === 'gagal') {
                $hasil['perbaikan'] = 'lengkapi tabel ' . implode(',', $kurang);
            }
            return $hasil;
    }

    $h['alasan'] = 'belum ada penghitung untuk aturan ini';
    return $h;
}

/** Jalankan semua aturan aktif untuk satu prodi/periode. */
function konsistensi_jalankan(int $prodi, ?int $periode): array
{
    $hasil = [];
    foreach (kn_aturan() as $r) {
        $hasil[] = kn_evaluasi($r, $prodi, $periode);
    }
    return $hasil;
}

/** Hitungan per status. lulus + gagal + belum = jumlah aturan aktif. */
function konsistensi_ringkas(array $hasil): array
{
    $out = ['total' => count($hasil), 'lulus' => 0, 'gagal' => 0, 'belum' => 0];
    foreach ($hasil as $h) {
        $out[$h['status']]++;
    }
    return $out;
}

/**
 * Aturan tingkat 'blokir' yang GAGAL — dipakai modul ekspor (Agent 9).
 * Kosong = ekspor boleh jalan. 'belum' tidak memblokir: data yang belum ada
 * bukan pelanggaran.
 */
function konsistensi_blokir(?int $prodi = null, ?int $periode = null): array
{
    if ($prodi === null) {
        // Dipanggil dari CLI/uji: auth.php belum tentu termuat, dan tanpa sesi
        // memang tidak ada prodi aktif. function_exists menjaga dua-duanya.
        $prodi = function_exists('prodi_aktif') ? (int) (prodi_aktif() ?? 0) : 0;
    }
    if ($periode === null && $prodi > 0) {
        $per = periode_aktif($prodi);
        $periode = $per ? (int) $per['id'] : null;
    }
    if ($prodi === 0) {
        return [];
    }
    return array_values(array_filter(
        konsistensi_jalankan($prodi, $periode),
        static fn(array $h): bool => $h['tingkat'] === 'blokir' && $h['status'] === 'gagal'
    ));
}
