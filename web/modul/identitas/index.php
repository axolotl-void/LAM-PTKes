<?php
declare(strict_types=1);

/**
 * Modul: Identitas UPPS & Prodi (F-01).
 *
 * Halaman ini menentukan seluruh sistem: `jenjang` dan `punya_tahap_profesi`
 * menyaring 38 tabel jadi 32 atau tetap 38. Karena itu konsekuensinya
 * ditampilkan di halaman, dihitung dari `tabel_dukung` — bukan diketik.
 */

require_once __DIR__ . '/../../system/layout.php';
require_once __DIR__ . '/../../tampilan/kerangka/komponen.php';
require_once __DIR__ . '/bantu.php';

$aksi = $_GET['a'] ?? 'index';
$P = prodi_terpilih();

if (!$P) {
    render('Identitas UPPS & Prodi', halaman_galat(409, 'Belum ada program studi aktif.'), ['aktif' => 'identitas']);
    return;
}
$prodiId = (int) $P['id'];

/* ── simpan ─────────────────────────────────────────────────────── */
if ($aksi === 'simpan') {
    wajib('ISI');
    csrf_periksa();

    $galat = [];
    $nilaiU = [];
    $nilaiP = [];

    foreach (spec_upps() as $f) {
        $v = masuk_input($f['kunci']);
        if ($f['wajib'] && $v === '') {
            $galat[] = $f['label'] . ' wajib diisi.';
        } elseif (mb_strlen($v) > $f['maks']) {
            $galat[] = sprintf('%s maksimal %d karakter.', $f['label'], $f['maks']);
        }
        $nilaiU[$f['kunci']] = $v === '' ? null : $v;
    }
    if ($nilaiU['email'] !== null && !filter_var($nilaiU['email'], FILTER_VALIDATE_EMAIL)) {
        $galat[] = 'Email tidak sah.';
    }

    foreach (spec_prodi() as $f) {
        $v = masuk_input($f['kunci']);
        if ($f['wajib'] && $v === '') {
            $galat[] = $f['label'] . ' wajib diisi.';
        } elseif ($v !== '' && mb_strlen($v) > $f['maks']) {
            $galat[] = sprintf('%s maksimal %d karakter.', $f['label'], $f['maks']);
        }
        $nilaiP[$f['kunci']] = $v === '' ? null : $v;
    }

    if ($nilaiP['jenjang'] !== null && !in_array($nilaiP['jenjang'], opsi_jenjang(), true)) {
        $galat[] = 'Jenjang tidak dikenal.';
    }
    // Jenjang Profesi/Spesialis mustahil tanpa tahap profesi. Memaksa 1 di sini
    // mencegah kombinasi yang membuat Tabel 30 wajib tapi tidak bisa diisi.
    $nilaiP['punya_tahap_profesi'] = in_array((string) $nilaiP['jenjang'], ['Profesi', 'Spesialis'], true)
        ? 1 : ((int) masuk_input('punya_tahap_profesi') === 1 ? 1 : 0);

    if ($nilaiP['lama_studi_semester'] !== null
        && (!ctype_digit((string) $nilaiP['lama_studi_semester'])
            || (int) $nilaiP['lama_studi_semester'] < 1 || (int) $nilaiP['lama_studi_semester'] > 14)) {
        $galat[] = 'Lama studi harus 1–14 semester.';
    }
    if ($nilaiP['tgl_akreditasi'] !== null && strtotime((string) $nilaiP['tgl_akreditasi']) === false) {
        $galat[] = 'Tanggal akreditasi tidak sah.';
    }
    if ($nilaiP['tgl_berakhir_akreditasi'] !== null
        && strtotime((string) $nilaiP['tgl_berakhir_akreditasi']) === false) {
        $galat[] = 'Tanggal berakhir tidak sah.';
    }
    if ($nilaiP['tgl_akreditasi'] && $nilaiP['tgl_berakhir_akreditasi']
        && $nilaiP['tgl_berakhir_akreditasi'] < $nilaiP['tgl_akreditasi']) {
        $galat[] = 'Tanggal berakhir tidak boleh sebelum tanggal akreditasi.';
    }
    if (!$nilaiP['punya_tahap_profesi'] && $nilaiP['nama_profesi_lulusan'] !== null) {
        $galat[] = 'Nama profesi lulusan hanya untuk prodi bertahap profesi.';
    }

    if ($galat) {
        // Tampilkan ulang yang baru diketik: pengguna harus bisa memperbaiki
        // isian salah tanpa mengetik ulang semuanya.
        $P = array_merge($P, $nilaiU, array_map(fn($v) => (string) $v, $nilaiP));
        $pesan = implode(' ', $galat);
    } else {
        transaksi(function () use ($P, $nilaiU, $nilaiP, $prodiId) {
            ubah('upps', $nilaiU, 'id', $P['upps_id']);
            ubah('prodi', $nilaiP, 'id', $prodiId);
        });
        alihkan(BASE_URL . 'index.php?m=identitas&a=index&p=' . $prodiId . '&ok=1');
    }
}

/* ── data tampil ────────────────────────────────────────────────── */
$W = wajib_tabel($prodiId);
$bolehIsi = boleh('ISI');
$akr = pesan_akreditasi($P['tgl_berakhir_akreditasi']);
$barisKhusus = baris_khusus_profesi($prodiId);
$daftarProdi = q('SELECT id, nama_ps, jenjang FROM prodi WHERE aktif = 1 ORDER BY nama_ps');

$kepala =
    (!empty($_GET['ok']) ? callout('ok', 'Tersimpan',
        'Identitas diperbarui. Rekap dan label tahun mengikuti nilai baru.') : '')
    . (isset($pesan) ? callout('bad', 'Tidak tersimpan', e($pesan)) : '')
    . (!$bolehIsi ? callout('warn', 'Hanya lihat',
        'Peran Anda tidak boleh mengubah identitas. Yang boleh: Admin UPPS dan Operator.') : '')
    . ($akr ? callout($akr['jenis'], $akr['judul'], e($akr['pesan'])) : '')
    . (!$P['punya_tahap_profesi'] && $barisKhusus > 0 ? callout('warn', 'Ada data di tabel profesi',
        e($barisKhusus) . ' baris tersimpan di tabel khusus profesi. Data tidak dihapus — tabelnya jadi'
        . ' <b>tidak berlaku</b> dan keluar dari rekap selama tahap profesi dimatikan.') : '');

$formUpps = kartu('Unit pengelola program studi',
    '<div class="fld-grid">'
    . implode('', array_map(fn($f) => kendali($f, $P[$f['kunci']] ?? '', $bolehIsi), spec_upps()))
    . '</div>',
    ['catatan' => count(spec_upps()) . ' field · berlaku untuk semua prodi di UPPS ini']);

$formProdi = kartu('Program studi',
    '<div class="fld-grid">'
    . implode('', array_map(fn($f) => kendali($f, $P[$f['kunci']] ?? '', $bolehIsi), spec_prodi()))
    . '</div>'
    . '<div class="kons" aria-live="polite"><div class="kons-angka"><b id="konsJumlah">' . e($W['wajib']) . '</b>'
    . ' tabel wajib</div><div class="kons-teks" id="konsTeks"></div></div>',
    ['catatan' => count(spec_prodi()) . ' field · satu prodi per halaman']);

$aksiSimpan = $bolehIsi
    ? '<div class="id-aksi"><button class="btn btn-primary" type="submit">Simpan identitas</button>'
      . '<span class="fn">Perubahan langsung memengaruhi rekap, label tahun, dan ekspor.</span></div>'
    : '';

$relKonsekuensi = kartu('Konsekuensi tabel',
    '<table class="tbl">'
    . '<tr><td>Tabel instrumen</td><td class="r num">' . e($W['total']) . '</td></tr>'
    . '<tr><td>Khusus tahap profesi</td><td class="r num">' . e(count($W['khusus'])) . '</td></tr>'
    . '<tr><td>Wajib untuk prodi ini</td><td class="r num"><b id="konsJumlah2">' . e($W['wajib']) . '</b></td></tr>'
    . '<tr><td>Tidak berlaku</td><td class="r num" id="konsNa">' . e($W['total'] - $W['wajib']) . '</td></tr>'
    . '</table>'
    . '<p class="fn">Tabel ' . e(implode(' · ', $W['khusus'])) . ' hanya wajib bila prodi punya tahap profesi.'
    . ' Daftar ini dibaca dari <code>tabel_dukung</code>.</p>');

$sisa = sisa_hari($P['tgl_berakhir_akreditasi']);
$relAkr = kartu('Masa berlaku akreditasi',
    $sisa === null
        ? '<p class="muted">Tanggal berakhir belum diisi.</p>'
        : '<p class="num id-besar ' . ($sisa < 0 ? 'bad' : ($sisa <= 180 ? 'warn' : '')) . '">'
          . e($sisa) . ' hari</p><p class="fn">sisa sampai ' . e(tgl($P['tgl_berakhir_akreditasi']))
          . ' · akreditasi ' . e(tgl($P['tgl_akreditasi']))
          . ' · ' . e($P['peringkat_akreditasi'] ?: 'peringkat belum diisi') . '</p>');

$tautanProdi = '';
foreach ($daftarProdi as $d) {
    $tautanProdi .= sprintf('<a class="pilih-prodi%s" href="%sindex.php?m=identitas&amp;p=%d">%s<span>%s</span></a>',
        (int) $d['id'] === $prodiId ? ' aktif' : '', e(BASE_URL), (int) $d['id'],
        e($d['nama_ps']), e($d['jenjang']));
}
// Tautan, bukan form: pemilih ini tidak boleh jadi <form> bersarang di dalam form simpan.
$relProdi = kartu('Prodi', '<div class="daftar-prodi">' . $tautanProdi . '</div>'
    . '<p class="fn">Operator terkunci ke prodinya sendiri; Admin dan Pimpinan boleh berpindah.</p>');

ob_start();
?>
<?= $kepala ?>
<form method="post" action="<?= e(BASE_URL) ?>index.php?m=identitas&amp;a=simpan&amp;p=<?= e($prodiId) ?>"
      id="formIdentitas" data-penuh="<?= e($W['penuh']) ?>" data-tanpa="<?= e($W['tanpa']) ?>"
      data-khusus="<?= e(implode(',', $W['khusus'])) ?>">
  <?= csrf_input() ?>
  <div class="id-grid">
    <div><?= $formUpps . $formProdi . $aksiSimpan ?></div>
    <aside><?= $relKonsekuensi . $relAkr . $relProdi ?></aside>
  </div>
</form>

<script>
// Pratinjau konsekuensi. Angka datang dari server (dihitung dari tabel_dukung);
// skrip ini hanya memilih mana yang berlaku — tidak ada angka yang diketik di sini.
(function () {
  var f = document.getElementById('formIdentitas');
  if (!f) { return; }
  var jumlah = document.getElementById('konsJumlah');
  var jumlah2 = document.getElementById('konsJumlah2');
  var na = document.getElementById('konsNa');
  var teks = document.getElementById('konsTeks');
  var penuh = f.dataset.penuh, tanpa = f.dataset.tanpa;
  var khusus = (f.dataset.khusus || '').split(',').filter(Boolean);
  var cb = f.querySelector('input[type=checkbox][name=punya_tahap_profesi]');
  var jen = f.querySelector('select[name=jenjang]');

  function hitung() {
    var profesi = (cb && cb.checked) || (jen && (jen.value === 'Profesi' || jen.value === 'Spesialis'));
    var n = profesi ? penuh : tanpa;
    [jumlah, jumlah2].forEach(function (el) { if (el) { el.textContent = n; } });
    if (na) { na.textContent = String(Number(penuh) - Number(n)); }
    if (teks) {
      teks.innerHTML = profesi
        ? 'Semua <b>' + penuh + '</b> tabel berlaku, termasuk ' + khusus.length
          + ' tabel khusus tahap profesi (Tabel ' + khusus.join(' · ') + ').'
        : '<b>' + khusus.length + ' tabel tidak berlaku</b>: Tabel ' + khusus.join(' · ')
          + '. Target prodi ini ' + n + ' tabel, bukan ' + penuh
          + ' — Ringkasan 8 Kriteria ikut menyesuaikan.';
    }
  }
  if (cb) { cb.addEventListener('change', hitung); }
  if (jen) { jen.addEventListener('change', hitung); }
  hitung();
})();
</script>
<?php
$isi = ob_get_clean();
render('Identitas UPPS & Prodi', $isi, [
    'aktif' => 'identitas',
    'sub'   => sprintf('F-01 · %s · %s · dipakai %d tabel wajib dan semua ekspor',
        (string) $P['nama_ps'], (string) $P['jenjang'], $W['wajib']),
]);
