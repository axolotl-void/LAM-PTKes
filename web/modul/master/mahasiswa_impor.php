<?php
declare(strict_types=1);

/**
 * Halaman impor CSV mahasiswa: unggah -> pratinjau 10 baris -> konfirmasi.
 * Pratinjau memakai validator yang SAMA dengan penyimpanan, jadi baris yang
 * tampil "ditolak" di pratinjau tidak mungkin lolos saat konfirmasi.
 *
 * CSV disimpan di sesi antara unggah dan konfirmasi — bukan di disk, supaya
 * tidak ada berkas sementara yang perlu dibersihkan.
 */

$galat ??= '';   // induk (mahasiswa.php/dosen.php) sudah mengisinya

$contoh = 'nim,nama,tahun_masuk,jalur_masuk,tahap,status,ipk,tanggal_lulus,tahun_lulus' . "\n"
    . '2023010,Siti Aminah,2023,Reguler,Akademik,Aktif,3.45,,' . "\n"
    . '2023011,Nur Aisyah,2023,Transfer,Profesi,Lulus,3.80,2026-08-31,2026';

$isi .= kartu('Impor mahasiswa dari CSV',
    '<form method="post" enctype="multipart/form-data" action="' . e(BASE_URL) . 'index.php?m=master&amp;a=mahasiswa-impor">'
    . csrf_input()
    . '<label class="unggah">Berkas CSV<input type="file" name="berkas" accept=".csv,text/csv" required></label>'
    . '<button class="btn btn-primary" type="submit">Unggah &amp; pratinjau</button></form>'
    . '<p class="foot-note">Urutan kolom: <code>nim, nama, tahun_masuk, jalur_masuk, tahap, status, ipk, tanggal_lulus, tahun_lulus</code>. '
    . 'Baris header opsional. Maks 2 MB. Titik atau koma desimal diterima.</p>'
    . '<details class="mt8"><summary>Lihat contoh</summary><pre class="contoh-csv">' . e($contoh) . '</pre></details>',
    ['aksi' => '<a href="' . e(BASE_URL) . 'index.php?m=master&amp;a=mahasiswa">Kembali ke daftar</a>']);

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
            'nim'   => $k[0] ?? '',
            'nama'  => $k[1] ?? '',
            'tahun' => $k[2] ?? '',
            'status'=> $k[5] ?? '',
            'ipk'   => $k[6] ?? '',
            'ket'   => $r['galat']
                ? '<span class="badge badge-bad">ditolak</span> ' . e(implode(' ', $r['galat']))
                : '<span class="badge badge-lengkap">siap</span>',
        ];
    }

    $isiPrat = $barisPrat
        ? tabel_html([
            ['kunci' => 'baris', 'label' => 'Baris', 'kelas' => 'r num'],
            ['kunci' => 'nim', 'label' => 'NIM', 'kelas' => 'mono'],
            ['kunci' => 'nama', 'label' => 'Nama'],
            ['kunci' => 'tahun', 'label' => 'Tahun', 'kelas' => 'r num'],
            ['kunci' => 'status', 'label' => 'Status'],
            ['kunci' => 'ipk', 'label' => 'IPK', 'kelas' => 'r num'],
            ['kunci' => 'ket', 'label' => 'Keterangan', 'html' => true],
        ], $barisPrat)
        : '<p class="muted">Berkas tidak berisi baris data.</p>';

    if (count($pratinjau) > 10) {
        $isiPrat .= '<p class="foot-note">10 baris pertama ditampilkan; '
            . (count($pratinjau) - 10) . ' baris lain ikut diperiksa saat konfirmasi.</p>';
    }

    $isiPrat .= '<form class="mt8" method="post" action="' . e(BASE_URL) . 'index.php?m=master&amp;a=mahasiswa-impor-simpan"'
        . ' data-konfirmasi="Simpan ' . $sah . ' baris sah? ' . $rusak . ' baris gagal akan dilaporkan, bukan membatalkan sisanya.">'
        . csrf_input()
        . '<button class="btn btn-primary" type="submit"' . ($sah === 0 ? ' disabled' : '') . '>Simpan ' . $sah . ' baris</button> '
        . '<a class="btn" href="' . e(BASE_URL) . 'index.php?m=master&amp;a=mahasiswa">Batal</a></form>';

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
        . 'index.php?m=master&amp;a=mahasiswa">Lihat daftar mahasiswa</a></p>';

    $isi .= kartu('Hasil impor', $isiHasil, [
        'catatan' => (int) $hasil['masuk'] . ' masuk · ' . count($hasil['tolak']) . ' ditolak',
    ]);
}

render('Impor mahasiswa', $isi, ['aktif' => 'mahasiswa', 'sub' => 'Unggah → pratinjau → konfirmasi']);
