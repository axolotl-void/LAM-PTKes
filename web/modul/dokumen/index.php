<?php
declare(strict_types=1);

/**
 * Modul: Dokumen Pendukung.
 *
 * 78 butir instrumen, dikelompokkan per kriteria. Satu butir boleh punya
 * banyak versi; yang dipakai selalu yang TERBARU, versi lama tetap tersimpan
 * sebagai riwayat (asesor sering minta versi sebelumnya).
 *
 * Aksi:
 *   index   daftar per kriteria
 *   unggah  POST — simpan berkas (wajib ISI)
 *   hapus   POST — buang satu versi lama (wajib ISI)
 *
 * Logika tulis/validasi ada di logika.php supaya bisa diuji tanpa HTTP.
 */

require_once __DIR__ . '/../../system/layout.php';
require_once dirname(__DIR__, 2) . '/tampilan/kerangka/komponen.php';
require_once __DIR__ . '/logika.php';

$aksi = preg_replace('/[^a-z]/', '', $_GET['a'] ?? 'index');
$PRODI = prodi_aktif();
$per = $PRODI !== null ? periode_aktif($PRODI) : null;

if ($PRODI === null || $per === null) {
    render('Dokumen Pendukung', gaya_kerangka() . callout('warn', 'Prodi/periode belum ditetapkan',
        'Pilih prodi dan periode dulu di menu <b>Periode Akreditasi</b>. '
        . 'Tanpa itu dokumen tidak punya tempat menempel.'), ['aktif' => 'dokumen']);
    return;
}

$PERIODE  = (int) $per['id'];
$HARI_INI = date('Y-m-d');

/* ── unggah ─────────────────────────────────────────────────────── */
if ($aksi === 'unggah') {
    wajib('ISI');
    csrf_periksa();

    $dokId = (int) ($_POST['dok_spec_id'] ?? 0);
    $hasil = dok_simpan_unggah(
        $dokId, $PRODI, $PERIODE,
        $_FILES['berkas'] ?? [],
        (int) (pengguna()['id'] ?? 0),
        masuk_input('berlaku_sampai'),
        masuk_input('catatan')
    );

    $_SESSION['dok_flash'] = $hasil['ok']
        ? ['ok', 'Berkas tersimpan: ' . dok_nama_bersih((string) ($_FILES['berkas']['name'] ?? ''))]
        : ['bad', $hasil['pesan']];

    alihkan(BASE_URL . 'index.php?m=dokumen&butir=' . $dokId . '#dok-' . $dokId);
}

/* ── hapus satu versi lama ──────────────────────────────────────── */
if ($aksi === 'hapus') {
    wajib('ISI');
    csrf_periksa();

    $id = (int) ($_POST['unggah_id'] ?? 0);
    $baris = q1('SELECT id, berkas, nama_asli FROM dokumen_unggah
                 WHERE id = ? AND prodi_id = ? AND periode_id = ?', [$id, $PRODI, $PERIODE]);
    if (!$baris) {
        $_SESSION['dok_flash'] = ['bad', 'Versi unggahan tidak ditemukan di prodi/periode ini.'];
    } else {
        // Versi terbaru tidak boleh dihapus dari sini: menghapusnya membuat
        // butir balik ke versi lama tanpa disadari orang.
        $terbaru = (int) qv('SELECT id FROM dokumen_unggah WHERE dok_spec_id = ?
                             ORDER BY diunggah_pada DESC, id DESC LIMIT 1',
            [(int) qv('SELECT dok_spec_id FROM dokumen_unggah WHERE id = ?', [$id], 0)], 0);
        if ($terbaru === $id) {
            $_SESSION['dok_flash'] = ['bad', 'Versi ini yang sedang dipakai. Unggah versi pengganti dulu, baru versi lama boleh dihapus.'];
        } else {
            ex('DELETE FROM dokumen_unggah WHERE id = ?', [$id]);
            dok_hapus_berkas((string) $baris['berkas']);
            $_SESSION['dok_flash'] = ['ok', 'Versi "' . $baris['nama_asli'] . '" dihapus.'];
        }
    }
    alihkan(BASE_URL . 'index.php?m=dokumen');
}

/* ── data ───────────────────────────────────────────────────────── */
$flash = $_SESSION['dok_flash'] ?? null;
unset($_SESSION['dok_flash']);

$rekap  = rekap_dokumen($PRODI, $PERIODE);
$daftar = dok_daftar($PRODI, $PERIODE, $HARI_INI);
$lewat  = dok_kedaluwarsa($PRODI, $PERIODE, $HARI_INI);
$bolehIsi = boleh('ISI');
$sorot = (int) ($_GET['butir'] ?? 0);

$labelStatus = [
    'belum'       => ['Belum ada', 'badge-kosong', '—'],
    'ada'         => ['Ada', 'badge-lengkap', '✓'],
    'kedaluwarsa' => ['Kedaluwarsa', 'badge-bad', '!'],
];

ob_start();
?>
<link rel="stylesheet" href="<?= e(BASE_URL) ?>aset/css/dokumen.css?v=1">

<?php if ($flash): ?>
  <?= callout($flash[0] === 'ok' ? 'ok' : 'bad',
      $flash[0] === 'ok' ? 'Tersimpan' : 'Berkas ditolak', e($flash[1])) ?>
<?php endif; ?>

<?php
// Kotak "kedaluwarsa" hanya muncul kalau ada — angka 0 di sini bukan indikator
// masalah, dan blok kosong dilarang aturan tampilan.
$strip = [
    ['label' => 'Butir dokumen', 'nilai' => $rekap['total'], 'satuan' => 'butir'],
    ['label' => 'Sudah diunggah', 'nilai' => $rekap['unggah'], 'dari' => $rekap['total']],
    ['label' => 'Belum ada', 'nilai' => $rekap['belum'], 'satuan' => 'butir'],
];
if ($rekap['kedaluwarsa'] > 0) {
    $strip[] = ['label' => 'Kedaluwarsa', 'nilai' => $rekap['kedaluwarsa'], 'satuan' => 'butir',
                'ket' => 'perlu diganti'];
}
echo strip_angka($strip);
?>

<?php if ($lewat): ?>
  <?= callout('bad', count($lewat) . ' dokumen lewat masa berlaku',
      'Versi terpakai sudah kedaluwarsa. Unggah dokumen terbaru supaya asesor '
      . 'tidak menilai bukti yang sudah tidak sah.') ?>
  <div class="card dok-perhatian">
    <div class="tbl-wrap">
      <table class="tbl">
        <thead><tr><th>Butir</th><th>Dokumen</th><th>Berlaku sampai</th><th>Sisa</th></tr></thead>
        <tbody>
        <?php foreach ($lewat as $l): ?>
          <tr class="is-bad">
            <td class="mono"><a href="#dok-<?= (int) $l['dok_spec_id'] ?>"><?= e($l['kriteria']) ?>-<?= e($l['nomor']) ?></a></td>
            <td><?= e($l['nama']) ?><div class="dok-meta"><?= e($l['nama_asli']) ?></div></td>
            <td><?= e(tgl($l['berlaku_sampai'])) ?></td>
            <td class="dok-lewat-hari"><?= $l['sisa'] < 0
                ? 'lewat ' . e(abs($l['sisa'])) . ' hari'
                : 'sisa ' . e($l['sisa']) . ' hari' ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>

<?php foreach ($daftar as $grup): ?>
  <section class="card dok-krit" id="krit-<?= e($grup['kode']) ?>">
    <div class="card-head dok-krit-head">
      <h2><?= e($grup['kode']) ?> · <?= e($grup['nama']) ?></h2>
      <div class="dok-jumlah">
        <span class="mono"><?= e($grup['unggah']) ?>/<?= e($grup['total']) ?></span> butir terunggah
        <?php if ($grup['unggah'] < $grup['total']): ?>
          <span class="muted">· <?= e($grup['total'] - $grup['unggah']) ?> belum ada</span>
        <?php endif; ?>
      </div>
      <?= bar_bagian((float) $grup['unggah'], (float) $grup['total']) ?>
    </div>

    <?php if (!$grup['butir']): ?>
      <p class="muted">Belum ada butir untuk kriteria ini.</p>
    <?php else: ?>
    <div class="tbl-wrap">
      <table class="tbl dok-tabel">
        <thead>
          <tr>
            <th class="dok-c-no">No</th>
            <th>Butir dokumen</th>
            <th class="dok-c-status">Status</th>
            <th class="dok-c-berkas">Berkas dipakai</th>
            <th class="dok-c-tgl">Diunggah</th>
            <th class="dok-c-berlaku">Berlaku sampai</th>
            <th class="dok-c-aksi">Aksi</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($grup['butir'] as $b):
            [$lbl, $kls, $ikon] = $labelStatus[$b['status']];
            $versi = $b['versi'];
            $pakai = $b['pakai'];
        ?>
          <tr id="dok-<?= (int) $b['id'] ?>" class="<?= $b['id'] === $sorot ? 'dok-sorot' : '' ?><?= $b['status'] === 'kedaluwarsa' ? ' is-bad' : '' ?>">
            <td class="dok-c-no mono"><?= e($b['nomor']) ?></td>
            <td>
              <div class="dok-nama"><?= e($b['nama']) ?><?= (int) $b['wajib'] === 0 ? ' <span class="muted">(tidak wajib)</span>' : '' ?></div>
              <?php if (!empty($b['rincian'])): ?>
                <details class="dok-rincian"><summary>Rincian</summary><p><?= nl2br(e($b['rincian'])) ?></p></details>
              <?php endif; ?>
            </td>
            <td class="dok-c-status">
              <span class="badge <?= e($kls) ?>"><?= e($ikon) ?> <?= e($lbl) ?></span>
              <?php if ($b['status'] === 'kedaluwarsa' && $b['sisa'] !== null): ?>
                <div class="dok-sisa">lewat <?= e(abs((int) $b['sisa'])) ?> hari</div>
              <?php endif; ?>
            </td>
            <td class="dok-c-berkas">
              <?php if ($pakai): ?>
                <a class="dok-berkas" href="<?= e(BASE_URL) ?>unggah/<?= e(rawurlencode((string) $pakai['berkas'])) ?>"
                   target="_blank" rel="noopener"><?= e($pakai['nama_asli']) ?></a>
                <div class="dok-meta">
                  <?= e(number_format(((int) $pakai['ukuran']) / 1024, 0, ',', '.')) ?> KB
                  <?php if (!empty($pakai['oleh_nama'])): ?> · <?= e($pakai['oleh_nama']) ?><?php endif; ?>
                </div>
              <?php else: ?>
                <span class="muted">—</span>
              <?php endif; ?>
            </td>
            <td class="dok-c-tgl"><?= $pakai ? e(tgl((string) $pakai['diunggah_pada'])) : '<span class="muted">—</span>' ?></td>
            <td class="dok-c-berlaku">
              <?= $pakai && $pakai['berlaku_sampai'] ? e(tgl((string) $pakai['berlaku_sampai'])) : '<span class="muted">—</span>' ?>
            </td>
            <td class="dok-c-aksi">
              <?php if ($bolehIsi): ?>
                <details class="dok-form">
                  <summary class="btn <?= $pakai ? '' : 'btn-primary' ?>"><?= $pakai ? 'Ganti' : 'Unggah' ?></summary>
                  <form method="post" enctype="multipart/form-data"
                        action="<?= e(BASE_URL) ?>index.php?m=dokumen&amp;a=unggah" class="dok-isi">
                    <?= csrf_input() ?>
                    <input type="hidden" name="dok_spec_id" value="<?= (int) $b['id'] ?>">
                    <label>Berkas
                      <input type="file" name="berkas" required accept=".pdf,.docx,.xlsx,.jpg,.jpeg,.png">
                    </label>
                    <label>Berlaku sampai <span class="muted">(boleh kosong)</span>
                      <input type="date" name="berlaku_sampai"
                             value="<?= e((string) ($pakai['berlaku_sampai'] ?? '')) ?>">
                    </label>
                    <label>Catatan
                      <input type="text" name="catatan" maxlength="255" placeholder="mis. revisi setelah audit">
                    </label>
                    <p class="foot-note">pdf, docx, xlsx, jpg, jpeg, png · maks 20 MB.</p>
                    <button class="btn btn-primary" type="submit">Simpan berkas</button>
                  </form>
                </details>
              <?php else: ?>
                <span class="muted">hanya lihat</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php if (count($versi) > 1): ?>
          <tr class="dok-riwayat-baris">
            <td></td>
            <td colspan="6">
              <details class="dok-riwayat">
                <summary>Riwayat <?= count($versi) ?> versi</summary>
                <ol>
                  <?php foreach ($versi as $i => $v): ?>
                    <li>
                      <?php if ($i === 0): ?><span class="badge badge-lengkap">dipakai</span>
                      <?php else: ?><span class="badge">versi lama</span><?php endif; ?>
                      <span class="dok-berkas-nama"><?= e($v['nama_asli']) ?></span>
                      · <?= e(tgl((string) $v['diunggah_pada'])) ?>
                      <?php if ($v['berlaku_sampai']): ?> · berlaku s.d. <?= e(tgl((string) $v['berlaku_sampai'])) ?><?php endif; ?>
                      <?php if (!empty($v['oleh_nama'])): ?> · <?= e($v['oleh_nama']) ?><?php endif; ?>
                      <?php if (!empty($v['catatan'])): ?> · “<?= e($v['catatan']) ?>”<?php endif; ?>
                      <?php if ($bolehIsi && $i > 0): ?>
                        <form method="post" action="<?= e(BASE_URL) ?>index.php?m=dokumen&amp;a=hapus"
                              class="dok-hapus" data-konfirmasi="Hapus versi lama ini?">
                          <?= csrf_input() ?>
                          <input type="hidden" name="unggah_id" value="<?= (int) $v['id'] ?>">
                          <button class="dok-tautan" type="submit">hapus</button>
                        </form>
                      <?php endif; ?>
                    </li>
                  <?php endforeach; ?>
                </ol>
              </details>
            </td>
          </tr>
          <?php endif; ?>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </section>
<?php endforeach; ?>

<?php
$isi = (string) ob_get_clean();

render('Dokumen Pendukung', $isi, [
    'aktif' => 'dokumen',
    'sub'   => sprintf('%d butir · %d sudah diunggah · %d kedaluwarsa',
        $rekap['total'], $rekap['unggah'], $rekap['kedaluwarsa']),
]);
