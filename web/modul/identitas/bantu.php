<?php
declare(strict_types=1);

/**
 * Bantu modul Identitas (F-01) dan Periode (F-02).
 *
 * Dua hal di halaman ini menentukan seluruh sistem, jadi dihitung — tidak diketik:
 *   1. berapa tabel yang wajib untuk satu prodi (dari `tabel_dukung`)
 *   2. label tahun relatif (dari tahun absolut di `periode`)
 */

/**
 * Tabel wajib untuk satu prodi. Tabel `khusus_profesi` (4,6,7,8,11,30) hanya
 * wajib bila punya tahap profesi. 38 dan 32 TIDAK ditulis di sini — diturunkan.
 */
function wajib_tabel(int $prodiId): array
{
    $khusus = array_map('intval', array_column(
        q('SELECT nomor FROM tabel_dukung WHERE khusus_profesi = 1 ORDER BY nomor'), 'nomor'));
    $total = (int) qv('SELECT COUNT(*) FROM tabel_dukung', [], 0);
    $punya = (int) qv('SELECT punya_tahap_profesi FROM prodi WHERE id = ?', [$prodiId], 0) === 1;

    return [
        'total'  => $total,
        'khusus' => $khusus,
        'penuh'  => $total,
        'tanpa'  => $total - count($khusus),
        'wajib'  => $punya ? $total : $total - count($khusus),
    ];
}

/** Baris yang sudah tersimpan di tabel khusus profesi — jadi `na`, bukan hilang. */
function baris_khusus_profesi(int $prodiId): int
{
    return (int) qv(
        'SELECT COUNT(*) FROM baris_tabel b
         JOIN tabel_dukung t ON t.kode = b.tabel_kode
         WHERE b.prodi_id = ? AND t.khusus_profesi = 1', [$prodiId], 0);
}

/** Pilihan jenjang dibaca dari ENUM kolom `prodi.jenjang`, bukan disalin manual. */
function opsi_jenjang(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $tipe = (string) qv(
        "SELECT COLUMN_TYPE FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'prodi' AND COLUMN_NAME = 'jenjang'",
        [], '');
    preg_match_all("/'([^']+)'/", $tipe, $m);
    return $cache = ($m[1] ?: ['Sarjana']);
}

/**
 * Prodi yang sedang dibuka. Operator/Verifikator terikat ke prodinya sendiri;
 * Admin dan Pimpinan berpindah lewat `?p=<id>`, divalidasi ke tabel prodi.
 */
function prodi_terpilih(): ?array
{
    $pid = prodi_aktif();
    if (!$pid) {
        $minta = (int) ($_GET['p'] ?? 0);
        $pid = (int) qv('SELECT id FROM prodi WHERE id = ? AND aktif = 1', [$minta], 0);
        if (!$pid) {
            $pid = (int) qv('SELECT id FROM prodi WHERE aktif = 1 ORDER BY id LIMIT 1', [], 0);
        }
    }
    if (!$pid) {
        return null;
    }
    // 10 field prodi + 8 field UPPS sekaligus: halaman ini menampilkan keduanya.
    return q1(
        'SELECT p.id, p.upps_id, p.nama_ps, p.jenjang, p.bidang, p.punya_tahap_profesi,
                p.nama_profesi_lulusan, p.lama_studi_semester, p.nama_uji_kompetensi,
                p.peringkat_akreditasi, p.tgl_akreditasi, p.tgl_berakhir_akreditasi,
                u.nama_pt, u.nama_upps, u.alamat, u.kota, u.telepon, u.email, u.website,
                u.narahubung
         FROM prodi p JOIN upps u ON u.id = p.upps_id
         WHERE p.id = ?', [$pid]);
}

/** 8 field UPPS. `maks` = panjang kolom, supaya pesan galat jelas, bukan potong diam-diam. */
function spec_upps(): array
{
    return [
        ['kunci' => 'nama_pt',    'label' => 'Nama perguruan tinggi', 'kendali' => 'text',  'wajib' => true,  'maks' => 160, 'catatan' => 'Berlaku untuk semua prodi di UPPS ini'],
        ['kunci' => 'nama_upps',  'label' => 'Nama unit pengelola',   'kendali' => 'text',  'wajib' => true,  'maks' => 160, 'catatan' => 'Fakultas / sekolah tinggi'],
        ['kunci' => 'narahubung', 'label' => 'Narahubung asesor',     'kendali' => 'text',  'wajib' => true,  'maks' => 120, 'catatan' => 'Nama dan nomor seluler yang dihubungi asesor'],
        ['kunci' => 'kota',       'label' => 'Kota / kabupaten',      'kendali' => 'text',  'wajib' => false, 'maks' => 80,  'catatan' => ''],
        ['kunci' => 'alamat',     'label' => 'Alamat',                'kendali' => 'text',  'wajib' => false, 'maks' => 255, 'catatan' => 'Alamat lengkap unit pengelola'],
        ['kunci' => 'telepon',    'label' => 'Telepon',               'kendali' => 'tel',   'wajib' => false, 'maks' => 40,  'catatan' => 'Nomor yang aktif pada jam kerja'],
        ['kunci' => 'email',      'label' => 'Email',                 'kendali' => 'email', 'wajib' => false, 'maks' => 120, 'catatan' => ''],
        ['kunci' => 'website',    'label' => 'Situs web',             'kendali' => 'text',  'wajib' => false, 'maks' => 120, 'catatan' => ''],
    ];
}

/** 10 field prodi. */
function spec_prodi(): array
{
    return [
        ['kunci' => 'nama_ps',                 'label' => 'Nama program studi',    'kendali' => 'text',   'wajib' => true,  'maks' => 120, 'catatan' => ''],
        ['kunci' => 'jenjang',                 'label' => 'Jenjang',               'kendali' => 'select', 'wajib' => true,  'maks' => 20,  'catatan' => 'Menentukan varian Tabel 5'],
        ['kunci' => 'bidang',                  'label' => 'Bidang',                'kendali' => 'text',   'wajib' => true,  'maks' => 80,  'catatan' => ''],
        ['kunci' => 'punya_tahap_profesi',     'label' => 'Punya tahap profesi',   'kendali' => 'flag',   'wajib' => true,  'maks' => 1,   'catatan' => 'Menentukan tabel 4, 6, 7, 8, 11, 30 wajib atau tidak'],
        ['kunci' => 'nama_profesi_lulusan',    'label' => 'Nama profesi lulusan',  'kendali' => 'text',   'wajib' => false, 'maks' => 80,  'catatan' => 'Kosongkan bila tidak ada tahap profesi'],
        ['kunci' => 'lama_studi_semester',     'label' => 'Lama studi (semester)', 'kendali' => 'number', 'wajib' => false, 'maks' => 2,   'catatan' => ''],
        ['kunci' => 'nama_uji_kompetensi',     'label' => 'Nama uji kompetensi',   'kendali' => 'text',   'wajib' => false, 'maks' => 120, 'catatan' => ''],
        ['kunci' => 'peringkat_akreditasi',    'label' => 'Peringkat akreditasi',  'kendali' => 'text',   'wajib' => false, 'maks' => 40,  'catatan' => ''],
        ['kunci' => 'tgl_akreditasi',          'label' => 'Tanggal akreditasi',    'kendali' => 'date',   'wajib' => false, 'maks' => 10,  'catatan' => ''],
        ['kunci' => 'tgl_berakhir_akreditasi', 'label' => 'Berakhir akreditasi',   'kendali' => 'date',   'wajib' => false, 'maks' => 10,  'catatan' => 'Diperingatkan otomatis 180 hari sebelum berakhir'],
    ];
}

/** Sisa hari sampai tanggal. Negatif = sudah lewat. null = kosong / tidak sah. */
function sisa_hari(?string $iso): ?int
{
    if (!$iso) {
        return null;
    }
    $t = strtotime($iso);
    if ($t === false) {
        return null;
    }
    return (int) floor(($t - strtotime(date('Y-m-d'))) / 86400);
}

/**
 * Peringatan masa berlaku akreditasi, dihitung dari tanggal. null = masih jauh,
 * tidak perlu blok apa pun (jangan render blok kosong).
 */
function pesan_akreditasi(?string $iso, int $ambang = 180): ?array
{
    $sisa = sisa_hari($iso);
    if ($sisa === null) {
        return null;
    }
    if ($sisa < 0) {
        return ['jenis' => 'bad', 'judul' => 'Akreditasi sudah berakhir',
                'pesan' => sprintf('Berakhir %s — %d hari lalu. Ajukan reakreditasi.', tgl($iso), abs($sisa))];
    }
    if ($sisa <= $ambang) {
        return ['jenis' => 'warn', 'judul' => 'Akreditasi hampir berakhir',
                'pesan' => sprintf('Berakhir %s — %d hari lagi.', tgl($iso), $sisa)];
    }
    return null;
}

/**
 * Label tahun tahap profesi. SS-1 = tahun pertama tahap profesi, jadi SS
 * bergerak MAJU dari `tahun_ss` — kebalikan TS yang menghitung mundur.
 */
function label_ss(int $tahunSs, int $n): string
{
    $t = $tahunSs + $n - 1;
    return sprintf('SS-%d (%d/%d)', $n, $t, $t + 1);
}

/**
 * Satu kendali form identitas. $nilai sudah final (POST gagal atau DB).
 * Ada di sini, bukan di index.php: index.php di-require ulang oleh uji dan
 * mendeklarasikan fungsi di dalamnya akan bentrok (fatal redeclare).
 */
function kendali(array $f, mixed $nilai, bool $boleh): string
{
    $id = 'f_' . $f['kunci'];
    $v = (string) ($nilai ?? '');
    $wajib = $f['wajib'] ? ' required' : '';
    $mati = $boleh ? '' : ' disabled';

    if ($f['kendali'] === 'select') {
        $o = '';
        foreach (opsi_jenjang() as $j) {
            $o .= sprintf('<option value="%s"%s>%s</option>', e($j), $j === $v ? ' selected' : '', e($j));
        }
        $isi = sprintf('<select id="%s" name="%s"%s%s>%s</select>', $id, e($f['kunci']), $wajib, $mati, $o);
    } elseif ($f['kendali'] === 'flag') {
        // Hidden 0 selalu ikut: checkbox tak dicentang tidak mengirim apa pun,
        // dan "tidak ada" tidak boleh berarti "jangan ubah".
        $isi = '<span class="flag"><input type="hidden" name="' . e($f['kunci']) . '" value="0">'
            . '<input type="checkbox" id="' . $id . '" name="' . e($f['kunci']) . '" value="1"'
            . ((int) $v === 1 ? ' checked' : '') . $mati . '>'
            . '<label for="' . $id . '">Ya, prodi ini punya tahap profesi</label></span>';
    } else {
        $tipe = in_array($f['kendali'], ['email', 'date', 'tel', 'number'], true) ? $f['kendali'] : 'text';
        $extra = $tipe === 'number' ? ' min="1" max="14" step="1" inputmode="numeric"' : '';
        $isi = sprintf('<input type="%s" id="%s" name="%s" value="%s" maxlength="%d"%s%s%s>',
            $tipe, $id, e($f['kunci']), e($v), $f['maks'], $wajib, $extra, $mati);
    }

    return '<div class="fld"><label for="' . $id . '">' . e($f['label'])
        . ($f['wajib'] ? ' <span class="req">wajib</span>' : '') . '</label>' . $isi
        . ($f['catatan'] !== '' ? '<div class="fn">' . e($f['catatan']) . '</div>' : '') . '</div>';
}
