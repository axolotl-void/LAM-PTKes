<?php
declare(strict_types=1);

/**
 * Logika dokumen pendukung. Dipisah dari index.php supaya bisa diuji tanpa
 * HTTP (web/uji/uji_dokumen.php cukup me-require berkas ini).
 *
 * Dua aturan yang menentukan seluruh berkas ini:
 *  1. Nama berkas simpanan TIDAK PERNAH berasal dari nama asli. Nama asli cuma
 *     dipakai untuk mengambil ekstensi dan disimpan di kolom `nama_asli`.
 *  2. Tipe berkas ditentukan dari ISI (finfo), bukan dari $_FILES['type'] —
 *     itu dikirim klien dan bisa apa saja.
 */

require_once __DIR__ . '/../../system/rekap.php';

/** Batas ukuran unggahan: 20 MB. */
const DOK_MAKS_BYTE = 20 * 1024 * 1024;

/** Ekstensi -> MIME yang sah. Dua-duanya harus cocok, bukan salah satu. */
const DOK_TIPE = [
    'pdf'  => ['application/pdf'],
    'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
    'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
    'jpg'  => ['image/jpeg'],
    'jpeg' => ['image/jpeg'],
    'png'  => ['image/png'],
];

const DOK_EKSTENSI = ['pdf', 'docx', 'xlsx', 'jpg', 'jpeg', 'png'];

/** Nama berkas yang dipakai di tampilan, tanpa direktori. */
function dok_nama_bersih(string $nama): string
{
    $n = str_replace('\\', '/', $nama);
    $n = basename($n);
    return $n === '' ? 'berkas' : $n;
}

/**
 * Validasi satu berkas. Mengembalikan alasan penolakan yang konkret, bukan
 * "gagal unggah" — operator harus tahu apa yang harus diperbaiki.
 *
 * @return array{ok:bool, alasan:string, ext:string, mime:string}
 */
function dok_validasi_berkas(string $path, string $namaAsli, int $ukuran): array
{
    $nama = dok_nama_bersih($namaAsli);
    $ext  = strtolower((string) pathinfo($nama, PATHINFO_EXTENSION));

    if ($ext === '') {
        return ['ok' => false, 'ext' => '', 'mime' => '',
                'alasan' => sprintf('Berkas "%s" tidak punya ekstensi. Yang boleh: %s.',
                    $nama, implode(', ', DOK_EKSTENSI))];
    }
    if (!isset(DOK_TIPE[$ext])) {
        return ['ok' => false, 'ext' => $ext, 'mime' => '',
                'alasan' => sprintf('Ekstensi .%s tidak diizinkan. Yang boleh: %s.',
                    $ext, implode(', ', DOK_EKSTENSI))];
    }
    if ($ukuran > DOK_MAKS_BYTE) {
        return ['ok' => false, 'ext' => $ext, 'mime' => '',
                'alasan' => sprintf('Ukuran berkas %s MB melebihi batas 20 MB.',
                    number_format($ukuran / 1048576, 1, ',', '.'))];
    }
    if ($ukuran <= 0 || !is_file($path)) {
        return ['ok' => false, 'ext' => $ext, 'mime' => '',
                'alasan' => 'Berkas kosong atau tidak terbaca dari server.'];
    }

    // finfo membaca magic bytes. Berkas .exe yang diganti nama jadi .pdf
    // tetap terdeteksi executable di sini.
    $mime = '';
    if (function_exists('finfo_open')) {
        $fi = finfo_open(FILEINFO_MIME_TYPE);
        if ($fi !== false) {
            $mime = (string) finfo_file($fi, $path);
            // finfo_close() deprecated di PHP 8.5; objek dilepas otomatis.
        }
    }
    if ($mime === '') {
        return ['ok' => false, 'ext' => $ext, 'mime' => '',
                'alasan' => 'Tipe isi berkas tidak bisa diperiksa server.'];
    }
    if (!in_array($mime, DOK_TIPE[$ext], true)) {
        return ['ok' => false, 'ext' => $ext, 'mime' => $mime,
                'alasan' => sprintf('Isi berkas terdeteksi "%s", bukan berkas .%s yang sah.', $mime, $ext)];
    }

    return ['ok' => true, 'alasan' => '', 'ext' => $ext, 'mime' => $mime];
}

/** Terjemahan kode galat unggahan PHP jadi kalimat. */
function dok_alasan_galat_php(int $kode): string
{
    return match ($kode) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE =>
            'Ukuran berkas melebihi batas server (upload_max_filesize ' . ini_get('upload_max_filesize') . ').',
        UPLOAD_ERR_PARTIAL   => 'Berkas hanya terkirim sebagian. Coba unggah ulang.',
        UPLOAD_ERR_NO_FILE   => 'Belum ada berkas yang dipilih.',
        UPLOAD_ERR_NO_TMP_DIR => 'Folder sementara server tidak ada.',
        UPLOAD_ERR_CANT_WRITE => 'Server gagal menulis berkas ke disk.',
        UPLOAD_ERR_EXTENSION => 'Unggahan dihentikan ekstensi PHP.',
        default => 'Unggahan gagal dengan kode ' . $kode . '.',
    };
}

/**
 * Simpan satu berkas untuk satu butir dokumen.
 *
 * @param array{name:string,tmp_name:string,size:int,error:int} $berkas elemen dari $_FILES
 * @return array{ok:bool,pesan:string,id:int,berkas:string}
 */
function dok_simpan_unggah(
    int $dokSpecId,
    int $prodiId,
    int $periodeId,
    array $berkas,
    ?int $oleh,
    ?string $berlakuSampai = null,
    string $catatan = ''
): array {
    // Pemeriksaan di sini, bukan di pemanggil: satu jalan masuk, satu validasi.
    $spec = q1('SELECT id, kriteria, nomor, nama FROM dokumen_spec WHERE id = ?', [$dokSpecId]);
    if (!$spec) {
        return ['ok' => false, 'pesan' => 'Butir dokumen tidak dikenal.', 'id' => 0, 'berkas' => ''];
    }

    $kode = (int) ($berkas['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($kode !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'pesan' => dok_alasan_galat_php($kode), 'id' => 0, 'berkas' => ''];
    }

    $namaAsli = (string) ($berkas['name'] ?? '');
    $tmp      = (string) ($berkas['tmp_name'] ?? '');
    $ukuran   = (int) ($berkas['size'] ?? 0);

    // is_uploaded_file() memastikan path benar-benar hasil unggahan PHP, bukan
    // path yang diselundupkan. Di CLI tidak ada unggahan, jadi uji memakai jalur
    // rename — keduanya tetap menulis ke UNGGAH dengan nama buatan sendiri.
    $dariUnggahan = is_uploaded_file($tmp);
    if (!$dariUnggahan && PHP_SAPI !== 'cli') {
        return ['ok' => false, 'pesan' => 'Berkas bukan berasal dari form unggah.', 'id' => 0, 'berkas' => ''];
    }

    $v = dok_validasi_berkas($tmp, $namaAsli, $ukuran);
    if (!$v['ok']) {
        return ['ok' => false, 'pesan' => $v['alasan'], 'id' => 0, 'berkas' => ''];
    }

    if (!is_dir(UNGGAH)) {
        if (!mkdir(UNGGAH, 0755, true) && !is_dir(UNGGAH)) {
            return ['ok' => false, 'pesan' => 'Folder unggah tidak bisa dibuat di ' . UNGGAH . '.',
                    'id' => 0, 'berkas' => ''];
        }
        @chmod(UNGGAH, 0755);
    }
    if (!is_writable(UNGGAH)) {
        return ['ok' => false, 'pesan' => 'Folder unggah tidak bisa ditulis. Hubungi Admin server.',
                'id' => 0, 'berkas' => ''];
    }

    //
    // Nama simpanan: <id butir>_<16 hex>.<ext>
    // random_bytes cukup: ditebak orang pun tidak memberi keuntungan, karena
    // berkasnya hanya dirujuk lewat id unggahan di DB.
    $simpan = $dokSpecId . '_' . bin2hex(random_bytes(8)) . '.' . $v['ext'];
    $tujuan = UNGGAH . '/' . $simpan;

    // Pertahanan terakhir: hasil akhir WAJIB di dalam UNGGAH.
    $nyata = realpath(UNGGAH);
    if ($nyata === false || !str_starts_with($tujuan, $nyata . DIRECTORY_SEPARATOR)) {
        return ['ok' => false, 'pesan' => 'Tujuan simpan berkas di luar folder unggah. Ditolak.',
                'id' => 0, 'berkas' => ''];
    }

    $pindah = $dariUnggahan ? move_uploaded_file($tmp, $tujuan) : rename($tmp, $tujuan);
    if (!$pindah) {
        return ['ok' => false, 'pesan' => 'Berkas lolos validasi tapi gagal dipindahkan ke folder unggah.',
                'id' => 0, 'berkas' => ''];
    }
    @chmod($tujuan, 0644);

    $berlaku = dok_tanggal_sah($berlakuSampai);
    if ($berlaku === false) {
        @unlink($tujuan);
        return ['ok' => false, 'pesan' => 'Tanggal berlaku "' . (string) $berlakuSampai
            . '" bukan tanggal yang sah (format YYYY-MM-DD).', 'id' => 0, 'berkas' => ''];
    }

    try {
        $id = sisip('dokumen_unggah', [
            'dok_spec_id'     => $dokSpecId,
            'prodi_id'        => $prodiId,
            'periode_id'      => $periodeId,
            'berkas'          => $simpan,
            'nama_asli'       => dok_nama_bersih($namaAsli),
            'ukuran'          => $ukuran,
            'mime'            => $v['mime'],
            'berlaku_sampai'  => $berlaku,
            'diunggah_oleh'   => $oleh,
            'catatan'         => $catatan !== '' ? mb_substr($catatan, 0, 255) : null,
        ]);
    } catch (Throwable $e) {
        @unlink($tujuan);
        return ['ok' => false, 'pesan' => 'Berkas tersimpan tapi baris database gagal dibuat: ' . $e->getMessage(),
                'id' => 0, 'berkas' => ''];
    }

    return ['ok' => true, 'pesan' => 'Berkas tersimpan.', 'id' => $id, 'berkas' => $simpan];
}

/** '' / null -> null; tanggal sah -> 'Y-m-d'; selain itu -> false. */
function dok_tanggal_sah(?string $iso): string|false|null
{
    $s = trim((string) $iso);
    if ($s === '') {
        return null;
    }
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $s, $m)
        || !checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
        return false;
    }
    return $s;
}

/**
 * Hapus berkas fisik, tapi hanya kalau jalurnya benar-benar di dalam UNGGAH.
 * Satu fungsi supaya modul dan uji tidak punya dua versi aturan ini.
 */
function dok_hapus_berkas(string $namaSimpan): bool
{
    $p = UNGGAH . '/' . dok_nama_bersih($namaSimpan);
    $nyata = realpath($p);
    if ($nyata === false || !is_file($nyata)) {
        return false;
    }
    $akar = (string) realpath(UNGGAH);
    if ($akar === '' || !str_starts_with($nyata, $akar . DIRECTORY_SEPARATOR)) {
        return false;
    }
    return @unlink($nyata);
}

/**
 * Semua unggahan prodi+periode, terbaru dulu, dikelompokkan per butir.
 * Versi lama TIDAK dihapus — itu riwayat revisi dokumen.
 */
function dok_unggahan_per_butir(int $prodiId, int $periodeId): array
{
    $baris = q(
        'SELECT u.id, u.dok_spec_id, u.berkas, u.nama_asli, u.ukuran, u.mime,
                u.berlaku_sampai, u.diunggah_pada, u.catatan,
                p.nama AS oleh_nama
         FROM dokumen_unggah u
         LEFT JOIN pengguna p ON p.id = u.diunggah_oleh
         WHERE u.prodi_id = ? AND u.periode_id = ?
         ORDER BY u.diunggah_pada DESC, u.id DESC',
        [$prodiId, $periodeId]);

    $out = [];
    foreach ($baris as $b) {
        $out[(int) $b['dok_spec_id']][] = $b;
    }
    return $out;
}

/** Status satu butir dari versi terpakai (yang terbaru). */
function dok_status_butir(?array $terpakai, ?string $hariIni = null): string
{
    if (!$terpakai) {
        return 'belum';
    }
    $hariIni ??= date('Y-m-d');
    $b = $terpakai['berlaku_sampai'] ?? null;
    if ($b !== null && $b !== '' && $b < $hariIni) {
        return 'kedaluwarsa';
    }
    return 'ada';
}

/** Sisa hari sampai berlaku_sampai. Negatif = sudah lewat. */
function dok_sisa_hari(string $berlaku, ?string $hariIni = null): int
{
    $hariIni ??= date('Y-m-d');
    $a = strtotime($berlaku . ' 00:00:00');
    $b = strtotime($hariIni . ' 00:00:00');
    return (int) round(($a - $b) / 86400);
}

/**
 * Butir yang versi terpakainya sudah lewat berlaku_sampai.
 * Hanya versi terpakai yang dihitung: versi lama yang kedaluwarsa tapi sudah
 * diganti tidak perlu ditengok lagi.
 */
function dok_kedaluwarsa(int $prodiId, int $periodeId, ?string $hariIni = null): array
{
    $hariIni ??= date('Y-m-d');
    $out = [];
    foreach (dok_unggahan_per_butir($prodiId, $periodeId) as $dokId => $versi) {
        $pakai = $versi[0] ?? null;
        if (dok_status_butir($pakai, $hariIni) !== 'kedaluwarsa') {
            continue;
        }
        $spec = q1('SELECT kriteria, nomor, nama FROM dokumen_spec WHERE id = ?', [$dokId]);
        $out[] = [
            'dok_spec_id'    => $dokId,
            'kriteria'       => $spec['kriteria'] ?? '',
            'nomor'          => (int) ($spec['nomor'] ?? 0),
            'nama'           => $spec['nama'] ?? '',
            'berlaku_sampai' => (string) $pakai['berlaku_sampai'],
            'sisa'           => dok_sisa_hari((string) $pakai['berlaku_sampai'], $hariIni),
            'nama_asli'      => (string) $pakai['nama_asli'],
        ];
    }
    usort($out, fn($a, $b) => [$a['kriteria'], $a['nomor']] <=> [$b['kriteria'], $b['nomor']]);
    return $out;
}

/**
 * 78 butir dikelompokkan per kriteria, lengkap dengan versi terpakai + riwayat.
 * Angka per kriteria diambil dari rekap_dokumen_per_kriteria() — bukan dihitung
 * ulang di sini, supaya badge dan daftar tidak pernah berbeda.
 */
function dok_daftar(int $prodiId, int $periodeId, ?string $hariIni = null): array
{
    $hariIni ??= date('Y-m-d');
    $unggahan = dok_unggahan_per_butir($prodiId, $periodeId);
    $angka = [];
    foreach (rekap_dokumen_per_kriteria($prodiId, $periodeId) as $k) {
        $angka[$k['kriteria']] = ['total' => (int) $k['total'], 'unggah' => (int) $k['unggah']];
    }

    $spec = q('SELECT id, kriteria, nomor, nama, rincian, wajib
               FROM dokumen_spec ORDER BY kriteria, nomor');

    $kelompok = [];
    foreach ($spec as $s) {
        $id = (int) $s['id'];
        $versi = $unggahan[$id] ?? [];
        $pakai = $versi[0] ?? null;
        $s['id'] = $id;
        $s['versi'] = $versi;
        $s['pakai'] = $pakai;
        $s['status'] = dok_status_butir($pakai, $hariIni);
        $s['sisa'] = ($pakai && $pakai['berlaku_sampai'])
            ? dok_sisa_hari((string) $pakai['berlaku_sampai'], $hariIni) : null;
        $kelompok[$s['kriteria']][] = $s;
    }

    $out = [];
    foreach (semua_kriteria() as $k) {
        $kode = $k['kode'];
        $butir = $kelompok[$kode] ?? [];
        $out[] = [
            'kode'    => $kode,
            'nama'    => $k['nama'],
            'total'   => $angka[$kode]['total'] ?? count($butir),
            'unggah'  => $angka[$kode]['unggah'] ?? 0,
            'butir'   => $butir,
        ];
    }
    return $out;
}
