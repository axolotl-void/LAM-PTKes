<?php
declare(strict_types=1);

/**
 * Data ekspor: satu tempat untuk XLSX, PDF, dan uji.
 *
 * Kenapa dipisah dari index.php: halaman dan uji harus membaca angka yang
 * sama. Kalau pembentuk sheet ada di dalam halaman, uji hanya bisa menguji
 * salinannya.
 *
 * Tanpa HTTP, tanpa sesi — kecuali `ekspor_blokir()` yang memakai pengguna()
 * bila ada sesi.
 */

require_once __DIR__ . '/../../system/rekap.php';
require_once __DIR__ . '/../konsistensi/fungsi.php';

/**
 * Teks penanda (a)–(g) Tabel 5 dan 6.
 *
 * Isi catatan kaki penanda ini tidak ada di `spec_field` — hanya judul
 * kolomnya. Yang bisa dicetak hanya daftar penandanya, dan itu yang wajib
 * muncul; mengarang isi catatan kaki akan menambah klaim ke instrumen.
 */
const EKSPOR_PENANDA = '(a) (b) (c) (d) (e) (f) (g)';

/** Tabel yang penandanya wajib tercetak walau belum punya baris. */
const EKSPOR_WAJIB_PENANDA = ['05', '06'];

/**
 * Alasan ekspor diblokir. Kosong = boleh.
 *
 * Dua sebab, keduanya harus disebutkan dengan jelas — "tidak bisa ekspor"
 * tanpa alasan membuat operator mengira sistem rusak.
 */
function ekspor_blokir(?int $prodi = null): array
{
    $prodi ??= (int) (prodi_aktif() ?? 0);
    $alasan = [];

    if ($prodi <= 0) {
        $alasan[] = [
            'kode'  => 'PRODI',
            'nama'  => 'Prodi aktif belum dipilih',
            'pesan' => 'Ekspor butuh satu prodi. Masuk sebagai akun yang terikat prodi '
                     . 'atau pilih prodi lebih dulu.',
        ];
    } else {
        foreach (konsistensi_blokir($prodi) as $h) {
            $alasan[] = [
                'kode'  => (string) $h['kode'],
                'nama'  => (string) $h['nama'],
                'pesan' => sprintf('selisih %s %s — perbaiki di %s',
                    ekspor_angka((float) ($h['selisih'] ?? 0)),
                    (string) $h['satuan'],
                    $h['perbaikan'] !== '' ? (string) $h['perbaikan'] : 'tabel terkait'),
            ];
        }
    }

    foreach (ekspor_akun_ganda() as $u) {
        $alasan[] = [
            'kode'  => 'AKUN',
            'nama'  => 'Akun berperan ganda: ' . $u['email'],
            'pesan' => 'Peran "' . $u['peran_kode'] . '" memuat dua peran sekaligus. '
                     . 'Pisahkan perannya di menu Pengguna sebelum ekspor.',
        ];
    }

    return $alasan;
}

/** Akun aktif yang perannya memuat lebih dari satu peran. */
function ekspor_akun_ganda(): array
{
    $out = [];
    foreach (q('SELECT id, email, nama, peran_kode FROM pengguna WHERE aktif = 1 ORDER BY id') as $u) {
        if (peran_ganda((string) $u['peran_kode'])) {
            $out[] = $u;
        }
    }
    return $out;
}

/** Angka gaya Indonesia tanpa nol ekor: 7 -> "7", 7.5 -> "7,5". */
function ekspor_angka(float $n): string
{
    $s = fmod($n, 1.0) === 0.0
        ? number_format($n, 0, ',', '.')
        : number_format($n, 2, ',', '.');
    return $s;
}

/**
 * Susun sheet ekspor untuk satu prodi/periode.
 *
 * Balikan: ['kepala' => [...], 'sheet' => [['nama','header','baris','kode'], ...]]
 * Tabel tanpa baris dilewati, kecuali Tabel 05/06 (penanda wajib tercetak).
 */
function ekspor_data(int $prodi, ?int $periode): array
{
    $prodiInfo = q1('SELECT id, nama_ps, jenjang, punya_tahap_profesi FROM prodi WHERE id = ?', [$prodi]);
    $per = $periode !== null
        ? q1('SELECT id, tahun_ts, tahun_ss, tgl_pengajuan FROM periode WHERE id = ?', [$periode])
        : null;

    $kepala = [
        'prodi'   => (string) ($prodiInfo['nama_ps'] ?? '—'),
        'jenjang' => (string) ($prodiInfo['jenjang'] ?? '—'),
        'tahun_ts' => $per ? (int) $per['tahun_ts'] : null,
        'tahun_ss' => $per && $per['tahun_ss'] !== null ? (int) $per['tahun_ss'] : null,
    ];

    $tabel = q('SELECT kode, nomor, judul, kriteria FROM tabel_dukung ORDER BY nomor');
    $sheet = [];

    foreach ($tabel as $t) {
        $kode = (string) $t['kode'];
        $field = q('SELECT urut, label, kunci, tipe, asal FROM spec_field
                    WHERE tabel_kode = ? ORDER BY urut', [$kode]);

        $baris = q('SELECT id, urut FROM baris_tabel
                    WHERE tabel_kode = ? AND prodi_id = ? AND periode_id = ?
                    ORDER BY urut, id', [$kode, $prodi, (int) $periode]);

        $wajibPenanda = in_array($kode, EKSPOR_WAJIB_PENANDA, true);
        if ($baris === [] && !$wajibPenanda) {
            continue;
        }

        // Nilai tersimpan, satu query per tabel.
        $nilai = [];
        if ($baris !== []) {
            foreach (q('SELECT s.baris_id, s.field_kunci, s.nilai_angka, s.nilai_teks
                        FROM sel s JOIN baris_tabel b ON b.id = s.baris_id
                        WHERE b.tabel_kode = ? AND b.prodi_id = ? AND b.periode_id = ?',
                       [$kode, $prodi, (int) $periode]) as $s) {
                $nilai[(int) $s['baris_id']][(string) $s['field_kunci']] =
                    $s['nilai_angka'] !== null ? $s['nilai_angka'] : $s['nilai_teks'];
            }
        }

        $adaTetap = false;
        // Tabel 01 punya field 'No' asal=Input. Kolom nomor buatan jangan
        // diduplikasi — pembaca cetak melihat dua kolom "No".
        $punyaNo = false;
        foreach ($field as $f) {
            if (in_array(mb_strtolower(trim((string) $f['label'])), ['no', 'no.'], true)) {
                $punyaNo = true;
            }
        }
        $header = $punyaNo ? [] : ['No'];
        foreach ($field as $f) {
            $header[] = (string) $f['label'];
            if ($f['asal'] === 'Tetap') {
                $adaTetap = true;
            }
        }
        // Tabel 06 tidak punya field Tetap di spec_field, padahal penandanya
        // wajib tercetak. Kolomnya ditambahkan di sini, bukan di data.
        if ($wajibPenanda && !$adaTetap) {
            $header[] = 'Penanda (a) - (g)';
        }

        $isi = [];
        foreach ($baris as $b) {
            $r = $punyaNo ? [] : [(int) $b['urut']];
            $sudahTetap = false;
            foreach ($field as $f) {
                if ($f['asal'] === 'Tetap') {
                    // Tidak pernah disimpan di `sel`; nilainya teks tetap.
                    $r[] = EKSPOR_PENANDA;
                    $sudahTetap = true;
                    continue;
                }
                $r[] = $nilai[(int) $b['id']][(string) $f['kunci']] ?? '';
            }
            if ($wajibPenanda && !$sudahTetap) {
                $r[] = EKSPOR_PENANDA;
            }
            $isi[] = $r;
        }
        // Tabel 05/06 tanpa baris: tetap satu baris penanda supaya tercetak.
        if ($isi === []) {
            $isi[] = array_merge(array_fill(0, count($header) - 1, ''), [EKSPOR_PENANDA]);
        }

        $judulBersih = trim((string) preg_replace('/^\s*\d+[.\-]?\s*/', '', (string) $t['judul']));
        $sheet[] = [
            'kode'   => $kode,
            'nomor'  => (int) $t['nomor'],
            'kriteria' => (string) $t['kriteria'],
            // Judul di DB (seed) berbunyi "01. Tabel 01", jadi nomor depan
            // dibuang: kop tabel "T1 · 01. Tabel 01" terbaca konyol.
            'judul'  => $judulBersih,
            'nama'   => sprintf('T%d %s', (int) $t['nomor'], $judulBersih !== '' ? $judulBersih : $t['judul']),
            'header' => $header,
            'baris'  => $isi,
        ];
    }

    return ['kepala' => $kepala, 'sheet' => $sheet, 'tabel' => $tabel,
            'periode' => $per, 'prodi_id' => $prodi];
}

/** Sheet 'Ringkasan': identitas + keadaan tiap tabel. Selalu sheet pertama. */
function ekspor_sheet_ringkasan(array $data): array
{
    $prodi = (int) $data['prodi_id'];
    $perId = $data['periode'] ? (int) $data['periode']['id'] : 0;
    $k = $data['kepala'];

    $baris = [
        ['Prodi', $k['prodi']],
        ['Jenjang', $k['jenjang']],
        ['Tahun TS', $k['tahun_ts'] ?? '—'],
        ['Tahun SS', $k['tahun_ss'] ?? '—'],
        ['Tabel diekspor', count($data['sheet'])],
        ['Dicetak', date('Y-m-d H:i')],
    ];

    if ($perId > 0) {
        $r = rekap_ringkas($prodi, $perId);
        $baris[] = ['Tabel wajib', $r['wajib']];
        $baris[] = ['Selesai (lengkap + terkunci)', $r['selesai']];
        $baris[] = ['Sebagian', $r['sebagian']];
        $baris[] = ['Kosong', $r['kosong']];
    }

    $baris[] = ['', ''];
    $baris[] = ['Tabel', 'Baris · field terisi'];
    foreach ($data['tabel'] as $t) {
        $kode = (string) $t['kode'];
        $ada = null;
        foreach ($data['sheet'] as $s) {
            if ($s['kode'] === $kode) {
                $ada = $s;
                break;
            }
        }
        if ($ada === null) {
            $baris[] = [sprintf('T%s %s', $t['nomor'], $t['judul']), 'tidak diekspor — belum ada baris'];
            continue;
        }
        $st = $perId > 0 ? status_satu_tabel($kode, $prodi, $perId) : null;
        $baris[] = [
            sprintf('T%s %s', $t['nomor'], $t['judul']),
            $st !== null
                ? sprintf('%d baris · %d/%d field terisi', $st['baris'], $st['terisi'], $st['total'])
                : sprintf('%d baris', count($ada['baris'])),
        ];
    }

    return ['kode' => '00', 'nomor' => 0, 'judul' => 'Ringkasan',
            'nama' => 'Ringkasan', 'header' => ['Keterangan', 'Nilai'], 'baris' => $baris];
}

/** Semua sheet siap tulis: Ringkasan dulu, lalu satu per tabel. */
function ekspor_sheets(int $prodi, ?int $periode): array
{
    $data = ekspor_data($prodi, $periode);
    return array_merge([ekspor_sheet_ringkasan($data)], array_map(
        static fn(array $s): array => [
            'kode' => $s['kode'], 'nomor' => $s['nomor'], 'judul' => $s['judul'],
            'nama' => $s['nama'], 'header' => $s['header'], 'baris' => $s['baris'],
        ],
        $data['sheet']
    ));
}
