<?php
declare(strict_types=1);

/**
 * Komponen tampilan. KELAS CSS-NYA HARUS SAMA dengan `mockup/assets/js/pagekit.js`.
 *
 * app.css disalin apa adanya dari mockup/, jadi markup yang berbeda = tampilan
 * yang berbeda. Setiap fungsi di sini adalah padanan satu fungsi JS:
 *
 *   head()      <-> pagekit.js  head()
 *   strip()     <-> pagekit.js  strip()
 *   badge()     <-> pagekit.js  badge()
 *   kartu()     <-> pagekit.js  card()
 *   cardHead()  <-> pagekit.js  cardHead()
 *   callout()   <-> pagekit.js  callout()
 *   dlist()     <-> pagekit.js  dlist()
 *   mbar()      <-> pagekit.js  mbar()
 *   tabel_html()<-> pagekit.js  table()
 *
 * Jangan menambah kelas CSS baru di sini. Kalau butuh gaya baru, cek dulu
 * apakah app.css sudah punya — 900 baris itu sudah punya banyak.
 */

/** Padanan BADGE_KIND di pagekit.js. */
const BADGE_KIND = [
    'lengkap' => 'ok', 'sebagian' => 'warn', 'kosong' => 'muted',
    'terkunci' => 'lock', 'na' => 'na', 'gagal' => 'bad',
    'blokir' => 'bad', 'peringatan' => 'warn',
    // 'hitung' = tabel yang seluruh field-nya dihitung sistem. Operator tidak
    // bisa mengisinya, jadi jangan dilabeli "kosong" — itu menyalahkan orang
    // yang memang tidak punya pekerjaan di situ.
    'hitung' => 'lock',
];

/** badge(kind, label) — terima nama status mentah ('lengkap') atau kelas ('ok'). */
function badge(string $kind, string $label): string
{
    $cls = BADGE_KIND[$kind] ?? $kind;
    return '<span class="badge ' . e($cls) . '">' . e($label) . '</span>';
}

/* badge_status() sudah ada di web/system/view.php — jangan dideklarasikan dua
   kali. PHP fatal "Cannot redeclare function". */

/**
 * strip angka besar: [[nilai, label, kelas?], ...] atau
 * [['label'=>, 'nilai'=>, 'dari'=>, 'satuan'=>, 'ket'=>, 'kelas'=>], ...]
 *
 * MARKUP MENGIKUTI app.css (`strip .val` + `strip .lab`), bukan app/. Dulu uji
 * menuntut `.strip-label`/`.strip-angka` — kelas itu tidak pernah ada di app.css
 * (satu-satunya sumber gaya), jadi markup-nya yang benar yang menentukan.
 *
 * `dari` ditulis di baris ANGKA ("/ 38"), bukan ditempel ke label: label harus
 * tetap pendek supaya bisa dicocokkan dengan angka rekap oleh uji, dan supaya
 * label tidak berubah tiap kali penyebutnya berubah.
 */
function strip(array $sel): string
{
    $out = '<div class="strip">';
    foreach ($sel as $s) {
        // Dua bentuk dipakai modul: [nilai, label, kelas] dan
        // ['label'=>, 'nilai'=>, 'satuan'=>]. Keduanya diterima.
        if (is_array($s) && isset($s['label'])) {
            $nilai = (string) ($s['nilai'] ?? '');
            $label = (string) $s['label'];
            $dari  = $s['dari'] ?? null;
            $satuan = (string) ($s['satuan'] ?? '');
            $ket   = (string) ($s['ket'] ?? '');
            $cls   = (string) ($s['kelas'] ?? '');
        } else {
            $nilai = (string) ($s[0] ?? '');
            $label = (string) ($s[1] ?? '');
            $dari  = null;
            $satuan = '';
            $ket   = '';
            $cls   = (string) ($s[2] ?? '');
        }

        $angka = '<b class="num">' . e($nilai) . '</b>';
        if ($dari !== null && $dari !== '' && (float) $dari > 0) {
            $angka .= '<span class="strip-dari">/ ' . e($dari) . '</span> ';
        }
        if ($satuan !== '') {
            $angka .= '<span class="strip-satuan">' . e($satuan) . '</span>';
        }

        $out .= '<div class="cell"><div class="val ' . e($cls) . '">' . $angka . '</div>'
            . '<div class="lab">' . e($label) . '</div>';
        // Bar mengkodekan PORSI yang sama dengan angka di sebelahnya (3/12 =
        // 25%). Tanpa penyebut tidak ada porsi, jadi tidak ada bar — bar penuh
        // untuk "38 tabel wajib" akan berbohong soal kemajuan.
        if ($dari !== null && $dari !== '' && (float) $dari > 0) {
            $out .= mbar((float) $nilai / (float) $dari * 100, $cls !== '' ? $cls : 'ok');
            $out .= '<div class="foot-note">' . e(number_format((float) $nilai / (float) $dari * 100, 0, ',', '.')) . '%</div>';
        }
        if ($ket !== '') {
            $out .= '<div class="foot-note">' . e($ket) . '</div>';
        }
        $out .= '</div>';
    }
    return $out . '</div>';
}

/**
 * Kartu putih. Dua bentuk pemanggilan, keduanya dipakai modul:
 *
 *   kartu($isi)                         -> kartu tanpa judul
 *   kartu($judul, $isi, ['catatan'=>, 'kelas'=>, 'id'=>])
 *
 * $isi sudah HTML (tidak di-escape); $judul dan $catatan di-escape.
 * Kelas tambahan hanya ditulis kalau ada — `class="card "` dengan spasi
 * menggantung membuat pencocokan markup (dan uji) gagal tanpa sebab.
 */
function kartu(string $judulAtauIsi, ?string $isi = null, array $opsi = []): string
{
    if ($isi === null) {
        // Bentuk satu argumen: isinya HTML siap pakai, bukan nama kelas.
        // Dulu isi ini ditempel ke atribut class dan tag-nya tidak ditutup.
        return '<div class="card">' . $judulAtauIsi . '</div>';
    }
    $kelas = trim((string) ($opsi['kelas'] ?? ''));
    $id = $opsi['id'] ?? '';
    $catatan = $opsi['catatan'] ?? '';
    return '<div class="card' . ($kelas !== '' ? ' ' . e($kelas) : '') . '"'
        . ($id !== '' ? ' id="' . e((string) $id) . '"' : '') . '>'
        . cardHead($judulAtauIsi, (string) $catatan)
        . $isi
        . '</div>';
}

/**
 * kepala kartu: judul + catatan kanan + tombol kanan.
 *
 * $catatan DI-ESCAPE: isinya data (nama prodi, jumlah baris), bukan markup.
 * $kanan tidak di-escape — pemanggil yang menaruh tombol/tautan di situ.
 */
function cardHead(string $judul, string $catatan = '', string $kanan = ''): string
{
    $out = '<div class="card-head"><h2>' . e($judul) . '</h2>';
    if ($catatan !== '') {
        $out .= '<span class="note">' . e($catatan) . '</span>';
    }
    if ($kanan !== '') {
        $out .= '<span style="margin-left:auto">' . $kanan . '</span>';
    }
    return $out . '</div>';
}

/**
 * Ikon per jenis banner. Aturan desain anti-slop: status SELALU kata + ikon,
 * bukan warna saja — asesor mencetak hitam-putih, dan hijau/merah jadi abu-abu.
 * Path-nya sama dengan ikon di web/assets/ikon.php (16x16, stroke currentColor).
 */
const CALLOUT_IKON = [
    'bad'  => ['Galat:', 'M8 1.5L15 14H1ZM8 6V9.5M8 11V12'],
    'warn' => ['Perhatian:', 'M8 1.5L15 14H1ZM8 6V9.5M8 11V12'],
    'lock' => ['Terkunci:', 'M4 7V4.5A4 4 0 0 1 12 4.5V7M2.5 7H13.5V14H2.5Z'],
    'info' => ['Info:', 'M8 15A7 7 0 1 0 8 1A7 7 0 1 0 8 15ZM8 7V11.5M8 4.5V5'],
];

/** banner: $jenis = bad | warn | lock | info. */
function callout(string $jenis, string $judul, string $isi, string $aksi = ''): string
{
    [$kata, $path] = CALLOUT_IKON[$jenis] ?? ['', 'M8 15A7 7 0 1 0 8 1A7 7 0 1 0 8 15ZM8 7V11.5M8 4.5V5'];
    $ikon = '<svg viewBox="0 0 16 16" width="16" height="16" fill="none" stroke="currentColor"'
        . ' stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"'
        . ' focusable="false"><path d="' . $path . '"/></svg>';
    return '<div class="callout ' . e($jenis) . '">' . $ikon . '<div class="c-body">'
        . '<h3>' . ($kata !== '' ? '<span class="callout-kata">' . e($kata) . '</span> ' : '')
        . e($judul) . '</h3>' . $isi
        . '</div>' . $aksi . '</div>';
}

/** daftar titik: [[judul, keterangan], ...] */
function dlist(array $item): string
{
    $out = '<div class="dlist">';
    foreach ($item as $i) {
        $out .= '<div class="item"><span class="dot"></span><div><h4>' . e($i[0]) . '</h4>'
            . '<p>' . ($i[1] ?? '') . '</p></div></div>';
    }
    return $out . '</div>';
}

/** bar tipis: $pct 0..100. */
function mbar(float $pct, string $cls = 'ok', int $h = 5): string
{
    $p = max(0, min(100, $pct));
    return '<div class="mbar" style="height:' . $h . 'px"><i class="' . e($cls)
        . '" style="width:' . number_format($p, 1, '.', '') . '%"></i></div>';
}

/**
 * tabel standar.
 *
 * $kolom = ['Nama'] | ['Nama', 'r'] | ['kunci'=>, 'label'=>, 'align'=>'r'|'kelas'=>, 'html'=>true]
 * $baris = [['sel', 'sel'], ...] atau [['kunci'=>nilai, ...], ...]
 *          atau [['cells'=>[...], 'cls'=>'is-bad'], ...]
 *
 * SEL DI-ESCAPE SECARA BAWAAN. Ini perubahan yang disengaja: sebelumnya semua
 * sel dianggap HTML, jadi satu nama mahasiswa berisi `<script>` langsung jalan
 * di halaman. Mempercayai sel berdasarkan isinya ("ada tag berarti HTML") bukan
 * pilihan — itu tebak-tebakan yang bisa dibalik penyerang. Yang menentukan
 * hanya tanda eksplisit:
 *
 *   kolom ['html' => true]        -> seluruh kolom berisi HTML milik pemanggil
 *   sel   ['html' => '<b>x</b>']  -> satu sel HTML milik pemanggil
 *
 * `null`/`''` ditulis sebagai tanda pisah, bukan sel kosong: sel kosong di
 * antara angka terbaca seperti angka yang hilang, bukan data yang tidak ada.
 */
function tabel_html(array $kolom, array $baris, array $opsi = []): string
{
    $foot = $opsi['foot'] ?? null;
    $cls = trim((string) ($opsi['cls'] ?? ''));
    $kosong = (string) ($opsi['kosong'] ?? '');

    $norm = static function ($k): array {
        if (is_array($k)) {
            if (isset($k['label'])) {
                return [
                    (string) $k['label'],
                    (string) ($k['align'] ?? $k['kelas'] ?? ''),
                    (bool) ($k['html'] ?? false),
                ];
            }
            return [(string) ($k[0] ?? ''), (string) ($k[1] ?? ''), (bool) ($k[2] ?? false)];
        }
        return [(string) $k, '', false];
    };
    $kolomAsli = $kolom;
    $kolom = array_map($norm, $kolom);

    $out = ($opsi['tanpa_bungkus'] ?? false) ? '' : '<div class="tbl-wrap">';
    $out .= '<table class="tbl' . ($cls !== '' ? ' ' . e($cls) : '') . '"><thead><tr>';
    foreach ($kolom as $k) {
        $out .= '<th class="' . ($k[1] === 'r' ? 'r' : '') . '">' . e($k[0]) . '</th>';
    }
    $out .= '</tr></thead><tbody>';

    if ($baris === [] && $kosong !== '') {
        $out .= '<tr><td class="muted" colspan="' . count($kolom) . '">' . e($kosong) . '</td></tr>';
    }

    $kunciKolom = array_map(static fn($k) => is_array($k) ? ($k['kunci'] ?? null) : null, $kolomAsli);
    $adaKunci = $kunciKolom !== [] && array_filter($kunciKolom, static fn($k) => $k !== null) !== [];

    foreach ($baris as $r) {
        $trCls = is_array($r) && isset($r['cls']) && !isset($r['html']) && !isset($r['isi'])
            ? ' class="' . e($r['cls']) . '"' : '';

        // Satu baris bisa datang sebagai:
        //   ['a', 'b']                 daftar sel posisional
        //   ['kunci' => nilai, ...]    dipetakan lewat 'kunci' kolom
        //   ['html' => '<b>x</b>']     SATU sel HTML — jangan diperlakukan
        //   ['isi' => 'x', 'cls' => …] sebagai baris asosiatif; dulu bentuk ini
        //                              dianggap peta kunci dan isinya hilang.
        if (is_array($r) && isset($r['cells'])) {
            $sel = $r['cells'];
        } elseif (is_array($r) && (array_key_exists('html', $r) || array_key_exists('isi', $r))) {
            $sel = [$r];
        } elseif (is_array($r) && !array_is_list($r) && $adaKunci) {
            $urut = [];
            foreach ($kunciKolom as $kk) {
                $urut[] = $kk !== null ? ($r[$kk] ?? null) : null;
            }
            $sel = $urut;
        } else {
            $sel = $r;
        }

        $out .= '<tr' . $trCls . '>';
        foreach ($sel as $i => $c) {
            $kanan = ($kolom[$i][1] ?? '') === 'r' ? 'r' : '';
            $htmlKolom = $kolom[$i][2] ?? false;

            // Sel boleh berupa array ['isi'=>, 'cls'=>, 'html'=>] / ['html'=>] —
            // dipakai modul dashboard untuk bar + angka dalam satu sel, dan
            // modul master untuk tautan tombol.
            if (is_array($c)) {
                if (array_key_exists('html', $c)) {
                    $isi = (string) $c['html'];
                    $htmlSel = true;
                } else {
                    $isi = $c['isi'] ?? $c[0] ?? null;
                    $htmlSel = false;
                }
                $clsSel = ' ' . (string) ($c['cls'] ?? $c['kelas'] ?? $c[1] ?? '');
            } else {
                $isi = $c;
                $htmlSel = false;
                $clsSel = '';
            }

            if ($isi === null || $isi === '') {
                $td = '<span class="muted">—</span>';
            } elseif ($htmlSel || $htmlKolom) {
                $td = $isi;
            } else {
                $td = e($isi);
            }
            // Atribut class hanya ditulis kalau ada isinya: `<td class="">` bikin
            // markup sulit dibaca dan sulit dicocokkan tanpa alasan.
            $attr = trim($kanan . $clsSel);
            $out .= '<td' . ($attr !== '' ? ' class="' . $attr . '"' : '') . '>' . $td . '</td>';
        }
        $out .= '</tr>';
    }
    $out .= '</tbody>';
    if ($foot !== null) {
        $out .= '<tfoot><tr>';
        foreach ($foot as $i => $c) {
            $kanan = ($kolom[$i][1] ?? '') === 'r' ? 'r' : '';
            $out .= '<td class="' . $kanan . '">' . $c . '</td>';
        }
        $out .= '</tr></tfoot>';
    }
    $out .= '</table>';
    return ($opsi['tanpa_bungkus'] ?? false) ? $out : $out . '</div>';
}

/**
 * Potongan halaman galat — BUKAN dokumen utuh.
 * Modul memanggil ini di dalam render(), jadi mengembalikan <html> akan
 * menyarangkan dokumen di dalam dokumen.
 *
 * ┌─ KONTRAK KEAMANAN ────────────────────────────────────────────────┐
 * │ $pesan DIPERLAKUKAN SEBAGAI HTML, bukan teks. Itu disengaja:       │
 * │ pemanggil menaruh markup di dalamnya ('<b>Periode Akreditasi</b>', │
 * │ '<a href=...>Atur periode</a>').                                  │
 * │                                                                   │
 * │ KONSEKUENSINYA: SETIAP data dari DB/URL yang disisipkan ke $pesan  │
 * │ WAJIB lewat e() di sisi pemanggil. Contoh yang sudah benar:        │
 * │   web/modul/tabel/index.php:116                                   │
 * │     'Kode tabel <b>' . e($tabelKode) . '</b> tidak ada…'          │
 * │ Jangan menyisipkan nilai mentah ke sini — fungsi ini tidak bisa    │
 * │ membedakan markup pemanggil dari data penyerang.                  │
 * └───────────────────────────────────────────────────────────────────┘
 *
 * Kode status ditulis besar dan terpisah supaya pembaca tahu ini 409/404,
 * bukan kegagalan tampilan biasa.
 */
function halaman_galat(string|int $kode, string $pesan, string $judul = ''): string
{
    $judul = $judul !== '' ? $judul : 'Halaman tidak bisa dibuka';
    return '<div class="card galat-halaman"><div class="card-head"><h2>' . e($judul) . '</h2>'
        . '<span class="note">galat</span></div>'
        . callout('bad', 'Kode ' . (string) $kode,
            '<p class="galat-kode"><b class="num">' . e((string) $kode) . '</b> '
            . '<span class="muted">' . e($judul) . '</span></p><p>' . $pesan . '</p>')
        . '</div>';
}

/** Nama lama dari agent 1 — diteruskan ke strip() yang sesuai desain asli. */
function strip_angka(array $sel): string
{
    return strip($sel);
}

/**
 * Dulu mengembalikan blok <style> untuk CSS kerangka buatan agent.
 * Sekarang CSS-nya `assets/css/app.css` milik desain asli, dimuat oleh layout.
 * Fungsi ini tetap ada supaya pemanggil lama tidak perlu diubah.
 */
function gaya_kerangka(): string
{
    return '';
}

/**
 * Bar bagian + label. Padanan `mbar()` di pagekit.js.
 * Bar mengkodekan PORSI ($bagian/$total), jadi angka di sebelahnya harus
 * porsi itu juga — bukan besaran lain. Aturan anti-slop no. 2.
 */
function bar_bagian(float $bagian, float $total, string $cls = 'ok'): string
{
    if ($total <= 0) {
        return '<span class="muted">—</span>';
    }
    $pct = $bagian / $total * 100;
    return mbar($pct, $cls) . '<div class="foot-note">'
        . e(number_format($bagian, 0, ',', '.')) . ' dari '
        . e(number_format($total, 0, ',', '.')) . ' · '
        . e(number_format($pct, 0, ',', '.')) . '%</div>';
}

/**
 * Rekap per kriteria dengan kunci yang SUDAH dinormalkan ('K1'..'K8').
 * Sejak bug `'2'` vs `'K2'` diperbaiki di skema, ini cukup meneruskan
 * rekap_per_kriteria() — tapi dipakai banyak modul, jadi tetap disediakan
 * sebagai satu nama supaya kalau bentuknya berubah, satu tempat saja.
 */
function rekap_kriteria(int $prodiId, int $periodeId): array
{
    return rekap_per_kriteria($prodiId, $periodeId);
}

/** Keadaan kosong yang jujur: sebut apa yang belum ada, jangan kotak berisi 0. */
function kosong(string $judul, array $butuh): string
{
    $daftar = '';
    foreach ($butuh as $b) {
        $daftar .= '<li>' . e($b) . '</li>';
    }
    return kartu(
        cardHead($judul, 'belum ada data')
        . '<p class="foot-note">Data yang dibutuhkan:</p>'
        . '<ul class="foot-note" style="padding-left:18px;margin-top:6px">' . $daftar . '</ul>'
    );
}
