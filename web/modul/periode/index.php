<?php
declare(strict_types=1);

/**
 * Modul: Periode Akreditasi (F-02).
 *
 * Yang disimpan tahun ABSOLUT; label TS-n / SS-n dihitung saat tampil lewat
 * label_ts()/label_ss(). Karena itu seluruh pratinjau di bawah dihitung dari
 * tahun, dan mengganti tahun tidak menyentuh `baris_tabel` / `sel` sama sekali.
 */

require_once __DIR__ . '/../../system/layout.php';
require_once __DIR__ . '/../../tampilan/kerangka/komponen.php';
require_once __DIR__ . '/../identitas/bantu.php';

$aksi = $_GET['a'] ?? 'index';
$P = prodi_terpilih();

if (!$P) {
    render('Periode Akreditasi', halaman_galat(409, 'Belum ada program studi aktif.'), ['aktif' => 'periode']);
    return;
}
$prodiId = (int) $P['id'];
$punyaProfesi = (int) $P['punya_tahap_profesi'] === 1;

/* ── simpan ─────────────────────────────────────────────────────── */
if ($aksi === 'simpan') {
    wajib('ISI');
    csrf_periksa();

    $ts = (int) masuk_input('tahun_ts');
    $ssMentah = masuk_input('tahun_ss');
    $ss = $ssMentah === '' ? null : (int) $ssMentah;
    $tgl = masuk_input('tgl_pengajuan');
    $galat = [];

    if ($ts < 2000 || $ts > 2100) {
        $galat[] = 'Tahun TS harus antara 2000 dan 2100.';
    }
    if ($ss !== null && ($ss < 2000 || $ss > 2100)) {
        $galat[] = 'Tahun SS harus antara 2000 dan 2100.';
    }
    if ($ss !== null && $ts > 0 && $ss > $ts) {
        $galat[] = 'Tahun SS tidak boleh setelah TS — tahap profesi berjalan setelah akademik.';
    }
    if (!$punyaProfesi && $ss !== null) {
        $galat[] = 'Prodi ini tidak punya tahap profesi, jadi SS tidak dipakai. Kosongkan.';
    }
    if ($tgl !== '' && strtotime($tgl) === false) {
        $galat[] = 'Tanggal pengajuan tidak sah.';
    }

    if (!$galat) {
        // Baris tahun yang sudah ada dipakai ulang: periode_id di baris_tabel
        // tidak boleh jadi yatim hanya karena tahun diganti.
        $adakah = (int) qv('SELECT id FROM periode WHERE prodi_id = ? AND tahun_ts = ?', [$prodiId, $ts], 0);
        transaksi(function () use ($prodiId, $ts, $ss, $tgl, $adakah) {
            if ($adakah) {
                ubah('periode', ['tahun_ss' => $ss, 'tgl_pengajuan' => $tgl ?: null, 'aktif' => 1], 'id', $adakah);
                $id = $adakah;
            } else {
                $id = sisip('periode', [
                    'prodi_id' => $prodiId, 'tahun_ts' => $ts, 'tahun_ss' => $ss,
                    'tgl_pengajuan' => $tgl ?: null, 'aktif' => 1,
                ]);
            }
            ex('UPDATE periode SET aktif = 0 WHERE prodi_id = ? AND id <> ?', [$prodiId, $id]);
        });
        alihkan(BASE_URL . 'index.php?m=periode&p=' . $prodiId . '&ok=1');
    }
}

/* ── data tampil ────────────────────────────────────────────────── */
$per = periode_aktif($prodiId);
$bolehIsi = boleh('ISI');

// Tahun dari POST yang gagal, atau periode aktif, atau tahun berjalan.
$ts = isset($galat) ? (int) masuk_input('tahun_ts') : (int) ($per['tahun_ts'] ?? date('Y'));
$ss = isset($galat)
    ? (masuk_input('tahun_ss') === '' ? null : (int) masuk_input('tahun_ss'))
    : (isset($per['tahun_ss']) && $per['tahun_ss'] !== null ? (int) $per['tahun_ss'] : null);
$tglPengajuan = isset($galat) ? masuk_input('tgl_pengajuan') : (string) ($per['tgl_pengajuan'] ?? '');

$barisAda = (int) qv('SELECT COUNT(*) FROM baris_tabel WHERE prodi_id = ?', [$prodiId], 0);
$selAda = (int) qv('SELECT COUNT(*) FROM sel s JOIN baris_tabel b ON b.id = s.baris_id
                    WHERE b.prodi_id = ?', [$prodiId], 0);
$riwayat = q('SELECT id, tahun_ts, tahun_ss, tgl_pengajuan, aktif FROM periode
              WHERE prodi_id = ? ORDER BY tahun_ts DESC', [$prodiId]);
$akr = pesan_akreditasi($P['tgl_berakhir_akreditasi']);

// Pratinjau: TS-0..TS-6 mundur, SS-1..SS-16 maju. Dihitung, tidak disimpan.
$barisTs = [];
foreach (range(0, 6) as $n) {
    $label = label_ts($ts, $n);
    $barisTs[] = [
        // TS-0 = periode berjalan; ditandai tebal, bukan warna saja (dicetak hitam-putih).
        'label' => $n === 0 ? ['html' => '<b>' . e($label) . '</b>'] : $label,
        'tahun' => ($ts - $n) . '/' . ($ts - $n + 1),
        'rentang' => '1 Jul ' . ($ts - $n) . ' – 30 Jun ' . ($ts - $n + 1),
    ];
}
$barisSs = [];
if ($ss !== null) {
    foreach (range(1, 16) as $n) {
        $barisSs[] = [
            'label' => label_ss($ss, $n),
            'tahun' => ($ss + $n - 1) . '/' . ($ss + $n),
        ];
    }
}

$kepala =
    (!empty($_GET['ok']) ? callout('ok', 'Tersimpan',
        'Periode diperbarui. Label tahun di seluruh tabel ikut bergeser; data tidak berubah.') : '')
    . (isset($galat) && $galat ? callout('bad', 'Tidak tersimpan', e(implode(' ', $galat))) : '')
    . (!$bolehIsi ? callout('warn', 'Hanya lihat',
        'Peran Anda tidak boleh mengubah periode. Yang boleh: Admin UPPS dan Operator.') : '')
    . callout('info', 'Tahun absolut, label dihitung',
        'Mengganti tahun <b>tidak mengubah data</b>. ' . e($barisAda) . ' baris dan ' . e($selAda)
        . ' sel tetap di tempatnya; hanya tulisan TS-n / SS-n yang bergeser.'
        . ' Itu sebabnya berkas bisa diajukan ulang tanpa menghitung ulang kolom.');

$formPeriode = kartu('Periode berjalan',
    '<div class="fld-grid">'
    . '<div class="fld"><label for="f_ts">TS — tahun terakhir periode pelaporan</label>'
    . '<input type="number" id="f_ts" name="tahun_ts" min="2000" max="2100" step="1" value="' . e($ts) . '" required'
    . ($bolehIsi ? '' : ' disabled') . '>'
    . '<div class="fn">Tahun pertama tahun akademik: 2025 berarti TS = 2025/2026.</div></div>'
    . '<div class="fld"><label for="f_ss">SS — tahun mulai tahap profesi</label>'
    . '<input type="number" id="f_ss" name="tahun_ss" min="2000" max="2100" step="1" value="' . e($ss ?? '') . '"'
    . ' placeholder="kosong bila tanpa profesi"' . ($punyaProfesi && $bolehIsi ? '' : ' disabled') . '>'
    . '<div class="fn">' . ($punyaProfesi
        ? 'Prodi ini punya tahap profesi, jadi SS dipakai.'
        : 'Prodi ini tidak punya tahap profesi — kolom SS-n tidak dipakai.') . '</div></div>'
    . '<div class="fld"><label for="f_tgl">Tanggal pengajuan</label>'
    . '<input type="date" id="f_tgl" name="tgl_pengajuan" value="' . e($tglPengajuan) . '"'
    . ($bolehIsi ? '' : ' disabled') . '>'
    . '<div class="fn">Tanggal berkas diajukan ke LAM-PTKes.</div></div>'
    . '<div class="fld"><label>Batas masa berlaku akreditasi</label>'
    . '<p class="id-baca">' . e(tgl($P['tgl_berakhir_akreditasi']))
    . (sisa_hari($P['tgl_berakhir_akreditasi']) !== null
        ? ' <span class="muted">· ' . e(sisa_hari($P['tgl_berakhir_akreditasi'])) . ' hari</span>' : '') . '</p>'
    . '<div class="fn">Diubah di <a href="' . e(BASE_URL) . 'index.php?m=identitas&amp;p=' . e($prodiId)
    . '">Identitas</a>.</div></div>'
    . '</div>'
    . ($bolehIsi ? '<div class="id-aksi"><button class="btn btn-primary" type="submit">Simpan periode</button></div>' : ''),
    ['catatan' => $P['nama_ps'] . ' · ' . $P['jenjang']]);

$tabelTs = kartu('Pratinjau label — TS',
    tabel_html(
        [['kunci' => 'label', 'label' => 'Label'], ['kunci' => 'tahun', 'label' => 'Tahun akademik'],
         ['kunci' => 'rentang', 'label' => 'Rentang']],
        $barisTs),
    ['catatan' => 'dihitung dari TS ' . $ts . ' · tidak disimpan']);

$tabelSs = $ss === null
    ? kartu('Pratinjau label — SS',
        '<p class="muted">Kolom SS-n di tabel tahap profesi tidak akan terisi. Isi SS bila prodi'
        . ' punya tahap profesi.</p>', ['catatan' => 'tidak dipakai: prodi tanpa tahap profesi'])
    : kartu('Pratinjau label — SS',
        tabel_html([['kunci' => 'label', 'label' => 'Label'], ['kunci' => 'tahun', 'label' => 'Tahun akademik']],
            $barisSs),
        ['catatan' => 'dihitung dari SS ' . $ss . ' · ' . count($barisSs) . ' label']);

$relAktif = kartu('Periode aktif', $per
    ? '<p class="num id-besar">TS ' . e($per['tahun_ts']) . '</p>'
      . '<p class="fn">' . e(label_ts((int) $per['tahun_ts'], 0))
      . ($per['tahun_ss'] ? ' · SS ' . e($per['tahun_ss']) : ' · tanpa SS') . '</p>'
      . ($akr ? callout($akr['jenis'], $akr['judul'], e($akr['pesan'])) : '')
    : '<p class="muted">Belum ada periode aktif.</p>');

$relData = kartu('Data tidak ikut bergeser',
    '<table class="tbl">'
    . '<tr><td>Baris tabel tersimpan</td><td class="r num">' . e($barisAda) . '</td></tr>'
    . '<tr><td>Sel terisi</td><td class="r num">' . e($selAda) . '</td></tr>'
    . '</table>'
    . '<p class="fn">Angka ini tidak berubah saat tahun diganti. <code>uji_identitas.php</code>'
    . ' membuktikannya.</p>');

$relRiwayat = kartu('Riwayat periode', $riwayat
    ? tabel_html(
        [['kunci' => 'th', 'label' => 'Tahun'], ['kunci' => 'st', 'label' => 'Status'],
         ['kunci' => 'tgl', 'label' => 'Diajukan', 'kelas' => 'r']],
        array_map(fn($r) => [
            // TS dan SS bukan satu tahun akademik — jangan digabung jadi "2025/2020".
            'th' => 'TS ' . $r['tahun_ts'] . ($r['tahun_ss'] ? ' · SS ' . $r['tahun_ss'] : ''),
            'st' => (int) $r['aktif'] === 1 ? 'Aktif' : 'Arsip',
            'tgl' => tgl($r['tgl_pengajuan']),
        ], $riwayat))
    : '<p class="muted">Belum ada periode.</p>');

ob_start();
?>
<?= $kepala ?>
<form method="post" action="<?= e(BASE_URL) ?>index.php?m=periode&amp;a=simpan&amp;p=<?= e($prodiId) ?>">
  <?= csrf_input() ?>
  <div class="per-grid">
    <div><?= $formPeriode . $tabelTs . $tabelSs ?></div>
    <aside><?= $relAktif . $relData . $relRiwayat ?></aside>
  </div>
</form>
<?php
$isi = ob_get_clean();
render('Periode Akreditasi', $isi, [
    'aktif' => 'periode',
    'sub'   => sprintf('F-02 · menentukan label TS-n dan SS-n di seluruh tabel instrumen · TS %d',
        (int) ($per['tahun_ts'] ?? $ts)),
]);
