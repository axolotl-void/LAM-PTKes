<?php
declare(strict_types=1);

/**
 * Tabel 07, 08, 09, 10, 12 — hitungan dari master (uji_kompetensi, mahasiswa,
 * survei). Tidak ada angka turunan yang disimpan: persentase dihitung ulang
 * setiap kali diminta, karena master bisa berubah kapan saja dan persen yang
 * disimpan akan basi tanpa ada yang tahu.
 *
 * BENTUK KELUARAN SERAGAM (lihat hitung_peta.php): judul, kolom, baris, catatan,
 * kosong. Tiap baris membawa `nilai` (assoc, untuk uji/API) dan `sel` (urutan
 * sama dengan `kolom`, untuk tampilan). `sel` = array_values(nilai) — tidak
 * perlu daftar kunci ganda, cukup pastikan urutan kunci = urutan kolom.
 *
 * ATURAN NILAI KOSONG: pembagian nol menghasilkan null, BUKAN 0. Nol berarti
 * "memang nol"; kosong berarti "tidak bisa dihitung". Tampilan mencetak null
 * sebagai '—'. Kalau pembagi nol ditulis 0, tabel melaporkan "0% lulus" untuk
 * angkatan yang belum punya peserta — angka yang terlihat sah tapi bohong.
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/hitung.php';

/** Persentase; null kalau pembilang/penyebut tidak ada atau penyebut nol. */
function persen_hitung(?int $bagi, ?int $penyebut, int $digit = 2): ?float
{
    if ($bagi === null || $penyebut === null || $penyebut === 0) {
        return null;
    }
    return round($bagi / $penyebut * 100, $digit);
}

/** Satu baris tabel: nilai assoc + sel berurutan sesuai kolom. */
function baris_hitung(array $nilai, string $kelas = ''): array
{
    return ['nilai' => $nilai, 'sel' => array_values($nilai), 'kelas' => $kelas];
}

/** Nilai int atau null dari baris sumber, tanpa mengubah null jadi 0. */
function angka_atau_null(array $baris, string $kunci): ?int
{
    return $baris[$kunci] === null ? null : (int) $baris[$kunci];
}

/**
 * Tabel 07 (CBT) / Tabel 08 (OSCE) — uji kompetensi mahasiswa.
 *
 * Struktur instrumen identik untuk kedua tabel; yang membedakan hanya kolom
 * `jenis` di master uji_kompetensi. Karena itu satu fungsi, satu parameter —
 * kalau disalin dua kali, cepat atau lambat salah satunya diperbaiki sendiri.
 *
 * @param string $jenis 'CBT' untuk Tabel 07, 'OSCE' untuk Tabel 08
 */
function t07_hitung(int $prodiId, string $jenis = 'CBT'): array
{
    $jenis = strtoupper($jenis) === 'OSCE' ? 'OSCE' : 'CBT';
    $per = periode_prodi($prodiId);

    $kolom = ['Tahun', 'Mahasiswa selesai stase', 'Peserta UKMPS',
              'FT peserta', 'FT lulus', 'FT %',
              'RT peserta', 'RT lulus', 'RT %',
              'Total lulusan', 'Lulusan %'];

    // Satu query untuk semua tahun, bukan satu query per baris: 3 baris x 2
    // tabel = 6 round-trip yang bisa saling tidak konsisten kalau ada tulisan
    // di antaranya.
    $sumber = [];
    if ($per !== null) {
        foreach (q('SELECT periode, selesai_stase, peserta_total,
                           ft_peserta, ft_lulus, rt_peserta, rt_lulus
                    FROM uji_kompetensi WHERE prodi_id = ? AND jenis = ?',
                    [$prodiId, $jenis]) as $r) {
            $sumber[(int) $r['periode']] = $r;
        }
    }

    $baris = [];
    foreach (['TS-2', 'TS-1', 'TS'] as $label) {
        $tahun = $per !== null ? ta_ke_tahun($label, $per) : null;
        $d = $tahun !== null ? ($sumber[$tahun] ?? null) : null;

        if ($d === null) {
            // Tahun tidak ada di master: SEMUA kolom kosong, bukan nol. Baris
            // tetap ditampilkan supaya terlihat bahwa tahun itu belum diisi.
            $baris[] = baris_hitung([
                'tahun' => $label . ($tahun !== null ? ' (' . $tahun . '/' . ($tahun + 1) . ')' : ''),
                'selesai_stase' => null, 'peserta_total' => null,
                'ft_peserta' => null, 'ft_lulus' => null, 'ft_persen' => null,
                'rt_peserta' => null, 'rt_lulus' => null, 'rt_persen' => null,
                'total_lulusan' => null, 'persen_lulusan' => null,
            ]);
            continue;
        }

        $ftPeserta = angka_atau_null($d, 'ft_peserta');
        $ftLulus   = angka_atau_null($d, 'ft_lulus');
        $rtPeserta = angka_atau_null($d, 'rt_peserta');
        $rtLulus   = angka_atau_null($d, 'rt_lulus');

        // Total lulusan = FT lulus + RT lulus. Kalau keduanya kosong, hasilnya
        // kosong — bukan 0, karena 0 berarti "tidak ada yang lulus" dan itu
        // klaim yang berbeda.
        $total = ($ftLulus === null && $rtLulus === null)
            ? null : (int) $ftLulus + (int) $rtLulus;

        $baris[] = baris_hitung([
            'tahun' => $label . ' (' . $tahun . '/' . ($tahun + 1) . ')',
            'selesai_stase' => angka_atau_null($d, 'selesai_stase'),
            'peserta_total' => angka_atau_null($d, 'peserta_total'),
            'ft_peserta' => $ftPeserta,
            'ft_lulus' => $ftLulus,
            'ft_persen' => persen_hitung($ftLulus, $ftPeserta),
            'rt_peserta' => $rtPeserta,
            'rt_lulus' => $rtLulus,
            'rt_persen' => persen_hitung($rtLulus, $rtPeserta),
            'total_lulusan' => $total,
            // Penyebut persentase lulusan = peserta UKMPS (peserta_total),
            // bukan FT+RT: peserta titipan tidak ikut dihitung di kolom ini.
            'persen_lulusan' => persen_hitung($total, angka_atau_null($d, 'peserta_total')),
        ]);
    }

    $catatan = 'Persentase dihitung dari angka yang ada di baris yang sama (lulus / peserta × 100) '
        . 'dan tidak disimpan, jadi selalu ikut berubah bila master diperbaiki. '
        . 'Sel bertanda — berarti tidak bisa dihitung (peserta 0 atau data belum diisi), '
        . 'bukan berarti 0%.';
    if ($per === null) {
        $catatan = 'Periode akreditasi prodi ini belum ditetapkan, jadi label TS tidak bisa '
            . 'diterjemahkan ke tahun dan seluruh angka dikosongkan. ' . $catatan;
    }

    return [
        'judul' => $jenis === 'OSCE' ? 'Uji Kompetensi (UKMPS-OSCE)' : 'Uji Kompetensi (UKMPS-CBT)',
        'kolom' => $kolom,
        'baris' => $baris,
        'catatan' => $catatan,
        'kosong' => 'Belum ada data uji kompetensi ' . $jenis . ' untuk prodi ini.',
    ];
}

/** Tabel 08 — OSCE. Sama persis dengan Tabel 07 kecuali sumber jenisnya. */
function t08_hitung(int $prodiId): array
{
    return t07_hitung($prodiId, 'OSCE');
}

/**
 * Tabel 09 — total mahasiswa pada UPPS, satu baris per prodi.
 *
 * Sengaja TIDAK menerima prodiId: instrumennya menanyakan seluruh prodi di
 * UPPS. Kalau dibatasi satu prodi, tabel ini menjawab pertanyaan yang berbeda
 * dari yang ditanyakan asesor.
 *
 * Hanya status 'Aktif'. Lulus/DO/Cuti bukan mahasiswa yang sedang terdaftar;
 * memasukkannya membuat total UPPS membengkak sepanjang sejarah prodi.
 * 'Non reguler' = jalur Transfer (istilah instrumen vs istilah master).
 */
function t09_hitung(): array
{
    $rows = q("SELECT pr.id, pr.nama_ps,
                      COALESCE(SUM(m.status = 'Aktif' AND m.jalur_masuk = 'Reguler'), 0)  AS reguler,
                      COALESCE(SUM(m.status = 'Aktif' AND m.jalur_masuk = 'Transfer'), 0) AS non_reguler,
                      COALESCE(SUM(m.status = 'Aktif' AND m.jalur_masuk = 'Asing'), 0)    AS asing
               FROM prodi pr
               LEFT JOIN mahasiswa m ON m.prodi_id = pr.id
               WHERE pr.aktif = 1
               GROUP BY pr.id, pr.nama_ps
               ORDER BY pr.id");

    $baris = [];
    foreach ($rows as $r) {
        $baris[] = baris_hitung([
            'program_studi' => $r['nama_ps'],
            'mahasiswa_reguler' => (int) $r['reguler'],
            'non_reguler' => (int) $r['non_reguler'],
            'asing' => (int) $r['asing'],
        ]);
    }

    return [
        'judul' => 'Total Mahasiswa pada UPPS',
        'kolom' => ['Program Studi', 'Mahasiswa reguler', 'Non reguler', 'Asing'],
        'baris' => $baris,
        'catatan' => 'Dihitung dari master mahasiswa berstatus Aktif, satu baris per prodi aktif '
            . 'di UPPS. "Non reguler" adalah mahasiswa jalur Transfer.',
        'kosong' => 'Belum ada prodi aktif di UPPS ini.',
    ];
}

/**
 * Tabel 10 — mahasiswa tahap akademik, TS-4 sampai TS.
 *
 * Kolom Input instrumen (daya tampung, calon ikut seleksi, calon lulus seleksi)
 * TIDAK punya master di sistem ini — datanya di panitia penerimaan. Fungsi ini
 * mengembalikan null untuk ketiganya, bukan angka karangan: asesor memakai
 * angka itu untuk menilai selektivitas, dan angka karangan di situ adalah
 * temuan.
 */
function t10_hitung(int $prodiId): array
{
    $per = periode_prodi($prodiId);

    $kolom = ['Tahun Akademik', 'Daya tampung', 'Calon ikut seleksi', 'Calon lulus seleksi',
              'Mhs baru reguler', 'Mhs baru transfer', 'Mhs baru asing',
              'Aktif reguler', 'Aktif transfer', 'Aktif asing'];

    // Dua hal berbeda dikumpulkan sekali jalan:
    //  - baru  : semua yang tahun_masuk = tahun itu, APA PUN statusnya sekarang
    //            (yang sudah lulus tetap pernah jadi mahasiswa baru tahun itu);
    //  - aktif : status Aktif sekarang, tanpa melihat tahun masuk.
    $baru = [];
    $aktif = ['Reguler' => 0, 'Transfer' => 0, 'Asing' => 0];
    foreach (q('SELECT tahun_masuk, jalur_masuk, status FROM mahasiswa WHERE prodi_id = ?',
                [$prodiId]) as $m) {
        $jalur = $m['jalur_masuk'];
        $th = (int) $m['tahun_masuk'];
        $baru[$th][$jalur] = ($baru[$th][$jalur] ?? 0) + 1;
        if ($m['status'] === 'Aktif') {
            $aktif[$jalur] = ($aktif[$jalur] ?? 0) + 1;
        }
    }

    $baris = [];
    foreach (rentang_ta('TS', 5) as $label) {
        $tahun = $per !== null ? ta_ke_tahun($label, $per) : null;
        $b = $tahun !== null ? ($baru[$tahun] ?? []) : [];
        $baris[] = baris_hitung([
            'tahun_akademik' => $label . ($tahun !== null ? ' (' . $tahun . '/' . ($tahun + 1) . ')' : ''),
            // Tiga kolom Input: tidak ada masternya, jangan dikarang.
            'daya_tampung' => null,
            'calon_ikut_seleksi' => null,
            'calon_lulus_seleksi' => null,
            'mahasiswa_baru_reguler' => (int) ($b['Reguler'] ?? 0),
            'mahasiswa_baru_transfer' => (int) ($b['Transfer'] ?? 0),
            'mahasiswa_baru_asing' => (int) ($b['Asing'] ?? 0),
            // Total aktif bersifat "sekarang", sama untuk setiap baris tahun.
            // Itu memang yang ditanyakan instrumen: komposisi mahasiswa aktif.
            'total_mahasiswa_aktif_reguler' => (int) ($aktif['Reguler'] ?? 0),
            'total_mahasiswa_aktif_transfer' => (int) ($aktif['Transfer'] ?? 0),
            'total_mahasiswa_aktif_asing' => (int) ($aktif['Asing'] ?? 0),
        ]);
    }

    $catatan = 'Kolom Daya tampung, Calon mahasiswa ikut seleksi, dan Calon mahasiswa lulus '
        . 'seleksi harus DIISI MANUAL: datanya ada di panitia penerimaan, tidak ada di master '
        . 'sistem ini. Selama kosong, sel bernilai — dan itu memang belum diisi, bukan nol. '
        . 'Kolom Total mahasiswa aktif menggambarkan keadaan saat ini, sehingga angkanya sama '
        . 'di setiap baris tahun.';
    if ($per === null) {
        $catatan = 'Periode akreditasi prodi ini belum ditetapkan, jadi label TS tidak bisa '
            . 'diterjemahkan ke tahun dan kolom mahasiswa baru dikosongkan. ' . $catatan;
    }

    return [
        'judul' => 'Mahasiswa Tahap Akademik',
        'kolom' => $kolom,
        'baris' => $baris,
        'catatan' => $catatan,
        'kosong' => 'Belum ada mahasiswa tahap akademik pada prodi ini.',
    ];
}

/**
 * Tabel 12 — kepuasan mahasiswa dari modul survei.
 *
 * Rata-rata tertimbang = (1·s1 + 2·s2 + 3·s3 + 4·s4) / (s1+s2+s3+s4).
 * Rata-rata biasa (jumlah skor / 4) salah di sini: yang dibagi adalah JUMLAH
 * RESPONDEN, bukan jumlah kategori.
 *
 * Skor NULL diperlakukan sebagai 0 responden pada kategori itu — bukan
 * dibuang dari penyebut, karena penyebutnya jumlah responden.
 */
function t12_hitung(int $prodiId): array
{
    $rows = q("SELECT aspek, periode, skor_1, skor_2, skor_3, skor_4
               FROM survei
               WHERE prodi_id = ? AND jenis = 'Mahasiswa'
               ORDER BY periode, aspek", [$prodiId]);

    $baris = [];
    $no = 0;
    foreach ($rows as $r) {
        $s1 = (int) ($r['skor_1'] ?? 0);
        $s2 = (int) ($r['skor_2'] ?? 0);
        $s3 = (int) ($r['skor_3'] ?? 0);
        $s4 = (int) ($r['skor_4'] ?? 0);
        $n = $s1 + $s2 + $s3 + $s4;

        $baris[] = baris_hitung([
            'no' => ++$no,
            'aspek' => $r['aspek'],
            'periode' => $r['periode'] !== null ? (int) $r['periode'] : null,
            'skor_1' => $r['skor_1'] !== null ? $s1 : null,
            'skor_2' => $r['skor_2'] !== null ? $s2 : null,
            'skor_3' => $r['skor_3'] !== null ? $s3 : null,
            'skor_4' => $r['skor_4'] !== null ? $s4 : null,
            'rata_tertimbang' => $n === 0 ? null
                : round((1 * $s1 + 2 * $s2 + 3 * $s3 + 4 * $s4) / $n, 2),
        ]);
    }

    return [
        'judul' => 'Kepuasan Mahasiswa',
        'kolom' => ['No', 'Aspek', 'Periode', 'Kurang (1)', 'Cukup (2)', 'Baik (3)',
                    'Sangat baik (4)', 'Rata-rata tertimbang'],
        'baris' => $baris,
        'catatan' => 'Skor 1–4 adalah jumlah responden per kategori, bukan persentase. '
            . 'Rata-rata tertimbang = (1·s1 + 2·s2 + 3·s3 + 4·s4) / jumlah responden; '
            . 'sel — berarti belum ada responden, bukan rata-rata 0.',
        'kosong' => 'Belum ada survei kepuasan mahasiswa untuk prodi ini.',
    ];
}
