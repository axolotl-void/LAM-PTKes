<?php
declare(strict_types=1);

/**
 * Tabel 02 — Persentase Keberhasilan Studi. Seluruhnya dihitung dari master
 * mahasiswa, tidak ada isian.
 *
 * BENTUK INSTRUMEN: tahun akademik TS-7 s.d. TS.
 * Kolom: No, Tahun Akademik, Jumlah Mahasiswa, Masa studi 5,5-6 th,
 *        Masa studi 6-10 th, DO — dan instrumen menulis DO = A - (B + C).
 *
 * JUMLAH BARIS = 8, BUKAN 7. Rentang "TS-7 s.d. TS" berisi delapan label
 * (TS-7, TS-6, ..., TS-1, TS) — sama dengan yang tertulis di
 * spec_field tabel 02 ('TS-7 sampai TS'), sumber transkrip instrumen proyek
 * ini. Angka "7 baris" dibaca sebagai salah hitung jumlah label, bukan batas
 * yang berbeda: memakai 7 baris berarti rentangnya TS-6..TS dan angkatan
 * TS-7 hilang dari tabel. Kalau kemudian ada lembar instrumen resmi yang
 * memang hanya meminta 7 tahun, ubah T02_BARIS jadi 7 dan rentangnya ikut
 * bergeser sendiri — tidak ada angka tahun yang ditulis tangan di sini.
 *
 * KEPUTUSAN YANG DIAMBIL DI SINI (dan kenapa):
 *
 * 1) TAHUN AJARAN vs TAHUN KALENDER.
 *    Instrumen memakai tahun ajaran (2019/2020), master hanya punya tahun
 *    kalender (tahun_masuk = 2019, tahun_lulus = 2025). Selisih kalender itu
 *    dipakai sebagai masa studi dalam tahun bulat; pecahan semester (5,5)
 *    tidak tersimpan di mana pun, jadi tidak bisa dihitung dan tidak dikarang.
 *
 * 2) BATAS RENTANG.
 *    Masa 5,5-6 th dibaca sebagai "5 atau 6 tahun" (batas atas 6 inklusif —
 *    lulus 6 tahun setelah masuk memang masih di bawah 6,5).
 *    Masa 6-10 th di instrumen bertumpang tindih di angka 6. Kalau dibaca
 *    harfiah 6..10, satu lulusan terhitung di B DAN C, lalu DO = A-(B+C)
 *    menjadi terlalu kecil / negatif hanya karena salah baca batas.
 *    Karena kedua kolom itu dipakai sebagai dua potongan yang dijumlahkan,
 *    batasnya dipisah: C = 7..10. Yang masa studinya >10 th atau <5 th tidak
 *    masuk B maupun C — itu bukan kelalaian, instrumen memang tidak punya
 *    kolomnya, dan sisa itulah yang muncul di kolom DO.
 *
 * 3) DO BUKAN STATUS DO.
 *    Instrumen mendefinisikan DO = A - (B + C), jadi kolom ini juga memuat
 *    mahasiswa yang masih aktif/cuti. Itu rumus instrumen, bukan bug. Angka
 *    status `DO` yang sebenarnya tersedia terpisah (mhs_per_angkatan()['do'])
 *    dan TIDAK dipakai di sini supaya kolomnya tetap cocok dengan template.
 *
 * 4) NEGATIF TIDAK DIPAKSA.
 *    Kalau A - (B + C) negatif (data ganda: satu orang tercatat dua baris
 *    dengan NIM berbeda, atau koreksi tahun_masuk), kolom DO ditulis 0 dan
 *    angka mentahnya tetap disimpan di 'do_mentah' + dihitung di
 *    'jumlah_negatif'. Memaksa angka supaya cocok akan menyembunyikan data
 *    rusak; membiarkan negatif akan ditolak asesor.
 */

require_once __DIR__ . '/hitung.php';

/**
 * Jumlah baris Tabel 02. 8 = banyak label pada rentang TS-7..TS; lihat
 * komentar berkas soal kenapa bukan 7.
 */
const T02_BARIS = 8;

/**
 * Kolom DO = A - (B + C), tidak pernah negatif.
 *
 * Dipisah jadi fungsi sendiri supaya cabang "negatif" bisa diuji langsung.
 * Dari master, negatif praktis MUSTAHIL: B dan C hanya memuat lulusan yang
 * tahun masuknya sama dengan baris itu, jadi B+C selalu <= A. Artinya cabang
 * ini murni penjaga untuk data yang nanti bisa masuk lewat impor/koreksi —
 * kalau tidak dipisah, satu-satunya cara mengujinya adalah menyisipkan data
 * yang secara skema tidak sah, dan uji seperti itu menguji hal yang salah.
 *
 * @return array{0:int,1:int} [nilai kolom (>=0), nilai mentah]
 */
function t02_do(int $jumlah, int $b, int $c): array
{
    $mentah = $jumlah - ($b + $c);
    return [max(0, $mentah), $mentah];
}

/**
 * Hitung Tabel 02 untuk satu prodi.
 *
 * @return array{
 *   baris: array<int,array{no:int,tahun_akademik:string,tahun:?int,
 *     jumlah_mahasiswa:int,masa_studi_5_5_6_th:int,masa_studi_6_10_th:int,
 *     do:int,do_mentah:int,catatan:string}>,
 *   jumlah_negatif:int, catatan:string
 * }
 */
function t02_hitung(int $prodiId): array
{
    $periode = periode_prodi($prodiId);
    if ($periode === null) {
        // Tanpa periode, "TS" tidak punya arti. Mengarang tahun berjalan
        // menghasilkan tabel yang terlihat benar tapi tidak bisa
        // dipertanggungjawabkan, jadi tabelnya dikosongkan dengan alasan.
        return [
            'baris' => [],
            'jumlah_negatif' => 0,
            'catatan' => 'Periode prodi belum ditetapkan, jadi label TS tidak bisa '
                . 'diterjemahkan ke tahun. Tabel tidak dihitung.',
        ];
    }

    $mhs = mhs_per_angkatan($prodiId);

    // Sebaran masa studi dikelompokkan per ANGKATAN, bukan per tahun lulus:
    // baris tabel ini adalah angkatan. lulusan_per_tahun() memberi histogram
    // masa studi per tahun lulus, jadi angkatan = tahun_lulus - masa.
    //
    // Tanpa filter tahap ('Akademik'): kolom Jumlah Mahasiswa menghitung
    // semua tahap, jadi pembilangnya juga harus semua tahap. Kalau lulusan
    // Profesi dikeluarkan dari B/C tapi tetap masuk A, kolom DO membengkak.
    $masa = [];
    foreach (lulusan_per_tahun($prodiId) as $tahunLulus => $d) {
        foreach ($d['masa_studi'] as $m => $n) {
            $angkatan = $tahunLulus - $m;
            $masa[$angkatan][$m] = ($masa[$angkatan][$m] ?? 0) + $n;
        }
    }

    $baris = [];
    $negatif = 0;

    foreach (rentang_ta('TS', T02_BARIS) as $i => $label) {
        $tahun = ta_ke_tahun($label, $periode);

        // Tahun = tahun MASUK untuk baris ini (angkatan), sama dengan tahun
        // awal tahun akademik pada labelnya.
        $jumlah = $tahun !== null ? (int) ($mhs[$tahun]['total'] ?? 0) : 0;

        $b = 0;
        $c = 0;
        foreach ($masa[$tahun] ?? [] as $m => $n) {
            if ($m >= 5 && $m <= 6) {
                $b += $n;
            } elseif ($m >= 7 && $m <= 10) {
                $c += $n;
            }
        }

        [$do, $mentah] = t02_do($jumlah, $b, $c);
        $ket = '';
        if ($mentah < 0) {
            $negatif++;
            $ket = 'A-(B+C) = ' . $mentah . ': lebih banyak lulusan daripada mahasiswa '
                . 'angkatan ini. Periksa data ganda / koreksi tahun masuk. DO ditulis 0.';
        }

        $baris[] = [
            'no' => $i + 1,
            'tahun_akademik' => $label,
            'tahun' => $tahun,
            'jumlah_mahasiswa' => $jumlah,
            'masa_studi_5_5_6_th' => $b,
            'masa_studi_6_10_th' => $c,
            'do' => $do,
            'do_mentah' => $mentah,
            'catatan' => $ket,
        ];
    }

    $catatan = 'Masa studi dihitung dari selisih tahun kalender masuk dan lulus, dalam '
        . 'tahun bulat; pecahan semester (5,5 th) tidak tersimpan di master sehingga tidak '
        . 'dihitung. Kolom 5,5-6 th memuat masa 5 dan 6 tahun; kolom 6-10 th memuat masa '
        . '7-10 tahun — batas 6 sengaja hanya masuk satu kolom supaya A-(B+C) tidak '
        . 'terpotong dua kali. Lulusan dengan masa <5 th atau >10 th tidak masuk B/C '
        . '(instrumen tidak menyediakan kolomnya) dan muncul di kolom DO. Kolom DO memakai '
        . 'rumus instrumen A-(B+C), jadi termasuk mahasiswa yang masih aktif, bukan hanya '
        . 'status DO.';

    if ($negatif > 0) {
        $catatan .= ' ADA ' . $negatif . ' baris yang A-(B+C) negatif: DO ditulis 0 dan '
            . 'angka mentahnya disimpan di do_mentah. Itu tanda data ganda, bukan angka '
            . 'yang boleh dibulatkan.';
    }

    return ['baris' => $baris, 'jumlah_negatif' => $negatif, 'catatan' => $catatan];
}
