<?php
declare(strict_types=1);

/**
 * Impor CSV dosen/tendik: unggah -> pratinjau 10 baris -> konfirmasi.
 * Kolom terakhir (ke-13) adalah daftar prodi dipisah `;`, boleh id atau nama.
 * Dosen lintas prodi cukup satu baris di CSV ini — bukan dua baris seperti
 * prototipe lama.
 */

$galat ??= '';   // induk (mahasiswa.php/dosen.php) sudah mengisinya

$contoh = 'nidn,nama,jenis,jabatan_fungsional,gelar_akademik,tanggal_lahir,sertifikat_pendidik,profesi,no_str,tanggal_str,tahap_mengajar,email,prodi' . "\n"
    . '0123456789,Siti Rahmah,Dosen Tetap,Lektor,S.ST.,1985-04-02,1,Bidan,123456,2024-01-31,Keduanya,siti@bbg.ac.id,Kebidanan' . "\n"
    . '9876543210,Nur Aisyah,Dosen Tetap,Asisten Ahli,S.Kep.,1990-09-14,0,Perawat,,,Akademik,nur@bbg.ac.id,Kebidanan;Keperawatan';

$isi .= kartu('Impor dosen & tendik dari CSV',
    '<form method="post" enctype="multipart/form-data" action="' . e(BASE_URL) . 'index.php?m=master&amp;a=dosen-impor">'
    . csrf_input()
    . '<label class="unggah">Berkas CSV<input type="file" name="berkas" accept=".csv,text/csv" required></label>'
    . '<button class="btn btn-primary" type="submit">Unggah &amp; pratinjau</button></form>'
    . '<p class="foot-note">Urutan kolom: <code>nidn, nama, jenis, jabatan_fungsional, gelar_akademik, tanggal_lahir, '
    . 'sertifikat_pendidik, profesi, no_str, tanggal_str, tahap_mengajar, email, prodi</code>. '
    . 'Kolom <code>prodi</code> boleh berisi beberapa nama dipisah <code>;</code>. Maks 2 MB.</p>'
    . '<details class="mt8"><summary>Lihat contoh</summary><pre class="contoh-csv">' . e($contoh) . '</pre></details>',
    ['aksi' => '<a href="' . e(BASE_URL) . 'index.php?m=master&amp;a=dosen">Kembali ke daftar</a>']);

if ($galat !== '') {
    $isi .= callout('bad', 'Berkas ditolak', e($galat));
}

if (is_array($pratinjau)) {
    $sah   = count(array_filter($pratinjau, fn($r) => !$r['galat']));
    $rusak = count($pratinjau) - $sah;

    $barisPrat = [];
    foreach (array_slice($pratinjau, 0, 10) as $r) {
        $k = $r['kolom'];
        $barisPrat[] = [
            'baris' => $r['baris'],
            'nidn'  => $k[0] ?? '',
            'nama'  => $k[1] ?? '',
            'jenis' => $k[2] ?? '',
            'prodi' => $k[12] ?? '',
            'ket'   => $r['galat']
                ? '<span class="badge badge-bad">ditolak</span> ' . e(implode(' ', $r['galat']))
                : '<span class="badge badge-lengkap">siap</span>',
        ];
    }

    $isiPrat = $barisPrat
        ? tabel_html([
            ['kunci' => 'baris', 'label' => 'Baris', 'kelas' => 'r num'],
            ['kunci' => 'nidn', 'label' => 'NIDN', 'kelas' => 'mono'],
            ['kunci' => 'nama', 'label' => 'Nama'],
            ['kunci' => 'jenis', 'label' => 'Jenis'],
            ['kunci' => 'prodi', 'label' => 'Prodi'],
            ['kunci' => 'ket', 'label' => 'Keterangan', 'html' => true],
        ], $barisPrat)
        : '<p class="muted">Berkas tidak berisi baris data.</p>';

    if (count($pratinjau) > 10) {
        $isiPrat .= '<p class="foot-note">10 baris pertama ditampilkan; '
            . (count($pratinjau) - 10) . ' baris lain ikut diperiksa saat konfirmasi.</p>';
    }

    $isiPrat .= '<form class="mt8" method="post" action="' . e(BASE_URL) . 'index.php?m=master&amp;a=dosen-impor-simpan"'
        . ' data-konfirmasi="Simpan ' . $sah . ' baris sah? ' . $rusak . ' baris gagal akan dilaporkan, bukan membatalkan sisanya.">'
        . csrf_input()
        . '<button class="btn btn-primary" type="submit"' . ($sah === 0 ? ' disabled' : '') . '>Simpan ' . $sah . ' baris</button> '
        . '<a class="btn" href="' . e(BASE_URL) . 'index.php?m=master&amp;a=dosen">Batal</a></form>';

    $isi .= kartu('Pratinjau', $isiPrat, [
        'catatan' => count($pratinjau) . ' baris terbaca · ' . $sah . ' siap masuk · ' . $rusak . ' akan ditolak',
    ]);
}

if (is_array($hasil)) {
    $isiHasil = '';
    if ($hasil['tolak']) {
        $barisTolak = [];
        foreach ($hasil['tolak'] as $t) {
            $barisTolak[] = ['baris' => $t['baris'],
                         'pesan' => ['isi' => $t['pesan'], 'kelas' => 'is-bad']];
        }
        $isiHasil .= tabel_html([
            ['kunci' => 'baris', 'label' => 'Baris', 'kelas' => 'r num'],
            ['kunci' => 'pesan', 'label' => 'Alasan ditolak', 'html' => true],
        ], $barisTolak)
            . '<p class="foot-note">Perbaiki baris di atas di berkas asal lalu impor ulang. Baris yang sudah masuk tidak terpengaruh.</p>';
    } else {
        $isiHasil .= '<p class="muted">Semua baris masuk.</p>';
    }
    $isiHasil .= '<p class="mt8"><a class="btn btn-primary" href="' . e(BASE_URL)
        . 'index.php?m=master&amp;a=dosen">Lihat daftar dosen</a></p>';

    $isi .= kartu('Hasil impor', $isiHasil, [
        'catatan' => (int) $hasil['masuk'] . ' masuk · ' . count($hasil['tolak']) . ' ditolak',
    ]);
}

render('Impor dosen', $css . $isi, ['aktif' => 'dosen', 'sub' => 'Unggah → pratinjau → konfirmasi']);
