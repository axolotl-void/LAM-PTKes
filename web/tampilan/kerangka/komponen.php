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
 * strip angka besar: [[nilai, label, kelas?], ...]
 * kelas: '' | ok | warn | bad | muted — sama dengan strip() di pagekit.js.
 */
function strip(array $sel): string
{
    $out = '<div class="strip">';
    foreach ($sel as $s) {
        $nilai = $s[0] ?? '';
        $label = $s[1] ?? '';
        $cls = $s[2] ?? '';
        $out .= '<div class="cell"><div class="val ' . e((string) $cls) . '">' . $nilai
            . '</div><div class="lab">' . e((string) $label) . '</div></div>';
    }
    return $out . '</div>';
}

/** kartu putih. $inner sudah HTML (tidak di-escape). */
function kartu(string $inner, string $cls = ''): string
{
    return '<div class="card ' . e($cls) . '">' . $inner . '</div>';
}

/** kepala kartu: judul + catatan kanan + tombol kanan. */
function cardHead(string $judul, string $catatan = '', string $kanan = ''): string
{
    $out = '<div class="card-head"><h2>' . e($judul) . '</h2>';
    if ($catatan !== '') {
        $out .= '<span class="note">' . $catatan . '</span>';
    }
    if ($kanan !== '') {
        $out .= '<span style="margin-left:auto">' . $kanan . '</span>';
    }
    return $out . '</div>';
}

/** banner: $jenis = bad | warn | lock | info. */
function callout(string $jenis, string $judul, string $isi, string $aksi = ''): string
{
    return '<div class="callout ' . e($jenis) . '"><div class="c-body">'
        . '<h3>' . e($judul) . '</h3>' . $isi
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
 * tabel standar. $kolom = [[label, align 'r'|''], ...]
 * $baris = [[sel, sel, ...], ...] atau [['cells'=>[...], 'cls'=>'is-bad'], ...]
 * Sel sudah HTML — jangan escape di sini, pemanggil yang tahu isinya.
 */
function tabel_html(array $kolom, array $baris, array $opsi = []): string
{
    $foot = $opsi['foot'] ?? null;
    $cls = $opsi['cls'] ?? '';
    // Dua bentuk kolom dipakai modul: ['Nama', 'r'] atau string 'Nama'.
    // Keduanya diterima supaya pemanggil tidak perlu diubah.
    $norm = static function ($k): array {
        if (is_array($k)) {
            // ['label'=>.., 'kunci'=>..] dipakai modul periode; ['Nama','r'] dipakai lainnya.
            if (isset($k['label'])) {
                return [(string) $k['label'], (string) ($k['align'] ?? '')];
            }
            return [(string) ($k[0] ?? ''), (string) ($k[1] ?? '')];
        }
        return [(string) $k, ''];
    };
    $kolom = array_map($norm, $kolom);

    $out = '<div class="tbl-wrap"><table class="tbl ' . e($cls) . '"><thead><tr>';
    foreach ($kolom as $k) {
        $out .= '<th class="' . ($k[1] === 'r' ? 'r' : '') . '">' . e($k[0]) . '</th>';
    }
    $out .= '</tr></thead><tbody>';
    foreach ($baris as $r) {
        $sel = is_array($r) && isset($r['cells']) ? $r['cells'] : $r;
        $trCls = is_array($r) && isset($r['cls']) ? ' class="' . e($r['cls']) . '"' : '';
        $out .= '<tr' . $trCls . '>';
        foreach ($sel as $i => $c) {
            $kanan = ($kolom[$i][1] ?? '') === 'r' ? 'r' : '';
            // Sel boleh berupa array ['isi'=>..., 'cls'=>...] — dipakai modul
            // dashboard untuk bar + angka dalam satu sel.
            if (is_array($c)) {
                $isi = $c['isi'] ?? $c[0] ?? '';
                $clsSel = ' ' . (string) ($c['cls'] ?? $c[1] ?? '');
            } else {
                $isi = $c;
                $clsSel = '';
            }
            $out .= '<td class="' . $kanan . $clsSel . '">' . $isi . '</td>';
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
    return $out . '</table></div>';
}

/**
 * Potongan halaman galat — BUKAN dokumen utuh.
 * Modul memanggil ini di dalam render(), jadi mengembalikan <html> akan
 * menyarangkan dokumen di dalam dokumen.
 */
function halaman_galat(string|int $kode, string $pesan): string
{
    $judul = is_int($kode) ? 'Tidak bisa dibuka (' . $kode . ')' : $kode;
    return callout('bad', $judul, '<p>' . e($pesan) . '</p>');
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
