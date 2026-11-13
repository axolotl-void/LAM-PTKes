<?php
declare(strict_types=1);

/**
 * Komponen tampilan bersama. Dipakai SEMUA modul — jangan bikin versi sendiri.
 *
 * Aturan yang dijaga di sini, bukan di tiap modul:
 *  - setiap nilai dari database lewat e(); satu tempat lupa = XSS di 38 tabel
 *  - bar selalu mengkodekan besaran yang sama dengan angka di sebelahnya
 *  - angka selalu berlabel ("17" harus jadi "17 tabel")
 *
 * `komponen.php` tidak menulis apa pun; hanya membentuk string HTML.
 */

require_once dirname(__DIR__, 2) . '/system/view.php';
require_once dirname(__DIR__, 2) . '/system/rekap.php';

/**
 * Berkas CSS modul kerangka. layout.php dibekukan dan hanya memuat app.css,
 * jadi modul memanggil ini di awal $isi. Satu tempat, bukan tiga.
 */
function gaya_kerangka(): string
{
    return '<link rel="stylesheet" href="' . e(BASE_URL) . 'aset/css/kerangka.css?v=1">';
}

/**
 * Kartu: kotak putih berjudul, wadah standar semua blok isi.
 *
 * $opsi
 *   catatan : teks kecil di kanan judul (sudah di-escape di sini)
 *   aksi    : HTML tombol — pemanggil WAJIB sudah meng-escape isinya
 *   kelas   : kelas tambahan pada <section>
 *   id      : atribut id
 */
function kartu(string $judul, string $isi, array $opsi = []): string
{
    $kelas = trim('card ' . (string) ($opsi['kelas'] ?? ''));
    $id = isset($opsi['id']) ? ' id="' . e((string) $opsi['id']) . '"' : '';

    $kepala = '';
    if ($judul !== '' || !empty($opsi['catatan']) || !empty($opsi['aksi'])) {
        $kepala = '<div class="card-head"><h2>' . e($judul) . '</h2>';
        if (!empty($opsi['catatan'])) {
            $kepala .= '<span class="note">' . e((string) $opsi['catatan']) . '</span>';
        }
        if (!empty($opsi['aksi'])) {
            $kepala .= '<span class="card-aksi">' . (string) $opsi['aksi'] . '</span>';
        }
        $kepala .= '</div>';
    }

    return '<section class="' . e($kelas) . '"' . $id . '>' . $kepala . $isi . '</section>';
}

/**
 * Tabel HTML.
 *
 * $kolom: daftar kolom. Tiap butir string (kunci = label) atau array:
 *   kunci  => nama kolom di $baris (wajib)
 *   label  => judul kolom (bawaan: kunci)
 *   kelas  => 'r' untuk angka rata kanan
 *   html   => true kalau nilai sudah HTML jadi hasil helper lain
 *             (mis. badge_status()). Nilai mentah dari DB JANGAN pakai ini.
 *   lebar  => lebar kolom, mis. '120px'
 *
 * $baris: daftar array asosiatif. Nilai null/'' jadi tanda pisah.
 * $opsi: 'kosong' (teks saat tanpa baris), 'kelas', 'tanpa_bungkus' (tanpa .tbl-wrap)
 */
function tabel_html(array $kolom, array $baris, array $opsi = []): string
{
    $norm = [];
    foreach ($kolom as $k => $kol) {
        if (is_string($kol)) {
            $kol = ['kunci' => $kol, 'label' => $kol];
        }
        $norm[] = [
            'kunci' => (string) ($kol['kunci'] ?? $k),
            'label' => (string) ($kol['label'] ?? $kol['kunci'] ?? $k),
            'kelas' => (string) ($kol['kelas'] ?? ''),
            'html'  => (bool) ($kol['html'] ?? false),
            'lebar' => isset($kol['lebar']) ? (string) $kol['lebar'] : '',
        ];
    }

    $th = '';
    foreach ($norm as $k) {
        $th .= '<th' . ($k['kelas'] !== '' ? ' class="' . e($k['kelas']) . '"' : '')
             . ($k['lebar'] !== '' ? ' style="width:' . e($k['lebar']) . '"' : '') . '>'
             . e($k['label']) . '</th>';
    }

    $tb = '';
    foreach ($baris as $b) {
        $tr = '';
        foreach ($norm as $k) {
            $v = $b[$k['kunci']] ?? null;
            $kelas = $k['kelas'];
            if (is_array($v)) {
                $isi = (string) ($v['html'] ?? e($v['isi'] ?? ''));
                $kelas = trim($kelas . ' ' . (string) ($v['kelas'] ?? ''));
            } elseif ($v === null || $v === '') {
                $isi = '<span class="muted">—</span>';
            } else {
                $isi = $k['html'] ? (string) $v : e($v);
            }
            $tr .= '<td' . ($kelas !== '' ? ' class="' . e($kelas) . '"' : '') . '>' . $isi . '</td>';
        }
        $tb .= '<tr>' . $tr . '</tr>';
    }

    if ($tb === '') {
        $tb = '<tr><td class="kosong" colspan="' . count($norm) . '">'
            . e((string) ($opsi['kosong'] ?? 'Belum ada data.')) . '</td></tr>';
    }

    $tabel = '<table class="tbl' . (isset($opsi['kelas']) ? ' ' . e((string) $opsi['kelas']) : '') . '">'
           . '<thead><tr>' . $th . '</tr></thead><tbody>' . $tb . '</tbody></table>';

    return !empty($opsi['tanpa_bungkus']) ? $tabel : '<div class="tbl-wrap">' . $tabel . '</div>';
}

/** Bar 0–100%. Satu-satunya tempat lebar bar dihitung. */
function bar_bagian(float $bagian, float $dari, string $kelas = ''): string
{
    if ($dari <= 0) {
        return '';
    }
    $persen = max(0.0, min(100.0, $bagian / $dari * 100));
    return '<span class="bar' . ($kelas !== '' ? ' ' . e($kelas) : '') . '">'
         . '<i style="width:' . e(number_format($persen, 1, '.', '')) . '%"></i></span>';
}

/**
 * Strip angka: deretan kotak angka untuk ringkasan atas halaman.
 *
 * $item: daftar butir, tiap butir
 *   label  => teks label, wajib. Angka tanpa label ditebak salah.
 *   nilai  => int
 *   dari   => int|null; kalau ada, bar menggambarkan nilai/dari — besaran yang
 *             SAMA dengan angka yang tertulis, bukan persen lain.
 *   ket    => keterangan kecil di bawah angka
 *   satuan => kata setelah angka ('tabel', 'dokumen')
 */
function strip_angka(array $item): string
{
    $out = '';
    foreach ($item as $it) {
        $nilai = (int) ($it['nilai'] ?? 0);
        $dari = isset($it['dari']) ? (int) $it['dari'] : null;
        $angka = '<b class="num">' . e(number_format($nilai, 0, ',', '.')) . '</b>';
        if ($dari !== null) {
            $angka .= '<span class="strip-dari">/ ' . e(number_format($dari, 0, ',', '.')) . '</span>';
        }
        if (!empty($it['satuan'])) {
            $angka .= ' <span class="strip-satuan">' . e((string) $it['satuan']) . '</span>';
        }
        $out .= '<div class="strip-item">'
              . '<div class="strip-label">' . e((string) ($it['label'] ?? '')) . '</div>'
              . '<div class="strip-angka">' . $angka . '</div>'
              . ($dari !== null ? bar_bagian($nilai, $dari) : '')
              . (!empty($it['ket']) ? '<div class="strip-ket">' . e((string) $it['ket']) . '</div>' : '')
              . '</div>';
    }
    return '<div class="strip">' . $out . '</div>';
}

/**
 * Callout: satu pesan yang harus dibaca. Jenis membawa kata + ikon, bukan
 * warna saja — halaman ini dicetak hitam-putih oleh asesor.
 * jenis: info | warn | bad | ok
 */
function callout(string $jenis, string $judul, string $isi): string
{
    $kata = ['info' => 'Catatan', 'warn' => 'Perhatian', 'bad' => 'Masalah', 'ok' => 'Selesai'];
    $ikon = [
        'info' => 'M12 8h.01M11 12h1v4h1',
        'warn' => 'M12 9v4M12 17h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z',
        'bad'  => 'M12 8v5M12 17h.01M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20Z',
        'ok'   => 'M20 6 9 17l-5-5',
    ];
    $jenis = isset($kata[$jenis]) ? $jenis : 'info';

    return '<div class="callout ' . e($jenis) . '" role="note">'
         . '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"'
         . ' stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
         . '<path d="' . $ikon[$jenis] . '"/></svg>'
         . '<div><h3>' . e(($kata[$jenis] . ': ' . $judul)) . '</h3><p>' . $isi . '</p></div>'
         . '</div>';
}

/**
 * Halaman galat: pengganti isi halaman saat modul tidak bisa menampilkan
 * datanya (prodi belum dipilih, periode belum ada, kode tabel tidak dikenal).
 *
 * Mengembalikan POTONGAN isi, bukan dokumen utuh — dipanggil sebagai
 * `render('...', halaman_galat(409, '...'))` supaya sidebar dan topbar tetap
 * ada. $pesan boleh HTML dari pemanggil asal sudah di-escape.
 */
function halaman_galat(int $kode, string $pesan, string $tautan = ''): string
{
    $tautan = $tautan !== '' ? $tautan : BASE_URL . 'index.php?m=dashboard';
    return '<div class="galat-halaman">'
         . '<div class="galat-kode num">' . e($kode) . '</div>'
         . '<h2>Halaman tidak bisa ditampilkan</h2>'
         . '<p>' . $pesan . '</p>'
         . '<a class="btn btn-primary" href="' . e($tautan) . '">Kembali ke Dashboard</a>'
         . '</div>';
}

/**
 * 8 kriteria + hitungan tabel, dari rekap.php.
 *
 * ponytail: jaring pengaman, bukan fitur. Di seed ini `tabel_dukung.kriteria`
 * berisi '2'..'8' sedangkan `kriteria.kode` berisi 'K1'..'K8', jadi
 * rekap_per_kriteria() menyusun kunci 'K2' lalu mencarinya di kunci '2' —
 * hasilnya semua kriteria 0 dan PHP mengeluarkan warning per tabel. Kalau
 * totalnya tidak menjumlah, hitung ulang dari rekap_semua_tabel() (baris dan
 * status yang sama, cuma dikelompokkan ulang). Hapus cabang ini begitu
 * rekap.php menormalkan kunci kriteria.
 */
function rekap_kriteria(int $prodiId, int $periodeId): array
{
    $semua = rekap_semua_tabel($prodiId, $periodeId);
    $per = @rekap_per_kriteria($prodiId, $periodeId);
    // Sehat = tiap butir punya kunci 'kriteria' dan totalnya menjumlah tepat.
    // Bug kunci tadi menghasilkan butir cacat TANPA 'kriteria' yang totalnya
    // kebetulan tetap 38, jadi jumlah saja tidak cukup untuk memeriksa.
    $sehat = $per !== [] && array_sum(array_column($per, 'total')) === count($semua);
    foreach ($per as $k) {
        if (!isset($k['kriteria'], $k['nama'], $k['lengkap'], $k['wajib'])) {
            $sehat = false;
        }
    }
    if ($sehat) {
        return $per;
    }

    $out = [];
    foreach (semua_kriteria() as $k) {
        $out[$k['kode']] = ['kriteria' => $k['kode'], 'nama' => $k['nama'],
                            'total' => 0, 'wajib' => 0, 'lengkap' => 0,
                            'sebagian' => 0, 'kosong' => 0];
    }
    foreach ($semua as $t) {
        $kode = (string) $t['kriteria'];
        $kode = str_starts_with($kode, 'K') ? $kode : 'K' . $kode;
        if (!isset($out[$kode])) {
            continue;
        }
        $out[$kode]['total']++;
        if ($t['status'] === 'na') {
            continue;
        }
        $out[$kode]['wajib']++;
        if (in_array($t['status'], ['lengkap', 'terkunci'], true)) {
            $out[$kode]['lengkap']++;
        } else {
            $out[$kode][$t['status']]++;
        }
    }
    ksort($out);
    return array_values($out);
}
