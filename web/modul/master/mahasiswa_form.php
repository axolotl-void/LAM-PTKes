<?php
declare(strict_types=1);

/**
 * Form tambah/ubah mahasiswa. Di-require dari mahasiswa.php, jadi $nilai,
 * $galat, $prodiNm, $bolehUbah sudah tersedia. Semua isian dipertahankan saat
 * validasi gagal — operator tidak boleh kehilangan 10 ketikan karena satu IPK.
 */

$n = fn(string $k, string $b = '') => e((string) ($nilai[$k] ?? $b));
$id = (int) ($nilai['id'] ?? 0);
$judul = $id > 0 ? 'Ubah mahasiswa' : 'Tambah mahasiswa';

if (!empty($galat)) {
    $isi .= callout('bad', 'Belum bisa disimpan', e($galat));
}

$isi .= kartu($judul, '<form class="grid-form" method="post" action="' . e(BASE_URL) . 'index.php?m=master&amp;a=mahasiswa-simpan">'
    . csrf_input()
    . '<input type="hidden" name="id" value="' . $id . '">'
    . '<label>NIM<input name="nim" required maxlength="24" value="' . $n('nim') . '"></label>'
    . '<label class="lebar">Nama lengkap<input name="nama" required maxlength="160" value="' . $n('nama') . '"></label>'
    . '<label>Tahun masuk<input type="number" name="tahun_masuk" required min="1990" max="' . e((string) (date('Y') + 1)) . '" value="' . $n('tahun_masuk') . '"></label>'
    . '<label>Jalur masuk<select name="jalur_masuk">'
    . pilihan(MHS_JALUR, (string) ($nilai['jalur_masuk'] ?? 'Reguler')) . '</select></label>'
    . '<label>Tahap<select name="tahap">'
    . pilihan(MHS_TAHAP, (string) ($nilai['tahap'] ?? 'Akademik')) . '</select></label>'
    . '<label>Status<select name="status">'
    . pilihan(MHS_STATUS, (string) ($nilai['status'] ?? 'Aktif')) . '</select></label>'
    . '<label>IPK (0–4)<input type="number" name="ipk" step="0.01" min="0" max="4" value="' . $n('ipk') . '"></label>'
    . '<label>Tanggal lulus<input type="date" name="tanggal_lulus" value="' . $n('tanggal_lulus') . '"></label>'
    . '<label>Tahun lulus<input type="number" name="tahun_lulus" min="1990" max="' . e((string) (date('Y') + 2)) . '" value="' . $n('tahun_lulus') . '"></label>'
    . '<div class="form-aksi"><button class="btn btn-primary" type="submit">Simpan</button>'
    . '<a class="btn" href="' . e(BASE_URL) . 'index.php?m=master&amp;a=mahasiswa">Batal</a></div>'
    . '</form>'
    . '<p class="foot-note">Tahun lulus terisi otomatis dari tanggal lulus bila dikosongkan. '
    . 'Status Lulus wajib punya tanggal lulus.</p>', ['catatan' => 'Prodi ' . $prodiNm]);

render($judul, $css . $isi, ['aktif' => 'mahasiswa', 'sub' => 'Prodi ' . $prodiNm]);
