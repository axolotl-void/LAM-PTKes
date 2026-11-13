<?php
declare(strict_types=1);

/**
 * Form dosen/tendik + riwayat pendidikan banyak baris. Di-require dari
 * dosen.php; $nilai, $galat, $petaProdi, $bolehUbah tersedia.
 *
 * Riwayat pendidikan ditambah/dihapus lewat submit biasa (bukan JS): satu
 * baris satu form hapus, jadi tidak butuh state di browser dan tetap jalan
 * kalau JS mati. Dosen baru harus disimpan dulu sebelum riwayat bisa diisi
 * karena dosen_pendidikan butuh dosen_id.
 */

$n    = fn(string $k, string $b = '') => e((string) ($nilai[$k] ?? $b));
$id   = (int) ($nilai['id'] ?? 0);
$dipilihProdi = array_map('intval', (array) ($nilai['prodi'] ?? []));
$judul = $id > 0 ? 'Ubah dosen / tendik' : 'Tambah dosen / tendik';

if (!empty($galat)) {
    $isi .= callout('bad', 'Belum bisa disimpan', e($galat));
}

$opsiProdiHtml = '';
foreach ($petaProdi as $pid => $pn) {
    $opsiProdiHtml .= '<label class="centang"><input type="checkbox" name="prodi[]" value="' . (int) $pid . '"'
        . (in_array($pid, $dipilihProdi, true) ? ' checked' : '') . '> ' . e($pn) . '</label>';
}

$isi .= kartu($judul, '<form class="grid-form" method="post" action="' . e(BASE_URL) . 'index.php?m=master&amp;a=dosen-simpan">'
    . csrf_input()
    . '<input type="hidden" name="id" value="' . $id . '">'
    . '<label>NIDN / NIDK / NUP<input name="nidn_nidk_nup" required maxlength="32" value="' . $n('nidn_nidk_nup') . '"></label>'
    . '<label class="lebar">Nama lengkap + gelar<input name="nama" required maxlength="160" value="' . $n('nama') . '"></label>'
    . '<label>Jenis<select name="jenis">' . pilihan(DOSEN_JENIS, (string) ($nilai['jenis'] ?? 'Dosen Tetap')) . '</select></label>'
    . '<label>Jabatan fungsional<select name="jabatan_fungsional"><option value="">—</option>'
    . pilihan(DOSEN_JABATAN, (string) ($nilai['jabatan_fungsional'] ?? '')) . '</select></label>'
    . '<label>Gelar akademik<input name="gelar_akademik" maxlength="120" value="' . $n('gelar_akademik') . '"></label>'
    . '<label>Tanggal lahir<input type="date" name="tanggal_lahir" value="' . $n('tanggal_lahir') . '"></label>'
    . '<label>Profesi<select name="profesi"><option value="">—</option>'
    . pilihan(DOSEN_PROFESI, (string) ($nilai['profesi'] ?? '')) . '</select></label>'
    . '<label>Tahap mengajar<select name="tahap_mengajar"><option value="">—</option>'
    . pilihan(DOSEN_TAHAP, (string) ($nilai['tahap_mengajar'] ?? '')) . '</select></label>'
    . '<label>No. STR<input name="no_str" maxlength="60" value="' . $n('no_str') . '"></label>'
    . '<label>Tanggal STR<input type="date" name="tanggal_str" value="' . $n('tanggal_str') . '"></label>'
    . '<label>Email<input type="email" name="email" maxlength="160" value="' . $n('email') . '"></label>'
    . '<label class="centang"><input type="checkbox" name="sertifikat_pendidik" value="1"'
    . ((int) ($nilai['sertifikat_pendidik'] ?? 0) === 1 ? ' checked' : '') . '> Sudah punya sertifikat pendidik</label>'
    . '<fieldset class="lebar prodi-pilih"><legend>Prodi tempat mengajar</legend>' . $opsiProdiHtml . '</fieldset>'
    . '<div class="form-aksi"><button class="btn btn-primary" type="submit">Simpan</button>'
    . '<a class="btn" href="' . e(BASE_URL) . 'index.php?m=master&amp;a=dosen">Batal</a></div>'
    . '</form>'
    . '<p class="foot-note">Centang lebih dari satu prodi untuk dosen lintas prodi. Data tetap satu baris; '
    . 'yang bertambah hanya rujukan di tabel dosen_prodi.</p>',
    ['catatan' => 'Satu orang satu baris — pilih semua prodi tempat mengajar di sini']);

/* riwayat pendidikan — hanya untuk dosen yang sudah tersimpan */
if ($id > 0) {
    $riwayat = dosen_pendidikan_dari($id);

    $kolomRiwayat = [
        ['kunci' => 'jenjang', 'label' => 'Jenjang'],
        ['kunci' => 'bidang', 'label' => 'Bidang'],
        ['kunci' => 'asal_pt', 'label' => 'Asal PT'],
        ['kunci' => 'tahun_lulus', 'label' => 'Tahun lulus', 'kelas' => 'r num'],
        ['kunci' => 'aksi', 'label' => 'Aksi', 'html' => true],
    ];
    $barisRiwayat = [];
    foreach ($riwayat as $p) {
        $barisRiwayat[] = [
            'jenjang'     => $p['jenjang'],
            'bidang'      => $p['bidang'],
            'asal_pt'     => $p['asal_pt'],
            'tahun_lulus' => $p['tahun_lulus'],
            'aksi'        => $bolehUbah
                ? '<form method="post" style="display:inline" action="' . e(BASE_URL) . 'index.php?m=master&amp;a=dosen-pendidikan-hapus"'
                    . ' data-konfirmasi="Hapus riwayat ' . e($p['jenjang']) . ' ' . e((string) ($p['bidang'] ?? '')) . '?">'
                    . csrf_input()
                    . '<input type="hidden" name="id" value="' . (int) $p['id'] . '">'
                    . '<input type="hidden" name="dosen_id" value="' . $id . '">'
                    . '<button class="btn btn-danger" type="submit">Hapus</button></form>'
                : '',
        ];
    }

    $isiRiwayat = $barisRiwayat
        ? tabel_html($kolomRiwayat, $barisRiwayat)
        : '<p class="muted">Belum ada riwayat pendidikan. Data ini dipakai tabel 3 dan 4 (kualifikasi dosen).</p>';

    if ($bolehUbah) {
        $isiRiwayat .= '<form class="grid-form mt8" method="post" action="' . e(BASE_URL) . 'index.php?m=master&amp;a=dosen-pendidikan-tambah">'
            . csrf_input() . '<input type="hidden" name="dosen_id" value="' . $id . '">'
            . '<label>Jenjang<select name="jenjang">' . pilihan(DOSEN_JENJANG, 'S2') . '</select></label>'
            . '<label>Bidang<input name="bidang" required maxlength="120" placeholder="mis. Kebidanan"></label>'
            . '<label>Asal perguruan tinggi<input name="asal_pt" required maxlength="160"></label>'
            . '<label>Tahun lulus<input type="number" name="tahun_lulus" min="1950" max="' . e((string) date('Y')) . '"></label>'
            . '<div class="form-aksi"><button class="btn" type="submit">Tambah baris pendidikan</button></div>'
            . '</form>';
    }

    $isi .= kartu('Riwayat pendidikan', $isiRiwayat, ['catatan' => count($riwayat) . ' baris']);
}

render($judul, $isi, ['aktif' => 'dosen', 'sub' => 'NIDN/NIDK/NUP unik · riwayat pendidikan banyak baris']);
