<?php
declare(strict_types=1);

/**
 * API JSON untuk frontend `app/` (HTML/CSS/JS yang sudah ada).
 *
 * ATURAN MODUL INI: tidak pernah mencetak HTML. Semua jawaban JSON.
 * Tampilan ada di `app/`, bukan di sini. Kalau butuh markup baru, ubah `app/`.
 *
 * Rute (semua di bawah /api/):
 *   POST /api/masuk            {nama, sandi, prodi}
 *   POST /api/keluar
 *   GET  /api/saya             sesi + matriks akses
 *   GET  /api/ringkasan        angka turunan dashboard
 *   GET  /api/tabel            daftar 38 tabel + status (dipakai datadukung.js)
 *   GET  /api/tabel/<kode>     spec field + baris + nilai satu tabel
 *   POST /api/tabel/<kode>/isi {terisi: n}   |  {baris: [...]}
 *   POST /api/tabel/<kode>/kunci
 *   POST /api/tabel/<kode>/buka
 *   GET  /api/master/<nama>    mahasiswa|dosen|mk|wahana|lab|pustaka
 *   GET  /api/identitas        UPPS + prodi
 *   GET  /api/periode          periode + pratinjau label TS/SS
 *   GET  /api/dokumen          daftar dokumen + rekap per kriteria
 *   GET  /api/konsistensi      10 aturan + hasil hitung
 *   GET  /api/pengguna         daftar akun + matriks izin
 *   GET  /api/riwayat          log audit
 */

require_once __DIR__ . '/system/view.php';
require_once __DIR__ . '/system/rekap.php';
require_once __DIR__ . '/system/data.php';
require_once __DIR__ . '/system/kerjasama.php';
require_once __DIR__ . '/system/dosen.php';
require_once __DIR__ . '/system/koleksi.php';
require_once __DIR__ . '/system/identitas.php';
require_once __DIR__ . '/system/kelompok.php';
require_once __DIR__ . '/system/tarik.php';
require_once __DIR__ . '/system/tabel15.php';
require_once __DIR__ . '/spesifikasi/tabel.php';

/* ── util ───────────────────────────────────────────────────────── */
function json_keluar(mixed $data, int $kode = 200): never
{
    http_response_code($kode);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function input_json(): array
{
    $mentah = file_get_contents('php://input') ?: '';
    if ($mentah === '') {
        return $_POST;
    }
    $data = json_decode($mentah, true);
    return is_array($data) ? $data : $_POST;
}

/* ── rute ───────────────────────────────────────────────────────── */
$metode = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$jalur = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$bagian = array_values(array_filter(explode('/', trim($jalur, '/')), fn($s) => $s !== ''));
if (($bagian[0] ?? '') === 'api') {
    array_shift($bagian);
}
$r0 = $bagian[0] ?? '';
$r1 = $bagian[1] ?? '';
$r2 = $bagian[2] ?? '';

/* ── masuk / keluar / saya ──────────────────────────────────────── */
if ($r0 === 'masuk' && $metode === 'POST') {
    $b = input_json();
    $nama = trim((string) ($b['nama'] ?? $b['email'] ?? ''));
    $sandi = (string) ($b['sandi'] ?? '');

    // `nama` di frontend lama = nama akun; di DB kita pakai email. Terima
    // keduanya: kalau mengandung '@' cari per email, kalau tidak per nama.
    $u = str_contains($nama, '@')
        ? q1('SELECT * FROM pengguna WHERE email = ?', [strtolower($nama)])
        : q1('SELECT * FROM pengguna WHERE nama = ?', [$nama]);

    if (!$u) {
        json_keluar(['error' => 'Email atau sandi salah.'], 401);
    }
    if (!$u['aktif']) {
        json_keluar(['error' => 'Akun ini dinonaktifkan. Hubungi Admin UPPS.'], 403);
    }
    if (!$u['sandi'] || !password_verify($sandi, (string) $u['sandi'])) {
        json_keluar(['error' => 'Email atau sandi salah.'], 401);
    }
    if (peran_ganda((string) $u['peran_kode'])) {
        json_keluar(['error' => 'Akun ini punya peran ganda. Hubungi Admin UPPS.'], 409);
    }

    $prodi = isset($b['prodi']) ? (int) qv('SELECT id FROM prodi WHERE nama_ps = ?', [$b['prodi']], 0) : 0;
    masuk_sebagai((int) $u['id'], $prodi ?: ($u['prodi_id'] !== null ? (int) $u['prodi_id'] : null));

    $sesi = sesi_json();
    json_keluar(['sesi' => $sesi]);
}

if ($r0 === 'keluar' && $metode === 'POST') {
    keluar();
    json_keluar(['keluar' => true]);
}

if ($r0 === 'saya') {
    json_keluar(['sesi' => sesi_json(), 'aksi' => daftar_aksi()]);
}

/** Bentuk sesi yang dibaca `app/assets/js/api.js` (SESI.data). */
function sesi_json(): ?array
{
    $u = pengguna();
    if (!$u) {
        return null;
    }
    $akses = [];
    foreach (daftar_aksi() as $a) {
        // Frontend memakai nama pendek: Isi, Impor, Verifikasi, ...
        $akses[$a['nama']] = $a['akses'];
    }
    $prodi = prodi_aktif();
    return [
        'id' => (int) $u['id'],
        'nama' => $u['nama'],
        'email' => $u['email'],
        'peran' => $u['peran_kode'],
        'prodi' => $prodi ? qv('SELECT nama_ps FROM prodi WHERE id = ?', [$prodi], null) : null,
        'prodi_id' => $prodi,
        'status' => 'aktif',
        'akses' => $akses,
    ];
}

function daftar_aksi(): array
{
    $u = pengguna();
    $out = [];
    foreach (q('SELECT kode, nama FROM aksi ORDER BY kode') as $a) {
        $out[] = [
            'kode' => $a['kode'],
            'nama' => $a['nama'],
            'akses' => $u ? akses($a['kode'], $u) : 0,
        ];
    }
    return $out;
}

/* ── ringkasan ──────────────────────────────────────────────────── */
if ($r0 === 'ringkasan') {
    wajib_masuk();
    $p = prodi_aktif();
    $per = $p ? periode_aktif($p) : null;
    if (!$p || !$per) {
        json_keluar(['error' => 'Prodi/periode belum ditetapkan.'], 409);
    }
    $r = rekap_ringkas($p, (int) $per['id']);
    $d = rekap_dokumen($p, (int) $per['id']);
    json_keluar($r + [
        'dokumen_total' => $d['total'],
        'dokumen_terunggah' => $d['unggah'],
        'dokumen_kedaluwarsa' => $d['kedaluwarsa'],
        'per_kriteria' => rekap_per_kriteria($p, (int) $per['id']),
        'tahun_ts' => (int) $per['tahun_ts'],
        'prodi' => qv('SELECT nama_ps FROM prodi WHERE id = ?', [$p], null),
    ]);
}

/* ── tabel ──────────────────────────────────────────────────────── */
if ($r0 === 'tabel' && $r1 === '') {
    wajib_masuk();
    $p = prodi_aktif();
    $per = $p ? periode_aktif($p) : null;
    if (!$p || !$per) {
        json_keluar([]);
    }
    $out = [];
    foreach (rekap_semua_tabel($p, (int) $per['id']) as $t) {
        $out[] = [
            'kode' => $t['kode'],
            'judul' => $t['judul'],
            'kode_kriteria' => $t['kriteria'],
            'status' => $t['status'],
            'field_terisi' => $t['terisi'],
            'field_total' => $t['total'],
            'berlaku' => $t['berlaku'] ? 1 : 0,
            'dikunci_oleh' => $t['dikunci'] ? 'verifikator' : null,
            'hanya_hitung' => $t['hanya_hitung'] ?? false,
            'matriks' => in_array($t['kode'], tabel_matriks(), true),
        ];
    }
    json_keluar($out);
}

if ($r0 === 'tabel' && $r1 !== '') {
    $kodeTabel = preg_replace('/[^0-9]/', '', $r1);
    if ($r2 === '') {
        if ($metode !== 'GET') {
            json_keluar(['error' => 'metode tidak didukung'], 405);
        }
        wajib_masuk();
        $p = prodi_aktif();
        $per = $p ? periode_aktif($p) : null;
        if (!$p || !$per) {
            json_keluar(['error' => 'Prodi/periode belum ditetapkan.'], 409);
        }
        $spec = require __DIR__ . '/spesifikasi/tabel.php';
        // Kelompok + pilih diambil dari DB (sumber kebenaran sama dengan
        // validasi server) dan ditempelkan ke spec supaya halaman isian tidak
        // menebak aturannya sendiri.
        $meta = [];
        foreach (spec_satu_tabel($kodeTabel) as $f) {
            $meta[$f['kunci']] = $f;
        }
        if (isset($spec[$kodeTabel]['fields'])) {
            foreach ($spec[$kodeTabel]['fields'] as &$ff) {
                if (isset($meta[$ff['kunci']])) {
                    $ff['kelompok'] = $meta[$ff['kunci']]['kelompok'];
                    $ff['pilih'] = $meta[$ff['kunci']]['pilih'];
                    $ff['asal'] = $meta[$ff['kunci']]['asal'];
                }
            }
            unset($ff);
        }
        $baris = q('SELECT id, urut, dikunci_oleh FROM baris_tabel
                    WHERE tabel_kode = ? AND prodi_id = ? AND periode_id = ? ORDER BY urut',
            [$kodeTabel, $p, (int) $per['id']]);
        $nilai = [];
        foreach ($baris as $b) {
            $nilai[(int) $b['id']] = [];
        }
        if ($baris !== []) {
            $ids = array_column($baris, 'id');
            $in = implode(',', array_fill(0, count($ids), '?'));
            foreach (q("SELECT baris_id, field_kunci, nilai_angka, nilai_teks FROM sel
                        WHERE baris_id IN ($in)", $ids) as $s) {
                $nilai[(int) $s['baris_id']][$s['field_kunci']] =
                    $s['nilai_angka'] !== null ? $s['nilai_angka'] : $s['nilai_teks'];
            }
        }
        $judul = qv('SELECT judul FROM tabel_dukung WHERE kode = ?', [$kodeTabel], '');
        json_keluar([
            'kode' => $kodeTabel,
            'judul' => $judul,
            'status' => status_satu_tabel($kodeTabel, $p, (int) $per['id']),
            'spec' => $spec[$kodeTabel] ?? null,
            'bisa_tarik' => in_array($kodeTabel, tabel_bisa_tarik(), true),
            'matriks' => in_array($kodeTabel, tabel_matriks(), true),
            'baris' => array_map(fn($b) => [
                'id' => (int) $b['id'], 'urut' => (int) $b['urut'],
                'dikunci' => $b['dikunci_oleh'] !== null,
                'nilai' => $nilai[(int) $b['id']] ?? [],
            ], $baris),
        ]);
    }

    // tulis
    $b = input_json();
    if ($r2 === 'isi') {
        wajib('ISI');
        // Dua bentuk: {terisi:n} (prototipe) atau {baris:[{nilai:{...}}]} (form).
        if (isset($b['baris']) && is_array($b['baris'])) {
            $p = prodi_aktif();
            $per = $p ? periode_aktif($p) : null;
            if (!$p || !$per) {
                json_keluar(['error' => 'Prodi/periode belum ditetapkan.'], 409);
            }
            // Aturan pilih-satu ditegakkan SEBELUM menyentuh DB: kalau ada
            // baris yang mustahil, tidak ada satu pun yang tersimpan. Simpan
            // sebagian lalu menolak sebagian membuat operator tidak tahu mana
            // yang masuk.
            $spec = spec_satu_tabel($kodeTabel);
            foreach ($b['baris'] as $i => $baris) {
                $salah = periksa_kelompok((array) ($baris['nilai'] ?? []), $spec);
                if ($salah !== null) {
                    json_keluar(['error' => 'Baris ' . ($i + 1) . ': ' . $salah], 400);
                }
            }

            $hasil = transaksi(static function () use ($b, $kodeTabel, $p, $per) {
                $n = 0;
                foreach ($b['baris'] as $i => $baris) {
                    $id = isset($baris['id']) && (int) $baris['id'] > 0
                        ? (int) $baris['id'] : 0;
                    if ($id === 0) {
                        $id = sisip('baris_tabel', [
                            'prodi_id' => $p, 'periode_id' => (int) $per['id'],
                            'tabel_kode' => $kodeTabel, 'urut' => $i + 1,
                        ]);
                    }
                    foreach (($baris['nilai'] ?? []) as $kunci => $v) {
                        if ($v === '' || $v === null) {
                            ex('DELETE FROM sel WHERE baris_id = ? AND field_kunci = ?', [$id, $kunci]);
                            continue;
                        }
                        // Kolom identitas (NIDN, NUP, kode, nomor) harus TEKS.
                        // Menyimpannya sebagai angka menghapus nol di depan:
                        // "0012345678" jadi 12345678, dan NIDN itu milik orang
                        // lain. Dicek dari label spec, bukan dari isi nilainya.
                        $specKunci = [];
                        foreach (spec_satu_tabel($kodeTabel) as $sf) {
                            $specKunci[$sf['kunci']] = $sf;
                        }
                        $labelKunci = $specKunci[$kunci]['label'] ?? '';
                        $identitas = (bool) preg_match('/\b(NIDN|NIDK|NUP|NIM|kode|nomor|no)\b/i', $labelKunci);

                        $angka = (!$identitas && is_numeric($v)) ? $v : null;
                        ex('INSERT INTO sel (baris_id, field_kunci, nilai_angka, nilai_teks)
                            VALUES (?,?,?,?)
                            ON DUPLICATE KEY UPDATE nilai_angka=VALUES(nilai_angka),
                                                    nilai_teks=VALUES(nilai_teks)',
                            [$id, $kunci, $angka, $angka === null ? (string) $v : null]);
                        $n++;
                    }
                }
                return $n;
            });
            json_keluar(['disimpan' => $hasil,
                         'status' => status_satu_tabel($kodeTabel, $p, (int) $per['id'])]);
        }
        if (!isset($b['terisi'])) {
            json_keluar(['error' => "butuh field 'terisi' atau 'baris'"], 400);
        }
        $data = isi_tabel_api($kodeTabel, (int) $b['terisi'], $pengguna['nama'] ?? 'operator');
        json_keluar($data);
    }
    if ($r2 === 'matriks') {
        // Tabel 15 seluruhnya dihitung dari master dosen; tidak ada isian.
        // Dihitung saat diminta, bukan disimpan — kalau disimpan, angkanya
        // basi setiap kali master dosen berubah.
        wajib_masuk();
        if (in_array($kodeTabel, tabel_matriks(), true)) {
            json_keluar(t15_hitung() + ['boleh_ubah' => boleh('ISI')]);
        }
        json_keluar(['error' => 'Tabel ' . $kodeTabel . ' bukan tabel matriks.'], 404);
    }
    if ($r2 === 'tarik') {
        // Tarik data master. `wajib('ISI')` karena ini MENULIS ke tabel —
        // siapa pun yang boleh mengisi boleh menarik; yang tidak boleh
        // mengisi tidak boleh juga.
        wajib('ISI');
        $b = input_json();
        $p = prodi_aktif();
        $per = $p ? periode_aktif($p) : null;
        if (!$p || !$per) {
            json_keluar(['error' => 'Prodi/periode belum ditetapkan.'], 409);
        }
        if (!in_array($kodeTabel, tabel_bisa_tarik(), true)) {
            json_keluar(['error' => 'Tabel ' . $kodeTabel . ' tidak ditarik dari master; isi manual.'], 400);
        }
        $hasil = tarik_master($kodeTabel, $p, (int) $per['id'], !empty($b['timpa']));
        if (isset($hasil['error'])) {
            json_keluar($hasil, 400);
        }
        ex('INSERT INTO audit (pengguna_id, prodi_id, aksi, entitas, entitas_id, nilai_baru)
            VALUES (?,?,?,?,?,?)',
            [pengguna()['id'] ?? null, $p, 'tarik', 'tabel', $kodeTabel,
             json_encode($hasil, JSON_UNESCAPED_UNICODE)]);
        json_keluar($hasil + ['status' => status_satu_tabel($kodeTabel, $p, (int) $per['id'])]);
    }
    if ($r2 === 'kunci') {
        wajib('VERIFIKASI');
        json_keluar(kunci_tabel_api($kodeTabel, pengguna()['nama']));
    }
    if ($r2 === 'buka') {
        wajib('BUKA_KUNCI');
        json_keluar(buka_kunci_api($kodeTabel, pengguna()['nama']));
    }
    json_keluar(['error' => 'aksi tidak dikenal'], 404);
}

/* ── master ─────────────────────────────────────────────────────── */
if ($r0 === 'master') {
    wajib_masuk();
    $p = prodi_aktif();
    $tabel = ['mahasiswa' => 'mahasiswa', 'dosen' => 'dosen', 'mk' => 'mata_kuliah',
              'wahana' => 'wahana', 'lab' => 'laboratorium', 'pustaka' => 'pustaka'];
    if (!isset($tabel[$r1])) {
        json_keluar(['error' => 'entitas tidak dikenal'], 404);
    }
    $nama = $tabel[$r1];
    $punyaProdi = $nama !== 'dosen';
    $sql = 'SELECT * FROM ' . $nama
        . ($punyaProdi ? ' WHERE prodi_id = ?' : '') . ' ORDER BY id';
    json_keluar(['entitas' => $r1,
                 'baris' => q($sql, $punyaProdi ? [$p] : [])]);
}

/* ── kerja sama (Tabel 38) ──────────────────────────────────────── */
if ($r0 === 'kerjasama') {
    wajib_masuk();

    if ($metode === 'GET') {
        $daftar = mitra_daftar(prodi_aktif());
        json_keluar([
            'mitra' => $daftar,
            'ringkas' => mitra_ringkas($daftar),
            'pilihan' => ['jenis' => MITRA_JENIS, 'lingkup' => MITRA_LINGKUP],
            'wahana' => array_map(fn($w) => ['id' => (int) $w['id'], 'nama' => $w['nama']],
                q('SELECT id, nama FROM wahana WHERE prodi_id = ? ORDER BY nama',
                  [prodi_aktif()])),
            'boleh_ubah' => boleh('ISI'),
        ]);
    }

    if ($metode === 'POST') {
        wajib('ISI');
        $b = input_json();
        $id = (int) ($b['id'] ?? 0);
        $hasil = mitra_simpan($b, $id);
        json_keluar($hasil, isset($hasil['error']) ? 400 : 200);
    }

    if ($metode === 'DELETE') {
        wajib('ISI');
        $b = input_json();
        $id = (int) ($b['id'] ?? $r1);
        json_keluar(mitra_hapus($id));
    }
    json_keluar(['error' => 'metode tidak didukung'], 405);
}

/* ── identitas / periode / keuangan ─────────────────────────────── */
if ($r0 === 'identitas') {
    wajib_masuk();
    if ($metode === 'GET') {
        json_keluar(identitas_baca() + ['boleh_ubah' => boleh('ISI')]);
    }
    wajib('ISI');
    json_keluar(identitas_simpan(input_json()));
}

if ($r0 === 'periode') {
    wajib_masuk();
    if ($metode === 'GET') {
        json_keluar([
            'baris' => periode_daftar(),
            'prodi' => q('SELECT id, nama_ps FROM prodi ORDER BY id'),
            'boleh_ubah' => boleh('ISI'),
        ]);
    }
    wajib('ISI');
    $b = input_json();
    if ($metode === 'POST') {
        $hasil = periode_simpan($b, (int) ($b['id'] ?? 0));
        json_keluar($hasil, isset($hasil['error']) ? 400 : 200);
    }
    if ($metode === 'DELETE') {
        json_keluar(periode_hapus((int) ($b['id'] ?? $r1)));
    }
    json_keluar(['error' => 'metode tidak didukung'], 405);
}

if ($r0 === 'keuangan') {
    wajib_masuk();
    if ($metode === 'GET') {
        $pid = isset($_GET['prodi']) ? (int) $_GET['prodi'] : null;
        json_keluar(keuangan_baca($pid) + [
            'prodi' => q('SELECT id, nama_ps FROM prodi WHERE aktif = 1 ORDER BY id'),
            'boleh_ubah' => boleh('ISI'),
        ]);
    }
    wajib('ISI');
    $b = input_json();
    $jenis = ($b['jenis_baris'] ?? $r1) === 'guna' ? 'guna' : 'terima';
    if ($metode === 'POST') {
        $hasil = keuangan_simpan($b, $jenis);
        json_keluar($hasil, isset($hasil['error']) ? 400 : 200);
    }
    if ($metode === 'DELETE') {
        json_keluar(keuangan_hapus($jenis, (int) ($b['id'] ?? 0)));
    }
    json_keluar(['error' => 'metode tidak didukung'], 405);
}

/* ── koleksi generik: mahasiswa, mata_kuliah, laboratorium, pustaka,
      uji_kompetensi, survei, tracer, stase ────────────────────────── */
if (koleksi_ada($r0)) {
    wajib_masuk();

    if ($metode === 'GET') {
        $daftar = koleksi_daftar($r0, ['cari' => $_GET['cari'] ?? '', 'prodi_id' => $_GET['prodi'] ?? '']);
        $field = [];
        foreach (koleksi_field($r0) as $kol => $f) {
            $field[$kol] = [
                'label' => $f['label'] ?? $kol,
                'jenis' => $f['jenis'] ?? 'teks',
                'wajib' => !empty($f['wajib']),
                'maks' => $f['maks'] ?? null,
                'min' => $f['min'] ?? null,
                'opsi' => $f['opsi'] ?? null,
                'opsi_teks' => $f['opsi_teks'] ?? null,
                'sumber' => $f['sumber'] ?? null,
                'pilihan' => isset($f['sumber']) ? koleksi_pilihan($f['sumber']) : null,
                'bawaan' => $f['bawaan'] ?? null,
            ];
        }
        $def = koleksi_def()[$r0];
        json_keluar([
            'baris' => $daftar,
            'field' => $field,
            'judul' => $def['judul'],
            'kode' => $def['kode'],
            'kolom' => $def['kolom'],
            'keterangan' => $def['keterangan'] ?? '',
            'prodi' => q('SELECT id, nama_ps FROM prodi WHERE aktif = 1 ORDER BY id'),
            'boleh_ubah' => boleh('ISI'),
        ]);
    }

    if ($metode === 'POST') {
        wajib('ISI');
        $b = input_json();
        $hasil = koleksi_simpan($r0, $b, (int) ($b['id'] ?? 0));
        json_keluar($hasil, isset($hasil['error']) ? 400 : 200);
    }

    if ($metode === 'DELETE') {
        wajib('ISI');
        $b = input_json();
        json_keluar(koleksi_hapus($r0, (int) ($b['id'] ?? $r1)));
    }
    json_keluar(['error' => 'metode tidak didukung'], 405);
}

/* ── dosen & tendik ─────────────────────────────────────────────── */
if ($r0 === 'dosen') {
    wajib_masuk();

    if ($metode === 'GET') {
        $saring = [
            'jenis' => $_GET['jenis'] ?? '',
            'prodi' => $_GET['prodi'] ?? '',
            'cari' => $_GET['cari'] ?? '',
        ];
        $daftar = dosen_daftar($saring);
        json_keluar([
            'dosen' => $daftar,
            'ringkas' => dosen_ringkas($daftar),
            'pilihan' => [
                'jenis' => DOSEN_JENIS, 'jabatan' => DOSEN_JABATAN,
                'profesi' => DOSEN_PROFESI, 'tahap' => DOSEN_TAHAP,
                'jenjang' => DOSEN_JENJANG,
            ],
            'prodi' => q('SELECT id, nama_ps FROM prodi WHERE aktif = 1 ORDER BY id'),
            'boleh_ubah' => boleh('ISI'),
        ]);
    }

    if ($metode === 'POST') {
        wajib('ISI');
        $b = input_json();
        $hasil = dosen_simpan($b, (int) ($b['id'] ?? 0));
        json_keluar($hasil, isset($hasil['error']) ? 400 : 200);
    }

    if ($metode === 'DELETE') {
        wajib('ISI');
        $b = input_json();
        json_keluar(dosen_nonaktifkan((int) ($b['id'] ?? $r1)));
    }
    json_keluar(['error' => 'metode tidak didukung'], 405);
}

/* ── identitas / periode ────────────────────────────────────────── */
if ($r0 === 'identitas') {
    wajib_masuk();
    $p = prodi_aktif();
    json_keluar([
        'upps' => q1('SELECT * FROM upps ORDER BY id LIMIT 1'),
        'prodi' => $p ? q1('SELECT * FROM prodi WHERE id = ?', [$p]) : null,
        'semua_prodi' => q('SELECT id, nama_ps, jenjang FROM prodi WHERE aktif = 1 ORDER BY id'),
    ]);
}

if ($r0 === 'periode') {
    wajib_masuk();
    $p = prodi_aktif();
    $per = $p ? periode_aktif($p) : null;
    if (!$per) {
        json_keluar(['error' => 'periode belum ada'], 409);
    }
    $ts = (int) $per['tahun_ts'];
    $ss = (int) ($per['tahun_ss'] ?? $ts - 5);
    $labelTs = [];
    for ($i = 0; $i <= 6; $i++) {
        $labelTs[] = ['mundur' => $i, 'tahun' => $ts - $i, 'label' => label_ts($ts, $i)];
    }
    $labelSs = [];
    for ($i = 1; $i <= 16; $i++) {
        $t = $ss + $i - 1;
        $labelSs[] = ['ke' => $i, 'tahun' => $t, 'label' => 'SS-' . $i . ' (' . $t . '/' . ($t + 1) . ')'];
    }
    json_keluar(['periode' => $per, 'label_ts' => $labelTs, 'label_ss' => $labelSs]);
}

/* ── dokumen ────────────────────────────────────────────────────── */
if ($r0 === 'dokumen') {
    wajib_masuk();
    $p = prodi_aktif();
    $per = $p ? periode_aktif($p) : null;
    if (!$p || !$per) {
        json_keluar(['error' => 'Prodi/periode belum ditetapkan.'], 409);
    }
    $daftar = q(
        'SELECT d.id, d.kriteria, d.nomor, d.nama,
                (SELECT COUNT(*) FROM dokumen_unggah u
                  WHERE u.dok_spec_id = d.id AND u.prodi_id = ? AND u.periode_id = ?) AS versi,
                (SELECT MAX(u.diunggah_pada) FROM dokumen_unggah u
                  WHERE u.dok_spec_id = d.id AND u.prodi_id = ? AND u.periode_id = ?) AS terakhir
         FROM dokumen_spec d ORDER BY d.kriteria, d.nomor',
        [$p, (int) $per['id'], $p, (int) $per['id']]);
    json_keluar([
        'rekap' => rekap_dokumen($p, (int) $per['id']),
        'per_kriteria' => rekap_dokumen_per_kriteria($p, (int) $per['id']),
        'butir' => $daftar,
    ]);
}

/* ── konsistensi ────────────────────────────────────────────────── */
if ($r0 === 'konsistensi') {
    wajib_masuk();
    $p = prodi_aktif();
    $per = $p ? periode_aktif($p) : null;
    if (!$p || !$per) {
        json_keluar(['error' => 'Prodi/periode belum ditetapkan.'], 409);
    }
    $berkas = __DIR__ . '/modul/konsistensi/fungsi.php';
    if (!is_file($berkas)) {
        json_keluar(['error' => 'modul konsistensi belum ada'], 500);
    }
    require_once $berkas;
    $hasil = konsistensi_jalankan($p, (int) $per['id']);
    json_keluar([
        'aturan' => q('SELECT kode, nama, tingkat, catatan FROM konsistensi_aturan
                       WHERE aktif = 1 ORDER BY kode'),
        'hasil' => $hasil,
        'ringkas' => function_exists('konsistensi_ringkas') ? konsistensi_ringkas($hasil) : null,
        'blokir' => function_exists('konsistensi_blokir') ? konsistensi_blokir() : [],
    ]);
}

/* ── pengguna ───────────────────────────────────────────────────── */
if ($r0 === 'pengguna') {
    wajib('KELOLA_PENGGUNA');
    json_keluar([
        'daftar' => daftar_pengguna(),
        'ringkasan' => ringkasan_akun(),
        'peran' => q('SELECT kode, nama FROM peran ORDER BY kode'),
        'izin' => q('SELECT peran_kode, aksi_kode, akses FROM izin ORDER BY peran_kode, aksi_kode'),
    ]);
}

if ($r0 === 'riwayat') {
    wajib_masuk();
    $batas = min(200, max(1, (int) ($_GET['batas'] ?? 50)));
    json_keluar(q('SELECT a.id, a.waktu, a.aksi, a.entitas, a.entitas_id, a.field,
                          a.nilai_lama, a.nilai_baru, p.nama AS oleh
                   FROM audit a LEFT JOIN pengguna p ON p.id = a.pengguna_id
                   ORDER BY a.id DESC LIMIT ' . $batas));
}

json_keluar(['error' => 'endpoint tidak ada: ' . $jalur], 404);
