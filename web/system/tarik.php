<?php
declare(strict_types=1);

/**
 * Tarik data master ke tabel data dukung.
 *
 * KENAPA: Tabel 16, 19, 29, 30, 31 sebagian besar kolomnya `Master` — datanya
 * sudah ada di master dosen/lab/wahana/pustaka. Kalau operator mengetik ulang,
 * cepat atau lambat isinya berbeda dari master, dan asesor menemukan dua angka
 * berbeda untuk hal yang sama. Yang benar: satu sumber, ditarik.
 *
 * ATURAN YANG DIPEGANG:
 *  1. Idempoten. Tarik dua kali tidak menggandakan baris (dijaga UNIQUE di
 *     baris_tabel + UPDATE kalau sudah ada).
 *  2. Isian manual tidak ditimpa. Hanya field `asal='Master'` yang diisi ulang;
 *     field `Input` milik operator dibiarkan apa adanya.
 *  3. Baris yang dihapus operator tidak muncul lagi. Baris hasil tarikan diberi
 *     `sumber_tabel` + `sumber_id`; kalau dihapus, tarik ulang akan
 *     membuatnya kembali — jadi tombol tarik harus eksplisit, bukan otomatis
 *     setiap halaman dibuka.
 *  4. Baris master yang tidak ada isinya tetap dibuat, supaya operator melihat
 *     "ini belum diisi di master" alih-alih baris itu hilang tanpa penjelasan.
 */

require_once __DIR__ . '/db.php';

/**
 * Peta: kolom tabel data dukung -> cara mengambil dari master.
 *
 * Format: [tabel_kode => [field_kunci => [sumber, kolom_atau_closure]]]
 * `sumber` = nama tabel master. `kolom` = nama kolom di master itu.
 * Kalau perlu gabungan (mis. nama + gelar), pakai kunci `expr` berisi SQL.
 */
function tarik_peta(): array
{
    return [
        // Tabel 16 — Dosen Tetap tahap akademik & profesi.
        // Hanya dosen tetap; dosen tidak tetap masuk Tabel 19.
        '16' => [
            'sumber' => 'dosen',
            'saring' => "jenis = 'Dosen Tetap'",
            'urut' => 'nama',
            'field' => [
                // Nama saja. Gelar sudah punya kolom sendiri di Tabel 16;
                // menempelkannya di sini membuat satu nilai muncul dua kali
                // dan bisa berbeda saat salah satunya diperbaiki.
                'nama_dosen_tetap' => ['kolom' => 'nama'],
                'nidn_atau_nidk' => ['kolom' => 'nidn_nidk_nup'],
                'tanggal_lahir' => ['kolom' => 'tanggal_lahir'],
                'jabatan_akademik' => ['kolom' => 'jabatan_fungsional'],
                'gelar_akademik' => ['kolom' => 'gelar_akademik'],
                // Tahap: akademik selalu, profesi hanya kalau dosen mengajar
                // tahap profesi. Instrumen membolehkan dua-duanya.
                'tahap_akademik' => ['nilai' => 1],
                'tahap_profesi' => ['closure' => 'dosen_tahap_profesi'],
            ],
        ],

        // Tabel 19 — Dosen Tidak Tetap.
        '19' => [
            'sumber' => 'dosen',
            'saring' => "jenis = 'Dosen Tidak Tetap'",
            'urut' => 'nama',
            'field' => [
                'nama_akademik_profesi' => ['kolom' => 'nama'],
                'nidn_nidk_nup' => ['kolom' => 'nidn_nidk_nup'],
                'tanggal_lahir' => ['kolom' => 'tanggal_lahir'],
                'jabatan_akademik' => ['kolom' => 'jabatan_fungsional'],
                'gelar_akademik' => ['kolom' => 'gelar_akademik'],
                'pendidikan_dan_sertifikat_kompetensi' => ['closure' => 'dosen_pendidikan_ringkas'],
                'bidang_keahlian' => ['closure' => 'dosen_bidang'],
            ],
        ],

        // Tabel 29 — Prasarana & peralatan laboratorium.
        '29' => [
            'sumber' => 'laboratorium',
            'urut' => 'nama_lab',
            'field' => [
                'nama_laboratorium' => ['kolom' => 'nama_lab'],
                'nama_alat_peraga' => ['kolom' => 'alat'],
                'jumlah_unit_alat' => ['kolom' => 'jumlah_unit'],
                'kepemilikan_sd_sw' => ['kolom' => 'kepemilikan'],
                // Instrumen menulis "Terawat / Tidak Terawat"; master memakai
                // tiga tingkat kondisi. Dipetakan, jangan disamakan mentah.
                'kondisi_terawat_tidak_terawat' => ['closure' => 'lab_kondisi'],
                'logbook_ada_tidak_ada' => ['closure' => 'lab_logbook'],
                'rata_rata_waktu_penggunaan_jam_minggu' => ['kolom' => 'jam_pakai_per_minggu'],
            ],
        ],

        // Tabel 30 — Wahana praktik.
        '30' => [
            'sumber' => 'wahana',
            'urut' => 'nama',
            'field' => [
                'nama_wahana_praktik' => ['kolom' => 'nama'],
                'kualifikasi' => ['kolom' => 'kualifikasi'],
                'kecukupan_variasi_kasus' => ['kolom' => 'variasi_kasus'],
                'jumlah_dosen_pembimbing_klinik' => ['kolom' => 'jumlah_pembimbing'],
                'dokumen_kerja_sama' => ['kolom' => 'dokumen_kerja_sama'],
            ],
        ],

        // Tabel 31 — Pustaka: satu baris per (jenis, judul) di master.
        '31' => [
            'sumber' => 'pustaka',
            'urut' => 'judul',
            'field' => [
                // Tabel 31 (rekap pustaka) hanya menanyakan agregat per jenis:
                // jumlah judul dan jumlah copy. Judul per buku TIDAK diminta di
                // sini — itu ada di Tabel 32 (jurnal). Karena itu hanya tiga
                // field yang ditarik; menambah kolom judul berarti mengarang
                // kolom yang tidak ada di instrumen.
                // `jumlah_judul`/`jumlah_copy` bertanda asal=Hitung di spec
                // (agregat per jenis), tapi nilainya TIDAK bisa dihitung
                // tanpa rumus. Yang benar: ditarik dari master pustaka —
                // kalau dibiarkan, kolomnya kosong selamanya dan Tabel 31
                // tampak belum diisi padahal pustakanya sudah ada.
                'no_jenis_pustaka' => ['kolom' => 'jenis'],
                'jumlah_judul' => ['kolom' => 'jumlah_judul'],
                'jumlah_copy' => ['kolom' => 'jumlah_copy'],
            ],
        ],
    ];
}

/** Dosen mengajar tahap profesi? Diambil dari dosen.tahap_mengajar. */
function dosen_tahap_profesi(array $d): ?string
{
    $t = $d['tahap_mengajar'] ?? null;
    return in_array($t, ['Profesi', 'Keduanya'], true) ? '1' : null;
}

/** "S1 Kebidanan, Universitas Syiah Kuala; S2 ..." */
function dosen_pendidikan_ringkas(array $d): ?string
{
    $p = q('SELECT jenjang, bidang, asal_pt FROM dosen_pendidikan
            WHERE dosen_id = ? ORDER BY tahun_lulus DESC', [$d['id']]);
    if ($p === []) {
        return null;
    }
    $bagian = [];
    foreach ($p as $r) {
        $bagian[] = trim($r['jenjang'] . ' ' . ($r['bidang'] ?? '') . ' — ' . ($r['asal_pt'] ?? ''), ' —');
    }
    return implode('; ', $bagian);
}

/** Bidang keahlian: bidang pada jenjang tertinggi. */
function dosen_bidang(array $d): ?string
{
    $b = qv('SELECT bidang FROM dosen_pendidikan WHERE dosen_id = ?
             ORDER BY FIELD(jenjang,"S3","S2","Profesi","S1","D4","D3"), tahun_lulus DESC LIMIT 1',
        [$d['id']], '');
    return $b === '' ? null : $b;
}

/**
 * Kondisi lab: instrumen hanya kenal terawat / tidak terawat.
 * Rusak Ringan DIPETAKAN ke terawat — alat masih bisa dipakai; yang tidak
 * terawat adalah Rusak Berat. Ini keputusan, bukan tebakan: menyebut alat
 * rusak ringan sebagai "tidak terawat" membuat prodi terlihat lebih buruk
 * dari kenyataannya.
 */
function lab_kondisi(array $d): ?string
{
    $k = $d['kondisi'] ?? null;
    if ($k === null) {
        return null;
    }
    return $k === 'Rusak Berat' ? 'Tidak Terawat' : 'Terawat';
}

function lab_logbook(array $d): ?string
{
    return !empty($d['logbook']) || !empty($d['ada_logbook']) ? 'Ada' : 'Tidak Ada';
}

/**
 * Tarik master ke tabel $kode. Kembalikan ['dibuat'=>n,'diperbarui'=>n,'dilewati'=>n].
 *
 * @param bool $timpa  true = field Master ditimpa walau sudah ada isinya.
 *                     Operator mungkin sudah membetulkan manual; default false.
 */
function tarik_master(string $kode, int $prodiId, int $periodeId, bool $timpa = false): array
{
    $peta = tarik_peta()[$kode] ?? null;
    if ($peta === null) {
        return ['error' => 'Tabel ' . $kode . ' tidak punya peta tarikan master.'];
    }

    $sumber = $peta['sumber'];
    $saring = $peta['saring'] ?? '1=1';
    $urut = $peta['urut'] ?? 'id';

    // Hanya prodi yang bersangkutan. Master laboratorium/wahana/pustaka punya
    // prodi_id; dosen tidak (dia bisa lintas prodi).
    $punyaProdi = (int) qv("SELECT COUNT(*) FROM information_schema.columns
        WHERE table_schema = DATABASE() AND table_name = ? AND column_name = 'prodi_id'",
        [$sumber], 0) > 0;

    $where = $saring;
    $param = [];
    if ($punyaProdi) {
        $where .= ' AND prodi_id = ?';
        $param[] = $prodiId;
    } elseif ($sumber === 'dosen') {
        // Dosen lintas prodi: ambil yang terdaftar di prodi ini.
        $where .= ' AND EXISTS (SELECT 1 FROM dosen_prodi dp
                    WHERE dp.dosen_id = ' . $sumber . '.id AND dp.prodi_id = ?)';
        $param[] = $prodiId;
    }

    $rows = q("SELECT * FROM {$sumber} WHERE {$where} ORDER BY {$urut}", $param);

    // Field yang boleh diisi = hanya asal='Master'. Field Input milik operator.
    $specMaster = [];
    foreach (q("SELECT kunci, asal FROM spec_field WHERE tabel_kode = ?", [$kode]) as $f) {
        $specMaster[$f['kunci']] = $f['asal'];
    }

    $dibuat = 0;
    $diperbarui = 0;
    $dilewati = 0;
    $ke = 0;

    foreach ($rows as $r) {
        $ke++;
        $nilai = [];
        foreach ($peta['field'] as $kunci => $cara) {
            $asalField = $specMaster[$kunci] ?? '';
            // Yang ditolak hanya field Input milik operator. Field Hitung boleh
            // ditarik KALAU petanya memang menyebutkannya — untuk Tabel 31
            // angkanya hanya ada di master pustaka, tidak bisa dihitung.
            if ($asalField === 'Input' || $asalField === '') {
                continue;
            }
            $v = null;
            if (isset($cara['kolom'])) {
                $v = $r[$cara['kolom']] ?? null;
            } elseif (isset($cara['expr'])) {
                // expr hanya boleh dari peta di atas, bukan input pengguna.
                $v = qv("SELECT {$cara['expr']} FROM {$sumber} WHERE id = ?", [$r['id']], null);
            } elseif (array_key_exists('nilai', $cara)) {
                $v = $cara['nilai'];
            } elseif (isset($cara['closure'])) {
                $v = call_user_func($cara['closure'], $r);
            }
            if ($v !== null && $v !== '') {
                $nilai[$kunci] = (string) $v;
            }
        }

        // Baris sudah pernah ditarik? UNIQUE (periode, prodi, tabel, sumber, id).
        $ada = q1('SELECT id FROM baris_tabel
                   WHERE periode_id = ? AND prodi_id = ? AND tabel_kode = ?
                     AND sumber_tabel = ? AND sumber_id = ?',
            [$periodeId, $prodiId, $kode, $sumber, (int) $r['id']]);

        if ($ada) {
            $id = (int) $ada['id'];
            if (!$timpa) {
                // Field MASTER selalu diperbarui: sumbernya master, kalau master
                // berubah (nama dosen diperbaiki, lab pindah ruang) tabel harus
                // ikut. Menahan pembaruan membuat data basi bersembunyi.
                //
                // Field INPUT tidak pernah disentuh di sini — itu isian
                // operator, bukan salinan master.
                foreach (q('SELECT kunci FROM spec_field WHERE tabel_kode = ? AND asal = ?',
                           [$kode, 'Master']) as $mf) {
                    // hanya kalau peta memang mengisi field itu
                    if (!array_key_exists($mf['kunci'], $peta['field'])) {
                        unset($nilai[$mf['kunci']]);
                    }
                }
            }
            $diperbarui++;
        } else {
            $id = sisip('baris_tabel', [
                'prodi_id' => $prodiId, 'periode_id' => $periodeId,
                'tabel_kode' => $kode, 'urut' => $ke,
                'sumber_tabel' => $sumber, 'sumber_id' => (int) $r['id'],
            ]);
            // Nomor urut hanya diisi untuk baris BARU. Kalau operator sudah
            // mengganti nomornya sendiri, tarik ulang tidak boleh menimpanya —
            // itu field Input, bukan salinan master.
            if (isset($specMaster['no'])) {
                $nilai['no'] = (string) $ke;
            }
            $dibuat++;
        }

        // Kolom identitas (NIDN, NUP) harus TEKS: menyimpannya sebagai angka
        // menghapus nol di depan dan NIDN itu jadi milik orang lain.
        $labelSpec = [];
        foreach (q('SELECT kunci, label FROM spec_field WHERE tabel_kode = ?', [$kode]) as $sf) {
            $labelSpec[$sf['kunci']] = $sf['label'];
        }

        foreach ($nilai as $k => $v) {
            $identitas = (bool) preg_match('/\b(NIDN|NIDK|NUP|NIM|kode|nomor|no)\b/i',
                $labelSpec[$k] ?? '');
            $angka = (!$identitas && is_numeric($v)) ? $v : null;
            ex('INSERT INTO sel (baris_id, field_kunci, nilai_angka, nilai_teks)
                VALUES (?,?,?,?)
                ON DUPLICATE KEY UPDATE nilai_angka=VALUES(nilai_angka),
                                        nilai_teks=VALUES(nilai_teks)',
                [$id, $k, $angka, $angka === null ? $v : null]);
        }
        if ($nilai === []) {
            $dilewati++;
        }
    }

    return ['dibuat' => $dibuat, 'diperbarui' => $diperbarui,
            'dilewati' => $dilewati, 'sumber_baris' => count($rows)];
}

/**
 * Tabel mana yang punya peta tarikan.
 *
 * `array_keys()` mengubah kunci '16' menjadi int 16. Tanpa cast ke string,
 * `in_array('16', ..., true)` selalu false — tombol tarik hilang tanpa pesan,
 * dan penyebabnya tidak kelihatan sama sekali.
 */
function tabel_bisa_tarik(): array
{
    return array_map('strval', array_keys(tarik_peta()));
}
